<?php
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_operations_emdr_schema.php";

ensure_operations_emdr_schema($conn);

if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["id"])) {
    $id = intval($_GET["id"]);

    $query = "SELECT * FROM operations WHERE entry_id = ? AND entry_type = 'DRY VAN ENTRY'";
    $stmt = $conn->prepare($query);
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    header("Content-Type: application/json");
    echo json_encode($record);
} else {
    header("Content-Type: application/json");
    echo json_encode(null);
}
?>
