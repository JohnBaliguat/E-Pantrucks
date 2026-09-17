<?php

/**
 * Builder for the "RAW for Billing" export — the wide reefer-RV detail layout
 * the finance/billing team uses (see RAW FOR BILLING.xlsx). Rows are grouped
 * by Customer -> Profit Center. Because the operations table has no profit
 * center column, it is derived from segment + customer.
 *
 * Now used ONLY to render RAW detail for the Billing preview and the per-invoice
 * RAW download (both drive off raw_billing_fetch_rows_by_ids + the display/xlsx
 * writers). The old batch export endpoints and their date-range fetch / selection
 * / CSV helpers were removed 2026-07-16.
 *
 * Public API:
 *   raw_billing_columns(): array  // ordered [key => header label]
 *   raw_billing_preview_columns(): array  // preview column set (CUSTOMER hidden)
 *   raw_billing_fetch_rows_by_ids(PDO, array $entryIds): array
 *   raw_billing_display_rows(array $rows): array  // preview-ready strings
 *   raw_billing_write_xlsx(array $rows, string $outPath, $dateFrom, $dateTo): void
 */

require_once __DIR__ . "/dashboard_matrix_revenue.php";
require_once __DIR__ . "/build_box_banana_billing.php"; // box_banana_* + billing_forex_rate / billing_sap_price_scale
require_once __DIR__ . "/sumifru_rate.php";             // sumifru_resolve_rate (Sumifru lane+tier)

function raw_billing_columns(): array
{
    // Header labels for the RAW export. Order matches the on-screen preview
    // (raw_billing_preview_columns): PH sits between the Pull-Out and Delivered
    // locations, and TRIP RECEIPT EMPTY / TRUCK EMPTY are the last two columns.
    // CUSTOMER leads (it drives the Customer -> Profit Center group banners) and
    // is dropped only from the preview.
    return [
        "customer" => "CUSTOMER",
        "profit_center" => "PROFIT CENTER",
        "hauling_date" => "HAULING DATE",
        "trip_receipt" => "TRIP RECEIPT",
        "truck" => "TRUCK",
        "van_size" => "VAN SIZE",
        "alpha" => "ALPHA",
        "van_number" => "VAN NUMBER",
        "shipping_line" => "SHIPPING LINE",
        "no_of_trips" => "NO. OF TRIPS",
        "load" => "LOAD",
        "ecs" => "ECS",
        "pullout_location" => "PULL OUT EMPTY/LADEN LOCATION",
        "ph" => "PH",
        "delivered_to" => "DELIVERED TO/RETURN EMPTY",
        "eir_in" => "EIR IN NUMBER",
        "eir_out" => "EIR OUT NUMBER",
        "genset_start" => "EQUIPMENT RENTAL",
        "genset_end" => "",
        "rental_days" => "HR:MN",
        "rental_hours" => "NO/ OF HOURS",
        "chassis" => "CHASSIS",
        "genset_no" => "GENSET #",
        "hubo_start" => "HUBO METER START",
        "hubo_end" => "HUBO METER END",
        "start2" => "START2",
        "end3" => "END3",
        "runtime_hrmn" => "HR:MN",
        "time" => "TIME",
        "runtime" => "RUNTIME",
        "fuel" => "FUEL",
        "dr_no" => "DR NO//FLEET NO/SN/SLPS",
        "bl_number" => "BL NUMBER",
        "van_volume" => "VAN VOLUME",
        "dollar_conversion" => "DOLLAR CONVERTION",
        "rate_charge" => "RATE CHARGES",
        "trip_receipt_empty" => "TRIP RECEIPT EMPTY",
        "truck_empty" => "TRUCK EMPTY",
    ];
}

/**
 * Columns whose values are numeric / date / datetime (everything else is text).
 */
function raw_billing_column_types(): array
{
    return [
        "hauling_date" => "date",
        "trip_receipt" => "number",
        "truck" => "number3",
        "van_number" => "number7",
        "genset_start" => "datetime",
        "genset_end" => "datetime",
        "rental_hours" => "number",
        "chassis" => "number3",
        "genset_no" => "number3",
        "hubo_start" => "number",
        "hubo_end" => "number",
        "runtime" => "number",
        "no_of_trips" => "number",
        "rate_charge" => "number",
        "dollar_conversion" => "number",
        "trip_receipt_empty" => "number",
        "truck_empty" => "number3",
    ];
}

