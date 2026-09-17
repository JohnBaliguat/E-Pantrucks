<?php
include "../config/config.php";
require_once __DIR__ . "/../helpers/waybill_duplicate.php";
require_once __DIR__ . "/../helpers/trip_rate_lookup.php";
require_once __DIR__ . "/../helpers/xlsx_helper.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json");

$response = ["success" => false, "message" => "", "imported" => 0, "skipped" => 0, "errors" => []];

if (!isset($_SESSION["user_idNumber"])) {
    $response["message"] = "Unauthorized.";
    echo json_encode($response);
    exit();
}

if (
    $_SERVER["REQUEST_METHOD"] !== "POST" ||
    !isset($_POST["action"]) ||
    $_POST["action"] !== "import-entries"
) {
    $response["message"] = "Invalid request.";
    echo json_encode($response);
    exit();
}

$entry_type = trim($_POST["entry_type"] ?? "");
$allowed = ["RV ENTRY", "DRY VAN ENTRY", "OTHERS ENTRY", "DPC_KDs & OPM ENTRY", "CARGO TRUCK ENTRY"];
if (!in_array($entry_type, $allowed, true)) {
    $response["message"] = "Invalid entry type.";
    echo json_encode($response);
    exit();
}

if (!isset($_FILES["xlsx_file"]) || $_FILES["xlsx_file"]["error"] !== UPLOAD_ERR_OK) {
    $response["message"] = "No file uploaded or upload error.";
    echo json_encode($response);
    exit();
}

$ext = strtolower(pathinfo($_FILES["xlsx_file"]["name"], PATHINFO_EXTENSION));
if ($ext !== "xlsx") {
    $response["message"] = "Only .xlsx files are accepted.";
    echo json_encode($response);
    exit();
}

$allRows = xlsx_read_rows($_FILES["xlsx_file"]["tmp_name"]);
if (count($allRows) < 2) {
    $response["success"] = true;
    $response["message"] = "File has no data rows.";
    echo json_encode($response);
    exit();
}

$created_by   = htmlspecialchars(trim($_SESSION["user_idNumber"]));
$created_date = date("Y-m-d H:i:s");

function imp_clean($value): string {
    return htmlspecialchars(trim((string)$value));
}

function imp_date($raw): string {
    $raw = trim((string)$raw);
    if ($raw === "") return "";
    if (ctype_digit($raw)) {
        $serial = (int)$raw;
        if ($serial >= 1 && $serial <= 73050) {
            if ($serial >= 60) $serial--;
            return gmdate('Y-m-d', ($serial - 25569) * 86400);
        }
    }
    $parts = explode("/", $raw);
    if (count($parts) < 2) return $raw;
    $month = str_pad($parts[0], 2, "0", STR_PAD_LEFT);
    $day   = str_pad($parts[1], 2, "0", STR_PAD_LEFT);
    $year  = isset($parts[2]) ? $parts[2] : date("Y");
    if (strlen($year) === 2) $year = "20" . $year;
    if (!is_numeric($month) || !is_numeric($day) || !is_numeric($year)) return $raw;
    return "$year-$month-$day";
}

function imp_time($raw): string {
    $raw = trim((string)$raw);
    if ($raw === "") return "";
    if (is_numeric($raw) && (float)$raw >= 0 && (float)$raw < 1) {
        $totalMin = (int)round((float)$raw * 1440);
        return str_pad((int)floor($totalMin / 60), 2, "0", STR_PAD_LEFT)
             . ':' . str_pad($totalMin % 60, 2, "0", STR_PAD_LEFT);
    }
    $t = str_replace(":", "", $raw);
    if (preg_match('/^\d{3,4}$/', $t)) {
        $t  = str_pad($t, 4, "0", STR_PAD_LEFT);
        $hh = substr($t, 0, 2);
        $mm = substr($t, 2, 2);
        if ((int)$hh > 23 || (int)$mm > 59) return $raw;
        return "$hh:$mm";
    }
    if (preg_match('/^\d{2}:\d{2}$/', $raw)) return $raw;
    return $raw;
}

