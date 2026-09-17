<?php
require_once __DIR__ . "/../helpers/json_error_guard.php";
include "../config/config.php";

header("Content-Type: application/json; charset=utf-8");

$payload = json_decode((string) ($_POST["locations"] ?? ""), true);
if (!is_array($payload)) {
    echo json_encode(["success" => false, "message" => "Invalid payload."]);
    exit();
}
// Region map (location_id => "DAVAO"/"PANABO"/""), sent alongside the matrix values.
$regions = json_decode((string) ($_POST["regions"] ?? ""), true);
if (!is_array($regions)) {
    $regions = [];
}

try {
    $conn->exec("ALTER TABLE location ADD COLUMN IF NOT EXISTS location_matrix TEXT");
    $conn->exec("ALTER TABLE location ADD COLUMN IF NOT EXISTS region TEXT");
} catch (Throwable $e) {
    // ignore
}

try {
    $conn->beginTransaction();

    // UPDATE only — this editor never inserts or deletes locations.
    $update = $conn->prepare("UPDATE location SET location_matrix = ? WHERE location_id = ?");
    $changed = 0;
    foreach ($payload as $id => $value) {
        $id = (int) $id;
        if ($id <= 0) {
            continue;
        }
        $matrix = trim((string) $value);
        $update->execute([$matrix !== "" ? $matrix : null, $id]);
        $changed += $update->rowCount();
    }

    // Region is optional; only touch the ids present in the regions payload.
    $updRegion = $conn->prepare("UPDATE location SET region = ? WHERE location_id = ?");
    foreach ($regions as $id => $value) {
        $id = (int) $id;
        if ($id <= 0) {
            continue;
        }
        $region = strtoupper(trim((string) $value));
        $updRegion->execute([$region !== "" ? $region : null, $id]);
    }

    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(["success" => false, "message" => "Save failed: " . $e->getMessage()]);
    exit();
}

echo json_encode(["success" => true, "message" => sprintf("Updated %d location(s).", $changed)]);
exit();
?>
