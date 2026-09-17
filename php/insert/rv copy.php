<?php
include "../config/config.php";
require_once __DIR__ . "/../helpers/waybill_duplicate.php";
require_once __DIR__ . "/../helpers/master_data_validate.php";
require_once __DIR__ . "/../helpers/trip_rate_lookup.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
function validate($data)
{
    return htmlspecialchars(trim($data));
}

function build_rv_route($location)
{
    $normalized = strtoupper(trim($location));
    $pnbLocations = ["DICT", "PW/DOLE", "CY/DOLE", "DOLE"];

    return in_array($normalized, $pnbLocations, true) ? "PNB" : "DVO";
}

function build_rv_billing_sku($shipper, $ph, $route1, $route2)
{
    $ph_number = (int) str_replace('PH', '', $ph);

    return trim($shipper) . "-" . $ph_number . "-" . $route1 . "-" . $route2;
}

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["action"]) &&
    $_POST["action"] === "add-rv"
) {
    $response = ["success" => false, "message" => ""];

    // =========================
    // COLLECT DATA
    // =========================

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

    $prime_mover = validate($_POST["prime_mover"] ?? "");
    $driver = validate($_POST["driver"] ?? "");

    $empty_pullout_location = validate($_POST["empty_pullout_location"] ?? "");

    $loaded_van_loading_start_date = validate(
        $_POST["loaded_van_loading_start_date"] ?? "",
    );
    $loaded_van_loading_start_time = validate(
        $_POST["loaded_van_loading_start_time"] ?? "",
    );
    $loaded_van_loading_finish_date = validate(
        $_POST["loaded_van_loading_finish_date"] ?? "",
    );
    $loaded_van_loading_finish_time = validate(
        $_POST["loaded_van_loading_finish_time"] ?? "",
    );

    $loaded_van_delivery_departure_date = validate(
        $_POST["loaded_van_delivery_departure_date"] ?? "",
    );
    $loaded_van_delivery_departure_time = validate(
        $_POST["loaded_van_delivery_departure_time"] ?? "",
    );
    $loaded_van_delivery_arrival_date = validate(
        $_POST["loaded_van_delivery_arrival_date"] ?? "",
    );
    $loaded_van_delivery_arrival_time = validate(
        $_POST["loaded_van_delivery_arrival_time"] ?? "",
    );

    $genset_shutoff_date = validate($_POST["genset_shutoff_date"] ?? "");
    $genset_shutoff_time = validate($_POST["genset_shutoff_time"] ?? "");

    $end_uploading_date = validate($_POST["end_uploading_date"] ?? "");
    $end_uploading_time = validate($_POST["end_uploading_time"] ?? "");

    $dr_no = validate($_POST["dr_no"] ?? "");
    $load_description = validate($_POST["load_description"] ?? "");

    $delivered_by_prime_mover = validate(
        $_POST["delivered_by_prime_mover"] ?? "",
    );
    $delivered_by_driver = validate($_POST["delivered_by_driver"] ?? "");
    $delivered_to = validate($_POST["delivered_to"] ?? "");

    $delivered_remarks = validate($_POST["remarks"] ?? "");

    $genset_hr_meter_start = validate($_POST["genset_hr_meter_start"] ?? "");
    $genset_hr_meter_end = validate($_POST["genset_hr_meter_end"] ?? "");

    $genset_start_date = validate($_POST["genset_start_date"] ?? "");
    $genset_start_time = validate($_POST["genset_start_time"] ?? "");
    $genset_end_date = validate($_POST["genset_end_date"] ?? "");
    $genset_end_time = validate($_POST["genset_end_time"] ?? "");
    $driver_idNumber = validate($_POST["driver_idNumber"] ?? "");
    $delivered_by_driverIdNumber = validate($_POST["delivered_by_driverIdNumber"] ?? "");
    $piece_rate = operations_lookup_piece_rate($conn, $segment, $activity);
    $route1 = build_rv_route($empty_pullout_location);
    $route2 = build_rv_route($delivered_to);
    $billing_sku = build_rv_billing_sku($shipper, $ph, $route1, $route2);

    $created_by = isset($_SESSION["user_name"])
        ? validate($_SESSION["user_name"])
        : "system";

    // =========================
    // VALIDATION
    // =========================

    if (empty($segment_empty) || empty($activity_empty)) {
        $response["message"] = "Empty segment and activity are required.";
    } elseif (empty($segment) || empty($activity)) {
        $response["message"] = "Loaded segment and activity are required.";
    } elseif (empty($waybill)) {
        $response["message"] = "Waybill is required.";
    } elseif (empty($ph)) {
        $response["message"] = "PH (Packing House / Location) is required.";
    } elseif (empty($tr)) {
        $response["message"] = "Trailer (TR) is required.";
    } elseif (empty($gs)) {
        $response["message"] = "Genset (GS) is required.";
    } elseif (empty($prime_mover)) {
        $response["message"] = "Prime mover is required.";
    } elseif (empty($driver)) {
        $response["message"] = "Driver is required.";
    } elseif (operations_waybill_exists($conn, $waybill)) {
        $response["message"] =
            "This waybill number is already in use. Please use a different waybill.";
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
    } else {
        // =========================
        // INSERT QUERY
        // =========================

        $sql = "INSERT INTO operations (
            entry_type,
            segment_empty,
            activity_empty,
            segment,
            activity,
            remarks,
            pullout_location_arrival_date,
            pullout_location_arrival_time,
            pullout_location_departure_date,
            pullout_location_departure_time,
            ph_arrival_date,
            ph_arrival_time,
            van_alpha,
            van_number,
            van_name,
            ph,
            shipper,
            ecs,
            tr,
            gs,
            waybill,
            waybill_empty,
            prime_mover,
            driver,
            empty_pullout_location,
            loaded_van_loading_start_date,
            loaded_van_loading_start_time,
            loaded_van_loading_finish_date,
            loaded_van_loading_finish_time,
            loaded_van_delivery_departure_date,
            loaded_van_delivery_departure_time,
            loaded_van_delivery_arrival_date,
            loaded_van_delivery_arrival_time,
            genset_shutoff_date,
            genset_shutoff_time,
            end_uploading_date,
            end_uploading_time,
            dr_no,
            load_description,
            delivered_by_prime_mover,
            delivered_by_driver,
            delivered_to,
            delivered_remarks,
            genset_hr_meter_start,
            genset_hr_meter_end,
            genset_start_date,
            genset_start_time,
            genset_end_date,
            genset_end_time,
            piece_rate,
            billing_sku,
            created_by,
            driver_idNumber,
            delivered_by_driverIdNumber
        )
        VALUES (
            ?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?
        )";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $response["message"] = "Prepare failed: " . $conn->error;
        } else {
            // 54 fields
            $stmt->bind_param(
                str_repeat("s", 54),

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
                $genset_start_date,
                $genset_start_time,
                $genset_end_date,
                $genset_end_time,
                $piece_rate,
                $billing_sku,
                $created_by,
                $driver_idNumber,
                $delivered_by_driverIdNumber,
            );

            if ($stmt->execute()) {
                $newId = $stmt->insert_id;

                $response["success"] = true;
                $response["message"] = "RV record created successfully.";

                $response["record"] = [
                    "entry_id" => $newId,
                    "segment_empty" => $segment_empty,
                    "activity_empty" => $activity_empty,
                    "segment" => $segment,
                    "activity" => $activity,
                    "waybill" => $waybill,
                    "driver" => $driver,
                    "remarks" => $remarks,
                    "piece_rate" => $piece_rate,
                    "billing_sku" => $billing_sku,
                ];
            } else {
                $response["message"] = "Execute failed: " . $stmt->error;
            }

            $stmt->close();
        }
    }

    header("Content-Type: application/json");
    echo json_encode($response);
    exit();
}
?>