/**
 * Column labels for the on-screen RAW preview. Same columns and order as the
 * export (raw_billing_columns), but the spreadsheet's blank/duplicated headers
 * (merged banners there) get real labels so an HTML table stays readable, and
 * CUSTOMER is dropped (one customer is already selected in the filter).
 */
function raw_billing_preview_columns(): array
{
    $labels = array_merge(raw_billing_columns(), [
        "genset_start" => "EQUIPMENT RENTAL START",
        "genset_end" => "EQUIPMENT RENTAL END",
        "rental_days" => "RENTAL HR:MN",
        "runtime_hrmn" => "RUNTIME HR:MN",
    ]);
    unset($labels["customer"]);
    return $labels;
}

/** Excel serial -> readable text (inverse of raw_billing_serial_date/datetime). */
function raw_billing_serial_to_text(float $serial, bool $withTime): string
{
    return gmdate($withTime ? "Y-m-d H:i" : "Y-m-d", (int) round(($serial - 25569) * 86400));
}

/**
 * Flatten built RAW rows for on-screen preview: Excel serials become readable
 * dates and numerics get plain formatting, so every column is a display string
 * keyed by raw_billing_preview_columns(). Returns ['rows','entry_ids','total'].
 */
function raw_billing_display_rows(array $rows): array
{
    $types = raw_billing_column_types();
    $keys = array_keys(raw_billing_columns());
    $out = [];
    $entryIds = [];
    $total = 0.0;

    foreach ($rows as $row) {
        $values = is_array($row["values"] ?? null) ? $row["values"] : [];
        $display = [];

        foreach ($keys as $key) {
            $value = $values[$key] ?? "";
            if ($value === null || $value === "") {
                $display[$key] = "";
                continue;
            }
            $type = $types[$key] ?? "text";
            if ($type === "date" || $type === "datetime") {
                $display[$key] = raw_billing_serial_to_text((float) $value, $type === "datetime");
            } elseif ($type === "number") {
                // Money keeps 2dp + separators; counts/meters drop trailing zeros.
                $display[$key] = $key === "rate_charge"
                    ? number_format((float) $value, 0)
                    : rtrim(rtrim(number_format((float) $value, 2, ".", ""), "0"), ".");
            } else {
                $display[$key] = (string) $value;
            }
        }

        $out[] = $display;
        $entryIds[] = (int) ($row["entry_id"] ?? 0);
        if (is_numeric($values["rate_charge"] ?? null)) {
            $total += (float) $values["rate_charge"];
        }
    }

    return ["rows" => $out, "entry_ids" => $entryIds, "total" => round($total, 2)];
}

/**
 * Derive a profit center [code, name] from segment + customer. Best-effort map
 * for the reefer-container RV segments; falls back to the segment name when the
 * mapping is unknown so the export still groups sensibly.
 */
function raw_billing_profit_center(string $segment, string $customer): array
{
    $segKey = strtoupper(trim($segment));
    $custKey = strtoupper(trim($customer));

    // Reefer container profit centers (from the profit_center master data).
    $map = [
        "DOLERV" => ["3200010010", "HAUL REEF CON DOLE"],
        "SUMIRV" => ["3200010020", "HAUL REEF CON SUMI"],
    ];

    if (isset($map[$segKey])) {
        return $map[$segKey];
    }

    // ABC reefer is split by location; resolve via customer when possible.
    if ($segKey === "ABCRV") {
        $abc = [
            "ABC LUPON" => ["3200010040", "HAUL REEF CON LUP"],
            "ABC PANTUKAN" => ["3200010050", "HAUL REEF CON PANT"],
            "ABC DON MARCELINO" => ["3200010060", "HAUL REEF CON DONMAR"],
            "ABC CATEEL" => ["3200010070", "HAUL REEF CON CATEEL"],
        ];
        foreach ($abc as $name => $pc) {
            if (strpos($custKey, $name) !== false) {
                return $pc;
            }
        }
        return ["3200010000", "HAUL REEF CON COMMON"];
    }

    // Unknown mapping: group under the raw segment label.
    return ["", $segment !== "" ? $segment : "UNMAPPED"];
}

