<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/billing_customers.php";
require_once __DIR__ . "/../helpers/build_customer_billing.php";
require_once __DIR__ . "/../helpers/ensure_customer_billing_schema.php";
require_once __DIR__ . "/../helpers/unified_billing.php";
require_once __DIR__ . "/../helpers/build_box_banana_billing.php";
require_once __DIR__ . "/../helpers/ensure_rate_fuel_schema.php";
require_once __DIR__ . "/../helpers/fuel_rate_engine.php";
require_once __DIR__ . "/../helpers/billing_activities.php";
require_once __DIR__ . "/../helpers/build_activity_billing.php";
require_once __DIR__ . "/../helpers/sumifru_rate.php";
require_once __DIR__ . "/../helpers/dict_shuttling.php";
require_once __DIR__ . "/../helpers/abc_kds.php";
require_once __DIR__ . "/../helpers/billing_document.php";
require_once __DIR__ . "/../helpers/customer_sap_codes.php";
require_once __DIR__ . "/../helpers/billing_readiness.php";

date_default_timezone_set("Asia/Manila");
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header("Content-Type: application/json; charset=utf-8");
ensure_customer_billing_schema($conn);
ensure_rate_fuel_schema($conn);

$customerKey = trim((string) ($_POST["customer"] ?? ""));
$activityCode = trim((string) ($_POST["activity"] ?? "hauling")) ?: "hauling";
$dateFrom = trim((string) ($_POST["date_from"] ?? ""));
$dateTo = trim((string) ($_POST["date_to"] ?? ""));
$includeBilled = !empty($_POST["include_billed"]) && $_POST["include_billed"] !== "0";
// Biller-chosen Document Date / Billing Date for the SAP file (blank = today). Set it once
// here so every builder stamps it, and it is persisted on the invoice for later rebuilds.
$documentDate = trim((string) ($_POST["document_date"] ?? ""));
billing_document_date($documentDate);
$selectedEntryIds = array_values(array_filter(array_map(
    "intval",
    preg_split('/\s*,\s*/', (string) ($_POST["selected_entry_ids"] ?? ""), -1, PREG_SPLIT_NO_EMPTY)
), static fn(int $id): bool => $id > 0));
$entryOrder = array_values(array_filter(array_map(
    "intval",
    preg_split('/\s*,\s*/', (string) ($_POST["entry_order"] ?? ""), -1, PREG_SPLIT_NO_EMPTY)
), static fn(int $id): bool => $id > 0));
$manualForexByEntry = [];
$manualForexInput = json_decode((string) ($_POST["manual_forex_by_entry"] ?? "{}"), true);
if (is_array($manualForexInput)) {
    foreach ($manualForexInput as $entryId => $rate) {
        $entryId = (int) $entryId;
        if ($entryId > 0 && is_numeric($rate) && (float) $rate > 0) {
            $manualForexByEntry[$entryId] = (float) $rate;
        }
    }
}
$storeManualForex = static function (PDO $conn, int $invoiceId, array $entryIds) use ($manualForexByEntry): void {
    if (empty($manualForexByEntry)) {
        return;
    }
    $insert = $conn->prepare("INSERT INTO billing_invoice_entry_forex (invoice_id, entry_id, forex_rate) VALUES (?, ?, ?) ON CONFLICT (invoice_id, entry_id) DO UPDATE SET forex_rate = EXCLUDED.forex_rate");
    foreach ($entryIds as $entryId) {
        $entryId = (int) $entryId;
        if (isset($manualForexByEntry[$entryId])) {
            $insert->execute([$invoiceId, $entryId, $manualForexByEntry[$entryId]]);
        }
    }
};
// Header FOREX stored on billing_invoices (shown in the Generated Invoices list). The
// automatic lookup uses the period-END date, which is 0 when no forex master row covers
// it — but the lines may have been priced with a manual per-entry forex. In that case
// surface the manual value so the list doesn't read "0" while the lines used a real rate.
$headerForex = static function (float $forex) use ($manualForexByEntry): float {
    if ($forex > 0) {
        return $forex;
    }
    $vals = array_filter(array_map("floatval", array_values($manualForexByEntry)), static fn($v) => $v > 0);
    return empty($vals) ? $forex : max($vals);
};
// Lock the per-entry PHP peso charge computed at generation time onto each invoice
// entry row, so the billed amount stays reproducible even if a rate/fuel price is
// later edited. Runs after the entry rows are inserted (same transaction). A
// pipeline that does not compute charges (e.g. DICT aggregate) passes an empty map.
$storeRateCharges = static function (PDO $conn, int $invoiceId, array $chargeByEntry): void {
    // BATCHED: one UPDATE per ~500 entries via a VALUES join, instead of a round-trip per
    // entry. A per-row loop here was a main cause of generate timeouts (HTTP 504) on large
    // date ranges (e.g. 1,500+ trips = 1,500 UPDATEs).
    $pairs = [];
    foreach ($chargeByEntry as $entryId => $charge) {
        if (is_numeric($charge)) {
            $pairs[(int) $entryId] = round((float) $charge, 2);
        }
    }
    if (empty($pairs)) {
        return;
    }
    foreach (array_chunk($pairs, 500, true) as $chunk) {
        $vals = [];
        $params = [];
        foreach ($chunk as $entryId => $charge) {
            $vals[] = "(?::int, ?::numeric)";
            $params[] = $entryId;
            $params[] = $charge;
        }
        $params[] = $invoiceId;
        $sql = "UPDATE billing_invoice_entries AS t SET rate_charge = v.charge
                FROM (VALUES " . implode(",", $vals) . ") AS v(entry_id, charge)
                WHERE t.entry_id = v.entry_id AND t.invoice_id = ?";
        $conn->prepare($sql)->execute($params);
    }
};

// Insert the invoice's trip rows in BATCHES (one multi-row INSERT per ~1000 entries)
// instead of a round-trip per entry — the other main cause of generate timeouts.
$insertEntries = static function (PDO $conn, int $invoiceId, array $entryIds): void {
    $ids = array_values(array_unique(array_filter(array_map("intval", $entryIds), static fn(int $v): bool => $v > 0)));
    if (empty($ids)) {
        return;
    }
    foreach (array_chunk($ids, 1000) as $chunk) {
        $vals = [];
        $params = [];
        foreach ($chunk as $eid) {
            $vals[] = "(?, ?)";
            $params[] = $invoiceId;
            $params[] = $eid;
        }
        $sql = "INSERT INTO billing_invoice_entries (invoice_id, entry_id) VALUES "
            . implode(",", $vals) . " ON CONFLICT DO NOTHING";
        $conn->prepare($sql)->execute($params);
    }
};

