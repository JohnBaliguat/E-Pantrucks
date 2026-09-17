<?php
include "../config/config.php";

header("Content-Type: application/json");

$q = trim((string) ($_GET["q"] ?? ""));

if ($q === "") {
    echo json_encode(["customers" => []]);
    exit();
}

$like = "%" . $q . "%";

$stmt = $conn->prepare(
    "SELECT val FROM (
        SELECT customer_ph AS val FROM operations WHERE customer_ph ILIKE ? AND customer_ph IS NOT NULL AND customer_ph != ''
        UNION
        SELECT shipper AS val FROM operations WHERE shipper ILIKE ? AND shipper IS NOT NULL AND shipper != ''
        UNION
        SELECT operations_ph AS val FROM operations WHERE operations_ph ILIKE ? AND operations_ph IS NOT NULL AND operations_ph != ''
     ) combined
     ORDER BY val ASC
     LIMIT 20"
);

$stmt->execute([$like, $like, $like]);

$customers = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $customers[] = $row["val"];
}

echo json_encode(["customers" => $customers]);
exit();
?>
