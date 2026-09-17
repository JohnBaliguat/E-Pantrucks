<?php
include "../config/config.php";
header("Content-Type: application/json; charset=utf-8");

// Returns the last recorded HR METER END reading for a given genset (gs), so the
// RV entry form can auto-fill HR METER START for the next trip.
$gs = trim((string) ($_GET["gs"] ?? ""));
if ($gs === "") {
    echo json_encode(["success" => true, "last_hr_meter" => null]);
    exit();
}

try {
    $sql = <<<SQL
SELECT genset_hr_meter_end
FROM operations
WHERE TRIM(gs) = TRIM(?)
  AND genset_hr_meter_end IS NOT NULL
  AND genset_hr_meter_end > 0
ORDER BY COALESCE(genset_end_date, created_date::date) DESC NULLS LAST, entry_id DESC
LIMIT 1
SQL;
    $stmt = $conn->prepare($sql);
    $stmt->execute([$gs]);
    $value = $stmt->fetchColumn();
    echo json_encode([
        "success" => true,
        "last_hr_meter" => $value !== false ? $value : null,
    ]);
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage(), "last_hr_meter" => null]);
}
exit();
?>
