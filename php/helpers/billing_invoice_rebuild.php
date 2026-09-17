<?php

require_once __DIR__ . "/unified_billing.php";
require_once __DIR__ . "/billing_customers.php";
require_once __DIR__ . "/build_customer_billing.php";
require_once __DIR__ . "/build_box_banana_billing.php";
require_once __DIR__ . "/box_banana_customers.php";
require_once __DIR__ . "/fuel_rate_engine.php";
require_once __DIR__ . "/sumifru_rate.php";
require_once __DIR__ . "/customer_sap_codes.php";
require_once __DIR__ . "/billing_activities.php";
require_once __DIR__ . "/build_activity_billing.php";
require_once __DIR__ . "/abc_kds.php";
require_once __DIR__ . "/dry_van.php";

/**
 * Rebuild an invoice's typed SAP rows from its stored trips + locked per-entry charges,
 * covering EVERY per-trip pipeline: matrix + SAP hauling, ABC KDs, Dry Vans, and the
 * non-hauling activities (Genset / Chassis / Container Van / Fuel). Single source of
 * truth shared by regenerate_invoice_file.php (writes the rebuilt file back to disk) and
 * the download endpoints (serve a rebuilt file when the stored one is missing), so all
 * three stay in lock-step.
 *
 * Returns ['rows' => array, 'total' => float, 'charge_by_entry' => array].
 *
 * $invoice must carry: customer_key, reference, date_from, date_to, forex_rate, activity.
 * $requestActivity lets a caller rebuild a DIFFERENT activity than the invoice's own
 * (the CSV endpoint uses this to export a hauling invoice's Genset/Chassis/… lines);
 * blank or unknown falls back to the invoice's stored activity.
 *
 * Throws RuntimeException with a machine code as the message on the failure modes the
 * callers surface differently:
 *   "unsupported"      - aggregate billing (DICT shuttling); no per-trip rebuild.
 *   "no_entries"       - the invoice has no stored trips.
 *   "unknown_customer" - the customer config could not be resolved.
 *   "out_of_range"     - the trips no longer fall in the billed date range.
 *   "no_rows"          - the rebuild produced no billable lines.
 * Any other Throwable from the builders propagates unchanged.
 */