function raw_billing_norm($value): string
{
    return trim((string) ($value ?? ""));
}

/**
 * Strip everything except digits, e.g. "PM800" -> "800", "GS739" -> "739".
 * Falls back to the original trimmed value when it contains no digits.
 */
function raw_billing_digits($value): string
{
    $text = raw_billing_norm($value);
    if ($text === "") {
        return "";
    }
    $digits = preg_replace('/\D+/', "", $text);
    return $digits !== "" ? $digits : $text;
}

function raw_billing_first_non_empty(...$values): string
{
    foreach ($values as $value) {
        $text = raw_billing_norm($value);
        if ($text !== "") {
            return $text;
        }
    }
    return "";
}

function raw_billing_serial_date(?string $date): ?float
{
    $date = raw_billing_norm($date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $date) || str_starts_with($date, "0000-")) {
        return null;
    }
    $timestamp = strtotime(substr($date, 0, 10) . " 00:00:00 UTC");
    if ($timestamp === false) {
        return null;
    }
    $serial = ($timestamp / 86400) + 25569;
    return $serial > 0 ? $serial : null;
}

function raw_billing_serial_datetime(?string $date, ?string $time): ?float
{
    $date = raw_billing_norm($date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $date) || str_starts_with($date, "0000-")) {
        return null;
    }
    $timePart = raw_billing_norm($time);
    $timestamp = strtotime(substr($date, 0, 10) . " " . ($timePart !== "" ? $timePart : "00:00:00") . " UTC");
    if ($timestamp === false) {
        return null;
    }
    $serial = ($timestamp / 86400) + 25569;
    return $serial > 0 ? $serial : null;
}

/**
 * Price ONE RV record EXACTLY as its per-customer SAP invoice does, so the RAW
 * export reconciles with the SAP billing line-for-line. Returns
 *   ['rate_charge' => ?float, 'forex' => ?float]
 * where rate_charge is the SAP "Price" cell = (PHP rate / forex) * scale
 *   - USD customers (Sumifru): scale 1000, forex = the dollar conversion
 *   - PHP customers:           scale 1,    forex = null (DOLLAR CONVERTION blank)
 * and forex is the value shown in the RAW DOLLAR CONVERTION column.
 *
 * The PHP rate is resolved through the SAME path as the SAP builder:
 *   - Sumifru (pricing = lane_tier)  -> sumifru_resolve_rate (lane + fuel tier)
 *   - matrix customers               -> the fuel rate matrix (dashboard_price_trip)
 * This fixes the old behaviour where the RAW priced Sumifru through the generic
 * matrix and disagreed with the SAP invoice.
 *
 * $lookup    = dashboard_matrix_lookup($conn) (loaded once by the caller)
 * $customers = box_banana_customers_config()  (loaded once by the caller)
 */
