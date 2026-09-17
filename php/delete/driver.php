<?php
include "../config/config.php";

function validate($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "delete-driver") {
    $response = ["success" => false, "message" => ""];

    $driverId = validate($_POST["driverId"] ?? "");

    if ($driverId === "") {
        $response["message"] = "Driver ID is required.";
    } else {
        $deleteSql = "DELETE FROM drivers WHERE driver_id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->execute([$driverId]);

        $response["success"] = true;
        $response["message"] = "Driver deleted successfully.";
    }

    header("Content-Type: application/json");
    echo json_encode($response);
    exit();
}
?>