$pipeline = unified_billing_pipeline($customerKey);
if ($pipeline === "") {
    echo json_encode(["success" => false, "message" => "Unknown customer."]);
    exit();
}
$customer = $pipeline === "matrix" ? box_banana_customer($customerKey) : billing_customer($customerKey);
if (is_array($customer)) {
    // Overlay finance's Master Data → Customer SAP Codes (Sold-To / Material / Profit Center).
    $customer = billing_customer_apply_sap_overrides($conn, $customerKey, $customer);
}
if (
    $dateFrom === "" || $dateTo === "" ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ||
    $dateFrom > $dateTo
) {
    echo json_encode(["success" => false, "message" => "Invalid date range."]);
    exit();
}

// Block generation (with a SweetAlert on the page) when the customer has no Service
// Material / Profit Center / Rate Matrix assigned. Applies to the fuel-rate-matrix
// pipelines (Box Bananas + Sumifru), hauling only — DICT/KDs price differently.
if (in_array($pipeline, ["matrix", "sap"], true) && $activityCode === "hauling" && is_array($customer)) {
    $missingAssignments = billing_customer_missing_assignments($conn, $customerKey, $customer, $dateTo);
    if (!empty($missingAssignments)) {
        echo json_encode([
            "success" => false,
            "blocked" => true,
            "missing" => $missingAssignments,
            "message" => "Cannot generate billing — assign the missing Master Data for this customer first.",
        ]);
        exit();
    }
}

// Document number for this generated billing (PREFIX-YYYY-NNN, sequence per
// customer per year). Combined into each branch's reference so it lands in the
// SAP Reference (J) column + file name, and stored on the invoice for lookup.
$documentNo = billing_next_document_no($conn, $customerKey);

// ---- ABC KDs: one line per trip, rate from this customer's own KDs matrix ----
if ($pipeline === "kds") {
    $kds = abc_kds_customer($customerKey);
    if (is_array($kds)) {
        $kds = billing_customer_apply_sap_overrides($conn, $customerKey, $kds);
    }

    // Readiness guard (blocked + SweetAlert on the page): Service Material + Profit
    // Center + the KDs rate matrix, plus KDs' own required SAP config (Sold-To / Route).
    if (is_array($kds)) {
        $kdsMissing = billing_customer_missing_sap_master($conn, $kds);
        if (!abc_kds_has_source_matrix($conn, $kds)) {
            $kdsMissing[] = "Box-banana Rate Matrix for the KDs source ('" . ($kds["box_banana_key"] ?? "") . "')";
        }
        foreach (abc_kds_missing_config($kds) as $cfgField) {
            $kdsMissing[] = "SAP config: " . $cfgField;
        }
        if (!empty($kdsMissing)) {
            echo json_encode([
                "success" => false,
                "blocked" => true,
                "missing" => $kdsMissing,
                "message" => "Cannot generate billing — assign the missing Master Data for this customer first.",
            ]);
            exit();
        }
    }

    $excludeEntryIds = [];
    if (!$includeBilled) {
        $excludeEntryIds = array_map("intval", $conn->query(
            "SELECT DISTINCT entry_id FROM billing_invoice_entries"
        )->fetchAll(PDO::FETCH_COLUMN));
    }

    $reference = billing_document_reference($documentNo, ($kds["reference_prefix"] ?? $kds["label"]) . " - " . billing_month_seq($conn, $customerKey, billing_document_date()));

    try {
        $entries = abc_kds_fetch($conn, $kds, $dateFrom, $dateTo, $excludeEntryIds);
        $entries = box_banana_apply_selection($entries, $selectedEntryIds, $entryOrder);
        $built = abc_kds_build($conn, $entries, $kds, $reference);
    } catch (Throwable $e) {
        echo json_encode(["success" => false, "message" => "Build failed: " . $e->getMessage()]);
        exit();
    }
    if (count($built["rows"]) === 0) {
        echo json_encode([
            "success" => false,
            "message" => count($entries) === 0
                ? "No unbilled KD trips for this date range."
                : "None of the " . count($entries) . " KD trip(s) could be priced from the rate matrix.",
        ]);
        exit();
    }

    $slug = preg_replace('/[^A-Za-z0-9_-]+/', "_", $kds["label"]);
    // Physical file saved in storage/billing/ carries the Month(Year): e.g.
    // "Sumifru Reefer Vans Sep(2026) - 1.xlsx" (the stored Reference stays clean).
    $fileName = billing_storage_filebase($reference, billing_document_date()) . ".xlsx";
    $storageDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "billing";
    if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
        echo json_encode(["success" => false, "message" => "Storage directory could not be created."]);
        exit();
    }
    $absolutePath = $storageDir . DIRECTORY_SEPARATOR . $fileName;
    $relativePath = "storage/billing/" . $fileName;

    try {
        billing_write_xlsx($built["rows"], $built["total"], $absolutePath);
        billing_write_csv_sibling($built["rows"], $built["total"], $absolutePath);
    } catch (Throwable $e) {
        @unlink($absolutePath);
        echo json_encode(["success" => false, "message" => "Build failed: " . $e->getMessage()]);
        exit();
    }

    $requestedBy = $_SESSION["user_idNumber"] ?? ($_SESSION["user_name"] ?? "system");
    $now = date("Y-m-d H:i:s");
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare('
            INSERT INTO billing_invoices (
                invoice_id, customer_key, customer_label, reference, date_from, date_to,
                forex_rate, file_name, file_path, file_size_bytes, line_count,
                requested_by, requested_at, status, activity
            ) VALUES (
                COALESCE((SELECT MAX(invoice_id) FROM billing_invoices), 0) + 1,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            ) RETURNING invoice_id
        ');
        $stmt->execute([
            $customerKey, $kds["label"], $reference, $dateFrom, $dateTo,
            1.0, $fileName, $relativePath, (int) filesize($absolutePath), count($built["rows"]),
            $requestedBy, $now, "ready", "kds",
        ]);
        $invoiceId = (int) $stmt->fetchColumn();
        $conn->prepare("UPDATE billing_invoices SET document_no = ?, document_date = ? WHERE invoice_id = ?")->execute([$documentNo, billing_document_date(), $invoiceId]);
        if (!empty($built["entry_ids"])) {
            $insertEntries($conn, $invoiceId, $built["entry_ids"]);
            $storeRateCharges($conn, $invoiceId, $built["charge_by_entry"] ?? []);
        }
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        @unlink($absolutePath);
        echo json_encode(["success" => false, "message" => "Could not record invoice: " . $e->getMessage()]);
        exit();
    }

    echo json_encode([
        "success" => true,
        "message" => sprintf(
            "Generated %d %s line(s) = PHP %s.%s",
            count($built["rows"]), $kds["label"], number_format($built["total"], 2),
            $built["unpriced"] > 0 ? " " . $built["unpriced"] . " trip(s) skipped (no matrix rate)." : ""
        ),
        "invoice" => [
            "invoice_id" => $invoiceId,
            "reference" => $reference,
            "file_name" => $fileName,
            "line_count" => count($built["rows"]),
            "download_url" => "php/fetch/download_customer_billing.php?id=" . $invoiceId,
        ],
    ]);
    exit();
}