function raw_billing_price(PDO $conn, array $lookup, array $customers, array $record): array
{
    $none = ["rate_charge" => null, "forex" => null];

    // Price on the BILLING trip date (COALESCE(waybill_date, delivery_departure)), the same
    // basis the rate summary / SAP use, so the RAW RATE CHARGES match the summary's charged
    // rate (otherwise a month-boundary trip prices in a different fuel period here).
    $tripDate = substr(raw_billing_first_non_empty(
        $record["waybill_date"] ?? "",
        $record["loaded_van_delivery_departure_date"] ?? "",
        $record["loaded_van_loading_start_date"] ?? "",
        $record["pullout_location_departure_date"] ?? "",
        $record["date_hauled"] ?? "",
        substr((string) ($record["created_date"] ?? ""), 0, 10)
    ), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tripDate)) {
        return $none;
    }

    // Trip-shaped array for the same customer matcher the dashboard uses.
    $trip = [
        "entry_type" => "RV ENTRY",
        "segment" => $record["segment"] ?? "",
        "customer_ph" => $record["customer_ph"] ?? "",
        "shipper" => $record["shipper"] ?? "",
        "operations_ph" => $record["operations_ph"] ?? "",
    ];

    $seenMatrixKey = [];
    foreach ($customers as $key => $customer) {
        $matrixKey = box_banana_matrix_key($key, $customer);
        if ($matrixKey === "" || isset($seenMatrixKey[$matrixKey])) {
            continue;
        }
        $seenMatrixKey[$matrixKey] = true;

        if (!dashboard_customer_matches($trip, $customer)) {
            continue;
        }

        $currency = strtoupper((string) ($customer["document_currency"] ?? "PHP"));
        $pricing = (string) ($customer["pricing"] ?? "");
        $dcode = box_banana_first_non_empty($record["destination"] ?? "", $record["delivered_to"] ?? "");

        // Resolve the PHP rate the SAME way the SAP invoice does for this customer.
        if ($pricing === "lane_tier") {
            $rate = (float) sumifru_resolve_rate($conn, [
                "delivered_to" => $record["delivered_to"] ?? "",
                "destination" => $record["destination"] ?? "",
                "empty_pullout_location" => $record["empty_pullout_location"] ?? "",
            ], $tripDate)["rate"];
        } else {
            if (empty($lookup["lanes"][$matrixKey])) {
                continue; // only matrix-configured customers can be priced
            }
            $fuelSource = (string) ($customer["fuel_source"] ?? "");
            // Price the SAME way the SAP invoice + rate summary do (resolve_lane_rate), NOT the
            // dashboard's in-memory port — otherwise the RAW RATE CHARGES resolve a different
            // fuel period/rate and the preview subtotal won't equal the summary's rate × trips.
            $resolved = function_exists("resolve_lane_rate")
                ? resolve_lane_rate($conn, $matrixKey, $dcode, $dcode, $tripDate, $fuelSource)
                : ["matched" => false, "rate" => 0.0];
            if (empty($resolved["matched"])) {
                continue; // try the next configured customer
            }
            $rate = (float) $resolved["rate"];
        }

        if ($rate <= 0) {
            return $none;
        }

        // SAP "Price" cell = (rate / forex) * scale. USD → per-1000 (scale 1000);
        // PHP → flat (scale 1, no forex).
        $forex = $currency === "USD" ? billing_forex_rate($conn, $tripDate, $key) : 0.0;
        return [
            "rate_charge" => round($rate, 0),
            "forex" => $forex > 0 ? round($forex, 3) : null,
        ];
    }

    return $none;
}

/**
 * The trip/hauling date the export's date range filters on. Deliberately the SAME
 * expression the SAP billing, the activity billing and the dashboard use
 * (build_box_banana_billing.php) so a date range means one thing system-wide.
 *
 * Changed 2026-07-16 (was `created_date::date`, the data-entry timestamp — when the
 * record was typed in, not when the truck ran). That made a one-day export return
 * trips hauled weeks earlier and put the RAW file out of step with the invoice.
 */
function raw_billing_trip_date_expr(): string
{
    return "COALESCE(loaded_van_loading_start_date, pullout_location_departure_date, "
        . "date_hauled, waybill_date, created_date::date)";
}

/** The operations columns every RAW row is shaped from (reefer-RV specific). */
function raw_billing_select_list(): string
{
    return "entry_id, segment, customer_ph, shipper, operations_ph, ph,
            waybill, waybill_empty, waybill_date, date_hauled, tr,
            loaded_van_loading_start_date, loaded_van_delivery_departure_date, pullout_location_departure_date,
            prime_mover, delivered_by_prime_mover, truck, truck2,
            van_alpha, van_number, van_name, size,
            total_trips, total_load, load_description,
            ecs, gs, dr_no, slp_no, booking,
            empty_pullout_location, pullout_location, destination, delivered_to, return_location,
            eir_in, eir_out,
            genset_start_date, genset_start_time, genset_end_date, genset_end_time,
            pullout_location_departure_time, end_uploading_date, end_uploading_time,
            genset_hr_meter_start, genset_hr_meter_end, genset_hr_meter, refueled,
            created_date";
}

