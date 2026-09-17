<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";
require_once __DIR__ . "/../helpers/waybill_duplicate.php";
require_once __DIR__ . "/../helpers/master_data_validate.php";
require_once __DIR__ . "/../helpers/trip_rate_lookup.php";
require_once __DIR__ . "/../helpers/db_value.php";
require_once __DIR__ . "/../helpers/ensure_operations_rv_dates_schema.php";
require_once __DIR__ . "/../helpers/location_validation.php";
ensure_operations_rv_dates_schema($conn);
date_default_timezone_set("Asia/Manila");
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
function validate($data)
{
    return htmlspecialchars(trim($data));
}

// Convert display format date (MM/DD/YYYY) to database format (YYYY-MM-DD).
// Empty input returns null so PostgreSQL receives SQL NULL instead of ''.
function convertDateToDb($displayDate)
{
    if (empty(trim((string) $displayDate))) return null;

    $parts = explode('/', trim($displayDate));
    if (count($parts) < 2 || count($parts) > 3) return $displayDate;

    $month = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
    $day = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
    $year = isset($parts[2]) ? $parts[2] : date('Y');

    if (strlen($year) === 2) {
        $year = '20' . $year;
    }

    if (!is_numeric($month) || !is_numeric($day) || !is_numeric($year)) return $displayDate;

    return "$year-$month-$day";
}

// Convert display format time (HHMM or HH:MM) to database format (HH:MM).
// Empty input returns null so PostgreSQL receives SQL NULL instead of ''.
function convertTimeToDb($displayTime)
{
    if (empty(trim((string) $displayTime))) return null;

    $time = str_replace(':', '', trim($displayTime));

    if (preg_match('/^\d{3,4}$/', $time)) {
        $time = str_pad($time, 4, '0', STR_PAD_LEFT);
        $hh = substr($time, 0, 2);
        $mm = substr($time, 2, 2);

        if ((int)$hh > 23 || (int)$mm > 59) return $displayTime;
        return "$hh:$mm";
    }

    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        list($hh, $mm) = explode(':', $time);
        if ((int)$hh > 23 || (int)$mm > 59) return $displayTime;
        return $time;
    }

    return $displayTime;
}

function build_rv_route($location)
{
    $normalized = strtoupper(trim($location));
    // PANABO-region locations; everything else resolves to DAVAO (DVO). Keep in
    // step with php/insert/rv.php and php/fetch/get_sku_preview.php.
    $pnbLocations = [
        "DICT", "DICT CY", "PW", "PANABO", "TDC",
        "DOLE CY", "DOLE(PANABO WHARF)",
        "PW/DOLE", "CY/DOLE", "DOLE",
    ];

    return in_array($normalized, $pnbLocations, true) ? "PNB" : "DVO";
}

function normalize_rv_ph_value($ph)
{
    $normalizedPh = trim((string) $ph);

    if (preg_match('/^[A-Z]+0*(\d+)$/i', $normalizedPh, $matches)) {
        $normalizedPh = ltrim($matches[1], '0');
        return $normalizedPh === "" ? "0" : $normalizedPh;
    }

    return $normalizedPh;
}

