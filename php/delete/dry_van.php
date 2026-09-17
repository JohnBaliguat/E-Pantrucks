<?php
include "../config/config.php";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["entry_id"])) {
    $entry_id = intval($_POST["entry_id"]);
    $response = ["success" => false, "message" => ""];

    if ($entry_id <= 0) {
        $response["message"] = "Invalid entry ID.";
    } else {
        $query = "DELETE FROM operations WHERE entry_id = ? AND entry_type = 'DRY VAN ENTRY'";
        $stmt = $conn->prepare($query);
        $stmt->execute([$entry_id]);

        if ($stmt->rowCount() > 0) {
            $response["success"] = true;
            $response["message"] = "Entry deleted successfully.";
        } else {
            $response["message"] = "Entry not found.";
        }
    }

    header("Content-Type: application/json");
    echo json_encode($response);
} else {
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
?>
