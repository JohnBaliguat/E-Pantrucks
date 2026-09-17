<?php

require_once __DIR__ . "/ensure_operations_emdr_schema.php";

/**
 * Build the Records-export .xlsx file at $outputPath, applying the given
 * filters. Returns metadata including the included entry_ids so callers
 * (transmittal generator) can persist them.
 *
 * Returns:
 *   [
 *     'record_count' => int,
 *     'file_size'    => int,
 *     'entry_ids'    => int[],
 *   ]
 *
 * Throws RuntimeException on any build failure.
 */
function build_records_xlsx(
    PDO $conn,
    string $dateFrom,
    string $dateTo,
    string $entryType,
    string $customer,
    array $excludeEntryIds,
    string $outputPath,
    string $createdBy = "",
    array $entryOrder = [],
    string $transmittalDate = ""
): array {
    ensure_operations_emdr_schema($conn);

    if (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ||
        $dateFrom > $dateTo
    ) {
        throw new RuntimeException("Invalid date range.");
    }

    if (!class_exists("ZipArchive")) {
        throw new RuntimeException("ZipArchive extension is required.");
    }

    $generatedAtDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $transmittalDate)
        ? $transmittalDate
        : (new DateTimeImmutable("now", new DateTimeZone("Asia/Manila")))->format("Y-m-d");

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

    $excludeSql = "";
    $excludeIds = array_values(array_unique(array_filter(array_map('intval', $excludeEntryIds), fn($v) => $v > 0)));
    if (!empty($excludeIds)) {
        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $excludeSql = " AND entry_id NOT IN ($placeholders)";
        foreach ($excludeIds as $id) {
            $params[] = $id;
        }
    }

    $createdBySql = "";
    if ($createdBy !== "") {
        $createdBySql = " AND created_by = ?";
        $params[] = $createdBy;
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
    empty_trip_receipt_date,
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
    pullout_date,
    pullout_time,
    eir_in,
    eir_out,
    \"eir_outDate\",
    \"eir_outTime\",
    date_hauled,
    date_unloaded,
    date_returned,
    departure_time,
    arrival_time,
    time_unloaded,
    segment,
    activity,
    deliver_from,
    pullout_location_departure_date,
    pullout_location_departure_time,
    loaded_van_delivery_arrival_date,
    loaded_van_delivery_arrival_time,
    loaded_van_loading_start_date,
    loaded_van_loading_start_time,
    loaded_van_loading_finish_date,
    loaded_van_loading_finish_time,
    end_uploading_date,
    end_uploading_time,
    fgtr_no,
    dr_no,
    slp_no,
    booking,
    shipment_no,
    vessel_name,
    voyage_no,
    emdr_no,
    return_location,
    load_description,
    delivered_to,
    total_trips,
    remarks,
    delivered_remarks,
    kms,
    billing_sku,
    ph_departure_date,
    ph_departure_time,
    wharf_arrival_date,
    wharf_arrival_time,
    load_quantity_weight,
    delivered_by_prime_mover,
    delivered_by_driver,
    unit_of_measure,
    total_load,
    reference_documents,
    size,
    commodity,
    destination,
    genset_hr_meter_start,
    genset_hr_meter_end,
    genset_start_date,
    genset_start_time,
    genset_end_date,
    genset_end_time,
    created_by,
    created_date,
    modified_date
FROM operations
WHERE created_date::date BETWEEN ? AND ?" . $entryTypeSql . $customerSql . $excludeSql . $createdBySql . "
ORDER BY
    COALESCE(NULLIF(created_by, ''), 'ZZZ') ASC,
    entry_type ASC,
    COALESCE(NULLIF(customer_ph, ''), NULLIF(shipper, ''), NULLIF(operations_ph, '')) ASC,
    COALESCE(NULLIF(billing_sku, ''), '') ASC,
    COALESCE(waybill_date::text, created_date::text) ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $rows = [];
    $entryIds = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $entryIds[] = (int) $row["entry_id"];
        $entryTypeValue = build_records_normalize_text($row["entry_type"] ?? "");
        $isDryVan = $entryTypeValue === "DRY VAN ENTRY";
        $isOthers = strcasecmp($entryTypeValue, "OTHERS ENTRY") === 0;
        $isRv = $entryTypeValue === "RV ENTRY";
        $isDpc = $entryTypeValue === "DPC_KDs & OPM ENTRY";
        // Dry Van for customer "CITIHARDWARE IMPORTS" has special column mapping:
        //   T  (DR No./Fleet No/TCARD) -> Shipment No.
        //   X  (Delivered To)          -> Return Location
        //   Z  (Remarks)               -> BL No. (Booking)
        //   AF (Van Size)              -> blank
        $isCithImport = $isDryVan
            && strcasecmp(build_records_normalize_text($row["customer_ph"] ?? ""), "CITIHARDWARE IMPORTS") === 0;
        // Every import-layout Dry Van customer (not just CITIHARDWARE) reports the
        // Return Location in column X.
        $isDryVanImport = $isDryVan && build_records_is_dryvan_import_customer($row["customer_ph"] ?? "");
        // CITIHARDWARE DOMESTIC also reports the Return Location in column X
        // (like the import-layout customers), not the Delivery Location.
        $isCithDomestic = $isDryVan
            && strcasecmp(build_records_normalize_text($row["customer_ph"] ?? ""), "CITIHARDWARE DOMESTIC") === 0;
        // TPD dry van captures a dedicated pull-out date/time; those feed the
        // "Date and Time of Withdrawal of Van" column instead of EIR Out.
        $isTpdDryVan = $isDryVan && in_array(
            strtoupper(build_records_normalize_text($row["customer_ph"] ?? "")),
            ["TPD DRYVAN IMPORT", "TPD DRYVAN EXPORT"],
            true
        );
        // Dry Van for customer "PSACC DOMESTIC" shifts the trailing columns left:
        //   AF (Van Size) -> AE (Volume)
        //   AP (Vessel)   -> AF (Van Size)
        //   AQ (Voyage)   -> AH (HR:MN)
        //   emdr_no       -> AI (moved next to HR:MN)
        // AP (Vessel) and AQ (Voyage) keep their original values too.
        // (AG is the Commodity column, inserted after Van Size.)
        $isPsaccDomestic = $isDryVan
            && strcasecmp(build_records_normalize_text($row["customer_ph"] ?? ""), "PSACC DOMESTIC") === 0;
        if ($isDryVan) {
            // Import customers capture the BL No. in "booking"; domestic/export ones
            // capture "destination" instead. A row never carries both, so take
            // whichever is filled.
            $remarks = build_records_first_non_empty($row["booking"] ?? "", $row["destination"] ?? "");
        } else {
            $remarks = build_records_first_non_empty($row["remarks"] ?? "", $row["delivered_remarks"] ?? "", $row["reference_documents"] ?? "", $row["booking"] ?? "");
        }

        $customerOrShipper = build_records_first_non_empty(
            $row["customer_ph"] ?? "",
            $row["shipper"] ?? "",
            $row["operations_ph"] ?? ""
        );

        if ($entryTypeValue === "DPC_KDs & OPM ENTRY") {
            $customerOrShipper = "DPC";
        }

        if ($isDryVan) {
            $transactionDate = build_records_excel_serial_date(build_records_normalize_text($row["date_hauled"] ?? ""));
        } elseif ($isOthers) {
            $transactionDate = build_records_excel_serial_date(build_records_normalize_text($row["waybill_date"] ?? ""));
        } elseif ($isRv) {
            // RV Transaction Date = the LOADED leg's Trip Receipt Date, stored in
            // waybill_date. Falls back to the legacy genset-end date for older rows.
            $transactionDate = build_records_excel_serial_date(build_records_first_non_empty(
                $row["waybill_date"] ?? "",
                $row["genset_end_date"] ?? ""
            ));
        } else {
            $transactionDate = build_records_excel_serial_date(build_records_normalize_text($row["genset_end_date"] ?? $row["waybill_date"] ?? ""));
        }

        if ($isDryVan) {
            // Withdrawal of the van = the pull-out. TPD records a dedicated
            // pullout_date/pullout_time; use those. Every other dry van customer
            // keeps the EIR Out date/time (unchanged).
            $pulloutDate = build_records_normalize_text($row["pullout_date"] ?? "");
            if ($isTpdDryVan && $pulloutDate !== "") {
                $withdrawalDateTime = build_records_excel_serial_datetime(
                    $pulloutDate,
                    build_records_normalize_text($row["pullout_time"] ?? "")
                );
            } else {
                $withdrawalDateTime = build_records_excel_serial_datetime(
                    build_records_normalize_text($row["eir_outDate"] ?? ""),
                    build_records_normalize_text($row["eir_outTime"] ?? "")
                );
            }
        } elseif ($isOthers) {
            $withdrawalDateTime = build_records_excel_serial_datetime(build_records_normalize_text($row["waybill_date"] ?? ""), "");
        } elseif ($isRv) {
            $withdrawalDateTime = build_records_excel_serial_datetime(
                build_records_normalize_text($row["pullout_location_departure_date"] ?? ""),
                build_records_normalize_text($row["pullout_location_departure_time"] ?? "")
            );
        } else {
            $withdrawalDateTime = build_records_excel_serial_datetime(
                build_records_first_non_empty($row["pullout_location_departure_date"] ?? "", substr((string) ($row["created_date"] ?? ""), 0, 10), $row["waybill_date"] ?? ""),
                build_records_first_non_empty($row["pullout_location_departure_time"] ?? "", substr((string) ($row["created_date"] ?? ""), 11, 8))
            );
        }

        $pmValue = ($isDryVan || $isOthers)
            ? build_records_first_non_empty($row["truck"] ?? "")
            : build_records_first_non_empty($row["prime_mover"] ?? "", $row["truck"] ?? "");

        if ($isDryVan) {
            $pulloutLocation = $row["pullout_location"] ?? "";
        } elseif ($isOthers) {
            $pulloutLocation = $row["deliver_from"] ?? "";
        } elseif ($isDpc) {
            $pulloutLocation = "TPD";
        } else {
            $pulloutLocation = $row["empty_pullout_location"] ?? "";
        }

        if ($isDryVan) {
            // Dry van unloading time now comes from the dedicated time_unloaded
            // column (paired with date_unloaded). Legacy arrival_time only ever
            // held a default 00:00:00, so it is not used as a fallback here.
            $unloadingDateTime = build_records_excel_serial_datetime(
                build_records_normalize_text($row["date_unloaded"] ?? ""),
                build_records_normalize_text($row["time_unloaded"] ?? "")
            );
        } elseif ($isRv) {
            $unloadingDateTime = build_records_excel_serial_datetime(
                substr(build_records_normalize_text($row["end_uploading_date"] ?? ""), 0, 10),
                build_records_normalize_text($row["end_uploading_time"] ?? "")
            );
        } else {
            $phDate = substr(build_records_normalize_text($row["ph_departure_date"] ?? ""), 0, 10);
            $phTime = build_records_normalize_text($row["ph_departure_time"] ?? "");
            $unloadingDateTime = build_records_excel_serial_datetime($phDate, $phTime);
            if ($unloadingDateTime === null) {
                $unloadingDateTime = build_records_excel_serial_datetime(
                    substr(build_records_normalize_text($row["loaded_van_loading_finish_date"] ?? ""), 0, 10),
                    build_records_normalize_text($row["loaded_van_loading_finish_time"] ?? "")
                );
            }
        }

        if ($isDryVan) {
            // Import customers capture a Shipment No.; export/domestic ones an SLPS
            // No. A row never carries both, so take whichever is filled.
            $drValue = build_records_first_non_empty($row["shipment_no"] ?? "", $row["slp_no"] ?? "");
        } elseif ($isDpc) {
            $drValue = $row["fgtr_no"] ?? "";
        } else {
            $drValue = $row["dr_no"] ?? "";
        }

        if ($isDryVan) {
            $loadValue = "1";
        } elseif ($isOthers) {
            $qty = build_records_normalize_text($row["load_quantity_weight"] ?? "");
            $uom = build_records_normalize_text($row["unit_of_measure"] ?? "");
            $loadValue = $uom !== "" ? trim($qty . " " . $uom) : $qty;
        } else {
            $loadValue = build_records_first_non_empty($row["load_description"] ?? "", $row["total_load"] ?? "");
        }

        $vanSize = ($isDryVan && !$isCithImport) ? ($row["size"] ?? "") : "";

        $rows[] = [
            "A" => ["value" => $row["entry_type"] ?? "-", "type" => "string"],
            // Transmittal date is a user-selected document date, rendered exactly
            // as MM.DD.YYYY (for example 07.17.2026).
            "B" => ["value" => (new DateTimeImmutable($generatedAtDate))->format("m.d.Y"), "type" => "string"],
            "C" => ["value" => build_records_first_non_empty($row["waybill"] ?? "-"), "type" => "string"],
            "D" => ["value" => $transactionDate, "type" => "date"],
            "E" => ["value" => build_records_display_datetime($withdrawalDateTime), "type" => "string"],
            "F" => ["value" => $row["van_alpha"] ?? "-", "type" => "string"],
            "G" => ["value" => $row["van_number"] ?? "-", "type" => "string"],
            "H" => ["value" => $isDryVan ? build_records_first_non_empty($row["shipper"] ?? "") : ($row["van_name"] ?? "-"), "type" => "string"],
            // PH column: OTHERS uses the customer; DRY VAN sources the delivery
            // location (the "ph" input was retired) and falls back to any stored
            // legacy "ph" value; everything else keeps the "ph" value.
            "I" => ["value" => $isOthers
                ? ($row["customer_ph"] ?? "-")
                : ($isDryVan
                    ? (build_records_first_non_empty($row["delivered_to"] ?? "", $row["ph"] ?? "") ?: "-")
                    : ($row["ph"] ?? "-")), "type" => "string"],
            "J" => ["value" => $customerOrShipper ?? "-", "type" => "string"],
            "K" => ["value" => build_records_first_non_empty($row["waybill_empty"] ?? "-"), "type" => "string"],
            "L" => ["value" => $row["ecs"] ?? "-", "type" => "string"],
            "M" => ["value" => $row["tr"] ?? "-", "type" => "string"],
            "N" => ["value" => $row["gs"] ?? "-", "type" => "string"],
            "O" => ["value" => $pmValue, "type" => "string"],
            "P" => ["value" => $row["driver"] ?? "-", "type" => "string"],
            "Q" => ["value" => $pulloutLocation, "type" => "string"],
            "R" => ["value" => build_records_display_datetime($unloadingDateTime), "type" => "string"],
            "S" => ["value" => $row["waybill"] ?? "-", "type" => "string"],
            "T" => ["value" => $drValue, "type" => "string"],
            "U" => ["value" => $loadValue, "type" => "string"],
            "V" => ["value" => ($isOthers || $isDpc) ? ($row["truck"] ?? "") : build_records_first_non_empty($row["truck2"] ?? "", $row["delivered_by_prime_mover"] ?? ""), "type" => "string"],
            "W" => ["value" => ($isOthers || $isDpc) ? ($row["driver"] ?? "") : build_records_first_non_empty($row["driver_return"] ?? "", $row["delivered_by_driver"] ?? ""), "type" => "string"],
            "X" => ["value" => $isDpc
                ? ($row["ph"] ?? "-")
                : (($isDryVanImport || $isCithDomestic)
                    ? build_records_first_non_empty($row["return_location"] ?? "", $row["delivered_to"] ?? "")
                    : ($row["delivered_to"] ?? "-")), "type" => "string"],
            "Y" => ["value" => $row["total_trips"] ?? 1, "type" => "number"],
            "Z" => ["value" => $remarks ?? "-", "type" => "string"],
            "AA" => ["value" => $row["kms"] ?? "-", "type" => "string"],
            "AB" => ["value" => $row["billing_sku"] ?? "-", "type" => "string"],
            "AC" => ["value" => $row["eir_out"] ?? "-", "type" => "string"],
            "AD" => ["value" => $row["eir_in"] ?? "-", "type" => "string"],
            "AE" => ["value" => $isPsaccDomestic ? ($row["size"] ?? "") : ($isDryVan ? ($isCithImport ? ($row["size"] ?? "") : "") : ($row["size"] ?? "-")), "type" => "string"],
            "AF" => ["value" => $isPsaccDomestic ? ($row["vessel_name"] ?? "") : $vanSize, "type" => "string"],
            // Commodity sits immediately after Van Size; captured on TPD dry van
            // entries and blank for rows/entry types that do not record it.
            "AG" => ["value" => $row["commodity"] ?? "", "type" => "string"],
            "AH" => ["value" => $isPsaccDomestic ? ($row["voyage_no"] ?? "") : "-", "type" => "string"],
            "AI" => ["value" => $isPsaccDomestic ? ($row["emdr_no"] ?? "") : "", "type" => "string"],
            "AJ" => ["value" => "-", "type" => "string"],
            "AK" => ["value" => $row["genset_hr_meter_start"] ?? null, "type" => "number"],
            "AL" => ["value" => $row["genset_hr_meter_end"] ?? null, "type" => "number"],
            "AM" => ["value" => build_records_display_datetime(build_records_excel_serial_datetime($row["genset_start_date"] ?? "", $row["genset_start_time"] ?? "")), "type" => "string"],
            "AN" => ["value" => build_records_display_datetime(build_records_excel_serial_datetime($row["genset_end_date"] ?? "", $row["genset_end_time"] ?? "")), "type" => "string"],
            "AO" => ["value" => (function () use ($withdrawalDateTime, $unloadingDateTime): ?int {
                if ($withdrawalDateTime === null || $unloadingDateTime === null) return null;
                $days = (int) floor($unloadingDateTime - $withdrawalDateTime);
                return $days >= 0 ? $days : null;
            })(), "type" => "number"],
            "AP" => ["value" => $row["vessel_name"] ?? "", "type" => "string"],
            "AQ" => ["value" => $row["voyage_no"] ?? "", "type" => "string"],
        ];
    }

    // Apply the caller-supplied row order, if any. Rows whose entry_id appears
    // in $entryOrder are placed in that sequence; everything else keeps the
    // default SQL order and trails behind. $rows and $entryIds stay aligned.
    $orderPositions = [];
    foreach (array_values($entryOrder) as $position => $orderedId) {
        $orderedId = (int) $orderedId;
        if ($orderedId > 0 && !isset($orderPositions[$orderedId])) {
            $orderPositions[$orderedId] = $position;
        }
    }
    if (!empty($orderPositions)) {
        $fallbackBase = count($orderPositions);
        $sortable = [];
        foreach ($rows as $i => $row) {
            $entryId = $entryIds[$i];
            $sortable[] = [
                "key" => $orderPositions[$entryId] ?? ($fallbackBase + $i),
                "index" => $i,
            ];
        }
        usort($sortable, fn($a, $b) => ($a["key"] <=> $b["key"]) ?: ($a["index"] <=> $b["index"]));
        $orderedRows = [];
        $orderedEntryIds = [];
        foreach ($sortable as $item) {
            $orderedRows[] = $rows[$item["index"]];
            $orderedEntryIds[] = $entryIds[$item["index"]];
        }
        $rows = $orderedRows;
        $entryIds = $orderedEntryIds;
    }

    build_records_write_xlsx($rows, $outputPath, $dateFrom, $dateTo, $entryType, $generatedAtDate);

    return [
        "record_count" => count($rows),
        "file_size" => (int) filesize($outputPath),
        "entry_ids" => $entryIds,
    ];
}