function lookup_rv_sku(PDO $conn, $shipper, $ph, $route1 = "", $route2 = "")
{
    $shipper = trim((string) $shipper);
    $ph = normalize_rv_ph_value($ph);

    if ($shipper === "" || $ph === "") {
        return null;
    }

    // Prefer the SKU whose origin/dest region matches the trip's actual pull-out /
    // delivered locations (e.g. DICT→DICT = PNB-PNB). SKU names end in
    // "-<origin>-<dest>" region codes; fall back to the first shipper+farm SKU when
    // no region-specific variant exists (farms whose SKUs don't encode a region).
    $r1 = strtoupper(trim((string) $route1));
    $r2 = strtoupper(trim((string) $route2));
    if ($r1 !== "" && $r2 !== "") {
        $stmt = $conn->prepare(
            "SELECT sku_name, \"sku_rountripDistance\"
             FROM sku
             WHERE LOWER(TRIM(sku_shipper_segment)) = LOWER(TRIM(?))
               AND LOWER(TRIM(sku_farm)) = LOWER(TRIM(?))
               AND UPPER(TRIM(sku_name)) LIKE ?
             LIMIT 1"
        );
        $stmt->execute([$shipper, $ph, "%-" . $r1 . "-" . $r2]);
        $regionRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($regionRow) {
            return [
                "billing_sku" => trim((string) ($regionRow["sku_name"] ?? "")),
                "kms" => trim((string) ($regionRow["sku_rountripDistance"] ?? "")),
            ];
        }
    }

    $sql = "SELECT sku_name, \"sku_rountripDistance\"
            FROM sku
            WHERE LOWER(TRIM(sku_shipper_segment)) = LOWER(TRIM(?))
              AND LOWER(TRIM(sku_farm)) = LOWER(TRIM(?))
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$shipper, $ph]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    return [
        "billing_sku" => trim((string) ($row["sku_name"] ?? "")),
        "kms" => trim((string) ($row["sku_rountripDistance"] ?? "")),
    ];
}

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["action"]) &&
    $_POST["action"] === "update-rv"
) {
    $response = ["success" => false, "message" => ""];

    $data_id = intval($_POST["data_id"] ?? 0);
    if ($data_id <= 0) {
        $response["message"] = "Invalid record ID.";
        header("Content-Type: application/json");
        echo json_encode($response);
        exit();
    }

    $entry_type = "RV ENTRY";
    $segment_empty = validate($_POST["segment_empty"] ?? "");
    $activity_empty = validate($_POST["activity_empty"] ?? "");
    $segment = validate($_POST["segment"] ?? "");
    $activity = validate($_POST["activity"] ?? "");
    $remarks = validate($_POST["remarks"] ?? "");
    $pullout_location_arrival_date = validate(
        $_POST["pullout_location_arrival_date"] ?? "",
    );
    $pullout_location_arrival_time = validate(
        $_POST["pullout_location_arrival_time"] ?? "",
    );
    $pullout_location_departure_date = validate(
        $_POST["pullout_location_departure_date"] ?? "",
    );
    $pullout_location_departure_time = validate(
        $_POST["pullout_location_departure_time"] ?? "",
    );
    $ph_arrival_date = validate($_POST["ph_arrival_date"] ?? "");
    $ph_arrival_time = validate($_POST["ph_arrival_time"] ?? "");
    $delivery_location_arrival_date = validate($_POST["delivery_location_arrival_date"] ?? "");
    $delivery_location_arrival_time = validate($_POST["delivery_location_arrival_time"] ?? "");
    $van_alpha = validate($_POST["van_alpha"] ?? "");
    $van_number = validate($_POST["van_number"] ?? "");
    $van_name = validate($_POST["van_name"] ?? "");
    $ph = validate($_POST["ph"] ?? "");
    $shipper = validate($_POST["shipper"] ?? "");
    $ecs = validate($_POST["ecs"] ?? "");
    $tr = validate($_POST["tr"] ?? "");
    $gs = validate($_POST["gs"] ?? "");
    $waybill = validate($_POST["waybill"] ?? "");
    $waybill_empty = validate($_POST["waybill_empty"] ?? "");
    $waybill_date = validate($_POST["waybill_date"] ?? "");
    $empty_trip_receipt_date = validate($_POST["empty_trip_receipt_date"] ?? "");
    $prime_mover = validate($_POST["prime_mover"] ?? "");
    $driver = validate($_POST["driver"] ?? "");
    $empty_pullout_location = validate($_POST["empty_pullout_location"] ?? "");

    // PH (Packing House) + Empty Pull-Out must be registered locations (Master Data → Locations).
    $__locError = unregistered_locations_message($conn, [
        "PH (Packing House / Location)" => $ph,
        "Empty Pull-Out Location" => $empty_pullout_location,
    ]);
    if ($__locError !== "") {
        header("Content-Type: application/json");
        echo json_encode(["success" => false, "message" => $__locError]);
        exit();
    }
    $loaded_van_loading_start_date = validate(
        $_POST["loaded_van_loading_start_date"] ??
            ($_POST["loading_start_date"] ?? ""),
    );
    $loaded_van_loading_start_time = validate(
        $_POST["loaded_van_loading_start_time"] ??
            ($_POST["loading_start_time"] ?? ""),
    );
    $loaded_van_loading_finish_date = validate(
        $_POST["loaded_van_loading_finish_date"] ??
            ($_POST["loading_finish_date"] ?? ""),
    );
    $loaded_van_loading_finish_time = validate(
        $_POST["loaded_van_loading_finish_time"] ??
            ($_POST["loading_finish_time"] ?? ""),
    );
    $loaded_van_delivery_departure_date = validate(
        $_POST["loaded_van_delivery_departure_date"] ??
            ($_POST["delivery_departure_date"] ?? ""),
    );
    $loaded_van_delivery_departure_time = validate(
        $_POST["loaded_van_delivery_departure_time"] ??
            ($_POST["delivery_departure_time"] ?? ""),
    );
    $loaded_van_delivery_arrival_date = validate(
        $_POST["loaded_van_delivery_arrival_date"] ??
            ($_POST["delivery_arrival_date"] ?? ""),
    );
    $loaded_van_delivery_arrival_time = validate(
        $_POST["loaded_van_delivery_arrival_time"] ??
            ($_POST["delivery_arrival_time"] ?? ""),
    );
    $genset_shutoff_date = validate(
        $_POST["genset_shutoff_date"] ??
            ($_POST["genset_shut_off_start_date"] ?? ""),
    );
    $genset_shutoff_time = validate(
        $_POST["genset_shutoff_time"] ??
            ($_POST["genset_shut_off_start_time"] ?? ""),
    );
    $end_uploading_date = validate(
        $_POST["end_uploading_date"] ??
            ($_POST["end_unloading_finish_date"] ?? ""),
    );
    $end_uploading_time = validate(
        $_POST["end_uploading_time"] ??
            ($_POST["end_unloading_finish_time"] ?? ""),
    );
    $dr_no = validate($_POST["dr_no"] ?? "");
    $load_description = validate(
        $_POST["load_description"] ?? ($_POST["load"] ?? ""),
    );
    $delivered_by_prime_mover = validate(
        $_POST["delivered_by_prime_mover"] ?? ($_POST["pm2"] ?? ""),
    );
    $delivered_by_driver = validate(
        $_POST["delivered_by_driver"] ?? ($_POST["driver2"] ?? ""),
    );
    $delivered_to = validate($_POST["delivered_to"] ?? "");
    $delivered_remarks = validate(
        $_POST["delivered_remarks"] ?? ($_POST["remarks"] ?? ""),
    );
    $genset_hr_meter_start = validate(
        $_POST["genset_hr_meter_start"] ?? ($_POST["hr_meter_start"] ?? ""),
    );
    $genset_hr_meter_end = validate(
        $_POST["genset_hr_meter_end"] ?? ($_POST["hr_meter_end"] ?? ""),
    );
    $genset_start_date = validate($_POST["genset_start_date"] ?? "");
    $genset_start_time = validate($_POST["genset_start_time"] ?? "");
    $genset_end_date = validate($_POST["genset_end_date"] ?? "");
    $genset_end_time = validate($_POST["genset_end_time"] ?? "");
    $driver_idNumber = validate($_POST["driver_idNumber"] ?? "");
    $delivered_by_driverIdNumber = validate($_POST["delivered_by_driverIdNumber"] ?? "");

    $reference_documents = validate($_POST["reference_documents"] ?? "");
    $genset_hr_meter = validate($_POST["genset_hr_meter"] ?? "");
    $genset_hr_reading = validate($_POST["genset_hr_reading"] ?? "");
    $refueled = validate($_POST["refueled"] ?? "");
    $defect_hubo = (!empty($_POST["defect_hubo"]) && $_POST["defect_hubo"] !== "0") ? "1" : "0";
    if ($defect_hubo === "1") {
        // The hour-meter database fields are numeric; a defective Hubo has no
        // numeric reading, so clear the fields instead of saving descriptive text.
        $genset_hr_meter_start = "";
        $genset_hr_meter_end = "";
        $genset_hr_meter = "";
        $genset_hr_reading = "";
    }

    $modified_by = isset($_SESSION["user_idNumber"])
        ? validate($_SESSION["user_idNumber"])
        : "system";
    $modified_date = date("Y-m-d H:i:s");

    // =========================
    // CONVERT DATES AND TIMES
    // =========================
    $pullout_location_arrival_date = convertDateToDb($pullout_location_arrival_date);
    $pullout_location_arrival_time = convertTimeToDb($pullout_location_arrival_time);
    $pullout_location_departure_date = convertDateToDb($pullout_location_departure_date);
    $pullout_location_departure_time = convertTimeToDb($pullout_location_departure_time);
    $ph_arrival_date = convertDateToDb($ph_arrival_date);
    $ph_arrival_time = convertTimeToDb($ph_arrival_time);
    $waybill_date = convertDateToDb($waybill_date);
    $empty_trip_receipt_date = convertDateToDb($empty_trip_receipt_date);
    $loaded_van_loading_start_date = convertDateToDb($loaded_van_loading_start_date);
    $loaded_van_loading_start_time = convertTimeToDb($loaded_van_loading_start_time);
    $loaded_van_loading_finish_date = convertDateToDb($loaded_van_loading_finish_date);
    $loaded_van_loading_finish_time = convertTimeToDb($loaded_van_loading_finish_time);
    $loaded_van_delivery_departure_date = convertDateToDb($loaded_van_delivery_departure_date);
    $loaded_van_delivery_departure_time = convertTimeToDb($loaded_van_delivery_departure_time);
    $loaded_van_delivery_arrival_date = convertDateToDb($loaded_van_delivery_arrival_date);
    $loaded_van_delivery_arrival_time = convertTimeToDb($loaded_van_delivery_arrival_time);
    $genset_shutoff_date = convertDateToDb($genset_shutoff_date);
    $genset_shutoff_time = convertTimeToDb($genset_shutoff_time);
    $end_uploading_date = convertDateToDb($end_uploading_date);
    $end_uploading_time = convertTimeToDb($end_uploading_time);
    $genset_start_date = convertDateToDb($genset_start_date);
    $genset_start_time = convertTimeToDb($genset_start_time);
    $genset_end_date = convertDateToDb($genset_end_date);
    $genset_end_time = convertTimeToDb($genset_end_time);
    $delivery_location_arrival_date = convertDateToDb($delivery_location_arrival_date);
    $delivery_location_arrival_time = convertTimeToDb($delivery_location_arrival_time);

    // Lookup piece rates for both empty and loaded segments
    $piece_rate_empty = operations_lookup_piece_rate($conn, $segment_empty, $activity_empty);
    $piece_rate_loaded = operations_lookup_piece_rate($conn, $segment, $activity);
    $piece_rate = (float)$piece_rate_empty + (float)$piece_rate_loaded;
    $route1 = build_rv_route($empty_pullout_location);
    $route2 = build_rv_route($delivered_to);
    $skuData = lookup_rv_sku($conn, $shipper, $ph, $route1, $route2);
    $billing_sku = $skuData["billing_sku"] ?? "";
    $kms = $skuData["kms"] ?? "";

    if (empty($segment_empty) || empty($activity_empty)) {
        $response["message"] = "Empty segment and activity are required.";
    } elseif (empty($segment) || empty($activity)) {
        $response["message"] = "Loaded segment and activity are required.";
    } elseif (empty($waybill)) {
        $response["message"] = "Waybill is required.";
    } elseif (empty($ph)) {
        $response["message"] = "PH (Packing House / Location) is required.";
    } elseif (empty($shipper)) {
        $response["message"] = "Shipper is required.";
    } elseif (empty($tr)) {
        $response["message"] = "Trailer (TR) is required.";
    } elseif (empty($gs)) {
        $response["message"] = "Genset (GS) is required.";
    } elseif (empty($prime_mover)) {
        $response["message"] = "Prime mover is required.";
    } elseif (empty($driver)) {
        $response["message"] = "Driver is required.";
    } elseif (operations_waybill_exists($conn, $waybill, $data_id)) {
        $response["message"] =
            "This waybill number is already in use. Please use a different waybill.";
    } elseif (operations_dr_no_exists($conn, $dr_no, $data_id)) {
        $response["message"] =
            "This DR. NO. is already in use. Please use a different DR. NO.";
    } elseif (
        ($e = master_validate_rv(
            $conn,
            $segment,
            $activity,
            $driver,
            $driver_idNumber,
            $ph,
            $tr,
            $gs,
            $prime_mover,
        )) !== null
    ) {
        $response["message"] = $e;
    } elseif ($skuData === null || $billing_sku === "") {
        $response["message"] =
            "No SKU found for the selected shipper and PH (Packing House / Location).";
    }
// 64
    if ($response["message"] === "") {
        $sql =
            "UPDATE operations SET entry_type = ?, segment_empty = ?, activity_empty = ?, segment = ?, activity = ?, remarks = ?, pullout_location_arrival_date = ?, pullout_location_arrival_time = ?, pullout_location_departure_date = ?, pullout_location_departure_time = ?, ph_arrival_date = ?, ph_arrival_time = ?, van_alpha = ?, van_number = ?, van_name = ?, ph = ?, shipper = ?, ecs = ?, tr = ?, gs = ?, waybill = ?, waybill_empty = ?, waybill_date = ?, empty_trip_receipt_date = ?, prime_mover = ?, driver = ?, empty_pullout_location = ?, loaded_van_loading_start_date = ?, loaded_van_loading_start_time = ?, loaded_van_loading_finish_date = ?, loaded_van_loading_finish_time = ?, loaded_van_delivery_departure_date = ?, loaded_van_delivery_departure_time = ?, loaded_van_delivery_arrival_date = ?, loaded_van_delivery_arrival_time = ?, genset_shutoff_date = ?, genset_shutoff_time = ?, end_uploading_date = ?, end_uploading_time = ?, dr_no = ?, load_description = ?, delivered_by_prime_mover = ?, delivered_by_driver = ?, delivered_to = ?, delivered_remarks = ?, genset_hr_meter_start = ?, genset_hr_meter_end = ?, reference_documents = ?, genset_hr_meter = ?, genset_hr_reading = ?, refueled = ?, defect_hubo = ?, genset_start_date = ?, genset_start_time = ?, genset_end_date = ?, genset_end_time = ?, piece_rate_empty = ?, piece_rate_loaded = ?, piece_rate = ?, kms = ?, billing_sku = ?, \"driver_idNumber\" = ?, \"delivered_by_driverIdNumber\" = ?, delivery_location_arrival_date = ?, delivery_location_arrival_time = ?, modified_by = ?, modified_date = ? WHERE entry_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute(db_nullable_all([
            $entry_type,
            $segment_empty,
            $activity_empty,
            $segment,
            $activity,
            $remarks,
            $pullout_location_arrival_date,
            $pullout_location_arrival_time,
            $pullout_location_departure_date,
            $pullout_location_departure_time,
            $ph_arrival_date,
            $ph_arrival_time,
            $van_alpha,
            $van_number,
            $van_name,
            $ph,
            $shipper,
            $ecs,
            $tr,
            $gs,
            $waybill,
            $waybill_empty,
            $waybill_date,
            $empty_trip_receipt_date,
            $prime_mover,
            $driver,
            $empty_pullout_location,
            $loaded_van_loading_start_date,
            $loaded_van_loading_start_time,
            $loaded_van_loading_finish_date,
            $loaded_van_loading_finish_time,
            $loaded_van_delivery_departure_date,
            $loaded_van_delivery_departure_time,
            $loaded_van_delivery_arrival_date,
            $loaded_van_delivery_arrival_time,
            $genset_shutoff_date,
            $genset_shutoff_time,
            $end_uploading_date,
            $end_uploading_time,
            $dr_no,
            $load_description,
            $delivered_by_prime_mover,
            $delivered_by_driver,
            $delivered_to,
            $delivered_remarks,
            $genset_hr_meter_start,
            $genset_hr_meter_end,
            $reference_documents,
            $genset_hr_meter,
            $genset_hr_reading,
            $refueled,
            $defect_hubo,
            $genset_start_date,
            $genset_start_time,
            $genset_end_date,
            $genset_end_time,
            $piece_rate_empty,
            $piece_rate_loaded,
            $piece_rate,
            $kms,
            $billing_sku,
            $driver_idNumber,
            $delivered_by_driverIdNumber,
            $delivery_location_arrival_date,
            $delivery_location_arrival_time,
            $modified_by,
            $modified_date,
            $data_id,
        ]));

        $response["success"] = true;
        $response["message"] = "RV record updated.";
        $response["record"] = [
            "entry_id" => $data_id,
            "segment_empty" => $segment_empty,
            "activity_empty" => $activity_empty,
            "segment" => $segment,
            "activity" => $activity,
            "waybill" => $waybill,
            "waybill_empty" => $waybill_empty,
            "waybill_date" => $waybill_date,
            "empty_trip_receipt_date" => $empty_trip_receipt_date,
            "van_alpha" => $van_alpha,
            "van_number" => $van_number,
            "van_name" => $van_name,
            "driver" => $driver,
            "delivered_by_driver" => $delivered_by_driver,
            "remarks" => $remarks,
            "created_date" => date('Y-m-d'),
            "piece_rate_empty" => $piece_rate_empty,
            "piece_rate_loaded" => $piece_rate_loaded,
            "piece_rate" => $piece_rate,
            "kms" => $kms,
            "billing_sku" => $billing_sku,
        ];
    }

    header("Content-Type: application/json");
    echo json_encode($response);
    exit();
}
?>