// ---- Dry Vans (dry container hauling): flat rate per trip, SAP ZPSO + PDF. ----
if ($pipeline === "dryvan") {
    $dv = dry_van_customer($customerKey);
    if (is_array($dv)) {
        $dv = billing_customer_apply_sap_overrides($conn, $customerKey, $dv);
    }
    if (!is_array($dv)) {
        echo json_encode(["success" => false, "message" => "Unknown dry van customer configuration."]);
        exit();
    }

    // Readiness guard: SAP master (Material / Profit Center) + Sold-To + a rate. Matrix
    // customers (e.g. CITIHARDWARE) are priced per-lane from the Rate Matrix, not a flat
    // rate — an unmatched lane simply yields no line (guarded below), so skip the flat-rate
    // requirement for them.
    $rate = dry_van_rate($conn, $dv);
    $dvMissing = billing_customer_missing_sap_master($conn, $dv);
    foreach (dry_van_missing_config($dv) as $cfgField) {
        $dvMissing[] = "SAP config: " . $cfgField;
    }
    if (!dry_van_is_matrix($dv) && $rate <= 0) {
        $dvMissing[] = "Flat rate (add rate code '" . ($dv["rate_code"] ?? "") . "' in Master Data → Rates)";
    }
    if (!empty($dvMissing)) {
        echo json_encode([
            "success" => false,
            "blocked" => true,
            "missing" => $dvMissing,
            "message" => "Cannot generate billing — assign the missing Master Data for this customer first.",
        ]);
        exit();
    }

    $excludeEntryIds = [];
    if (!$includeBilled) {
        $excludeEntryIds = array_map("intval", $conn->query(
            "SELECT DISTINCT entry_id FROM billing_invoice_entries"
        )->fetchAll(PDO::FETCH_COLUMN));
    }

    $reference = billing_document_reference($documentNo, ($dv["reference_prefix"] ?? $dv["label"]) . " - " . billing_month_seq($conn, $customerKey, billing_document_date()));

    try {
        $entries = dry_van_fetch($conn, $dv, $dateFrom, $dateTo, $excludeEntryIds);
        $entries = box_banana_apply_selection($entries, $selectedEntryIds, $entryOrder);
        $rateByEntry = dry_van_rates_by_entry($conn, $dv, $entries, $customerKey);
        $built = dry_van_build($conn, $entries, $dv, $rate, $reference, [], $rateByEntry);
    } catch (Throwable $e) {
        echo json_encode(["success" => false, "message" => "Build failed: " . $e->getMessage()]);
        exit();
    }
    if (count($built["rows"]) === 0) {
        echo json_encode(["success" => false, "message" => "No unbilled dry van trips for this date range."]);
        exit();
    }

    $slug = preg_replace('/[^A-Za-z0-9_-]+/', "_", $dv["label"]);
    // Physical file saved in storage/billing/ carries the Month(Year): e.g.
    // "Sumifru Reefer Vans Sep(2026) - 1.xlsx" (the stored Reference stays clean).
    $fileName = billing_storage_filebase($reference, billing_document_date()) . ".xlsx";
    $storageDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "billing";
    if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
        echo json_encode(["success" => false, "message" => "Storage directory could not be created."]);
        exit();
    }
    $absolutePath = $storageDir . DIRECTORY_SEPARATOR . $fileName;
    $relativePath = "storage/billing/" . $fileName;

    try {
        billing_write_xlsx($built["rows"], $built["total"], $absolutePath);
        billing_write_csv_sibling($built["rows"], $built["total"], $absolutePath);
    } catch (Throwable $e) {
        @unlink($absolutePath);
        echo json_encode(["success" => false, "message" => "Build failed: " . $e->getMessage()]);
        exit();
    }

    $requestedBy = $_SESSION["user_idNumber"] ?? ($_SESSION["user_name"] ?? "system");
    $now = date("Y-m-d H:i:s");
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare('
            INSERT INTO billing_invoices (
                invoice_id, customer_key, customer_label, reference, date_from, date_to,
                forex_rate, file_name, file_path, file_size_bytes, line_count,
                requested_by, requested_at, status, activity
            ) VALUES (
                COALESCE((SELECT MAX(invoice_id) FROM billing_invoices), 0) + 1,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            ) RETURNING invoice_id
        ');
        $stmt->execute([
            $customerKey, $dv["label"], $reference, $dateFrom, $dateTo,
            1.0, $fileName, $relativePath, (int) filesize($absolutePath), count($built["rows"]),
            $requestedBy, $now, "ready", "dryvan",
        ]);
        $invoiceId = (int) $stmt->fetchColumn();
        $conn->prepare("UPDATE billing_invoices SET document_no = ?, document_date = ? WHERE invoice_id = ?")->execute([$documentNo, billing_document_date(), $invoiceId]);
        if (!empty($built["entry_ids"])) {
            $insertEntries($conn, $invoiceId, $built["entry_ids"]);
            $storeRateCharges($conn, $invoiceId, $built["charge_by_entry"] ?? []);
        }
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        @unlink($absolutePath);
        echo json_encode(["success" => false, "message" => "Could not record invoice: " . $e->getMessage()]);
        exit();
    }

    echo json_encode([
        "success" => true,
        "message" => sprintf("Generated %d %s line(s) = PHP %s.", count($built["rows"]), $dv["label"], number_format($built["total"], 2)),
        "invoice" => [
            "invoice_id" => $invoiceId,
            "reference" => $reference,
            "file_name" => $fileName,
            "line_count" => count($built["rows"]),
            "download_url" => "php/fetch/download_customer_billing.php?id=" . $invoiceId,
        ],
    ]);
    exit();
}