/**
 * Shape ONE operations record into a RAW row. Shared by raw_billing_fetch_rows_by_ids
 * so the Billing preview and the per-invoice RAW download can render the SAP pipeline's
 * OWN records in the RAW columns.
 */
function raw_billing_shape_record(PDO $conn, array $record, array $rateLookup, array $rateCustomers, array $manualForexByEntry = [], array $chargeOverrideByEntry = []): array
{
    $customerName = raw_billing_first_non_empty(
        $record["customer_ph"] ?? "",
        $record["shipper"] ?? "",
        $record["operations_ph"] ?? ""
    );
    [$pcCode, $pcName] = raw_billing_profit_center(
        raw_billing_norm($record["segment"] ?? ""),
        $customerName
    );

    // EQUIPMENT RENTAL START / END use the SAME data as the transmittal's RV Withdrawal
    // and Unloading (build_records_xlsx.php, $isRv branch):
    //   Withdrawal of Vans = pullout_location_departure_date/time
    //   Van Unloading      = end_uploading_date/time  (the form's "END OF UNLOADING")
    $gensetStart = raw_billing_serial_datetime($record["pullout_location_departure_date"] ?? "", $record["pullout_location_departure_time"] ?? "");
    $gensetEnd = raw_billing_serial_datetime($record["end_uploading_date"] ?? "", $record["end_uploading_time"] ?? "");
    $rentalSpanDays = ($gensetStart !== null && $gensetEnd !== null && $gensetEnd >= $gensetStart)
        ? ($gensetEnd - $gensetStart)
        : null;
    $rentalHours = $rentalSpanDays !== null ? round($rentalSpanDays * 24, 2) : null;
    // RENTAL HR:MN shown as hours:minutes (e.g. 85:20) in BOTH the preview and the export.
    $rentalHrMn = "";
    if ($rentalSpanDays !== null) {
        $totalMin = (int) round($rentalSpanDays * 1440);
        $rentalHrMn = intdiv($totalMin, 60) . ":" . str_pad((string) ($totalMin % 60), 2, "0", STR_PAD_LEFT);
    }

    $huboStart = $record["genset_hr_meter_start"];
    $huboEnd = $record["genset_hr_meter_end"];
    $runtime = ($huboStart !== null && $huboEnd !== null && is_numeric($huboStart) && is_numeric($huboEnd))
        ? round((float) $huboEnd - (float) $huboStart, 2)
        : null;

    $values = [
        "customer" => $customerName,
        "profit_center" => $pcCode !== "" ? $pcCode : $pcName,
        "hauling_date" => raw_billing_serial_date(raw_billing_first_non_empty(
            $record["waybill_date"] ?? "",
            $record["date_hauled"] ?? "",
            $record["loaded_van_loading_start_date"] ?? "",
            $record["pullout_location_departure_date"] ?? "",
            substr((string) ($record["created_date"] ?? ""), 0, 10)
        )),
        "trip_receipt" => raw_billing_digits($record["waybill"] ?? ""),
        // Loaded trip uses the delivery PM, then the original truck as fallback.
        "truck" => raw_billing_digits(raw_billing_first_non_empty($record["delivered_by_prime_mover"] ?? "", $record["truck"] ?? "")),
        "trip_receipt_empty" => raw_billing_digits($record["waybill_empty"] ?? ""),
        // Empty trip uses the original PM, then truck2 as fallback.
        "truck_empty" => raw_billing_digits(raw_billing_first_non_empty($record["prime_mover"] ?? "", $record["truck2"] ?? "")),
        "ph" => raw_billing_norm($record["ph"] ?? ""),
        "van_size" => raw_billing_norm($record["size"] ?? ""),
        "alpha" => raw_billing_norm($record["van_alpha"] ?? ""),
        "van_number" => raw_billing_digits($record["van_number"] ?? ""),
        "shipping_line" => raw_billing_norm($record["van_name"] ?? ""),
        "no_of_trips" => is_numeric($record["total_trips"] ?? null) ? (float) $record["total_trips"] : null,
        "load" => raw_billing_first_non_empty($record["total_load"] ?? "", $record["load_description"] ?? ""),
        "ecs" => raw_billing_norm($record["ecs"] ?? ""),
        "pullout_location" => raw_billing_norm($record["empty_pullout_location"] ?? ""),
        "delivered_to" => raw_billing_first_non_empty($record["delivered_to"] ?? "", $record["return_location"] ?? ""),
        "eir_in" => raw_billing_norm($record["eir_in"] ?? ""),
        "eir_out" => raw_billing_norm($record["eir_out"] ?? ""),
        "genset_start" => $gensetStart,
        "genset_end" => $gensetEnd,
        "rental_days" => $rentalHrMn,
        "rental_hours" => $rentalHours,
        "chassis" => billing_trailer_label($record["tr"] ?? ""),
        "genset_no" => raw_billing_digits($record["gs"] ?? ""),
        "hubo_start" => is_numeric($huboStart) ? (float) $huboStart : null,
        "hubo_end" => is_numeric($huboEnd) ? (float) $huboEnd : null,
        "start2" => "",
        "end3" => "",
        "runtime_hrmn" => "",
        "time" => "",
        "runtime" => $runtime,
        "fuel" => raw_billing_norm($record["refueled"] ?? ""),
        "dr_no" => raw_billing_first_non_empty($record["dr_no"] ?? "", $record["slp_no"] ?? ""),
        "bl_number" => raw_billing_norm($record["booking"] ?? ""),
        "van_volume" => "",
    ];

    // RATE CHARGES + DOLLAR CONVERTION mirror the customer's SAP invoice:
    // rate_charge = the SAP Price cell, dollar_conversion = the forex used.
    $priceInfo = raw_billing_price($conn, $rateLookup, $rateCustomers, $record);
    $values["dollar_conversion"] = $priceInfo["forex"] !== null ? (string) $priceInfo["forex"] : "";
    $manualForex = (float) ($manualForexByEntry[(int) ($record["entry_id"] ?? 0)] ?? 0);
    if ($manualForex > 0) {
        $values["dollar_conversion"] = (string) $manualForex;
    }
    $values["rate_charge"] = $priceInfo["rate_charge"];
    // A LOCKED / hand-edited charge from the invoice wins over the recomputed one, so
    // the RAW export matches the Charges modal + (re)generated SAP file.
    $overrideId = (int) ($record["entry_id"] ?? 0);
    if (array_key_exists($overrideId, $chargeOverrideByEntry) && is_numeric($chargeOverrideByEntry[$overrideId])) {
        $values["rate_charge"] = (float) $chargeOverrideByEntry[$overrideId];
    }

    return [
        "entry_id" => (int) $record["entry_id"],
        "customer" => $customerName,
        "profit_center" => $values["profit_center"],
        "values" => $values,
        "types" => raw_billing_column_types(),
    ];
}

