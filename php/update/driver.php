<?php
include "../config/config.php";
date_default_timezone_set("Asia/Manila");
function validate($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "update-driver") {
    $response = ["success" => false, "message" => ""];

    $driverId = validate($_POST["driverId"] ?? "");
    $lname = validate($_POST["lastName"] ?? "");
    $fname = validate($_POST["firstName"] ?? "");
    $mname = validate($_POST["middleName"] ?? "");
    $idNumber = validate($_POST["idNumber"] ?? "");
    $dailyRate = validate($_POST["dailyRate"] ?? "");
    $hourlyRate = validate($_POST["hourlyRate"] ?? "");

    if ($driverId === "" || $lname === "" || $fname === "" || $idNumber === "" || $dailyRate === "" || $hourlyRate === "") {
        $response["message"] = "All required fields must be filled in.";
    } elseif (!is_numeric($dailyRate) || !is_numeric($hourlyRate)) {
        $response["message"] = "Rates must be numeric values.";
    } else {
        $checkSql = 'SELECT driver_id FROM drivers WHERE "driver_IdNumber" = ? AND driver_id != ?';
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([$idNumber, $driverId]);

        if ($checkStmt->fetch() !== false) {
            $response["message"] = "Driver ID Number already exists.";
        } else {
            $updateSql = 'UPDATE drivers SET driver_lname = ?, driver_fname = ?, driver_mname = ?, "driver_IdNumber" = ?, "driver_dailyRate" = ?, "driver_hourlyRate" = ? WHERE driver_id = ?';
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->execute([$lname, $fname, $mname, $idNumber, $dailyRate, $hourlyRate, $driverId]);

            $response["success"] = true;
            $response["message"] = "Driver updated successfully.";
        }
    }

    header("Content-Type: application/json");
    echo json_encode($response);
    exit();
}
?>
