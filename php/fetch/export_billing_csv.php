<?php
include "../config/config.php";

$dateFrom = trim((string) ($_GET["date_from"] ?? ""));
$dateTo   = trim((string) ($_GET["date_to"]   ?? ""));
$entryType = trim((string) ($_GET["entry_type"] ?? ""));
$customer  = trim((string) ($_GET["customer"]  ?? ""));

if (
    $dateFrom === "" ||
    $dateTo   === "" ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ||
    $dateFrom > $dateTo
) {
    http_response_code(400);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Invalid date range.";
    exit();
}

function normalize($value): string
{
    return trim((string) ($value ?? ""));
}

function first_non_empty_val(...$values): string
{
    foreach ($values as $v) {
        $t = normalize($v);
        if ($t !== "") return $t;
    }
    return "";
}

function fmt_date(?string $date): string
{
    if (!$date) return "";
    $date = normalize($date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || str_starts_with($date, '0000-')) return "";
    $parts = explode("-", $date);
    return $parts[1] . "/" . $parts[2] . "/" . $parts[0];
}

function fmt_datetime(?string $date, ?string $time): string
{
    $d = fmt_date($date);
    if ($d === "") return "";
    $t = normalize($time);
    return $t !== "" ? $d . " " . substr($t, 0, 5) : $d;
}

$params = [$dateFrom, $dateTo];
$entryTypeSql = "";
$customerSql  = "";

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

$sql = "SELECT
    entry_id,
    entry_type,
    customer_ph,
    ph,
    operations_ph,
    shipper,
    waybill,
    waybill_empty,
    waybill_date,
    van_alpha,
    van_number,
    van_name,
    ecs,
    tr,
    tr2,
    gs,
    prime_mover,
    truck,
    truck2,
    driver,
    driver_return,
    empty_pullout_location,
    pullout_location,
    deliver_from,
    eir_in,
    eir_out,
    \"eir_outDate\",
    \"eir_outTime\",
    date_hauled,
    date_unloaded,
    arrival_time,
    loaded_van_loading_start_date,
    loaded_van_loading_start_time,
    dr_no,
    slp_no,
    load_description,
    delivered_to,
    total_trips,
    remarks,
    delivered_remarks,
    kms,
    billing_sku,
    ph_departure_date,
    ph_departure_time,
    loaded_van_loading_finish_date,
    loaded_van_loading_finish_time,
    load_quantity_weight,
    delivered_by_prime_mover,
    delivered_by_driver,
    unit_of_measure,
    total_load,
    reference_documents,
    size,
    destination,
    genset_hr_meter_start,
    genset_hr_meter_end,
    genset_start_date,
    genset_start_time,
    genset_end_date,
    genset_end_time,
    created_date
FROM operations
WHERE created_date::date BETWEEN ? AND ?
  AND TRIM(COALESCE(billing_sku, '')) <> ''" . $entryTypeSql . $customerSql . "
ORDER BY
    entry_type ASC,
    COALESCE(NULLIF(customer_ph, ''), NULLIF(shipper, ''), NULLIF(operations_ph, '')) ASC,
    COALESCE(NULLIF(billing_sku, ''), '') ASC,
    COALESCE(waybill_date::text, created_date::text) ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);

$entryTypeLabel = ($entryType === "" || strtoupper($entryType) === "ALL")
    ? "ALL"
    : preg_replace('/[^A-Za-z0-9_-]+/', '_', $entryType);

$filename = sprintf("billing_%s_%s_%s.csv", $entryTypeLabel, $dateFrom, $dateTo);

header("Content-Type: text/csv; charset=utf-8");
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen("php://output", "w");

fputcsv($output, [
    "Entry",
    "Transmittal DATE",
    "Trip Receipt No.",
    "Transaction Date",
    "Date and Time of Withdrawal of Van",
    "Alpha",
    "Numeric",
    "Shipping Line",
    "PH",
    "Customer / Shipper",
    "TRIP RECEIPT MTY",
    "ECS",
    "TR",
    "GS",
    "PM",
    "Driver",
    "Pull-Out Location",
    "Date & Time of Van Unloading",
    "TRIP RECEIPT FCL",
    "DR No./Fleet No/TCARD",
    "Load",
    "PM2",
    "Driver2",
    "Delivered To",
    "No. Of Trips",
    "Remarks",
    "Standard Kms",
    "SKUs",
    "OUT",
    "IN",
    "Volume",
    "Van Size",
    "HR:MN",
    "NO. OF HOURS",
    "HOURS START",
    "HOURS END",
    "START",
    "END",
    "Trailer Rental",
]);

