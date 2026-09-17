<?php
include "../config/config.php";

$sql = 'SELECT driver_id, driver_fname, driver_mname, driver_lname, "driver_IdNumber"
        FROM drivers
        ORDER BY driver_lname ASC, driver_fname ASC';
$stmt = $conn->query($sql);

$drivers = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $lastName = strtoupper(trim((string) ($row["driver_lname"] ?? "")));
    $firstName = strtoupper(trim((string) ($row["driver_fname"] ?? "")));
    $middleName = strtoupper(trim((string) ($row["driver_mname"] ?? "")));
    $middleInitial = $middleName !== "" ? " " . substr($middleName, 0, 1) . "." : "";
    $formattedName = trim($lastName . ", " . $firstName . $middleInitial);

    $drivers[] = [
        "id" => trim((string) ($row["driver_IdNumber"] ?? "")),
        "name" => $formattedName,
    ];
}

header('Content-Type: application/json');
echo json_encode($drivers);