function build_records_normalize_text($value): string
{
    return trim((string) ($value ?? ""));
}

/**
 * Dry Van customers billed on the import layout. These mirror the "importCustomers"
 * set in public/Admin/dryVan.php: their entry form captures the BL No. (booking)
 * and a Return Location, so the export shows the Return Location in column X
 * rather than the Delivered To used by the export/domestic customers.
 */
function build_records_dryvan_import_customers(): array
{
    return [
        "CITIHARDWARE IMPORTS",
        "TPD DRYVAN IMPORT",
        "ECOSSENTIAL - IMPORT",
        "PHIL CEMENT",
        "NOVOCOCONUT - IMPORT",
        "NP CHANGS - IMPORT",
        "SOLARVISTA - IMPORT",
        "FRANKLIN BAKER - IMPORT",
        "EYE CARGO - IMPORT",
        "PHIL JDU - IMPORT",
        "BIO PULP - IMPORT",
        "SOUTHERN HARVEST - IMPORT",
        "HEADSPORT - IMPORT",
        "AGRI EXIM - IMPORT",
        "SOLARIS - IMPORT",
        "HURRAH AGRO - IMPORT",
    ];
}

function build_records_is_dryvan_import_customer($customer): bool
{
    $name = strtoupper(build_records_normalize_text($customer));
    return $name !== "" && in_array($name, build_records_dryvan_import_customers(), true);
}