// ---- DICT Van Shuttling: one file per lane, lines grouped by (truck, trailer).
// The lane is stored in the invoice's `activity` column so each lane bills its
// trips once and shows separately in Generated Invoices. ----
if ($pipeline === "shuttling") {
    $shuttling = billing_customer_apply_sap_overrides($conn, $customerKey, dict_shuttling_config());
    $laneKey = trim((string) ($_POST["lane"] ?? ""));
    $laneOptions = dict_shuttling_lane_options();
    if ($laneKey === "" || !isset($laneOptions[$laneKey])) {
        echo json_encode(["success" => false, "message" => "Select a shuttling lane."]);
        exit();
    }

    // Readiness guard (blocked + SweetAlert): Service Material + Profit Center. DICT has
    // no fuel rate matrix (it bills fixed lane prices), so only these two are checked.
    $dictMissing = billing_customer_missing_sap_master($conn, $shuttling);
    if (!empty($dictMissing)) {
        echo json_encode([
            "success" => false,
            "blocked" => true,
            "missing" => $dictMissing,
            "message" => "Cannot generate billing — assign the missing Master Data for this customer first.",
        ]);
        exit();
    }

    $excludeEntryIds = [];
    if (!$includeBilled) {
        $stmt = $conn->prepare(
            "SELECT DISTINCT e.entry_id FROM billing_invoice_entries e
             JOIN billing_invoices i ON i.invoice_id = e.invoice_id
             WHERE i.customer_key = ? AND i.activity = ? AND i.status <> 'deleted'"
        );
        $stmt->execute([$customerKey, $laneKey]);
        $excludeEntryIds = array_map("intval", $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    $laneLabel = $laneOptions[$laneKey];
    $reference = billing_document_reference($documentNo, sprintf("%s (%s)", $shuttling["label"], $laneLabel) . " - " . billing_month_seq($conn, $customerKey, billing_document_date()));

    try {
        // Lines aggregate several trips, so per-row selection does not apply here.
        $entries = dict_shuttling_fetch($conn, $dateFrom, $dateTo, $excludeEntryIds);
        $built = dict_shuttling_build($entries, $laneKey, $reference, $shuttling, $conn, $dateTo);
    } catch (Throwable $e) {
        echo json_encode(["success" => false, "message" => "Build failed: " . $e->getMessage()]);
        exit();
    }
    if (count($built["rows"]) === 0) {
        $msg = $includeBilled
            ? "No shuttling trips for " . $laneLabel . " in this date range."
            : "No unbilled shuttling trips for " . $laneLabel . " in this date range. Tick \"Include already-billed\" to re-generate.";
        echo json_encode(["success" => false, "message" => $msg]);
        exit();
    }

    $slug = preg_replace('/[^A-Za-z0-9_-]+/', "_", $shuttling["label"] . "_" . $laneLabel);
    // Physical file saved in storage/billing/ carries the Month(Year): e.g.
    // "Sumifru Reefer Vans Sep(2026) - 1.xlsx" (the stored Reference stays clean).
    $fileName = billing_storage_filebase($reference, billing_document_date()) . ".xlsx";
    $storageDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "billing";
    if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
        echo json_encode(["success" => false, "message" => "Storage directory could not be created."]);
        exit();
    }
    $absolutePath = $storageDir . DIRECTORY_SEPARATOR . $fileName;
    $relativePath = "storage/billing/" . $fileName;

    try {
        billing_write_xlsx($built["rows"], $built["total"], $absolutePath);
        billing_write_csv_sibling($built["rows"], $built["total"], $absolutePath);
    } catch (Throwable $e) {
        @unlink($absolutePath);
        echo json_encode(["success" => false, "message" => "Build failed: " . $e->getMessage()]);
        exit();
    }

    $requestedBy = $_SESSION["user_idNumber"] ?? ($_SESSION["user_name"] ?? "system");
    $now = date("Y-m-d H:i:s");
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare('
            INSERT INTO billing_invoices (
                invoice_id, customer_key, customer_label, reference, date_from, date_to,
                forex_rate, file_name, file_path, file_size_bytes, line_count,
                requested_by, requested_at, status, activity
            ) VALUES (
                COALESCE((SELECT MAX(invoice_id) FROM billing_invoices), 0) + 1,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            ) RETURNING invoice_id
        ');
        $stmt->execute([
            $customerKey, $shuttling["label"] . " - " . $laneLabel, $reference, $dateFrom, $dateTo,
            1.0, $fileName, $relativePath, (int) filesize($absolutePath), count($built["rows"]),
            $requestedBy, $now, "ready", $laneKey,
        ]);
        $invoiceId = (int) $stmt->fetchColumn();
        $conn->prepare("UPDATE billing_invoices SET document_no = ?, document_date = ? WHERE invoice_id = ?")->execute([$documentNo, billing_document_date(), $invoiceId]);
        if (!empty($built["entry_ids"])) {
            $insertEntries($conn, $invoiceId, $built["entry_ids"]);
            $storeRateCharges($conn, $invoiceId, $built["charge_by_entry"] ?? []);
        }
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        @unlink($absolutePath);
        echo json_encode(["success" => false, "message" => "Could not record invoice: " . $e->getMessage()]);
        exit();
    }

    echo json_encode([
        "success" => true,
        "message" => sprintf(
            "Generated %d %s line(s) — %s trips x PHP %s = PHP %s.",
            count($built["rows"]),
            $laneLabel,
            number_format(array_sum(array_map(static fn($r) => (float) $r[14][1], $built["rows"])), 0),
            number_format((float) $built["lane"]["price"], 2),
            number_format($built["total"], 2)
        ),
        "invoice" => [
            "invoice_id" => $invoiceId,
            "reference" => $reference,
            "file_name" => $fileName,
            "line_count" => count($built["rows"]),
            "download_url" => "php/fetch/download_customer_billing.php?id=" . $invoiceId,
        ],
    ]);
    exit();
}

// ---- DICT Industrial Waste / Garbage: one file per route, lines grouped by (truck, trailer).
// Mirrors DICT Van Shuttling; the route is stored in the invoice's `activity` column. ----
if ($pipeline === "industrial_waste") {
    $waste = billing_customer_apply_sap_overrides($conn, $customerKey, industrial_waste_config());

    // Readiness guard: Service Material + Profit Center (flat per-route pricing, no fuel matrix).
    $wasteMissing = billing_customer_missing_sap_master($conn, $waste);
    if (!empty($wasteMissing)) {
        echo json_encode([
            "success" => false,
            "blocked" => true,
            "missing" => $wasteMissing,
            "message" => "Cannot generate billing — assign the missing Master Data for this customer first.",
        ]);
        exit();
    }

    // ONE combined billing for both routes (BEHIND + WATERFALL), so exclude any trip already
    // in a garbage invoice for this customer. The invoice's `activity` is a fixed marker.
    $wasteActivity = "garbage";
    $excludeEntryIds = [];
    if (!$includeBilled) {
        $stmt = $conn->prepare(
            "SELECT DISTINCT e.entry_id FROM billing_invoice_entries e
             JOIN billing_invoices i ON i.invoice_id = e.invoice_id
             WHERE i.customer_key = ? AND i.status <> 'deleted'"
        );
        $stmt->execute([$customerKey]);
        $excludeEntryIds = array_map("intval", $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    $reference = billing_document_reference($documentNo, $waste["label"] . " - " . billing_month_seq($conn, $customerKey, billing_document_date()));

    try {
        $entries = industrial_waste_fetch($conn, $dateFrom, $dateTo, $excludeEntryIds);
        $built = industrial_waste_build($entries, $reference, $waste, $conn, $dateTo);
    } catch (Throwable $e) {
        echo json_encode(["success" => false, "message" => "Build failed: " . $e->getMessage()]);
        exit();
    }
    if (count($built["rows"]) === 0) {
        $msg = $includeBilled
            ? "No industrial-waste (garbage) trips in this date range."
            : "No unbilled industrial-waste (garbage) trips in this date range. Tick \"Include already-billed\" to re-generate.";
        echo json_encode(["success" => false, "message" => $msg]);
        exit();
    }

    // Physical file saved in storage/billing/ carries the Month(Year); stored Reference stays clean.
    $fileName = billing_storage_filebase($reference, billing_document_date()) . ".xlsx";
    $storageDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "billing";
    if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
        echo json_encode(["success" => false, "message" => "Storage directory could not be created."]);
        exit();
    }
    $absolutePath = $storageDir . DIRECTORY_SEPARATOR . $fileName;
    $relativePath = "storage/billing/" . $fileName;

    try {
        billing_write_xlsx($built["rows"], $built["total"], $absolutePath);
        billing_write_csv_sibling($built["rows"], $built["total"], $absolutePath);
    } catch (Throwable $e) {
        @unlink($absolutePath);
        echo json_encode(["success" => false, "message" => "Build failed: " . $e->getMessage()]);
        exit();
    }

    $requestedBy = $_SESSION["user_idNumber"] ?? ($_SESSION["user_name"] ?? "system");
    $now = date("Y-m-d H:i:s");
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare('
            INSERT INTO billing_invoices (
                invoice_id, customer_key, customer_label, reference, date_from, date_to,
                forex_rate, file_name, file_path, file_size_bytes, line_count,
                requested_by, requested_at, status, activity
            ) VALUES (
                COALESCE((SELECT MAX(invoice_id) FROM billing_invoices), 0) + 1,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            ) RETURNING invoice_id
        ');
        $stmt->execute([
            $customerKey, $waste["label"], $reference, $dateFrom, $dateTo,
            1.0, $fileName, $relativePath, (int) filesize($absolutePath), count($built["rows"]),
            $requestedBy, $now, "ready", $wasteActivity,
        ]);
        $invoiceId = (int) $stmt->fetchColumn();
        $conn->prepare("UPDATE billing_invoices SET document_no = ?, document_date = ? WHERE invoice_id = ?")->execute([$documentNo, billing_document_date(), $invoiceId]);
        if (!empty($built["entry_ids"])) {
            $insertEntries($conn, $invoiceId, $built["entry_ids"]);
            $storeRateCharges($conn, $invoiceId, $built["charge_by_entry"] ?? []);
        }
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        @unlink($absolutePath);
        echo json_encode(["success" => false, "message" => "Could not record invoice: " . $e->getMessage()]);
        exit();
    }

    echo json_encode([
        "success" => true,
        "message" => sprintf(
            "Generated %d garbage line(s) — %s trips = PHP %s (12%% VAT shown on the PDF).",
            count($built["rows"]),
            number_format(array_sum(array_map(static fn($r) => (float) $r[14][1], $built["rows"])), 0),
            number_format($built["total"], 2)
        ),
        "invoice" => [
            "invoice_id" => $invoiceId,
            "reference" => $reference,
            "file_name" => $fileName,
            "line_count" => count($built["rows"]),
            "download_url" => "php/fetch/download_customer_billing.php?id=" . $invoiceId,
        ],
    ]);
    exit();
}

// ---- Non-hauling activity (chassis / genset / container_van / fuel) ----
if ($activityCode !== "hauling" && billing_activity($activityCode) !== null) {
    if ($pipeline !== "matrix") {
        echo json_encode(["success" => false, "message" => "Activity billing is available for the breakbulk (matrix) customers."]);
        exit();
    }
    $currency = strtoupper((string) ($customer["document_currency"] ?? "PHP"));
    $forex = $currency === "USD" ? billing_forex_rate($conn, $dateTo, $customerKey) : 1.0;
    if ($forex <= 0) {
        $forex = 1.0;
    }

    $excludeEntryIds = [];
    if (!$includeBilled) {
        $excludeEntryIds = array_map("intval", $conn->query(
            "SELECT DISTINCT entry_id FROM billing_invoice_entries e JOIN billing_invoices i ON i.invoice_id = e.invoice_id WHERE i.activity = " . $conn->quote($activityCode)
        )->fetchAll(PDO::FETCH_COLUMN));
    }

    try {
        $entries = activity_fetch_entries($conn, $customer, $dateFrom, $dateTo, $excludeEntryIds);
        $entries = box_banana_apply_selection($entries, $selectedEntryIds, $entryOrder);
    } catch (Throwable $e) {
        echo json_encode(["success" => false, "message" => "Build failed: " . $e->getMessage()]);
        exit();
    }

    $activityDef = billing_activity($activityCode);
    $reference = billing_document_reference($documentNo, ($customer["label"] ?? $customerKey) . " - " . ($activityDef["short"] ?? $activityCode) . " - " . billing_month_seq($conn, $customerKey, billing_document_date()));

    $built = activity_build($conn, $entries, $customer, $customerKey, $activityCode, $forex, $reference, 0.0);
    if (count($built["sap_rows"]) === 0) {
        echo json_encode(["success" => false, "message" => "No chargeable " . ($activityDef["short"] ?? $activityCode) . " lines for this range (no rate set, or no detention/fuel data on the trips)."]);
        exit();
    }

    $slug = preg_replace('/[^A-Za-z0-9_-]+/', "_", ($customer["label"] ?? $customerKey) . "_" . ($activityDef["short"] ?? $activityCode));
    // Physical file saved in storage/billing/ carries the Month(Year): e.g.
    // "Sumifru Reefer Vans Sep(2026) - 1.xlsx" (the stored Reference stays clean).
    $fileName = billing_storage_filebase($reference, billing_document_date()) . ".xlsx";
    $storageDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "billing";
    if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
        echo json_encode(["success" => false, "message" => "Storage directory could not be created."]);
        exit();
    }
    $absolutePath = $storageDir . DIRECTORY_SEPARATOR . $fileName;
    $relativePath = "storage/billing/" . $fileName;

    try {
        billing_write_xlsx($built["sap_rows"], $built["total"], $absolutePath);
        billing_write_csv_sibling($built["sap_rows"], $built["total"], $absolutePath);
    } catch (Throwable $e) {
        @unlink($absolutePath);
        echo json_encode(["success" => false, "message" => "Build failed: " . $e->getMessage()]);
        exit();
    }

    $requestedBy = $_SESSION["user_idNumber"] ?? ($_SESSION["user_name"] ?? "system");
    $now = date("Y-m-d H:i:s");
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare('
            INSERT INTO billing_invoices (
                invoice_id, customer_key, customer_label, reference, date_from, date_to,
                forex_rate, file_name, file_path, file_size_bytes, line_count,
                requested_by, requested_at, status, activity
            ) VALUES (
                COALESCE((SELECT MAX(invoice_id) FROM billing_invoices), 0) + 1,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            ) RETURNING invoice_id
        ');
        $stmt->execute([
            $customerKey, $customer["label"] ?? $customerKey, $reference, $dateFrom, $dateTo,
            $forex, $fileName, $relativePath, (int) filesize($absolutePath), count($built["sap_rows"]),
            $requestedBy, $now, "ready", $activityCode,
        ]);
        $invoiceId = (int) $stmt->fetchColumn();
        $conn->prepare("UPDATE billing_invoices SET document_no = ?, document_date = ? WHERE invoice_id = ?")->execute([$documentNo, billing_document_date(), $invoiceId]);
        if (!empty($built["entry_ids"])) {
            $insertEntries($conn, $invoiceId, $built["entry_ids"]);
            $storeRateCharges($conn, $invoiceId, $built["charge_by_entry"] ?? []);
        }
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        @unlink($absolutePath);
        echo json_encode(["success" => false, "message" => "Could not record invoice: " . $e->getMessage()]);
        exit();
    }

    echo json_encode([
        "success" => true,
        "message" => sprintf("Generated %d %s line(s).", count($built["sap_rows"]), $activityDef["short"] ?? $activityCode),
        "invoice" => [
            "invoice_id" => $invoiceId,
            "reference" => $reference,
            "file_name" => $fileName,
            "line_count" => count($built["sap_rows"]),
            "download_url" => "php/fetch/download_customer_billing.php?id=" . $invoiceId,
        ],
    ]);
    exit();
}

// ---- Matrix pipeline (ABC/TDC breakbulk customers) — records into the same
// billing_invoices table so it appears in the one Generated Invoices list. ----
if ($pipeline === "matrix") {
    $flatRate = box_banana_rate($conn, (string) ($customer["rate_code"] ?? ""));
    $currency = strtoupper((string) ($customer["document_currency"] ?? "PHP"));
    $forex = $currency === "USD" ? billing_forex_rate($conn, $dateTo, $customerKey) : 1.0;
    // box_banana_build_sap_rows resolves forex per transaction date; a
    // transaction outside every forex effective period stays blank in SAP.

    $excludeEntryIds = [];
    if (!$includeBilled) {
        $excludeEntryIds = array_map(
            "intval",
            $conn->query("SELECT DISTINCT entry_id FROM billing_invoice_entries")->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    try {
        $entries = box_banana_fetch_entries($conn, $customer, $dateFrom, $dateTo, $excludeEntryIds);
        $entries = box_banana_apply_selection($entries, $selectedEntryIds, $entryOrder);
    } catch (Throwable $e) {
        echo json_encode(["success" => false, "message" => "Build failed: " . $e->getMessage()]);
        exit();
    }
    if (count($entries) === 0) {
        $msg = $includeBilled
            ? "No trips match the selected customer and date range."
            : "No unbilled trips match the selected customer and date range. Tick \"Include already-billed\" to re-generate.";
        echo json_encode(["success" => false, "message" => $msg]);
        exit();
    }

    $reference = billing_document_reference($documentNo, ($customer["reference_prefix"] ?? $customer["label"]) . " - " . billing_month_seq($conn, $customerKey, billing_document_date()));

    $built = box_banana_build_sap_rows($conn, $entries, $customer, $customerKey, $forex, $reference, $flatRate, null, $manualForexByEntry);
    $forex = $headerForex($forex);

    $slug = preg_replace('/[^A-Za-z0-9_-]+/', "_", $customer["label"]);
    // Physical file saved in storage/billing/ carries the Month(Year): e.g.
    // "Sumifru Reefer Vans Sep(2026) - 1.xlsx" (the stored Reference stays clean).
    $fileName = billing_storage_filebase($reference, billing_document_date()) . ".xlsx";
    $storageDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "billing";
    if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
        echo json_encode(["success" => false, "message" => "Storage directory could not be created."]);
        exit();
    }
    $absolutePath = $storageDir . DIRECTORY_SEPARATOR . $fileName;
    $relativePath = "storage/billing/" . $fileName;

    try {
        billing_write_xlsx($built["rows"], $built["total"], $absolutePath);
        billing_write_csv_sibling($built["rows"], $built["total"], $absolutePath);
    } catch (Throwable $e) {
        @unlink($absolutePath);
        echo json_encode(["success" => false, "message" => "Build failed: " . $e->getMessage()]);
        exit();
    }

    $requestedBy = $_SESSION["user_idNumber"] ?? ($_SESSION["user_name"] ?? "system");
    $now = date("Y-m-d H:i:s");

    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare('
            INSERT INTO billing_invoices (
                invoice_id, customer_key, customer_label, reference, date_from, date_to,
                forex_rate, file_name, file_path, file_size_bytes, line_count,
                requested_by, requested_at, status
            ) VALUES (
                COALESCE((SELECT MAX(invoice_id) FROM billing_invoices), 0) + 1,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            ) RETURNING invoice_id
        ');
        $stmt->execute([
            $customerKey,
            $customer["label"],
            $reference,
            $dateFrom,
            $dateTo,
            $forex,
            $fileName,
            $relativePath,
            (int) filesize($absolutePath),
            count($built["rows"]),
            $requestedBy,
            $now,
            "ready",
        ]);
        $invoiceId = (int) $stmt->fetchColumn();
        $conn->prepare("UPDATE billing_invoices SET document_no = ?, document_date = ? WHERE invoice_id = ?")->execute([$documentNo, billing_document_date(), $invoiceId]);

        if (!empty($built["entry_ids"])) {
            $insertEntries($conn, $invoiceId, $built["entry_ids"]);
            $storeManualForex($conn, $invoiceId, $built["entry_ids"]);
            $storeRateCharges($conn, $invoiceId, $built["charge_by_entry"] ?? []);
        }
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        @unlink($absolutePath);
        echo json_encode(["success" => false, "message" => "Could not record invoice: " . $e->getMessage()]);
        exit();
    }

    echo json_encode([
        "success" => true,
        "message" => sprintf("Generated %d line item(s)%s.", count($built["rows"]), $built["priced_via_matrix"] ? " priced via rate matrix" : ""),
        "invoice" => [
            "invoice_id" => $invoiceId,
            "reference" => $reference,
            "file_name" => $fileName,
            "line_count" => count($built["rows"]),
            "download_url" => "php/fetch/download_customer_billing.php?id=" . $invoiceId,
        ],
    ]);
    exit();
}

// ---- SAP customer with lane + fuel-tier pricing (Sumifru) ----
if ($pipeline === "sap" && ($customer["pricing"] ?? "") === "lane_tier") {
    $currency = strtoupper((string) ($customer["document_currency"] ?? "PHP"));
    $forex = $currency === "USD" ? billing_forex_rate($conn, $dateTo, $customerKey) : 1.0;
    // The SAP rows resolve forex per transaction date. Keep zero here when the
    // ending date has no covering rate; unavailable conversions remain blank.

    $excludeEntryIds = [];
    if (!$includeBilled) {
        // A returned invoice releases its trips for correction and re-billing.
        // Only entries on active, non-returned invoices stay excluded.
        $excludeEntryIds = array_map("intval", $conn->query(
            "SELECT DISTINCT e.entry_id
             FROM billing_invoice_entries e
             JOIN billing_invoices i ON i.invoice_id = e.invoice_id
             WHERE i.status <> 'deleted' AND i.returned_at IS NULL"
        )->fetchAll(PDO::FETCH_COLUMN));
    }

    try {
        $entries = box_banana_fetch_entries($conn, $customer, $dateFrom, $dateTo, $excludeEntryIds);
        $entries = box_banana_apply_selection($entries, $selectedEntryIds, $entryOrder);
    } catch (Throwable $e) {
        echo json_encode(["success" => false, "message" => "Build failed: " . $e->getMessage()]);
        exit();
    }
    if (count($entries) === 0) {
        $msg = $includeBilled
            ? "No trips match the selected customer and date range."
            : "No unbilled trips match the selected customer and date range. Tick \"Include already-billed\" to re-generate.";
        echo json_encode(["success" => false, "message" => $msg]);
        exit();
    }

    $reference = billing_document_reference($documentNo, ($customer["reference_prefix"] ?? $customer["label"]) . " - " . billing_month_seq($conn, $customerKey, billing_document_date()));

    $resolver = static function (array $e, string $tripDate, string $dcode) use ($conn): float {
        return sumifru_resolve_rate($conn, $e, $tripDate)["rate"];
    };
    $built = box_banana_build_sap_rows($conn, $entries, $customer, $customerKey, $forex, $reference, 0.0, $resolver, $manualForexByEntry);
    $forex = $headerForex($forex);

    $slug = preg_replace('/[^A-Za-z0-9_-]+/', "_", $customer["label"]);
    // Physical file saved in storage/billing/ carries the Month(Year): e.g.
    // "Sumifru Reefer Vans Sep(2026) - 1.xlsx" (the stored Reference stays clean).
    $fileName = billing_storage_filebase($reference, billing_document_date()) . ".xlsx";
    $storageDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "billing";
    if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
        echo json_encode(["success" => false, "message" => "Storage directory could not be created."]);
        exit();
    }
    $absolutePath = $storageDir . DIRECTORY_SEPARATOR . $fileName;
    $relativePath = "storage/billing/" . $fileName;

    try {
        billing_write_xlsx($built["rows"], $built["total"], $absolutePath);
        billing_write_csv_sibling($built["rows"], $built["total"], $absolutePath);
    } catch (Throwable $e) {
        @unlink($absolutePath);
        echo json_encode(["success" => false, "message" => "Build failed: " . $e->getMessage()]);
        exit();
    }

    $requestedBy = $_SESSION["user_idNumber"] ?? ($_SESSION["user_name"] ?? "system");
    $now = date("Y-m-d H:i:s");
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare('
            INSERT INTO billing_invoices (
                invoice_id, customer_key, customer_label, reference, date_from, date_to,
                forex_rate, file_name, file_path, file_size_bytes, line_count,
                requested_by, requested_at, status, activity
            ) VALUES (
                COALESCE((SELECT MAX(invoice_id) FROM billing_invoices), 0) + 1,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            ) RETURNING invoice_id
        ');
        $stmt->execute([
            $customerKey, $customer["label"], $reference, $dateFrom, $dateTo,
            $forex, $fileName, $relativePath, (int) filesize($absolutePath), count($built["rows"]),
            $requestedBy, $now, "ready", "hauling",
        ]);
        $invoiceId = (int) $stmt->fetchColumn();
        $conn->prepare("UPDATE billing_invoices SET document_no = ?, document_date = ? WHERE invoice_id = ?")->execute([$documentNo, billing_document_date(), $invoiceId]);
        if (!empty($built["entry_ids"])) {
            $insertEntries($conn, $invoiceId, $built["entry_ids"]);
            $storeManualForex($conn, $invoiceId, $built["entry_ids"]);
            $storeRateCharges($conn, $invoiceId, $built["charge_by_entry"] ?? []);
        }
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        @unlink($absolutePath);
        echo json_encode(["success" => false, "message" => "Could not record invoice: " . $e->getMessage()]);
        exit();
    }

    echo json_encode([
        "success" => true,
        "message" => sprintf("Generated %d line item(s) at forex %.3f (lane + fuel tier).", count($built["rows"]), $forex),
        "invoice" => [
            "invoice_id" => $invoiceId,
            "reference" => $reference,
            "file_name" => $fileName,
            "line_count" => count($built["rows"]),
            "download_url" => "php/fetch/download_customer_billing.php?id=" . $invoiceId,
        ],
    ]);
    exit();
}

$rate = billing_customer_rate($conn, $customer["rate_code"]);
if ($rate <= 0) {
    echo json_encode(["success" => false, "message" => "No rate set for rate code '" . $customer["rate_code"] . "'. Add it in Master Data → Rates."]);
    exit();
}
$currency = strtoupper((string) ($customer["document_currency"] ?? "PHP"));
// Each SAP line resolves its own effective forex date. A zero summary rate is
// valid here: those lines remain blank in the dollar-conversion columns.
$forex = $currency === "USD" ? billing_forex_rate($conn, $dateTo, $customerKey) : 1.0;

$excludeEntryIds = [];
if (!$includeBilled) {
    $excludeEntryIds = array_map(
        "intval",
        $conn->query("SELECT DISTINCT entry_id FROM billing_invoice_entries")->fetchAll(PDO::FETCH_COLUMN)
    );
}

try {
    $entries = billing_fetch_entries($conn, $customer, $dateFrom, $dateTo, $excludeEntryIds);
    $entries = billing_apply_selection($entries, $selectedEntryIds, $entryOrder);
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Build failed: " . $e->getMessage()]);
    exit();
}

if (count($entries) === 0) {
    $msg = $includeBilled
        ? "No trips match the selected customer and date range."
        : "No unbilled trips match the selected customer and date range. Tick \"Include already-billed\" to re-generate.";
    echo json_encode(["success" => false, "message" => $msg]);
    exit();
}

// Per-customer invoice series for the Reference label: "Month(Year) - N", resets monthly.
$reference = billing_document_reference($documentNo, $customer["reference_prefix"] . " - " . billing_month_seq($conn, $customerKey, billing_document_date()));

$built = billing_build_rows($entries, $customer, $rate, $forex, $reference, $conn, $manualForexByEntry);
$forex = $headerForex($forex);

$slug = preg_replace('/[^A-Za-z0-9_-]+/', "_", $customer["label"]);
// Physical file saved in storage/billing/ carries the Month(Year); the stored Reference stays clean.
$fileName = billing_storage_filebase($reference, billing_document_date()) . ".xlsx";

$storageDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "billing";
if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
    echo json_encode(["success" => false, "message" => "Storage directory could not be created."]);
    exit();
}
$absolutePath = $storageDir . DIRECTORY_SEPARATOR . $fileName;
$relativePath = "storage/billing/" . $fileName;

