<?php
include "../config/config.php";

header("Content-Type: application/json");

$dateFrom = trim((string) ($_GET["date_from"] ?? ""));
$dateTo = trim((string) ($_GET["date_to"] ?? ""));
$entryType = trim((string) ($_GET["entry_type"] ?? ""));
$customer = trim((string) ($_GET["customer"] ?? ""));
$createdBy = trim((string) ($_GET["created_by"] ?? ""));

if (
    $dateFrom === "" ||
    $dateTo === "" ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ||
    $dateFrom > $dateTo
) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid date range.",
    ]);
    exit();
}

$params = [$dateFrom, $dateTo];
$entryTypeSql = "";
$customerSql = "";

if ($entryType !== "" && strtoupper($entryType) !== "ALL") {
    $entryTypeSql = " AND entry_type = ?";
    $params[] = $entryType;
}

if ($customer !== "") {
    $customerSql = " AND (customer_ph LIKE ? OR ph LIKE ? OR operations_ph LIKE ?)";
    $like = "%" . $customer . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$createdBySql = "";
if ($createdBy !== "") {
    $createdBySql = " AND created_by = ?";
    $params[] = $createdBy;
}

$sql = "SELECT
    o.entry_id,
    o.entry_type,
    o.customer_ph,
    o.ph,
    o.operations_ph,
    o.waybill,
    o.waybill_empty,
    o.van_alpha,
    o.van_number,
    o.van_name,
    o.tr,
    o.tr2,
    o.truck,
    o.truck2,
    o.driver,
    o.driver_return,
    o.status,
    o.remarks,
    o.delivered_remarks,
    o.created_by,
    NULLIF(TRIM(CONCAT_WS(' ', u.user_fname, u.user_lname)), '') AS created_by_name,
    u.user_name AS created_by_username,
    o.created_date,
    o.modified_date
FROM operations o
LEFT JOIN \"user\" u
    ON u.\"user_idNumber\"::text = o.created_by OR u.user_name = o.created_by
WHERE o.created_date::date BETWEEN ? AND ?" . $entryTypeSql . $customerSql . $createdBySql . "
ORDER BY o.created_date DESC, o.entry_id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);

$records = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $waybills = array_values(array_unique(array_filter([
        trim((string) ($row["waybill"] ?? "")),
        trim((string) ($row["waybill_empty"] ?? "")),
    ], fn($value) => $value !== "")));

    $drivers = array_values(array_unique(array_filter([
        trim((string) ($row["driver"] ?? "")),
        trim((string) ($row["driver_return"] ?? "")),
    ], fn($value) => $value !== "")));

    $vanParts = array_values(array_unique(array_filter([
        trim((string) ($row["van_alpha"] ?? "")),
        trim((string) ($row["van_number"] ?? "")),
        trim((string) ($row["van_name"] ?? "")),
        trim((string) ($row["tr"] ?? "")),
        trim((string) ($row["tr2"] ?? "")),
    ], fn($value) => $value !== "")));

    $customer = trim((string) ($row["customer_ph"] ?? ""));
    if ($customer === "") {
        $customer = trim((string) ($row["ph"] ?? ""));
    }
    if ($customer === "") {
        $customer = trim((string) ($row["operations_ph"] ?? ""));
    }

    $createdBy = trim((string) ($row["created_by"] ?? ""));
    $createdByName = trim((string) ($row["created_by_name"] ?? ""));
    if ($createdByName === "") {
        $createdByName = trim((string) ($row["created_by_username"] ?? ""));
    }
    if ($createdByName === "") {
        $createdByName = $createdBy;
    }

    $records[] = [
        "entry_id" => (int) $row["entry_id"],
        "entry_type" => $row["entry_type"] ?? "",
        "customer" => $customer,
        "created_by" => $createdBy,
        "created_by_name" => $createdByName,
        "waybills" => $waybills,
        "waybill" => trim((string) ($row["waybill"] ?? "")),
        "waybill_empty" => trim((string) ($row["waybill_empty"] ?? "")),
        "van" => implode(" ", $vanParts),
        "drivers" => $drivers,
        "driver" => trim((string) ($row["driver"] ?? "")),
        "driver2" => trim((string) ($row["driver_return"] ?? "")),
        "status" => $row["status"] ?? "",
        "remarks" => trim((string) (($row["remarks"] ?? "") !== "" ? $row["remarks"] : ($row["delivered_remarks"] ?? ""))),
        "created_date" => $row["created_date"] ?? "",
        "modified_date" => $row["modified_date"] ?? "",
    ];
}

echo json_encode([
    "success" => true,
    "records" => $records,
    "generated_at" => date("c"),
]);
exit();
?>