function build_records_first_non_empty(...$values): string
{
    foreach ($values as $value) {
        $text = build_records_normalize_text($value);
        if ($text !== "") {
            return $text;
        }
    }
    return "";
}

function build_records_excel_serial_date(?string $date): ?float
{
    if (!$date) {
        return null;
    }
    $date = trim($date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || str_starts_with($date, '0000-')) {
        return null;
    }
    $timestamp = strtotime($date . " 00:00:00 UTC");
    if ($timestamp === false) {
        return null;
    }
    $serial = ($timestamp / 86400) + 25569;
    return $serial > 0 ? $serial : null;
}

/** Display a spreadsheet datetime as MM.DD.YYYY and 24-hour time (midnight = 24:00). */
function build_records_display_datetime(?float $serial): string
{
    if ($serial === null) return "";
    $seconds = (int) round($serial * 86400);
    $dt = (new DateTimeImmutable("1899-12-30 00:00:00", new DateTimeZone("Asia/Manila")))
        ->modify(($seconds >= 0 ? "+" : "") . $seconds . " seconds");
    return $dt->format("m.d.Y") . " " . ($dt->format("H:i") === "00:00" ? "24:00" : $dt->format("H:i"));
}

function build_records_excel_serial_datetime(?string $date, ?string $time): ?float
{
    if (!$date) {
        return null;
    }
    $date = trim($date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $date) || str_starts_with($date, '0000-')) {
        return null;
    }
    $timePart = trim((string) $time);
    $timestamp = strtotime($date . " " . ($timePart !== "" ? $timePart : "00:00:00") . " UTC");
    if ($timestamp === false) {
        return null;
    }
    $serial = ($timestamp / 86400) + 25569;
    return $serial > 0 ? $serial : null;
}