function imp_datetime($raw): string {
    $raw = trim((string)$raw);
    if ($raw === "") return "";
    if (is_numeric($raw)) {
        $val    = (float)$raw;
        $serial = (int)floor($val);
        $frac   = $val - $serial;
        if ($serial >= 1 && $serial <= 73050) {
            if ($serial >= 60) $serial--;
            $dateStr  = gmdate('Y-m-d', ($serial - 25569) * 86400);
            $totalMin = (int)round($frac * 1440);
            $hh = str_pad((int)floor($totalMin / 60), 2, "0", STR_PAD_LEFT);
            $mm = str_pad($totalMin % 60, 2, "0", STR_PAD_LEFT);
            return "$dateStr $hh:$mm:00";
        }
    }
    $parts    = preg_split('/\s+/', $raw, 2);
    $datePart = imp_date($parts[0]);
    $timePart = isset($parts[1]) ? imp_time($parts[1]) : "00:00";
    if ($datePart === "" || $timePart === "") return "";
    return $datePart . " " . $timePart . ":00";
}

function imp_fallback_date(...$values): string {
    foreach ($values as $v) {
        $v = trim((string)$v);
        if ($v !== "") return $v;
    }
    return date("Y-m-d");
}

function imp_fallback_time(...$values): string {
    foreach ($values as $v) {
        $v = trim((string)$v);
        if ($v !== "") return $v;
    }
    return "00:00:00";
}

function imp_dv_route($loc): string {
    return strtoupper(trim((string)$loc)) === "DICT" ? "PNB" : "DVO";
}

function imp_dv_billing_customer($customer): string {
    $map = [
        "CITIHARDWARE IMPORTS"      => "CTH",  "CITIHARDWARE DOMESTIC"     => "CTH",
        "PSACC DOMESTIC"            => "PSACC",
        "TPD DRYVAN IMPORT"         => "TPD",  "TPD DRYVAN EXPORT"         => "TPD",
        "ECOSSENTIAL - IMPORT"      => "ECOSSENTIAL",
        "PHIL CEMENT"               => "PHIL CEMENT",
        "NOVOCOCONUT - IMPORT"      => "NOVOCOCONUT",
        "NP CHANGS - IMPORT"        => "NP CHANGS",
        "SOLARVISTA - IMPORT"       => "SOLARVISTA",
        "FRANKLIN BAKER - IMPORT"   => "FRANKLIN BAKER",
        "EYE CARGO - IMPORT"        => "EYE CARGO",
        "PHIL JDU - IMPORT"         => "JDU",
        "SOUTHERN HARVEST - IMPORT" => "SOUTHERN HARVEST",
        "HEADSPORT - IMPORT"        => "HEADSPORT",
        "AGRI EXIM - IMPORT"        => "AGRI EXIM",
        "SOLARIS - IMPORT"          => "SOLARIS",
        "ECOSSENTIAL - EXPORT"      => "ECOSSENTIAL",
        "NOVOCOCONUT - EXPORT"      => "NOVOCOCONUT",
        "FRANKLIN BAKER - EXPORT"   => "FRANKLIN BAKER",
        "EYE CARGO - EXPORT"        => "EYE CARGO",
        "PHIL JDU - EXPORT"         => "JDU",
        "SOUTHERN HARVEST - EXPORT" => "SOUTHERN HARVEST",
        "HEADSPORT - EXPORT"        => "HEADSPORT",
        "AGRI EXIM - EXPORT"        => "AGRI EXIM",
        "SOLARIS - EXPORT"          => "SOLARIS",
    ];
    return $map[$customer] ?? $customer;
}

function imp_dv_billing_sku($customer, $pullout, $return_loc): string {
    $customer = trim((string)$customer);
    if ($customer === "") return "";
    return "Dry Container-" . imp_dv_billing_customer($customer)
         . "-" . imp_dv_route($pullout)
         . "-" . imp_dv_route($return_loc);
}

function imp_dpc_billing_sku($ph): string {
    $ph = trim($ph);
    if ($ph !== "" && is_numeric($ph)) {
        $symbol = "TDC Compound";
    } else {
        switch (strtolower($ph)) {
            case "lupon":    $symbol = "ABC Lupon";    break;
            case "donmar":   $symbol = "ABC Donmar";   break;
            case "cateel":   $symbol = "ABC Cateel";   break;
            case "pantukan": $symbol = "ABC Pantukan"; break;
            default:         $symbol = "Others";       break;
        }
    }
    return "DPC KDS-" . $symbol;
}

