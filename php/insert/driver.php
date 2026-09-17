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

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "add-driver") {
    $response = ["success" => false, "message" => ""];

    $lname = validate($_POST["lastName"] ?? "");
    $fname = validate($_POST["firstName"] ?? "");
    $mname = validate($_POST["middleName"] ?? "");
    $idNumber = validate($_POST["idNumber"] ?? "");
    $dailyRate = validate($_POST["dailyRate"] ?? "");
    $hourlyRate = validate($_POST["hourlyRate"] ?? "");

    if ($lname === "" || $fname === "" || $idNumber === "" || $dailyRate === "" || $hourlyRate === "") {
        $response["message"] = "All required fields must be filled in.";
    } elseif (!is_numeric($dailyRate) || !is_numeric($hourlyRate)) {
        $response["message"] = "Rates must be numeric values.";
    } else {
        try {
            $checkStmt = $conn->prepare('SELECT driver_id FROM drivers WHERE "driver_IdNumber" = ?');
            $checkStmt->execute([$idNumber]);

            if ($checkStmt->fetch() !== false) {
                $response["message"] = "Driver ID Number already exists.";
            } else {
                // driver_id has no auto-increment sequence; derive the next id like the rest of the app.
                $insertStmt = $conn->prepare('INSERT INTO drivers (driver_id, driver_lname, driver_fname, driver_mname, "driver_IdNumber", "driver_dailyRate", "driver_hourlyRate") VALUES (COALESCE((SELECT MAX(driver_id) FROM drivers), 0) + 1, ?,?,?,?,?,?)');
                $insertStmt->execute([$lname, $fname, $mname, $idNumber, $dailyRate, $hourlyRate]);

                $response["success"] = true;
                $response["message"] = "Driver registered successfully.";
            }
        } catch (PDOException $e) {
            $response["message"] = "Database error: " . $e->getMessage();
        }
    }

    header("Content-Type: application/json");
    echo json_encode($response);
    exit();
}
?>