try {
    billing_write_xlsx($built["rows"], $built["total"], $absolutePath);
    billing_write_csv_sibling($built["rows"], $built["total"], $absolutePath);
} catch (Throwable $e) {
    @unlink($absolutePath);
    echo json_encode(["success" => false, "message" => "Build failed: " . $e->getMessage()]);
    exit();
}

$requestedBy = $_SESSION["user_idNumber"] ?? ($_SESSION["user_name"] ?? "system");
$now = date("Y-m-d H:i:s");

try {
    $conn->beginTransaction();
    $insertSql = '
        INSERT INTO billing_invoices (
            invoice_id, customer_key, customer_label, reference, date_from, date_to,
            forex_rate, file_name, file_path, file_size_bytes, line_count,
            requested_by, requested_at, status
        ) VALUES (
            COALESCE((SELECT MAX(invoice_id) FROM billing_invoices), 0) + 1,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        ) RETURNING invoice_id
    ';
    $stmt = $conn->prepare($insertSql);
    $stmt->execute([
        $customerKey,
        $customer["label"],
        $reference,
        $dateFrom,
        $dateTo,
        $forex,
        $fileName,
        $relativePath,
        (int) filesize($absolutePath),
        count($built["rows"]),
        $requestedBy,
        $now,
        "ready",
    ]);
    $invoiceId = (int) $stmt->fetchColumn();
    $conn->prepare("UPDATE billing_invoices SET document_no = ?, document_date = ? WHERE invoice_id = ?")->execute([$documentNo, billing_document_date(), $invoiceId]);

    if (!empty($built["entry_ids"])) {
        $insertEntries($conn, $invoiceId, $built["entry_ids"]);
        $storeManualForex($conn, $invoiceId, $built["entry_ids"]);
        $storeRateCharges($conn, $invoiceId, $built["charge_by_entry"] ?? []);
    }
    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    @unlink($absolutePath);
    echo json_encode(["success" => false, "message" => "Could not record invoice: " . $e->getMessage()]);
    exit();
}

echo json_encode([
    "success" => true,
    "message" => sprintf("Generated %d line item(s) at forex %.3f.", count($built["rows"]), $forex),
    "invoice" => [
        "invoice_id" => $invoiceId,
        "reference" => $reference,
        "file_name" => $fileName,
        "line_count" => count($built["rows"]),
        "download_url" => "php/fetch/download_customer_billing.php?id=" . $invoiceId,
    ],
]);
exit();
?>