function imp_rv_normalize_ph($ph): string {
    $ph = trim((string)$ph);
    if (preg_match('/^[A-Z]+0*(\d+)$/i', $ph, $m)) {
        $n = ltrim($m[1], "0");
        return $n === "" ? "0" : $n;
    }
    return $ph;
}

function imp_rv_lookup_sku(PDO $conn, $shipper, $ph): ?array {
    $shipper = trim((string)$shipper);
    $ph      = imp_rv_normalize_ph($ph);
    if ($shipper === "" || $ph === "") return null;
    $stmt = $conn->prepare('SELECT sku_name, "sku_rountripDistance" FROM sku WHERE TRIM(sku_shipper_segment)=? AND TRIM(sku_farm)=? LIMIT 1');
    $stmt->execute([$shipper, $ph]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    return [
        "billing_sku" => trim((string)($row["sku_name"] ?? "")),
        "kms"         => trim((string)($row["sku_rountripDistance"] ?? "")),
    ];
}

function imp_dv_lookup_kms(PDO $conn, $billing_sku): string {
    if (trim((string)$billing_sku) === "") return "";
    $stmt = $conn->prepare('SELECT "sku_rountripDistance" FROM sku WHERE TRIM(sku_name)=? LIMIT 1');
    $stmt->execute([$billing_sku]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? trim((string)($row["sku_rountripDistance"] ?? "")) : "";
}

$imported = 0;
$skipped  = 0;
$errors   = [];

foreach (array_slice($allRows, 1) as $rowIdx => $row) {
    $rowNum = $rowIdx + 2;
    $row    = array_pad($row, 50, "");
    $err    = null;

    if ($entry_type === "OTHERS ENTRY") {
        $date        = imp_date(imp_clean($row[0]));
        $waybill     = imp_clean($row[1]);
        $truck       = imp_clean($row[2]);
        $driver      = imp_clean($row[3]);
        $tr          = imp_clean($row[4]);
        $gs          = imp_clean($row[5]);
        $operations  = imp_clean($row[6]);
        $customer_ph = imp_clean($row[7]);
        $load_qty    = imp_clean($row[8]);
        $uom         = imp_clean($row[9]);
        $kms         = imp_clean($row[10]);
        $deliver_from= imp_clean($row[11]);
        $deliver_to  = imp_clean($row[12]);
        $remarks     = imp_clean($row[13]);

        if ($waybill === "") { $err = "Row $rowNum: Waybill is required."; }
        elseif (operations_waybill_exists($conn, $waybill)) { $err = "Row $rowNum: Waybill '$waybill' already exists."; }

        if ($err === null) {
            $billing_sku = ($operations !== "" || $customer_ph !== "") ? ($operations . "-" . $customer_ph) : "";
            $et = "OTHERS ENTRY";
            $stmt = $conn->prepare("INSERT INTO operations (
                entry_type, waybill_date, waybill, truck, tr, gs, operations_ph, customer_ph,
                load_quantity_weight, unit_of_measure, kms, deliver_from, delivered_to,
                driver, remarks, billing_sku, created_by, created_date
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $et, $date, $waybill, $truck, $tr, $gs, $operations, $customer_ph,
                $load_qty, $uom, $kms, $deliver_from, $deliver_to,
                $driver, $remarks, $billing_sku, $created_by, $created_date,
            ]);
        }

    } elseif ($entry_type === "DPC_KDs & OPM ENTRY") {
        $waybill_date = imp_date(imp_clean($row[0]));
        $waybill      = imp_clean($row[1]);
        $driver       = imp_clean($row[2]);
        $departure    = imp_datetime(imp_clean($row[3]));
        $arrival      = imp_datetime(imp_clean($row[4]));
        $truck        = imp_clean($row[5]);
        $tr           = imp_clean($row[6]);
        $ph           = imp_clean($row[7]);
        $b13_body     = imp_clean($row[8]);
        $b13_cover    = imp_clean($row[9]);
        $b13_pads     = imp_clean($row[10]);
        $b18_body     = imp_clean($row[11]);
        $b18_cover    = imp_clean($row[12]);
        $b18_pads     = imp_clean($row[13]);
        $ot_body      = imp_clean($row[14]);
        $ot_cover     = imp_clean($row[15]);
        $ot_pads      = imp_clean($row[16]);
        $fgtr_no      = imp_clean($row[17]);
        $remarks      = imp_clean($row[18]);

        $b13_total  = (string)((int)$b13_body + (int)$b13_cover + (int)$b13_pads);
        $b18_total  = (string)((int)$b18_body + (int)$b18_cover + (int)$b18_pads);
        $ot_total   = (string)((int)$ot_body  + (int)$ot_cover  + (int)$ot_pads);
        $total_load = (string)((int)$b13_total + (int)$b18_total + (int)$ot_total);
        $dpc_date   = $waybill_date;

        if ($waybill === "") { $err = "Row $rowNum: Waybill is required."; }
        elseif (operations_waybill_exists($conn, $waybill)) { $err = "Row $rowNum: Waybill '$waybill' already exists."; }
        elseif ($fgtr_no !== "" && operations_fgtr_no_exists($conn, $fgtr_no)) { $err = "Row $rowNum: FGTR No '$fgtr_no' already exists."; }

        if ($err === null) {
            $billing_sku = imp_dpc_billing_sku($ph);
            $piece_rate = $segment = $activity = $evita_farmind = $driver_idNumber = "";
            $et = "DPC_KDs & OPM ENTRY";
            $stmt = $conn->prepare("INSERT INTO operations (
                entry_type, segment, activity, waybill_date, waybill, evita_farmind, driver, \"driver_idNumber\",
                departure, arrival, truck, tr, ph, \"13_body\", \"13_cover\", \"13_pads\", \"18_body\", \"18_cover\",
                \"18_pads\", \"13_total\", \"18_total\", other_body, other_cover, other_pads, other_total, total_load,
                fgtr_no, remarks, dpc_date, piece_rate, billing_sku, created_by, created_date
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $et, $segment, $activity, $waybill_date, $waybill, $evita_farmind, $driver, $driver_idNumber,
                $departure, $arrival, $truck, $tr, $ph, $b13_body, $b13_cover, $b13_pads,
                $b18_body, $b18_cover, $b18_pads, $b13_total, $b18_total,
                $ot_body, $ot_cover, $ot_pads, $ot_total, $total_load,
                $fgtr_no, $remarks, $dpc_date, $piece_rate, $billing_sku, $created_by, $created_date,
            ]);
        }

    } elseif ($entry_type === "CARGO TRUCK ENTRY") {
        $waybill_date = imp_date(imp_clean($row[0]));
        $waybill      = imp_clean($row[1]);
        $truck        = imp_clean($row[2]);
        $driver       = imp_clean($row[3]);
        $customer_ph  = imp_clean($row[4]);
        $outside      = imp_clean($row[5]);
        $compound     = imp_clean($row[6]);
        $total_trips  = imp_clean($row[7]);
        $operations   = imp_clean($row[8]);
        $deliver_from = imp_clean($row[9]);
        $deliver_to   = imp_clean($row[10]);
        $remarks      = imp_clean($row[11]);
        $cargo_date   = $waybill_date;

        if ($waybill === "") { $err = "Row $rowNum: Waybill is required."; }
        elseif (operations_waybill_exists($conn, $waybill)) { $err = "Row $rowNum: Waybill '$waybill' already exists."; }

        if ($err === null) {
            $billing_sku = "CT-Other Hauling";
            $piece_rate = $segment = $activity = $driver_idNumber = "";
            $et = "CARGO TRUCK ENTRY";
            $stmt = $conn->prepare("INSERT INTO operations (
                entry_type, segment, activity, waybill_date, waybill, truck, driver, \"driver_idNumber\",
                customer_ph, outside, compound, total_trips, operations, deliver_from,
                delivered_to, remarks, cargo_date, piece_rate, billing_sku, created_by, created_date
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $et, $segment, $activity, $waybill_date, $waybill, $truck, $driver, $driver_idNumber,
                $customer_ph, $outside, $compound, $total_trips, $operations, $deliver_from,
                $deliver_to, $remarks, $cargo_date, $piece_rate, $billing_sku, $created_by, $created_date,
            ]);
        }

    } elseif ($entry_type === "DRY VAN ENTRY") {
        $customer_ph          = imp_clean($row[0]);
        $ecs                  = imp_clean($row[1]);
        $van_alpha            = imp_clean($row[2]);
        $van_number           = imp_clean($row[3]);
        $shipper              = imp_clean($row[4]);
        $eir_out              = imp_clean($row[5]);
        $eir_outDate          = imp_date(imp_clean($row[6]));
        $eir_outTime          = imp_time(imp_clean($row[7]));
        $eir_in               = imp_clean($row[8]);
        $eir_inDate           = imp_date(imp_clean($row[9]));
        $pullout_location     = imp_clean($row[10]);
        $pullout_date         = imp_date(imp_clean($row[11]));
        $delivered_to         = imp_clean($row[12]);
        $return_location      = imp_clean($row[13]);
        $size                 = imp_clean($row[14]);
        $slp_no               = imp_clean($row[15]);
        $destination          = imp_clean($row[16]);
        $gs                   = imp_clean($row[17]);
        $genset_hr_meter_start= imp_clean($row[18]);
        $genset_hr_meter_end  = imp_clean($row[19]);
        $kms                  = imp_clean($row[20]);
        $booking              = imp_clean($row[21]);
        $shipment_no          = imp_clean($row[22]);
        $date_unloaded        = imp_date(imp_clean($row[23]));
        $seal                 = imp_clean($row[24]);
        $waybill              = imp_clean($row[25]);
        $date_hauled          = imp_date(imp_clean($row[26]));
        $driver               = imp_clean($row[27]);
        $truck                = imp_clean($row[28]);
        $tr                   = imp_clean($row[29]);
        $tr2                  = imp_clean($row[30]);
        $remarks              = imp_clean($row[31]);
        $waybill_empty        = imp_clean($row[32]);
        $date_returned        = imp_date(imp_clean($row[33]));
        $driver_return        = imp_clean($row[34]);
        $truck_return         = imp_clean($row[35]);
        $type                 = imp_clean($row[36]);
        $delivered_remarks    = imp_clean($row[37]);

        $status = $segment = $activity = $segment_empty = $activity_empty = "";
        $driver_idNumber = "0"; $driver_return_idNumber = "";

        $eir_outTime    = imp_fallback_time($eir_outTime);
        $departure_time = "00:00:00";
        $arrival_time   = "00:00:00";
        $date_hauled    = imp_fallback_date($date_hauled, $pullout_date, $date_returned, $eir_outDate, $eir_inDate);
        $date_unloaded  = imp_fallback_date($date_unloaded, $date_hauled, $pullout_date, $date_returned);
        $date_returned  = imp_fallback_date($date_returned, $pullout_date, $date_hauled, $date_unloaded);
        $pullout_date   = imp_fallback_date($pullout_date, $eir_outDate, $date_hauled, $date_returned);
        $eir_outDate    = imp_fallback_date($eir_outDate, $pullout_date, $date_hauled, $date_returned);
        $eir_inDate     = imp_fallback_date($eir_inDate, $date_hauled, $date_returned, $pullout_date);

        if ($customer_ph === "") { $err = "Row $rowNum: Customer/PH is required."; }
        elseif ($waybill !== "" && operations_waybill_exists($conn, $waybill)) {
            $err = "Row $rowNum: Waybill '$waybill' already exists.";
        } elseif ($waybill_empty !== "" && operations_waybill_exists($conn, $waybill_empty)) {
            $err = "Row $rowNum: Empty waybill '$waybill_empty' already exists.";
        } elseif ($booking !== "" && operations_booking_exists($conn, $booking)) {
            $err = "Row $rowNum: Booking '$booking' already exists.";
        }

        if ($err === null) {
            $billing_sku = imp_dv_billing_sku($customer_ph, $pullout_location, $return_location);
            if ($kms === "") $kms = imp_dv_lookup_kms($conn, $billing_sku);

            $et = "DRY VAN ENTRY";
            $stmt = $conn->prepare("INSERT INTO operations (
                entry_type, status, customer_ph, ecs, van_alpha, van_number, shipper,
                eir_out, \"eir_outDate\", \"eir_outTime\", eir_in, \"eir_inDate\",
                pullout_location, pullout_date, delivered_to, return_location, size, slp_no,
                destination, gs, genset_hr_meter_start, genset_hr_meter_end,
                segment, activity, waybill, date_hauled, driver, truck, tr, tr2, date_unloaded,
                departure_time, arrival_time, remarks,
                segment_empty, activity_empty, waybill_empty, date_returned, type, delivered_remarks,
                kms, booking, shipment_no, seal, created_by, \"driver_idNumber\",
                driver_return, truck2, \"driver_return_idNumber\", billing_sku
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $et, $status, $customer_ph, $ecs, $van_alpha, $van_number, $shipper,
                $eir_out, $eir_outDate, $eir_outTime, $eir_in, $eir_inDate,
                $pullout_location, $pullout_date, $delivered_to, $return_location, $size, $slp_no,
                $destination, $gs, $genset_hr_meter_start, $genset_hr_meter_end,
                $segment, $activity, $waybill, $date_hauled, $driver, $truck, $tr, $tr2, $date_unloaded,
                $departure_time, $arrival_time, $remarks,
                $segment_empty, $activity_empty, $waybill_empty, $date_returned, $type, $delivered_remarks,
                $kms, $booking, $shipment_no, $seal, $created_by, $driver_idNumber,
                $driver_return, $truck_return, $driver_return_idNumber, $billing_sku,
            ]);
        }

    } elseif ($entry_type === "RV ENTRY") {
        $segment_empty   = imp_clean($row[0]);
        $activity_empty  = imp_clean($row[1]);
        $waybill_empty   = imp_clean($row[2]);
        $pl_arr_date     = imp_date(imp_clean($row[3]));
        $pl_arr_time     = imp_time(imp_clean($row[4]));
        $pl_dep_date     = imp_date(imp_clean($row[5]));
        $pl_dep_time     = imp_time(imp_clean($row[6]));
        $ph_arr_date     = imp_date(imp_clean($row[7]));
        $ph_arr_time     = imp_time(imp_clean($row[8]));
        $van_alpha       = imp_clean($row[9]);
        $van_number      = imp_clean($row[10]);
        $van_name        = imp_clean($row[11]);
        $ph              = imp_clean($row[12]);
        $shipper         = imp_clean($row[13]);
        $ecs             = imp_clean($row[14]);
        $tr              = imp_clean($row[15]);
        $gs              = imp_clean($row[16]);
        $prime_mover     = imp_clean($row[17]);
        $driver          = imp_clean($row[18]);
        $empty_pullout   = imp_clean($row[19]);
        $segment         = imp_clean($row[20]);
        $activity        = imp_clean($row[21]);
        $waybill         = imp_clean($row[22]);
        $load_start_date = imp_date(imp_clean($row[23]));
        $load_start_time = imp_time(imp_clean($row[24]));
        $load_fin_date   = imp_date(imp_clean($row[25]));
        $load_fin_time   = imp_time(imp_clean($row[26]));
        $del_dep_date    = imp_date(imp_clean($row[27]));
        $del_dep_time    = imp_time(imp_clean($row[28]));
        $del_arr_date    = imp_date(imp_clean($row[29]));
        $del_arr_time    = imp_time(imp_clean($row[30]));
        $end_unl_s_date  = imp_date(imp_clean($row[31]));
        $end_unl_s_time  = imp_time(imp_clean($row[32]));
        $end_unl_f_date  = imp_date(imp_clean($row[33]));
        $end_unl_f_time  = imp_time(imp_clean($row[34]));
        $dr_no           = imp_clean($row[35]);
        $ref_docs        = imp_clean($row[36]);
        $load_desc       = imp_clean($row[37]);
        $del_by_pm       = imp_clean($row[38]);
        $del_by_driver   = imp_clean($row[39]);
        $delivered_to    = imp_clean($row[40]);
        $remarks         = imp_clean($row[41]);
        $gs_hr_start     = imp_clean($row[42]);
        $gs_hr_end       = imp_clean($row[43]);
        $gs_start_date   = imp_date(imp_clean($row[44]));
        $gs_start_time   = imp_time(imp_clean($row[45]));
        $gs_end_date     = imp_date(imp_clean($row[46]));
        $gs_end_time     = imp_time(imp_clean($row[47]));
        $refueled        = imp_clean($row[48]);

        $delivered_remarks   = $remarks;
        $driver_idNumber     = $del_by_driver_id = "";
        $genset_hr_meter     = $genset_hr_reading = "";
        $genset_shutoff_date = $genset_shutoff_time = "";

        if ($waybill === "") { $err = "Row $rowNum: Waybill (Loaded) is required."; }
        elseif (operations_waybill_exists($conn, $waybill)) { $err = "Row $rowNum: Waybill '$waybill' already exists."; }
        elseif ($waybill_empty !== "" && operations_waybill_exists($conn, $waybill_empty)) { $err = "Row $rowNum: Empty waybill '$waybill_empty' already exists."; }

        if ($err === null) {
            $sku_data          = imp_rv_lookup_sku($conn, $shipper, $ph);
            $billing_sku       = $sku_data["billing_sku"] ?? "";
            $kms               = $sku_data["kms"] ?? "";
            $piece_rate_empty  = operations_lookup_piece_rate($conn, $segment_empty, $activity_empty);
            $piece_rate_loaded = operations_lookup_piece_rate($conn, $segment, $activity);
            $piece_rate        = (string)((float)$piece_rate_empty + (float)$piece_rate_loaded);

            $et = "RV ENTRY";
            $stmt = $conn->prepare("INSERT INTO operations (
                entry_type, segment_empty, activity_empty, segment, activity, remarks,
                pullout_location_arrival_date, pullout_location_arrival_time,
                pullout_location_departure_date, pullout_location_departure_time,
                ph_arrival_date, ph_arrival_time,
                delivery_location_arrival_date, delivery_location_arrival_time,
                van_alpha, van_number, van_name, ph, shipper, ecs, tr, gs,
                waybill, waybill_empty, prime_mover, driver, empty_pullout_location,
                loaded_van_loading_start_date, loaded_van_loading_start_time,
                loaded_van_loading_finish_date, loaded_van_loading_finish_time,
                loaded_van_delivery_departure_date, loaded_van_delivery_departure_time,
                loaded_van_delivery_arrival_date, loaded_van_delivery_arrival_time,
                genset_shutoff_date, genset_shutoff_time,
                end_uploading_date, end_uploading_time,
                dr_no, load_description, delivered_by_prime_mover, delivered_by_driver,
                delivered_to, delivered_remarks,
                genset_hr_meter_start, genset_hr_meter_end, reference_documents,
                genset_hr_meter, genset_hr_reading, refueled,
                genset_start_date, genset_start_time, genset_end_date, genset_end_time,
                piece_rate_empty, piece_rate_loaded, piece_rate, kms, billing_sku,
                created_by, \"driver_idNumber\", \"delivered_by_driverIdNumber\"
            ) VALUES (
                ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
            )");
            $stmt->execute([
                $et, $segment_empty, $activity_empty, $segment, $activity, $remarks,
                $pl_arr_date, $pl_arr_time, $pl_dep_date, $pl_dep_time,
                $ph_arr_date, $ph_arr_time,
                $end_unl_f_date, $end_unl_f_time,
                $van_alpha, $van_number, $van_name, $ph, $shipper, $ecs, $tr, $gs,
                $waybill, $waybill_empty, $prime_mover, $driver, $empty_pullout,
                $load_start_date, $load_start_time, $load_fin_date, $load_fin_time,
                $del_dep_date, $del_dep_time, $del_arr_date, $del_arr_time,
                $genset_shutoff_date, $genset_shutoff_time,
                $end_unl_s_date, $end_unl_s_time,
                $dr_no, $load_desc, $del_by_pm, $del_by_driver,
                $delivered_to, $delivered_remarks,
                $gs_hr_start, $gs_hr_end, $ref_docs,
                $genset_hr_meter, $genset_hr_reading, $refueled,
                $gs_start_date, $gs_start_time, $gs_end_date, $gs_end_time,
                $piece_rate_empty, $piece_rate_loaded, $piece_rate, $kms, $billing_sku,
                $created_by, $driver_idNumber, $del_by_driver_id,
            ]);
        }
    }

    if ($err !== null) {
        $skipped++;
        $errors[] = $err;
    } else {
        $imported++;
    }
}

$response["success"]  = true;
$response["message"]  = "Import complete. $imported row(s) imported, $skipped row(s) skipped.";
$response["imported"] = $imported;
$response["skipped"]  = $skipped;
$response["errors"]   = $errors;

echo json_encode($response);
exit();
