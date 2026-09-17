<?php
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_customer_billing_schema.php";
require_once __DIR__ . "/../helpers/billing_customers.php";
require_once __DIR__ . "/../helpers/build_customer_billing.php";
require_once __DIR__ . "/../helpers/build_billing_pdf.php";
require_once __DIR__ . "/../helpers/unified_billing.php";
require_once __DIR__ . "/../helpers/box_banana_customers.php";
require_once __DIR__ . "/../helpers/build_box_banana_billing.php";
require_once __DIR__ . "/../helpers/ensure_rate_fuel_schema.php";
require_once __DIR__ . "/../helpers/fuel_rate_engine.php";
require_once __DIR__ . "/../helpers/billing_activities.php";
require_once __DIR__ . "/../helpers/build_activity_billing.php";
require_once __DIR__ . "/../helpers/build_activity_pdf.php";
require_once __DIR__ . "/../helpers/sumifru_rate.php";
require_once __DIR__ . "/../helpers/dict_shuttling.php";
require_once __DIR__ . "/../helpers/abc_kds.php";
require_once __DIR__ . "/../helpers/dry_van.php";
require_once __DIR__ . "/../helpers/billing_document.php"; // billing_reference_basename()
ensure_customer_billing_schema($conn);
ensure_rate_fuel_schema($conn);

$id = (int) ($_GET["id"] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Missing or invalid invoice id.";
    exit();
}