function build_records_load_xml(string $xml): DOMDocument
{
    $document = new DOMDocument("1.0", "UTF-8");
    $document->preserveWhiteSpace = false;
    $document->formatOutput = false;
    $document->loadXML($xml);
    return $document;
}

function build_records_xpath(DOMDocument $document): DOMXPath
{
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace("main", "http://schemas.openxmlformats.org/spreadsheetml/2006/main");
    $xpath->registerNamespace("rel", "http://schemas.openxmlformats.org/package/2006/relationships");
    return $xpath;
}

function build_records_append_inline_string(DOMDocument $document, DOMElement $row, string $reference, ?string $style, string $value): void
{
    $cell = $document->createElementNS("http://schemas.openxmlformats.org/spreadsheetml/2006/main", "c");
    $cell->setAttribute("r", $reference);
    if ($style !== null && $style !== "") {
        $cell->setAttribute("s", $style);
    }
    $cell->setAttribute("t", "inlineStr");
    $inlineString = $document->createElementNS("http://schemas.openxmlformats.org/spreadsheetml/2006/main", "is");
    $text = $document->createElementNS("http://schemas.openxmlformats.org/spreadsheetml/2006/main", "t");
    $text->appendChild($document->createTextNode($value));
    $inlineString->appendChild($text);
    $cell->appendChild($inlineString);
    $row->appendChild($cell);
}