/**
 * Shape an EXPLICIT list of entry_ids into RAW rows, preserving the given order.
 *
 * The Billing preview and the per-invoice RAW download pass in the SAP pipeline's
 * OWN entry_ids so the RAW columns cover EXACTLY the records that will be billed. A
 * date/customer RAW query would select a different set (RAW would range over
 * created_date and match customer free-text, vs the SAP side's real-trip-date +
 * segment filter), which previously made the preview disagree with the invoice.
 *
 * No entry_type / date / customer predicate: the caller has already decided the set.
 */
function raw_billing_fetch_rows_by_ids(PDO $conn, array $entryIds, array $manualForexByEntry = [], array $chargeOverrideByEntry = []): array
{
    $ids = array_values(array_unique(array_filter(array_map("intval", $entryIds), fn($v) => $v > 0)));
    if (empty($ids)) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT " . raw_billing_select_list() . " FROM operations
         WHERE entry_id IN (" . implode(",", array_fill(0, count($ids), "?")) . ")"
    );
    $stmt->execute($ids);

    $rateLookup = dashboard_matrix_lookup($conn);
    $rateCustomers = box_banana_customers_config();

    $byId = [];
    while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $byId[(int) $record["entry_id"]] = raw_billing_shape_record($conn, $record, $rateLookup, $rateCustomers, $manualForexByEntry, $chargeOverrideByEntry);
    }

    // Preserve the caller's order (= the invoice's line order).
    $rows = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $rows[] = $byId[$id];
        }
    }
    return $rows;
}

