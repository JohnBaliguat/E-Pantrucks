<?php

/**
 * Trip Receipt Status monitoring.
 *
 * Reconciles two independent systems by trip-receipt / waybill number:
 *   - the DISPATCH database (dispatch_db()) — trips the dispatch team has sent
 *     out; a trip receipt exists here once the trip has been dispatched.
 *   - the E-Pantrucks OPERATIONS table ($conn) — trips the encoders have keyed
 *     in (the "waybill" / "waybill_empty" columns hold the trip receipt no.).
 *
 * For every trip receipt seen in a recent window we report one of:
 *   - matched                 : dispatched AND encoded (all good)
 *   - dispatched_not_encoded  : dispatched but the encoder has not keyed it yet
 *   - encoded_not_dispatched  : encoded but no matching dispatch record
 *
 * GET ?days=30   (window, clamped 1..180; default 30)
 *
 * On-demand only (the page has no auto-poll) to keep Supabase egress low.
 */

include "../config/config.php"; // $conn (app DB)
require_once __DIR__ . "/../config/dispatch_config.php";

header("Content-Type: application/json; charset=utf-8");

$days = (int) ($_GET["days"] ?? 30);
if ($days < 1) {
    $days = 1;
}
if ($days > 180) {
    $days = 180;
}

// Bound the "exists anywhere" existence sets a bit wider than the display window
// so a counterpart keyed slightly outside the window still counts as matched,
// without pulling the whole (growing) history each request.
$existDays = $days + 45;

$dispatch = dispatch_db();
if (!$dispatch instanceof PDO) {
    echo json_encode([
        "success" => false,
        "message" => "Dispatch database is unavailable. Trip receipt status cannot be reconciled right now.",
    ]);
    exit();
}

$norm = static fn($v) => strtoupper(trim((string) $v));