$stmt = $conn->prepare("SELECT customer_key, reference, document_no, date_from, date_to, forex_rate, file_name,
                        COALESCE(activity, 'hauling') AS activity
                        FROM billing_invoices WHERE invoice_id = ? AND status <> 'deleted' LIMIT 1");
$stmt->execute([$id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$invoice) {
    http_response_code(404);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Invoice not found.";
    exit();
}

$customerKey = (string) $invoice["customer_key"];
$pipeline = unified_billing_pipeline($customerKey);
// Downloaded PDF is named to MATCH the SAP download: the clean reference WITHOUT the
// Month(Year) (e.g. "Sumifru Reefer Vans - 1.pdf"), even though the stored xlsx file_name
// carries the month. Falls back to the technical file_name if the reference is blank.
$sanitizePdfName = static fn(string $s): string => preg_replace('/[\/\\\\:*?"<>|]+/', "_", $s);
$pdfRefBase = billing_reference_basename(trim((string) ($invoice["reference"] ?? "")));
$pdfBase = $pdfRefBase !== ""
    ? $sanitizePdfName($pdfRefBase)
    : preg_replace('/\.xlsx$/i', "", (string) $invoice["file_name"]);
// The Actions dropdown passes ?activity= to view any activity's statement from
// this invoice's trips; otherwise fall back to the invoice's stored activity.
$activityCode = trim((string) ($_GET["activity"] ?? ""));
if ($activityCode === "" || billing_activity($activityCode) === null) {
    $activityCode = (string) ($invoice["activity"] ?? "hauling");
}
// view=1 shows the PDF inline (preview before download); otherwise attachment.
$disposition = !empty($_GET["view"]) ? "inline" : "attachment";

$entryStmt = $conn->prepare("SELECT entry_id FROM billing_invoice_entries WHERE invoice_id = ?");
$entryStmt->execute([$id]);
$entryIds = array_map("intval", $entryStmt->fetchAll(PDO::FETCH_COLUMN));

// LOCKED / hand-edited per-entry charges — so the PDF reflects the same amounts as
// the Charges modal and the (re)generated file. entry_id => peso charge.
$storedCharges = [];
if (!empty($entryIds)) {
    $placeholders = implode(",", array_fill(0, count($entryIds), "?"));
    $chargeStmt = $conn->prepare("SELECT entry_id, rate_charge FROM billing_invoice_entries WHERE invoice_id = ? AND entry_id IN ($placeholders)");
    $chargeStmt->execute(array_merge([$id], $entryIds));
    foreach ($chargeStmt->fetchAll(PDO::FETCH_ASSOC) as $chargeRow) {
        if (is_numeric($chargeRow["rate_charge"])) {
            $storedCharges[(int) $chargeRow["entry_id"]] = (float) $chargeRow["rate_charge"];
        }
    }
}

// Locked charges belong to the invoice's OWN activity. The Actions dropdown can view a
// DIFFERENT activity's statement from these trips (?activity=), e.g. the Chassis rental
// off a Hauling invoice — those hauling amounts must NOT override the rental/fuel formula,
// so drop them and let the requested activity recompute its real charge.
$invoiceActivity = (string) ($invoice["activity"] ?? "hauling");
if ($activityCode !== $invoiceActivity) {
    $storedCharges = [];
}

// ---- ABC KDs PANABO statement (one line per trip) ----
if ($pipeline === "kds") {
    $kds = abc_kds_customer($customerKey);
    if ($kds === null) {
        http_response_code(404);
        header("Content-Type: text/plain; charset=utf-8");
        echo "Unknown KDs customer configuration.";
        exit();
    }

    try {
        $entries = abc_kds_fetch_by_ids($conn, $entryIds);
        $built = abc_kds_detail_rows($conn, $entries, $kds);
        $applied = billing_pdf_apply_stored_charges($built["rows"], $built["entry_ids"], "charge", $storedCharges);
        $built["rows"] = $applied["rows"];
        $built["total"] = $applied["total"];
        $pdfBytes = build_activity_pdf([
            "charge_to" => $kds["charge_to"] ?? $kds["label"],
            "activity" => $kds["pdf_activity"] ?? "HAULING OF KD CARTONS",
            "destination" => $kds["pdf_destination"] ?? "",
            "sap_doc" => "",
            "date_from" => $invoice["date_from"],
            "date_to" => $invoice["date_to"],
        ], abc_kds_pdf_columns(), $built["rows"], "charge", $built["total"]);
    } catch (Throwable $e) {
        http_response_code(500);
        header("Content-Type: text/plain; charset=utf-8");
        echo "PDF build failed: " . $e->getMessage();
        exit();
    }

    $pdfName = $pdfBase . ".pdf";
    header("Content-Type: application/pdf");
    header('Content-Disposition: ' . $disposition . '; filename="' . $pdfName . '"');
    header("Content-Length: " . (string) strlen($pdfBytes));
    header("Cache-Control: max-age=0");
    echo $pdfBytes;
    exit();
}

// ---- Dry Vans PANABO statement (one line per trip) ----
if ($pipeline === "dryvan") {
    $dv = dry_van_customer($customerKey);
    if ($dv === null) {
        http_response_code(404);
        header("Content-Type: text/plain; charset=utf-8");
        echo "Unknown dry van customer configuration.";
        exit();
    }
    $dv = billing_customer_apply_sap_overrides($conn, $customerKey, $dv);
    $rate = dry_van_rate($conn, $dv);

    try {
        $entries = dry_van_fetch_by_ids($conn, $entryIds);
        $rateByEntry = dry_van_rates_by_entry($conn, $dv, $entries, $customerKey);
        $built = dry_van_detail_rows($entries, $dv, $rate, $rateByEntry);
        $applied = billing_pdf_apply_stored_charges($built["rows"], $built["entry_ids"], "charge", $storedCharges);
        $built["rows"] = $applied["rows"];
        $built["total"] = $applied["total"];
        $pdfBytes = build_activity_pdf([
            "charge_to" => $dv["charge_to"] ?? $dv["label"],
            "activity" => $dv["pdf_activity"] ?? "HAULING OF DRY VANS",
            "destination" => $dv["pdf_destination"] ?? "",
            "sap_doc" => "",
            "date_from" => $invoice["date_from"],
            "date_to" => $invoice["date_to"],
        ], dry_van_pdf_columns(), $built["rows"], "charge", $built["total"]);
    } catch (Throwable $e) {
        http_response_code(500);
        header("Content-Type: text/plain; charset=utf-8");
        echo "PDF build failed: " . $e->getMessage();
        exit();
    }

    $pdfName = $pdfBase . ".pdf";
    header("Content-Type: application/pdf");
    header('Content-Disposition: ' . $disposition . '; filename="' . $pdfName . '"');
    header("Content-Length: " . (string) strlen($pdfBytes));
    header("Cache-Control: max-age=0");
    echo $pdfBytes;
    exit();
}

// ---- DICT Van Shuttling PANABO statement (one line per trip receipt) ----
if ($pipeline === "shuttling") {
    $cfg = dict_shuttling_config();
    $laneKey = trim((string) ($invoice["activity"] ?? ""));
    if (!isset($cfg["lanes"][$laneKey])) {
        http_response_code(404);
        header("Content-Type: text/plain; charset=utf-8");
        echo "Unknown shuttling lane for this invoice.";
        exit();
    }

    try {
        $entries = dict_shuttling_fetch_by_ids($conn, $entryIds);
        $built = dict_shuttling_detail_rows($entries, $laneKey, $conn, (string) ($invoice["date_to"] ?? ""));
        $pdfBytes = build_activity_pdf([
            "charge_to" => $cfg["charge_to"],
            "activity" => $cfg["pdf_activity"],
            "destination" => $cfg["lanes"][$laneKey]["pdf_destination"],
            "sap_doc" => "",
            "date_from" => $invoice["date_from"],
            "date_to" => $invoice["date_to"],
        ], dict_shuttling_pdf_columns(), $built["rows"], "amount", $built["total"], 0.0, true); // $vat = true (12% VAT on the statement)
    } catch (Throwable $e) {
        http_response_code(500);
        header("Content-Type: text/plain; charset=utf-8");
        echo "PDF build failed: " . $e->getMessage();
        exit();
    }

    $pdfName = $pdfBase . ".pdf";
    header("Content-Type: application/pdf");
    header('Content-Disposition: ' . $disposition . '; filename="' . $pdfName . '"');
    header("Content-Length: " . (string) strlen($pdfBytes));
    header("Cache-Control: max-age=0");
    echo $pdfBytes;
    exit();
}

// ---- DICT Industrial Waste / Garbage PANABO statement (per route, with 12% VAT) ----
if ($pipeline === "industrial_waste") {
    $cfg = industrial_waste_config();
    try {
        // ONE combined statement covering both routes (BEHIND + WATERFALL), with the
        // DELIVERY AREA column and a single 12% VAT total.
        $entries = industrial_waste_fetch_by_ids($conn, $entryIds);
        $built = industrial_waste_detail_rows($entries, $conn, (string) ($invoice["date_to"] ?? ""));
        $pdfBytes = build_activity_pdf([
            "charge_to" => $cfg["charge_to"],
            "activity" => $cfg["pdf_activity"],
            "destination" => $cfg["pdf_destination"],
            "sap_doc" => "",
            "date_from" => $invoice["date_from"],
            "date_to" => $invoice["date_to"],
        ], industrial_waste_pdf_columns(), $built["rows"], "amount", $built["total"], 0.0, true); // $vat = true
    } catch (Throwable $e) {
        http_response_code(500);
        header("Content-Type: text/plain; charset=utf-8");
        echo "PDF build failed: " . $e->getMessage();
        exit();
    }

    $pdfName = $pdfBase . ".pdf";
    header("Content-Type: application/pdf");
    header('Content-Disposition: ' . $disposition . '; filename="' . $pdfName . '"');
    header("Content-Length: " . (string) strlen($pdfBytes));
    header("Cache-Control: max-age=0");
    echo $pdfBytes;
    exit();
}

// ---- Non-hauling activity PDF (generic PANABO statement) ----
if ($activityCode !== "hauling" && billing_activity($activityCode) !== null) {
    $bb = box_banana_customer($customerKey);
    if ($bb === null) {
        http_response_code(404);
        header("Content-Type: text/plain; charset=utf-8");
        echo "Unknown customer configuration.";
        exit();
    }
    $currency = strtoupper((string) ($bb["document_currency"] ?? "PHP"));
    $forex = is_numeric($invoice["forex_rate"]) ? (float) $invoice["forex_rate"] : ($currency === "USD" ? billing_forex_rate($conn, $invoice["date_to"], $customerKey) : 1.0);
    if ($forex <= 0) {
        $forex = 1.0;
    }
    $actEntries = activity_fetch_entries_by_ids($conn, $entryIds);
    // Pass the locked charges as overrides so the PDF matches the (re)generated file.
    $built = activity_build($conn, $actEntries, $bb, $customerKey, $activityCode, $forex, (string) $invoice["reference"], 0.0, $storedCharges);
    $pdfBytes = build_activity_pdf([
        "charge_to" => $bb["charge_to"] ?? $bb["label"] ?? "",
        "activity" => $built["label"] ?? "",
        // Use the fixed statement destination when set, matching the hauling PDF.
        "destination" => billing_pdf_destination($actEntries, $bb),
        "sap_doc" => $invoice["document_no"] ?? "",
        "date_from" => $invoice["date_from"],
        "date_to" => $invoice["date_to"],
    ], $built["columns"], $built["detail_rows"], "charge", $built["total"], $currency === "USD" ? $forex : 0.0);

    $pdfName = $pdfBase . "_" . $activityCode . ".pdf";
    header("Content-Type: application/pdf");
    header('Content-Disposition: ' . $disposition . '; filename="' . $pdfName . '"');
    header("Content-Length: " . (string) strlen($pdfBytes));
    header("Cache-Control: max-age=0");
    echo $pdfBytes;
    exit();
}

$entries = billing_pdf_entries($conn, $entryIds);
$manualForexByEntry = [];
if (!empty($entryIds)) {
    $placeholders = implode(",", array_fill(0, count($entryIds), "?"));
    $manualStmt = $conn->prepare("SELECT entry_id, forex_rate FROM billing_invoice_entry_forex WHERE invoice_id = ? AND entry_id IN ($placeholders)");
    $manualStmt->execute(array_merge([$id], $entryIds));
    foreach ($manualStmt->fetchAll(PDO::FETCH_ASSOC) as $manualRow) {
        $manualForexByEntry[(int) $manualRow["entry_id"]] = (float) $manualRow["forex_rate"];
    }
}

// Resolve each trip's automatic or manually supplied forex for the PDF columns.
$forexByEntry = [];
foreach ($entries as $row) {
    $entryId = (int) ($row["entry_id"] ?? 0);
    $tripDate = billing_pdf_first(
        $row["loaded_van_loading_start_date"] ?? "",
        $row["waybill_date"] ?? "",
        $row["date_hauled"] ?? "",
        $row["pullout_location_departure_date"] ?? "",
        substr((string) ($row["created_date"] ?? ""), 0, 10)
    );
    $lineForex = (float) ($manualForexByEntry[$entryId] ?? 0);
    if ($lineForex <= 0) {
        $lineForex = billing_forex_rate($conn, substr($tripDate, 0, 10), $customerKey);
    }
    if ($lineForex > 0) {
        $forexByEntry[$entryId] = $lineForex;
    }
}

// A PDF has one footer USD total, so only show it when every included trip has the
// same forex rate that covers its own transaction date. Otherwise the dollar
// conversion is intentionally omitted.
$effectiveForexForEntries = static function (array $rows) use ($forexByEntry): float {
    $effective = null;
    foreach ($rows as $row) {
        $lineForex = (float) ($forexByEntry[(int) ($row["entry_id"] ?? 0)] ?? 0);
        if ($lineForex <= 0) {
            return 0.0;
        }
        if ($effective === null) {
            $effective = $lineForex;
        } elseif (abs($effective - $lineForex) > 0.000001) {
            return 0.0;
        }
    }
    return $effective ?? 0.0;
};

$ratesByEntryId = [];
$showUsd = true;

if ($pipeline === "matrix") {
    // Breakbulk customers: map the box-banana config onto the PDF's invoice
    // header, price each trip via the rate matrix, and (PHP) drop the USD total.
    $bb = box_banana_customer($customerKey);
    if ($bb === null) {
        http_response_code(404);
        header("Content-Type: text/plain; charset=utf-8");
        echo "Unknown customer configuration.";
        exit();
    }
    $customer = [
        "bill_to_name" => $bb["charge_to"] ?? $bb["label"] ?? "",
        "activity" => $bb["pdf_activity"] ?? $bb["activity"] ?? "Hauling Containerized Bananas",
        "destination" => $bb["destination"] ?? "",
        "pdf_origin" => $bb["pdf_origin"] ?? "",
        "pdf_destination" => $bb["pdf_destination"] ?? "",
        "pdf_layout" => $bb["pdf_layout"] ?? "",
        // Show the PH (packing house) column on the statement — TDC - Dole Asia only
        // (its approved statement lists PH next to Truck; ABC Lupon's does not).
        "show_ph" => box_banana_matrix_key($customerKey, $bb) === "dole_asia_tdc",
        "pdf_hide_columns" => $bb["pdf_hide_columns"] ?? [],
        "origin" => $bb["origin"] ?? "",
        "packing_station" => $bb["packing_station"] ?? "",
        "port_of_loading" => $bb["port_of_loading"] ?? "",
        // VAT flag (Master Data → Customer SAP Codes): adds 12% VAT on the PDF totals.
        "is_vat" => !empty($bb["is_vat"]),
    ];

    $currency = strtoupper((string) ($bb["document_currency"] ?? "PHP"));
    $showUsd = $currency === "USD";
    $forex = $showUsd ? $effectiveForexForEntries($entries) : 1.0;
    $showUsd = $showUsd && $forex > 0;
    $rate = box_banana_rate($conn, (string) ($bb["rate_code"] ?? ""));

    $matrixKey = box_banana_matrix_key($customerKey, $bb);
    $fuelSource = (string) ($bb["fuel_source"] ?? "");
    foreach ($entries as $e) {
        // Rate/fuel date = the billing trip date (COALESCE(waybill_date,
        // loaded_van_delivery_departure_date), supplied as trip_date by billing_pdf_entries),
        // matching the SAP/preview so the PDF prices each trip in the same fuel period.
        $tripDate = billing_pdf_first(
            $e["trip_date"] ?? "",
            $e["waybill_date"] ?? "",
            $e["loaded_van_delivery_departure_date"] ?? "",
            $e["loaded_van_loading_start_date"] ?? "",
            $e["date_hauled"] ?? "",
            $e["pullout_location_departure_date"] ?? "",
            substr((string) ($e["created_date"] ?? ""), 0, 10)
        );
        $tripDate = substr($tripDate, 0, 10);
        $dcode = billing_pdf_first($e["destination"] ?? "", $e["delivered_to"] ?? "");
        $resolved = resolve_lane_rate($conn, $matrixKey, $dcode, $dcode, $tripDate, $fuelSource);
        $ratesByEntryId[(int) $e["entry_id"]] = !empty($resolved["matched"]) ? (float) $resolved["rate"] : $rate;
    }
} else {
    $customer = billing_customer($customerKey);
    if ($customer === null) {
        http_response_code(404);
        header("Content-Type: text/plain; charset=utf-8");
        echo "Unknown customer configuration.";
        exit();
    }
    $rate = billing_customer_rate($conn, $customer["rate_code"]);
    $currency = strtoupper((string) ($customer["document_currency"] ?? "PHP"));
    $showUsd = $currency === "USD";
    $forex = $showUsd ? $effectiveForexForEntries($entries) : 1.0;
    $showUsd = $showUsd && $forex > 0;

    // Sumifru: price each trip via lane + fuel-period tier from the rates table.
    if (($customer["pricing"] ?? "") === "lane_tier") {
        foreach ($entries as $e) {
            // Rate/fuel date = the billing trip date (see matrix branch above).
            $tripDate = billing_pdf_first(
                $e["trip_date"] ?? "",
                $e["waybill_date"] ?? "",
                $e["loaded_van_delivery_departure_date"] ?? "",
                $e["loaded_van_loading_start_date"] ?? "",
                $e["date_hauled"] ?? "",
                $e["pullout_location_departure_date"] ?? "",
                substr((string) ($e["created_date"] ?? ""), 0, 10)
            );
            $resolved = sumifru_resolve_rate($conn, $e, substr($tripDate, 0, 10));
            $ratesByEntryId[(int) $e["entry_id"]] = $resolved["rate"];
        }
    }
}

// LOCKED / edited charges win over the recomputed per-trip rate (covers flat-rate
// customers too, whose $ratesByEntryId was otherwise empty).
foreach ($storedCharges as $eid => $charge) {
    $ratesByEntryId[(int) $eid] = $charge;
}

try {
    $pdfBytes = build_billing_pdf($entries, $customer, $rate, $forex, [
        "date_from" => $invoice["date_from"],
        "date_to" => $invoice["date_to"],
        "sap_doc" => $invoice["document_no"] ?? "",
    ], $ratesByEntryId, $showUsd);
} catch (Throwable $e) {
    http_response_code(500);
    header("Content-Type: text/plain; charset=utf-8");
    echo "PDF build failed: " . $e->getMessage();
    exit();
}

$pdfName = $pdfBase . ".pdf";
if (!str_ends_with(strtolower($pdfName), ".pdf")) {
    $pdfName .= ".pdf";
}

header("Content-Type: application/pdf");
header('Content-Disposition: ' . $disposition . '; filename="' . $pdfName . '"');
header("Content-Length: " . (string) strlen($pdfBytes));
header("Cache-Control: max-age=0");
echo $pdfBytes;
exit();
?>