function build_records_append_numeric(DOMDocument $document, DOMElement $row, string $reference, ?string $style, $value): void
{
    if ($value === null || $value === "") {
        return;
    }
    $cell = $document->createElementNS("http://schemas.openxmlformats.org/spreadsheetml/2006/main", "c");
    $cell->setAttribute("r", $reference);
    if ($style !== null && $style !== "") {
        $cell->setAttribute("s", $style);
    }
    $valueNode = $document->createElementNS("http://schemas.openxmlformats.org/spreadsheetml/2006/main", "v", rtrim(rtrim(number_format((float) $value, 10, ".", ""), "0"), "."));
    $cell->appendChild($valueNode);
    $row->appendChild($cell);
}

function build_records_append_cell(DOMDocument $document, DOMElement $row, string $reference, ?string $style, $value, string $type = "string"): void
{
    if ($type === "number" || $type === "date" || $type === "datetime") {
        build_records_append_numeric($document, $row, $reference, $style, $value);
        return;
    }
    $text = build_records_normalize_text($value);
    if ($text === "") {
        return;
    }
    build_records_append_inline_string($document, $row, $reference, $style, $text);
}

function build_records_column_index(string $letters): int
{
    $letters = strtoupper($letters);
    $index = 0;
    for ($i = 0, $length = strlen($letters); $i < $length; $i++) {
        $index = ($index * 26) + (ord($letters[$i]) - 64);
    }
    return $index;
}

function build_records_column_labels(): array
{
    return [
        "A" => "Entry",
        "B" => "Transmittal DATE",
        "C" => "Trip Receipt No.",
        "D" => "Transaction Date",
        "E" => "Date and Time of Withdrawal of Van",
        "F" => "Alpha",
        "G" => "Numeric",
        "H" => "Shipping Line",
        "I" => "PH",
        "J" => "Customer / Shipper",
        "K" => "TRIP RECEIPT MTY",
        "L" => "ECS",
        "M" => "TR",
        "N" => "GS",
        "O" => "PM",
        "P" => "Driver",
        "Q" => "Pull-Out Location",
        "R" => "Date & Time of Van Unloading",
        "S" => "TRIP RECEIPT FCL",
        "T" => "DR No./Fleet No/TCARD",
        "U" => "Load",
        "V" => "PM2",
        "W" => "Driver2",
        "X" => "Delivered To",
        "Y" => "No. Of Trips",
        "Z" => "Remarks",
        "AA" => "Standard Kms",
        "AB" => "SKUs",
        "AC" => "OUT",
        "AD" => "IN",
        "AE" => "Volume",
        "AF" => "Van Size",
        "AG" => "Commodity",
        "AH" => "HR:MN",
        "AI" => "EMDR NO.",
        "AJ" => "NO. OF HOURS",
        "AK" => "HOURS START",
        "AL" => "HOURS END",
        "AM" => "START",
        "AN" => "END",
        "AO" => "Trailer Rental",
        "AP" => "Vessel",
        "AQ" => "Voyage No.",
    ];
}

function build_records_auto_widths(array $rows): array
{
    $labels = build_records_column_labels();
    $widths = [];

    foreach ($labels as $column => $label) {
        $widths[$column] = strlen($label);
    }

    foreach ($rows as $row) {
        foreach ($row as $column => $cellData) {
            $type = $cellData["type"] ?? "string";
            $value = $cellData["value"] ?? "";
            if ($type === "date") {
                $display = $value === null ? "" : "00/00/0000";
            } elseif ($type === "datetime") {
                $display = $value === null ? "" : "00/00/0000 00:00";
            } elseif ($type === "number") {
                $display = $value === null ? "" : (string) $value;
            } else {
                $display = build_records_normalize_text($value);
            }
            $widths[$column] = max($widths[$column] ?? 0, strlen($display));
        }
    }

    foreach ($widths as $column => $length) {
        $widths[$column] = min(max($length + 2, 8), 40);
    }

    return $widths;
}