function billing_rebuild_invoice_sap(PDO $conn, array $invoice, int $invoiceId, string $requestActivity = ""): array
{
    $customerKey = (string) ($invoice["customer_key"] ?? "");
    $invoiceActivity = (string) ($invoice["activity"] ?? "hauling");
    $reference = (string) ($invoice["reference"] ?? "");
    $dateFrom = (string) ($invoice["date_from"] ?? "");
    $dateTo = (string) ($invoice["date_to"] ?? "");

    // Stamp the file with the invoice's ORIGINAL Document/Billing Date so a rebuilt or
    // re-downloaded file matches the first generation. Blank (legacy invoices) falls back
    // to today, which is the pre-feature behaviour.
    billing_document_date((string) ($invoice["document_date"] ?? ""));

    // Which activity to build: an explicit, valid non-hauling request wins; else the
    // invoice's own activity.
    $activityCode = trim($requestActivity);
    if ($activityCode === "" || billing_activity($activityCode) === null) {
        $activityCode = $invoiceActivity;
    }
    $isActivity = $activityCode !== "hauling" && billing_activity($activityCode) !== null;

    $pipeline = unified_billing_pipeline($customerKey);
    if (!$isActivity && !in_array($pipeline, ["matrix", "sap", "kds", "dryvan"], true)) {
        throw new RuntimeException("unsupported");
    }

    // Stored entries + their locked charges (the source of truth for what this file bills).
    $stored = $conn->prepare("SELECT entry_id, rate_charge FROM billing_invoice_entries WHERE invoice_id = ? ORDER BY entry_id");
    $stored->execute([$invoiceId]);
    $storedCharges = [];
    foreach ($stored->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $storedCharges[(int) $r["entry_id"]] = $r["rate_charge"];
    }
    if (empty($storedCharges)) {
        throw new RuntimeException("no_entries");
    }
    $storedIds = array_keys($storedCharges);

    // Only entries with a locked charge override the pipeline's normal rate resolution;
    // legacy NULLs fall back to the live rate.
    $chargeOverrideByEntry = [];
    foreach ($storedCharges as $eid => $charge) {
        if (is_numeric($charge)) {
            $chargeOverrideByEntry[(int) $eid] = (float) $charge;
        }
    }

    // Keep only this invoice's entries, in stored order.
    $filterToStored = static function (array $allEntries) use ($storedIds): array {
        $byId = [];
        foreach ($allEntries as $e) {
            $byId[(int) ($e["entry_id"] ?? 0)] = $e;
        }
        $out = [];
        foreach ($storedIds as $eid) {
            if (isset($byId[$eid])) {
                $out[] = $byId[$eid];
            }
        }
        return $out;
    };

    // Resolve the customer config (by pipeline; activity billing shares the hauling config).
    if ($isActivity || $pipeline === "matrix" || $pipeline === "sap") {
        $customer = $pipeline === "matrix" ? box_banana_customer($customerKey) : billing_customer($customerKey);
        if (!is_array($customer)) {
            // Activity customers may live in either config; try the other.
            $customer = box_banana_customer($customerKey) ?: billing_customer($customerKey);
        }
    } elseif ($pipeline === "kds") {
        $customer = abc_kds_customer($customerKey);
    } else { // dryvan
        $customer = dry_van_customer($customerKey);
    }
    if (!is_array($customer)) {
        throw new RuntimeException("unknown_customer");
    }
    $customer = billing_customer_apply_sap_overrides($conn, $customerKey, $customer);

    // Forex: stored rate wins; otherwise resolve for USD customers, else 1.0 (PHP).
    $currency = strtoupper((string) ($customer["document_currency"] ?? "PHP"));
    $forex = is_numeric($invoice["forex_rate"] ?? null) && (float) $invoice["forex_rate"] > 0
        ? (float) $invoice["forex_rate"]
        : ($currency === "USD" ? billing_forex_rate($conn, $dateTo, $customerKey) : 1.0);
    if ($forex <= 0) {
        $forex = 1.0;
    }

    // Per-entry manual forex captured at generation, still honoured on rebuild.
    $manualForexByEntry = [];
    $fx = $conn->prepare("SELECT entry_id, forex_rate FROM billing_invoice_entry_forex WHERE invoice_id = ?");
    $fx->execute([$invoiceId]);
    foreach ($fx->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (is_numeric($r["forex_rate"]) && (float) $r["forex_rate"] > 0) {
            $manualForexByEntry[(int) $r["entry_id"]] = (float) $r["forex_rate"];
        }
    }

    if ($isActivity) {
        $entries = $filterToStored(activity_fetch_entries($conn, $customer, $dateFrom, $dateTo, []));
        if (empty($entries)) {
            throw new RuntimeException("out_of_range");
        }
        $built = activity_build($conn, $entries, $customer, $customerKey, $activityCode, $forex, $reference ?: "billing", 0.0, $chargeOverrideByEntry);
        $sapRows = $built["sap_rows"];
    } elseif ($pipeline === "kds") {
        $entries = $filterToStored(abc_kds_fetch($conn, $customer, $dateFrom, $dateTo, []));
        if (empty($entries)) {
            throw new RuntimeException("out_of_range");
        }
        $built = abc_kds_build($conn, $entries, $customer, $reference, $chargeOverrideByEntry);
        $sapRows = $built["rows"];
    } elseif ($pipeline === "dryvan") {
        $rate = dry_van_rate($conn, $customer);
        $entries = $filterToStored(dry_van_fetch($conn, $customer, $dateFrom, $dateTo, []));
        if (empty($entries)) {
            throw new RuntimeException("out_of_range");
        }
        $rateByEntry = dry_van_rates_by_entry($conn, $customer, $entries, $customerKey);
        $built = dry_van_build($conn, $entries, $customer, $rate, $reference, $chargeOverrideByEntry, $rateByEntry);
        $sapRows = $built["rows"];
    } else { // matrix / SAP hauling
        $entries = $filterToStored(box_banana_fetch_entries($conn, $customer, $dateFrom, $dateTo, []));
        if (empty($entries)) {
            throw new RuntimeException("out_of_range");
        }
        if ($pipeline === "matrix") {
            $flatRate = box_banana_rate($conn, (string) ($customer["rate_code"] ?? ""));
            $built = box_banana_build_sap_rows($conn, $entries, $customer, $customerKey, $forex, $reference ?: "billing", $flatRate, null, $manualForexByEntry, $chargeOverrideByEntry);
        } elseif (($customer["pricing"] ?? "") === "lane_tier") {
            $resolver = static function (array $e, string $tripDate, string $dcode) use ($conn): float {
                return sumifru_resolve_rate($conn, $e, $tripDate)["rate"];
            };
            $built = box_banana_build_sap_rows($conn, $entries, $customer, $customerKey, $forex, $reference ?: "billing", 0.0, $resolver, $manualForexByEntry, $chargeOverrideByEntry);
        } else {
            $flatRate = billing_customer_rate($conn, (string) ($customer["rate_code"] ?? ""));
            $built = billing_build_rows($entries, $customer, $flatRate, $forex, $reference ?: "billing", $conn, $manualForexByEntry, $chargeOverrideByEntry);
        }
        $sapRows = $built["rows"];
    }

    if (empty($sapRows)) {
        throw new RuntimeException("no_rows");
    }

    return [
        "rows" => $sapRows,
        "total" => (float) ($built["total"] ?? 0),
        "charge_by_entry" => $built["charge_by_entry"] ?? [],
    ];
}