$generatedDate = (new DateTimeImmutable("now", new DateTimeZone("Asia/Manila")))->format("m/d/Y");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $entryTypeValue = normalize($row["entry_type"] ?? "");
    $isDryVan = $entryTypeValue === "DRY VAN ENTRY";
    $isOthers = strcasecmp($entryTypeValue, "OTHERS ENTRY") === 0;

    $customerOrShipper = first_non_empty_val(
        $row["customer_ph"] ?? "",
        $row["shipper"] ?? "",
        $row["operations_ph"] ?? ""
    );
    if ($entryTypeValue === "DPC_KDs & OPM ENTRY") {
        $customerOrShipper = "DPC";
    }

    if ($isDryVan) {
        $transactionDate = fmt_date(normalize($row["date_hauled"] ?? ""));
    } elseif ($isOthers) {
        $transactionDate = fmt_date(normalize($row["waybill_date"] ?? ""));
    } else {
        $transactionDate = fmt_date(first_non_empty_val($row["genset_end_date"] ?? "", $row["waybill_date"] ?? ""));
    }

    if ($isDryVan) {
        $withdrawalRawDate = substr(normalize($row["eir_outDate"] ?? ""), 0, 10);
        $withdrawalDateTime = fmt_datetime(normalize($row["eir_outDate"] ?? ""), normalize($row["eir_outTime"] ?? ""));
    } elseif ($isOthers) {
        $withdrawalRawDate = substr(normalize($row["waybill_date"] ?? ""), 0, 10);
        $withdrawalDateTime = fmt_date(normalize($row["waybill_date"] ?? ""));
    } else {
        $wdDate = first_non_empty_val(
            $row["loaded_van_loading_start_date"] ?? "",
            substr((string) ($row["created_date"] ?? ""), 0, 10),
            $row["waybill_date"] ?? ""
        );
        $withdrawalRawDate = substr($wdDate, 0, 10);
        $withdrawalDateTime = fmt_datetime(
            $wdDate,
            first_non_empty_val($row["loaded_van_loading_start_time"] ?? "", substr((string) ($row["created_date"] ?? ""), 11, 8))
        );
    }

    $pmValue = ($isDryVan || $isOthers)
        ? normalize($row["truck"] ?? "")
        : first_non_empty_val($row["prime_mover"] ?? "", $row["truck"] ?? "");

    if ($isDryVan) {
        $pulloutLocation = normalize($row["pullout_location"] ?? "");
    } elseif ($isOthers) {
        $pulloutLocation = normalize($row["deliver_from"] ?? "");
    } else {
        $pulloutLocation = normalize($row["empty_pullout_location"] ?? "");
    }

    if ($isDryVan) {
        $unloadingRawDate = substr(normalize($row["date_unloaded"] ?? ""), 0, 10);
        $unloadingDateTime = fmt_datetime(normalize($row["date_unloaded"] ?? ""), normalize($row["arrival_time"] ?? ""));
    } else {
        $phDate = substr(normalize($row["ph_departure_date"] ?? ""), 0, 10);
        $phTime = normalize($row["ph_departure_time"] ?? "");
        $unloadingDateTime = fmt_datetime($phDate, $phTime);
        if ($unloadingDateTime === "") {
            $phDate = substr(normalize($row["loaded_van_loading_finish_date"] ?? ""), 0, 10);
            $phTime = normalize($row["loaded_van_loading_finish_time"] ?? "");
            $unloadingDateTime = fmt_datetime($phDate, $phTime);
        }
        $unloadingRawDate = $phDate;
    }

    $drValue = $isDryVan ? normalize($row["slp_no"] ?? "") : normalize($row["dr_no"] ?? "");

    if ($isDryVan) {
        $loadValue = normalize($row["destination"] ?? "");
    } elseif ($isOthers) {
        $qty = normalize($row["load_quantity_weight"] ?? "");
        $uom = normalize($row["unit_of_measure"] ?? "");
        $loadValue = $uom !== "" ? trim($qty . " " . $uom) : $qty;
    } else {
        $loadValue = first_non_empty_val($row["load_description"] ?? "", $row["total_load"] ?? "");
    }

    $vanSize = $isDryVan ? normalize($row["size"] ?? "") : "";

    $remarks = first_non_empty_val($row["remarks"] ?? "", $row["delivered_remarks"] ?? "", $row["reference_documents"] ?? "");

    $trailerRental = "";
    if (
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $withdrawalRawDate) && !str_starts_with($withdrawalRawDate, '0000-') &&
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $unloadingRawDate) && !str_starts_with($unloadingRawDate, '0000-')
    ) {
        $trailerRental = (string) (new DateTimeImmutable($withdrawalRawDate))->diff(new DateTimeImmutable($unloadingRawDate))->days;
    }

    fputcsv($output, [
        $entryTypeValue,
        $generatedDate,
        normalize($row["waybill"] ?? ""),
        $transactionDate,
        $withdrawalDateTime,
        normalize($row["van_alpha"] ?? ""),
        normalize($row["van_number"] ?? ""),
        normalize($row["van_name"] ?? ""),
        $isOthers ? normalize($row["customer_ph"] ?? "") : normalize($row["ph"] ?? ""),
        $customerOrShipper,
        normalize($row["waybill_empty"] ?? ""),
        normalize($row["ecs"] ?? ""),
        normalize($row["tr"] ?? ""),
        normalize($row["gs"] ?? ""),
        $pmValue,
        normalize($row["driver"] ?? ""),
        $pulloutLocation,
        $unloadingDateTime,
        normalize($row["waybill"] ?? ""),
        $drValue,
        $loadValue,
        $isOthers ? normalize($row["truck"] ?? "") : first_non_empty_val($row["truck2"] ?? "", $row["delivered_by_prime_mover"] ?? ""),
        $isOthers ? normalize($row["driver"] ?? "") : first_non_empty_val($row["driver_return"] ?? "", $row["delivered_by_driver"] ?? ""),
        normalize($row["delivered_to"] ?? ""),
        normalize($row["total_trips"] ?? ""),
        $remarks,
        normalize($row["kms"] ?? ""),
        normalize($row["billing_sku"] ?? ""),
        normalize($row["eir_out"] ?? ""),
        normalize($row["eir_in"] ?? ""),
        normalize($row["size"] ?? ""),
        $vanSize,
        "",
        "",
        normalize($row["genset_hr_meter_start"] ?? ""),
        normalize($row["genset_hr_meter_end"] ?? ""),
        fmt_datetime($row["genset_start_date"] ?? "", $row["genset_start_time"] ?? ""),
        fmt_datetime($row["genset_end_date"] ?? "", $row["genset_end_time"] ?? ""),
        $trailerRental,
    ]);
}

fclose($output);
exit();
?>