function build_records_ensure_styles(string $stylesXml): array
{
    $document = build_records_load_xml($stylesXml);
    $xpath = build_records_xpath($document);
    $namespace = "http://schemas.openxmlformats.org/spreadsheetml/2006/main";

    $fontsNode = $xpath->query("//main:fonts")->item(0);
    $fillsNode = $xpath->query("//main:fills")->item(0);
    $bordersNode = $xpath->query("//main:borders")->item(0);
    $cellXfsNode = $xpath->query("//main:cellXfs")->item(0);

    if (
        !$fontsNode instanceof DOMElement ||
        !$fillsNode instanceof DOMElement ||
        !$bordersNode instanceof DOMElement ||
        !$cellXfsNode instanceof DOMElement
    ) {
        return [
            "xml" => $stylesXml,
            "styles" => [
                "title" => "1", "meta" => "1", "header" => "7",
                "body" => "24", "body_center" => "24",
                "body_date" => "22", "body_datetime" => "23", "body_number" => "24",
            ],
        ];
    }

    $appendFont = static function (DOMDocument $doc, DOMElement $parent, array $definition) use ($namespace): int {
        $fontIndex = $parent->childNodes->length;
        $fontNode = $doc->createElementNS($namespace, "font");
        foreach ($definition as $key => $value) {
            if ($value === true) {
                $fontNode->appendChild($doc->createElementNS($namespace, $key));
                continue;
            }
            if (is_array($value)) {
                $child = $doc->createElementNS($namespace, $key);
                foreach ($value as $attribute => $attributeValue) {
                    $child->setAttribute($attribute, (string) $attributeValue);
                }
                $fontNode->appendChild($child);
                continue;
            }
            $child = $doc->createElementNS($namespace, $key);
            $child->setAttribute("val", (string) $value);
            $fontNode->appendChild($child);
        }
        $parent->appendChild($fontNode);
        $parent->setAttribute("count", (string) $parent->childNodes->length);
        return $fontIndex;
    };

    $appendFill = static function (DOMDocument $doc, DOMElement $parent, string $rgb) use ($namespace): int {
        $fillIndex = $parent->childNodes->length;
        $fillNode = $doc->createElementNS($namespace, "fill");
        $patternNode = $doc->createElementNS($namespace, "patternFill");
        $patternNode->setAttribute("patternType", "solid");
        $fgNode = $doc->createElementNS($namespace, "fgColor");
        $fgNode->setAttribute("rgb", $rgb);
        $bgNode = $doc->createElementNS($namespace, "bgColor");
        $bgNode->setAttribute("indexed", "64");
        $patternNode->appendChild($fgNode);
        $patternNode->appendChild($bgNode);
        $fillNode->appendChild($patternNode);
        $parent->appendChild($fillNode);
        $parent->setAttribute("count", (string) $parent->childNodes->length);
        return $fillIndex;
    };

    $appendBorder = static function (DOMDocument $doc, DOMElement $parent, string $rgb) use ($namespace): int {
        $borderIndex = $parent->childNodes->length;
        $borderNode = $doc->createElementNS($namespace, "border");
        foreach (["left", "right", "top", "bottom"] as $side) {
            $sideNode = $doc->createElementNS($namespace, $side);
            $sideNode->setAttribute("style", "thin");
            $colorNode = $doc->createElementNS($namespace, "color");
            $colorNode->setAttribute("rgb", $rgb);
            $sideNode->appendChild($colorNode);
            $borderNode->appendChild($sideNode);
        }
        $borderNode->appendChild($doc->createElementNS($namespace, "diagonal"));
        $parent->appendChild($borderNode);
        $parent->setAttribute("count", (string) $parent->childNodes->length);
        return $borderIndex;
    };

    $appendXf = static function (
        DOMDocument $doc, DOMElement $parent, int $fontId, int $fillId, int $borderId,
        int $numFmtId, array $alignment, bool $applyNumberFormat
    ) use ($namespace): int {
        $xfIndex = $parent->childNodes->length;
        $xfNode = $doc->createElementNS($namespace, "xf");
        $xfNode->setAttribute("numFmtId", (string) $numFmtId);
        $xfNode->setAttribute("fontId", (string) $fontId);
        $xfNode->setAttribute("fillId", (string) $fillId);
        $xfNode->setAttribute("borderId", (string) $borderId);
        $xfNode->setAttribute("xfId", "0");
        $xfNode->setAttribute("applyFont", "1");
        $xfNode->setAttribute("applyFill", "1");
        $xfNode->setAttribute("applyBorder", "1");
        $xfNode->setAttribute("applyAlignment", "1");
        if ($applyNumberFormat) {
            $xfNode->setAttribute("applyNumberFormat", "1");
        }
        $alignmentNode = $doc->createElementNS($namespace, "alignment");
        foreach ($alignment as $key => $value) {
            $alignmentNode->setAttribute($key, (string) $value);
        }
        $xfNode->appendChild($alignmentNode);
        $parent->appendChild($xfNode);
        $parent->setAttribute("count", (string) $parent->childNodes->length);
        return $xfIndex;
    };

    $titleFontId = $appendFont($document, $fontsNode, ["b" => true, "sz" => "16", "color" => ["rgb" => "FFFFFFFF"], "name" => "Aptos Display", "family" => "2"]);
    $metaFontId = $appendFont($document, $fontsNode, ["b" => true, "sz" => "10", "color" => ["rgb" => "FF334155"], "name" => "Aptos", "family" => "2"]);
    $headerFontId = $appendFont($document, $fontsNode, ["b" => true, "sz" => "10", "color" => ["rgb" => "FFFFFFFF"], "name" => "Aptos", "family" => "2"]);
    $bodyFontId = $appendFont($document, $fontsNode, ["sz" => "10", "color" => ["rgb" => "FF0F172A"], "name" => "Aptos", "family" => "2"]);

    $titleFillId = $appendFill($document, $fillsNode, "FF0F172A");
    $metaFillId = $appendFill($document, $fillsNode, "FFF8FAFC");
    $headerFillId = $appendFill($document, $fillsNode, "FF2563EB");
    $bodyFillId = $appendFill($document, $fillsNode, "FFFFFFFF");

    $borderId = $appendBorder($document, $bordersNode, "FFE2E8F0");

    $titleStyleId = $appendXf($document, $cellXfsNode, $titleFontId, $titleFillId, $borderId, 0, ["horizontal" => "left", "vertical" => "center", "wrapText" => "1"], false);
    $metaStyleId = $appendXf($document, $cellXfsNode, $metaFontId, $metaFillId, $borderId, 0, ["horizontal" => "left", "vertical" => "center", "wrapText" => "1"], false);
    $headerStyleId = $appendXf($document, $cellXfsNode, $headerFontId, $headerFillId, $borderId, 0, ["horizontal" => "center", "vertical" => "center", "wrapText" => "1"], false);
    $bodyStyleId = $appendXf($document, $cellXfsNode, $bodyFontId, $bodyFillId, $borderId, 0, ["horizontal" => "left", "vertical" => "center", "wrapText" => "1"], false);
    $bodyCenterStyleId = $appendXf($document, $cellXfsNode, $bodyFontId, $bodyFillId, $borderId, 0, ["horizontal" => "center", "vertical" => "center", "wrapText" => "1"], false);
    $bodyDateStyleId = $appendXf($document, $cellXfsNode, $bodyFontId, $bodyFillId, $borderId, 164, ["horizontal" => "center", "vertical" => "center", "wrapText" => "1"], true);
    $bodyDateTimeStyleId = $appendXf($document, $cellXfsNode, $bodyFontId, $bodyFillId, $borderId, 167, ["horizontal" => "center", "vertical" => "center", "wrapText" => "1"], true);
    $bodyNumberStyleId = $appendXf($document, $cellXfsNode, $bodyFontId, $bodyFillId, $borderId, 0, ["horizontal" => "center", "vertical" => "center", "wrapText" => "1"], false);

    return [
        "xml" => $document->saveXML(),
        "styles" => [
            "title" => (string) $titleStyleId,
            "meta" => (string) $metaStyleId,
            "header" => (string) $headerStyleId,
            "body" => (string) $bodyStyleId,
            "body_center" => (string) $bodyCenterStyleId,
            "body_date" => (string) $bodyDateStyleId,
            "body_datetime" => (string) $bodyDateTimeStyleId,
            "body_number" => (string) $bodyNumberStyleId,
        ],
    ];
}