// ---------------------------------------------------------------------------
// XLSX writer (from scratch, inline strings, grouped banner rows)
// ---------------------------------------------------------------------------

function raw_billing_col_letter(int $index): string
{
    $letters = "";
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $letters = chr(65 + $mod) . $letters;
        $index = (int) (($index - $mod) / 26);
    }
    return $letters;
}

function raw_billing_xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, "UTF-8");
}

function raw_billing_write_xlsx(array $rows, string $outputPath, string $dateFrom, string $dateTo): void
{
    if (!class_exists("ZipArchive")) {
        throw new RuntimeException("ZipArchive extension is required.");
    }

    $columns = raw_billing_columns();
    $columnKeys = array_keys($columns);
    $colCount = count($columnKeys);
    $lastCol = raw_billing_col_letter($colCount);

    // Style indices (see styles.xml below):
    //   1 = title, 2 = header, 3 = banner, 4 = date, 5 = datetime, 6 = number,
    //   7 = text, 8 = three-digit number, 9 = seven-digit number
    $sheetRows = [];
    $rowNum = 0;

    $cellXml = static function (string $ref, $value, string $type, int $style): string {
        if ($type === "date" || $type === "datetime" || $type === "number" || $type === "number3" || $type === "number7") {
            if ($value === null || $value === "") {
                return '<c r="' . $ref . '" s="' . $style . '"/>';
            }
            $num = rtrim(rtrim(number_format((float) $value, 8, ".", ""), "0"), ".");
            if ($num === "" || $num === "-") {
                $num = "0";
            }
            return '<c r="' . $ref . '" s="' . $style . '"><v>' . $num . "</v></c>";
        }
        $text = raw_billing_norm($value);
        if ($text === "") {
            return '<c r="' . $ref . '" s="' . $style . '"/>';
        }
        return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">'
            . raw_billing_xml_escape($text) . "</t></is></c>";
    };

    // Title row
    $rowNum++;
    $title = "RAW FOR BILLING   |   " . $dateFrom . " to " . $dateTo;
    $sheetRows[] = '<row r="' . $rowNum . '" ht="22" customHeight="1">'
        . $cellXml("A" . $rowNum, $title, "string", 1) . "</row>";
    $titleRowNum = $rowNum;

    // Header row
    $rowNum++;
    $headerCells = "";
    $colIndex = 0;
    foreach ($columns as $key => $label) {
        $colIndex++;
        $ref = raw_billing_col_letter($colIndex) . $rowNum;
        $headerCells .= $cellXml($ref, $label !== "" ? $label : " ", "string", 2);
    }
    $sheetRows[] = '<row r="' . $rowNum . '" ht="26" customHeight="1">' . $headerCells . "</row>";

    $merges = ['A' . $titleRowNum . ':' . $lastCol . $titleRowNum];

    // Data rows, with a banner whenever customer + profit center changes.
    $currentGroup = null;
    foreach ($rows as $row) {
        $groupKey = $row["customer"] . "||" . $row["profit_center"];
        if ($groupKey !== $currentGroup) {
            $currentGroup = $groupKey;
            $rowNum++;
            $banner = "CUSTOMER: " . ($row["customer"] !== "" ? $row["customer"] : "-")
                . "      PROFIT CENTER: " . ($row["profit_center"] !== "" ? $row["profit_center"] : "-");
            $sheetRows[] = '<row r="' . $rowNum . '" ht="20" customHeight="1">'
                . $cellXml("A" . $rowNum, $banner, "string", 3) . "</row>";
            $merges[] = "A" . $rowNum . ":" . $lastCol . $rowNum;
        }

        $rowNum++;
        $cells = "";
        $colIndex = 0;
        foreach ($columnKeys as $key) {
            $colIndex++;
            $ref = raw_billing_col_letter($colIndex) . $rowNum;
            $type = $row["types"][$key] ?? "string";
            $value = $row["values"][$key] ?? null;
            if ($type === "date") {
                $style = 4;
            } elseif ($type === "datetime") {
                $style = 5;
            } elseif ($type === "number") {
                $style = 6;
            } elseif ($type === "number3") {
                $style = 8;
            } elseif ($type === "number7") {
                $style = 9;
            } else {
                $style = 7;
            }
            $cells .= $cellXml($ref, $value, $type, $style);
        }
        $sheetRows[] = '<row r="' . $rowNum . '">' . $cells . "</row>";
    }

    $dimension = "A1:" . $lastCol . max($rowNum, 2);

    // Column widths
    $colsXml = "<cols>";
    $colIndex = 0;
    foreach ($columns as $key => $label) {
        $colIndex++;
        $width = max(12, min(28, strlen($label) + 2));
        if ($key === "customer" || $key === "profit_center") {
            $width = 22;
        }
        $colsXml .= '<col min="' . $colIndex . '" max="' . $colIndex . '" width="' . $width . '" customWidth="1"/>';
    }
    $colsXml .= "</cols>";

    $mergeXml = '<mergeCells count="' . count($merges) . '">';
    foreach ($merges as $merge) {
        $mergeXml .= '<mergeCell ref="' . $merge . '"/>';
    }
    $mergeXml .= "</mergeCells>";

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="' . $dimension . '"/>'
        . '<sheetViews><sheetView tabSelected="1" workbookViewId="0">'
        . '<pane ySplit="2" topLeftCell="A3" activePane="bottomLeft" state="frozen"/>'
        . '<selection pane="bottomLeft" activeCell="A3" sqref="A3"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . $colsXml
        . "<sheetData>" . implode("", $sheetRows) . "</sheetData>"
        . $mergeXml
        . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
        . "</worksheet>";

    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="4">'
        . '<numFmt numFmtId="166" formatCode="mm/dd/yyyy"/>'
        . '<numFmt numFmtId="167" formatCode="mm/dd/yyyy\ h:mm"/>'
        . '<numFmt numFmtId="168" formatCode="000"/>'
        . '<numFmt numFmtId="169" formatCode="0000000"/>'
        . "</numFmts>"
        . '<fonts count="4">'
        . '<font><sz val="10"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="13"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="10"/><color rgb="FF0F172A"/><name val="Calibri"/></font>'
        . "</fonts>"
        . '<fills count="5">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF0F172A"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/><bgColor indexed="64"/></patternFill></fill>'
        . "</fills>"
        . '<borders count="2">'
        . "<border><left/><right/><top/><bottom/><diagonal/></border>"
        . '<border><left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right>'
        . '<top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom><diagonal/></border>'
        . "</borders>"
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="10">'
        // 0 default
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        // 1 title
        . '<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
        // 2 header
        . '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        // 3 banner
        . '<xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
        // 4 date
        . '<xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>'
        // 5 datetime
        . '<xf numFmtId="167" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>'
        // 6 number
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>'
        // 7 text
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
        // 8 three-digit number
        . '<xf numFmtId="168" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>'
        // 9 seven-digit number
        . '<xf numFmtId="169" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>'
        . "</cellXfs>"
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . "</styleSheet>";

    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="RAW" sheetId="1" r:id="rId1"/></sheets></workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . "</Relationships>";

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . "</Types>";

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . "</Relationships>";

    @unlink($outputPath);
    $zip = new ZipArchive();
    if ($zip->open($outputPath, ZipArchive::CREATE) !== true) {
        throw new RuntimeException("Failed to create xlsx file.");
    }
    $zip->addFromString("[Content_Types].xml", $contentTypes);
    $zip->addFromString("_rels/.rels", $rootRels);
    $zip->addFromString("xl/workbook.xml", $workbookXml);
    $zip->addFromString("xl/_rels/workbook.xml.rels", $workbookRels);
    $zip->addFromString("xl/styles.xml", $stylesXml);
    $zip->addFromString("xl/worksheets/sheet1.xml", $sheetXml);
    $zip->close();
}