try {
    /* ---- 1. Encoded trip receipts that exist anywhere in the window+ ------- */
    $encodedExists = [];
    $stmt = $conn->prepare(
        "SELECT waybill, waybill_empty
         FROM operations
         WHERE COALESCE(waybill_date, created_date::date) >= (CURRENT_DATE - (? || ' days')::interval)::date
           AND (COALESCE(TRIM(waybill), '') <> '' OR COALESCE(TRIM(waybill_empty), '') <> '')"
    );
    $stmt->execute([$existDays]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        foreach (["waybill", "waybill_empty"] as $c) {
            $k = $norm($r[$c] ?? "");
            if ($k !== "") {
                $encodedExists[$k] = true;
            }
        }
    }

    /* ---- 2. Dispatched trip receipts that exist anywhere in the window+ ---- */
    $dispatchedExists = [];
    $stmt = $dispatch->prepare(
        "SELECT d_tripreceipt
         FROM dispatch
         WHERE d_datetime >= (now() - (? || ' days')::interval)
           AND COALESCE(TRIM(d_tripreceipt), '') <> ''"
    );
    $stmt->execute([$existDays]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $k = $norm($r["d_tripreceipt"] ?? "");
        if ($k !== "") {
            $dispatchedExists[$k] = true;
        }
    }

    /* ---- 3. Dispatch detail rows in the display window --------------------- */
    $rows = []; // keyed by normalized trip receipt
    $stmt = $dispatch->prepare(
        "SELECT d_tripreceipt, d_datetime, d_drivername, d_truck, d_trailer,
                d_genset, costumer, d_origin, workflow_stage
         FROM dispatch
         WHERE d_datetime >= (now() - (? || ' days')::interval)
           AND COALESCE(TRIM(d_tripreceipt), '') <> ''
         ORDER BY d_datetime DESC"
    );
    $stmt->execute([$days]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $tr = trim((string) ($r["d_tripreceipt"] ?? ""));
        $key = $norm($tr);
        if ($key === "" || isset($rows[$key])) {
            continue; // keep the most recent dispatch row per trip receipt
        }
        $rows[$key] = [
            "trip_receipt"    => $tr,
            "dispatched"      => true,
            "encoded"         => isset($encodedExists[$key]),
            "dispatch_date"   => $r["d_datetime"],
            "driver"          => trim((string) ($r["d_drivername"] ?? "")),
            "truck"           => trim((string) ($r["d_truck"] ?? "")),
            "trailer"         => trim((string) ($r["d_trailer"] ?? "")),
            "customer"        => trim((string) ($r["costumer"] ?? "")),
            "origin"          => trim((string) ($r["d_origin"] ?? "")),
            "workflow_stage"  => trim((string) ($r["workflow_stage"] ?? "")),
            "entry_type"      => "",
            "encoded_date"    => null,
            "entry_id"        => null,
        ];
    }

    /* ---- 4. Encoded detail rows in the display window ---------------------- */
    $stmt = $conn->prepare(
        "SELECT entry_id, entry_type, waybill, waybill_empty, driver, driver_return,
                van_alpha, van_number, van_name, waybill_date, created_date
         FROM operations
         WHERE COALESCE(waybill_date, created_date::date) >= (CURRENT_DATE - (? || ' days')::interval)::date
           AND (COALESCE(TRIM(waybill), '') <> '' OR COALESCE(TRIM(waybill_empty), '') <> '')
         ORDER BY entry_id DESC"
    );
    $stmt->execute([$days]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // A single operations row can carry a loaded and an empty waybill.
        $trs = array_unique(array_filter([
            trim((string) ($r["waybill"] ?? "")),
            trim((string) ($r["waybill_empty"] ?? "")),
        ], fn($v) => $v !== ""));

        $van = implode(" ", array_values(array_filter([
            trim((string) ($r["van_alpha"] ?? "")),
            trim((string) ($r["van_number"] ?? "")),
            trim((string) ($r["van_name"] ?? "")),
        ], fn($v) => $v !== "")));
        $driver = trim((string) ($r["driver"] ?? "")) ?: trim((string) ($r["driver_return"] ?? ""));

        foreach ($trs as $tr) {
            $key = $norm($tr);
            if (isset($rows[$key])) {
                // Already have a dispatch row for this TR — enrich it as encoded.
                $rows[$key]["encoded"]    = true;
                $rows[$key]["entry_type"] = $rows[$key]["entry_type"] ?: trim((string) ($r["entry_type"] ?? ""));
                $rows[$key]["entry_id"]   = $rows[$key]["entry_id"] ?? (int) $r["entry_id"];
                $rows[$key]["encoded_date"] = $rows[$key]["encoded_date"] ?? $r["waybill_date"];
                if ($rows[$key]["truck"] === "" && $van !== "") {
                    $rows[$key]["truck"] = $van;
                }
                if ($rows[$key]["driver"] === "" && $driver !== "") {
                    $rows[$key]["driver"] = $driver;
                }
                continue;
            }
            if (isset($rows[$key]) === false) {
                $rows[$key] = [
                    "trip_receipt"   => $tr,
                    "dispatched"     => isset($dispatchedExists[$key]),
                    "encoded"        => true,
                    "dispatch_date"  => null,
                    "driver"         => $driver,
                    "truck"          => $van,
                    "trailer"        => "",
                    "customer"       => "",
                    "origin"         => "",
                    "workflow_stage" => "",
                    "entry_type"     => trim((string) ($r["entry_type"] ?? "")),
                    "encoded_date"   => $r["waybill_date"] ?: ($r["created_date"] ?? null),
                    "entry_id"       => (int) $r["entry_id"],
                ];
            }
        }
    }
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Reconciliation failed: " . $e->getMessage()]);
    exit();
}

/* ---- 5. Classify + tally ------------------------------------------------- */
$records = [];
$counts = ["matched" => 0, "dispatched_not_encoded" => 0, "encoded_not_dispatched" => 0];
foreach ($rows as $row) {
    if ($row["dispatched"] && $row["encoded"]) {
        $status = "matched";
    } elseif ($row["dispatched"]) {
        $status = "dispatched_not_encoded";
    } else {
        $status = "encoded_not_dispatched";
    }
    $counts[$status]++;
    $row["status"] = $status;
    $records[] = $row;
}

echo json_encode([
    "success"      => true,
    "days"         => $days,
    "counts"       => $counts,
    "total"        => count($records),
    "records"      => $records,
    "generated_at" => date("c"),
]);
exit();