function build_records_write_xlsx(
    array $rows,
    string $outputPath,
    string $dateFrom,
    string $dateTo,
    string $entryType,
    string $generatedAtDate
): void {
    $templatePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . "records-template.xlsx";
    if (!is_file($templatePath)) {
        throw new RuntimeException("Template file not found.");
    }

    $outputDir = dirname($outputPath);
    if (!is_dir($outputDir)) {
        if (!mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            throw new RuntimeException("Failed to create output directory: " . $outputDir);
        }
    }

    @unlink($outputPath);

    if (!copy($templatePath, $outputPath)) {
        throw new RuntimeException("Failed to copy template to output path.");
    }

    $zip = new ZipArchive();
    if ($zip->open($outputPath) !== true) {
        @unlink($outputPath);
        throw new RuntimeException("Failed to open template export.");
    }

    $sheetXml = $zip->getFromName("xl/worksheets/sheet1.xml");
    $stylesXml = $zip->getFromName("xl/styles.xml");
    $workbookXml = $zip->getFromName("xl/workbook.xml");
    $workbookRelsXml = $zip->getFromName("xl/_rels/workbook.xml.rels");

    if ($sheetXml === false || $stylesXml === false || $workbookXml === false || $workbookRelsXml === false) {
        $zip->close();
        @unlink($outputPath);
        throw new RuntimeException("Template structure is invalid.");
    }

    $sheetDocument = build_records_load_xml($sheetXml);
    $sheetXpath = build_records_xpath($sheetDocument);
    $sheetData = $sheetXpath->query("//main:sheetData")->item(0);
    $styleBundle = build_records_ensure_styles($stylesXml);
    $styleMap = $styleBundle["styles"];

    if (!$sheetData instanceof DOMElement) {
        $zip->close();
        @unlink($outputPath);
        throw new RuntimeException("Template worksheet is invalid.");
    }

    $rowsToRemove = [];
    foreach ($sheetXpath->query("//main:sheetData/main:row") as $rowNode) {
        if (!$rowNode instanceof DOMElement) {
            continue;
        }
        if ((int) $rowNode->getAttribute("r") >= 4) {
            $rowsToRemove[] = $rowNode;
        }
    }
    foreach ($rowsToRemove as $rowNode) {
        $sheetData->removeChild($rowNode);
    }

    $labels = build_records_column_labels();
    $columnWidths = build_records_auto_widths($rows);
    $existingCols = $sheetXpath->query("//main:cols")->item(0);
    if ($existingCols instanceof DOMElement) {
        $existingCols->parentNode?->removeChild($existingCols);
    }

    $sheetFormatPr = $sheetXpath->query("//main:sheetFormatPr")->item(0);
    $colsNode = $sheetDocument->createElementNS("http://schemas.openxmlformats.org/spreadsheetml/2006/main", "cols");
    foreach ($columnWidths as $column => $width) {
        $columnIndex = build_records_column_index($column);
        $colNode = $sheetDocument->createElementNS("http://schemas.openxmlformats.org/spreadsheetml/2006/main", "col");
        $colNode->setAttribute("min", (string) $columnIndex);
        $colNode->setAttribute("max", (string) $columnIndex);
        $colNode->setAttribute("width", number_format($width, 2, ".", ""));
        $colNode->setAttribute("bestFit", "1");
        $colNode->setAttribute("customWidth", "1");
        $colsNode->appendChild($colNode);
    }
    if ($sheetFormatPr instanceof DOMElement && $sheetFormatPr->parentNode instanceof DOMNode) {
        $sheetFormatPr->parentNode->insertBefore($colsNode, $sheetData);
    }

    $sheetView = $sheetXpath->query("//main:sheetViews/main:sheetView")->item(0);
    if ($sheetView instanceof DOMElement) {
        while ($sheetView->firstChild) {
            $sheetView->removeChild($sheetView->firstChild);
        }
        $paneNode = $sheetDocument->createElementNS("http://schemas.openxmlformats.org/spreadsheetml/2006/main", "pane");
        $paneNode->setAttribute("ySplit", "3");
        $paneNode->setAttribute("topLeftCell", "A4");
        $paneNode->setAttribute("activePane", "bottomLeft");
        $paneNode->setAttribute("state", "frozen");
        $sheetView->appendChild($paneNode);
        $selectionNode = $sheetDocument->createElementNS("http://schemas.openxmlformats.org/spreadsheetml/2006/main", "selection");
        $selectionNode->setAttribute("pane", "bottomLeft");
        $selectionNode->setAttribute("activeCell", "A4");
        $selectionNode->setAttribute("sqref", "A4");
        $sheetView->appendChild($selectionNode);
    }

    $titleRow = $sheetDocument->createElementNS("http://schemas.openxmlformats.org/spreadsheetml/2006/main", "row");
    $titleRow->setAttribute("r", "1");
    $titleRow->setAttribute("spans", "1:43");
    $titleRow->setAttribute("customFormat", "1");
    $titleRow->setAttribute("ht", "28");
    $titleRow->setAttribute("customHeight", "1");
    $titleRow->setAttributeNS("http://schemas.microsoft.com/office/spreadsheetml/2009/9/ac", "x14ac:dyDescent", "0.3");
    build_records_append_cell($sheetDocument, $titleRow, "A1", $styleMap["title"], "Operations Records Export", "string");
    $sheetData->appendChild($titleRow);

    $metaRow = $sheetDocument->createElementNS("http://schemas.openxmlformats.org/spreadsheetml/2006/main", "row");
    $metaRow->setAttribute("r", "2");
    $metaRow->setAttribute("spans", "1:43");
    $metaRow->setAttribute("customFormat", "1");
    $metaRow->setAttribute("ht", "20");
    $metaRow->setAttribute("customHeight", "1");
    $metaRow->setAttributeNS("http://schemas.microsoft.com/office/spreadsheetml/2009/9/ac", "x14ac:dyDescent", "0.3");
    $entryTypeDisplay = strtoupper($entryType) === "ALL" || $entryType === "" ? "All Entries" : $entryType;
    $metaText = sprintf(
        "Date Range: %s to %s   |   Entry Type: %s   |   Generated: %s",
        $dateFrom, $dateTo, $entryTypeDisplay, $generatedAtDate
    );
    build_records_append_cell($sheetDocument, $metaRow, "A2", $styleMap["meta"], $metaText, "string");
    $sheetData->appendChild($metaRow);

    $headerRow = $sheetDocument->createElementNS("http://schemas.openxmlformats.org/spreadsheetml/2006/main", "row");
    $headerRow->setAttribute("r", "3");
    $headerRow->setAttribute("spans", "1:43");
    $headerRow->setAttribute("customFormat", "1");
    $headerRow->setAttribute("ht", "28");
    $headerRow->setAttribute("customHeight", "1");
    $headerRow->setAttributeNS("http://schemas.microsoft.com/office/spreadsheetml/2009/9/ac", "x14ac:dyDescent", "0.3");
    foreach ($labels as $column => $label) {
        build_records_append_cell($sheetDocument, $headerRow, $column . "3", $styleMap["header"], $label, "string");
    }
    $sheetData->appendChild($headerRow);

    $centeredColumns = ["A", "C", "F", "G", "H", "I", "K", "L", "M", "N", "O", "P", "R", "S", "V", "W", "Y", "AA", "AB", "AC", "AD", "AE", "AF", "AH", "AJ"];
    $dateColumns = ["B", "D"];
    $dateTimeColumns = ["E", "R", "AM", "AN"];
    $numberColumns = ["Y", "AK", "AL", "AO"];

    $lastRowNumber = 3;
    foreach ($rows as $index => $rowData) {
        $rowNumber = 4 + $index;
        $lastRowNumber = $rowNumber;
        $rowNode = $sheetDocument->createElementNS("http://schemas.openxmlformats.org/spreadsheetml/2006/main", "row");
        $rowNode->setAttribute("r", (string) $rowNumber);
        $rowNode->setAttribute("spans", "1:43");
        $rowNode->setAttribute("customFormat", "1");
        $rowNode->setAttribute("ht", "20");
        $rowNode->setAttribute("customHeight", "1");
        $rowNode->setAttributeNS("http://schemas.microsoft.com/office/spreadsheetml/2009/9/ac", "x14ac:dyDescent", "0.3");
        foreach ($rowData as $column => $cellData) {
            if (in_array($column, $dateColumns, true)) {
                $style = $styleMap["body_date"];
            } elseif (in_array($column, $dateTimeColumns, true)) {
                $style = $styleMap["body_datetime"];
            } elseif (in_array($column, $numberColumns, true)) {
                $style = $styleMap["body_number"];
            } elseif (in_array($column, $centeredColumns, true)) {
                $style = $styleMap["body_center"];
            } else {
                $style = $styleMap["body"];
            }
            build_records_append_cell($sheetDocument, $rowNode, $column . $rowNumber, $style, $cellData["value"] ?? "", $cellData["type"] ?? "string");
        }
        $sheetData->appendChild($rowNode);
    }

    $dimensionNode = $sheetXpath->query("//main:dimension")->item(0);
    if ($dimensionNode instanceof DOMElement) {
        $dimensionNode->setAttribute("ref", "A1:AQ" . max($lastRowNumber, 3));
    }

    foreach ($sheetXpath->query("//main:mergeCells") as $node) { $node->parentNode?->removeChild($node); }
    foreach ($sheetXpath->query("//main:conditionalFormatting") as $node) { $node->parentNode?->removeChild($node); }
    foreach ($sheetXpath->query("//main:autoFilter") as $node) { $node->parentNode?->removeChild($node); }

    $pageMarginsNode = $sheetXpath->query("//main:pageMargins")->item(0);
    $autoFilterNode = $sheetDocument->createElementNS("http://schemas.openxmlformats.org/spreadsheetml/2006/main", "autoFilter");
    $autoFilterNode->setAttribute("ref", "A3:AQ" . max($lastRowNumber, 3));
    if ($pageMarginsNode instanceof DOMElement && $pageMarginsNode->parentNode instanceof DOMNode) {
        $pageMarginsNode->parentNode->insertBefore($autoFilterNode, $pageMarginsNode);
    }

    $mergeCellsNode = $sheetDocument->createElementNS("http://schemas.openxmlformats.org/spreadsheetml/2006/main", "mergeCells");
    $mergeCellsNode->setAttribute("count", "2");
    $mergeOne = $sheetDocument->createElementNS("http://schemas.openxmlformats.org/spreadsheetml/2006/main", "mergeCell");
    $mergeOne->setAttribute("ref", "A1:AQ1");
    $mergeTwo = $sheetDocument->createElementNS("http://schemas.openxmlformats.org/spreadsheetml/2006/main", "mergeCell");
    $mergeTwo->setAttribute("ref", "A2:AQ2");
    $mergeCellsNode->appendChild($mergeOne);
    $mergeCellsNode->appendChild($mergeTwo);
    if ($pageMarginsNode instanceof DOMElement && $pageMarginsNode->parentNode instanceof DOMNode) {
        $pageMarginsNode->parentNode->insertBefore($mergeCellsNode, $pageMarginsNode);
    }

    $workbookDocument = build_records_load_xml($workbookXml);
    $workbookXpath = build_records_xpath($workbookDocument);
    foreach ($workbookXpath->query("//main:externalReferences") as $node) { $node->parentNode?->removeChild($node); }

    $workbookRelsDocument = build_records_load_xml($workbookRelsXml);
    $workbookRelsXpath = build_records_xpath($workbookRelsDocument);
    foreach ($workbookRelsXpath->query('//rel:Relationship[contains(@Type, "externalLink") or contains(@Type, "calcChain")]') as $relationship) {
        $relationship->parentNode?->removeChild($relationship);
    }

    $zip->addFromString("xl/worksheets/sheet1.xml", $sheetDocument->saveXML());
    $zip->addFromString("xl/styles.xml", $styleBundle["xml"]);
    $zip->addFromString("xl/workbook.xml", $workbookDocument->saveXML());
    $zip->addFromString("xl/_rels/workbook.xml.rels", $workbookRelsDocument->saveXML());
    $zip->close();
}
