<?php

/**
 * Full-detail transactions export: one worksheet per entry type, each carrying
 * the complete set of fields that entry type actually uses.
 *
 * Unlike build_records_xlsx (a curated 42-column unified layout), this dumps
 * every operations column that holds data for a given entry type. A column is
 * included on a sheet when at least one row of that entry type has a non-empty
 * value for it, so each sheet shows only the fields relevant to that type.
 *
 * Writes the workbook to $outputPath. Returns:
 *   [
 *     'sheet_count'  => int,
 *     'record_count' => int,        // total rows across all sheets
 *     'per_type'     => [type => count],
 *     'file_size'    => int,
 *   ]
 *
 * Throws RuntimeException on failure.
 */
function build_transactions_detail_xlsx(
    PDO $conn,
    string $dateFrom,
    string $dateTo,
    string $entryType,
    string $customer,
    string $outputPath
): array {
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

    $params = [$dateFrom, $dateTo];
    $sql = "SELECT * FROM operations WHERE created_date::date BETWEEN ? AND ?";

    if ($entryType !== "" && strtoupper($entryType) !== "ALL") {
        $sql .= " AND entry_type = ?";
        $params[] = $entryType;
    }
    if ($customer !== "") {
        $sql .= " AND (customer_ph LIKE ? OR ph LIKE ? OR operations_ph LIKE ?)";
        $like = "%" . $customer . "%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= " ORDER BY entry_type ASC, entry_id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Master column order comes from the result set (table definition order).
    $columnOrder = [];
    if (!empty($allRows)) {
        $columnOrder = array_keys($allRows[0]);
    }

    // Group rows by entry type.
    $groups = [];
    foreach ($allRows as $row) {
        $type = trim((string) ($row["entry_type"] ?? ""));
        if ($type === "") {
            $type = "(No Entry Type)";
        }
        $groups[$type][] = $row;
    }

    // Present the known entry types first, in a sensible order; anything else
    // (unexpected/legacy values) trails behind alphabetically.
    $preferredOrder = [
        "RV ENTRY",
        "DRY VAN ENTRY",
        "OTHERS ENTRY",
        "DPC_KDs & OPM ENTRY",
        "CARGO TRUCK ENTRY",
    ];
    $orderedTypes = [];
    foreach ($preferredOrder as $type) {
        if (isset($groups[$type])) {
            $orderedTypes[] = $type;
        }
    }
    $remaining = array_diff(array_keys($groups), $orderedTypes);
    sort($remaining);
    foreach ($remaining as $type) {
        $orderedTypes[] = $type;
    }

    // Resolve encoder ids/usernames to display names so created_by / modified_by
    // read as the encoder's name rather than a raw id number.
    $encoderNames = transactions_detail_encoder_map($conn);
    $encoderColumns = ["created_by", "modified_by"];

    $sheets = [];
    $perType = [];
    $usedSheetNames = [];

    foreach ($orderedTypes as $type) {
        $rows = $groups[$type];
        $perType[$type] = count($rows);

        // Columns intentionally omitted from the detail export (redundant with
        // their loaded-leg counterparts).
        $excludedColumns = ["segment_empty" => true, "activity_empty" => true];

        // Which columns hold data for this entry type (entry_id and entry_type
        // are always kept so every sheet stays identifiable).
        $hasData = [];
        foreach ($columnOrder as $col) {
            if ($col === "entry_id" || $col === "entry_type") {
                $hasData[$col] = true;
                continue;
            }
            if (isset($excludedColumns[$col])) {
                continue;
            }
            foreach ($rows as $row) {
                if (trim((string) ($row[$col] ?? "")) !== "") {
                    $hasData[$col] = true;
                    break;
                }
            }
        }

        // Order the columns to follow this entry type's data-entry form sequence.
        // Any data columns not in that sequence trail behind in table order.
        $preferred = transactions_detail_column_order($type);
        $usedColumns = [];
        $seen = [];
        foreach ($preferred as $col) {
            if (!empty($hasData[$col]) && empty($seen[$col])) {
                $usedColumns[] = $col;
                $seen[$col] = true;
            }
        }
        foreach ($columnOrder as $col) {
            if (!empty($hasData[$col]) && empty($seen[$col])) {
                $usedColumns[] = $col;
                $seen[$col] = true;
            }
        }

        $sheetRows = [];
        foreach ($rows as $row) {
            $line = [];
            foreach ($usedColumns as $col) {
                $value = (string) ($row[$col] ?? "");
                if (in_array($col, $encoderColumns, true)) {
                    $value = transactions_detail_encoder_name($value, $encoderNames);
                }
                $line[] = $value;
            }
            $sheetRows[] = $line;
        }

        $sheets[] = [
            "name" => transactions_detail_sheet_name($type, $usedSheetNames),
            "headers" => array_map('transactions_detail_header_label', $usedColumns),
            "rows" => $sheetRows,
        ];
    }

    if (empty($sheets)) {
        $sheets[] = [
            "name" => "No Data",
            "headers" => ["Message"],
            "rows" => [["No transactions found for the selected filters."]],
        ];
    }

    transactions_detail_write_workbook($sheets, $outputPath);

    return [
        "sheet_count" => count($sheets),
        "record_count" => count($allRows),
        "per_type" => $perType,
        "file_size" => (int) filesize($outputPath),
    ];
}

/**
 * Build a lookup of encoder identifier -> display name. Keyed by both
 * user_idNumber and user_name (the two forms stored in operations.created_by /
 * modified_by). Name is "First Last", falling back to the username.
 */
function transactions_detail_encoder_map(PDO $conn): array
{
    $map = [];
    try {
        $stmt = $conn->query('SELECT "user_idNumber", user_name, user_fname, user_lname FROM "user"');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $name = trim(((string) ($u["user_fname"] ?? "")) . " " . ((string) ($u["user_lname"] ?? "")));
            if ($name === "") {
                $name = trim((string) ($u["user_name"] ?? ""));
            }
            if ($name === "") {
                continue;
            }
            $id = trim((string) ($u["user_idNumber"] ?? ""));
            $username = trim((string) ($u["user_name"] ?? ""));
            if ($id !== "") {
                $map[$id] = $name;
            }
            if ($username !== "") {
                $map[$username] = $name;
            }
        }
    } catch (Throwable $e) {
        // If the user table is unavailable, fall back to the raw stored values.
    }
    return $map;
}

/** Translate a stored created_by/modified_by value to the encoder's name. */
function transactions_detail_encoder_name(string $raw, array $map): string
{
    $key = trim($raw);
    if ($key === "") {
        return "";
    }
    return $map[$key] ?? $raw;
}

/**
 * Preferred column order per entry type, following each data-entry form's own
 * field sequence (mirrors the INSERT column lists in php/insert/*.php). This
 * keeps the loaded-leg and empty-leg fields grouped the way the form presents
 * them, instead of the interleaved raw table order. Columns with data that are
 * not listed here trail behind in table order, so nothing is dropped.
 * Returns [] for unknown entry types (falls back to table order entirely).
 */
function transactions_detail_column_order(string $type): array
{
    $orders = [
        "RV ENTRY" => [
            "entry_id", "entry_type",
            "segment_empty", "activity_empty", "segment", "activity", "remarks",
            "pullout_location_arrival_date", "pullout_location_arrival_time",
            "pullout_location_departure_date", "pullout_location_departure_time",
            "ph_arrival_date", "ph_arrival_time",
            "delivery_location_arrival_date", "delivery_location_arrival_time",
            "van_alpha", "van_number", "van_name", "ph", "shipper", "ecs", "tr", "gs",
            "waybill", "waybill_empty", "waybill_date", "empty_trip_receipt_date",
            "prime_mover", "driver", "driver_idNumber", "empty_pullout_location",
            "loaded_van_loading_start_date", "loaded_van_loading_start_time",
            "loaded_van_loading_finish_date", "loaded_van_loading_finish_time",
            "loaded_van_delivery_departure_date", "loaded_van_delivery_departure_time",
            "loaded_van_delivery_arrival_date", "loaded_van_delivery_arrival_time",
            "genset_shutoff_date", "genset_shutoff_time",
            "end_uploading_date", "end_uploading_time",
            "dr_no", "load_description",
            "delivered_by_prime_mover", "delivered_by_driver", "delivered_by_driverIdNumber",
            "delivered_to", "delivered_remarks",
            "genset_hr_meter_start", "genset_hr_meter_end", "reference_documents",
            "genset_hr_meter", "genset_hr_reading", "refueled", "defect_hubo",
            "genset_start_date", "genset_start_time", "genset_end_date", "genset_end_time",
            "piece_rate_empty", "piece_rate_loaded", "piece_rate", "kms", "billing_sku",
            "created_by", "created_date", "modified_by", "modified_date",
        ],
        "DRY VAN ENTRY" => [
            "entry_id", "entry_type", "status", "customer_ph", "ph", "ecs",
            "van_alpha", "van_number", "shipper",
            "eir_out", "eir_outDate", "eir_outTime", "eir_in", "eir_inDate", "eir_inTime",
            "pullout_location", "pullout_date", "pullout_time",
            "delivered_to", "return_location", "size", "commodity", "slp_no",
            "destination", "gs", "genset_hr_meter_start", "genset_hr_meter_end",
            "segment", "activity", "waybill", "date_hauled",
            "driver", "driver_idNumber", "truck", "tr", "tr2",
            "date_unloaded", "departure_time", "arrival_time", "time_unloaded", "remarks",
            "segment_empty", "activity_empty", "waybill_empty", "date_returned", "type", "delivered_remarks",
            "kms", "booking", "shipment_no", "vessel_name", "voyage_no", "emdr_no", "seal",
            "driver_return", "driver_return_idNumber", "truck2", "billing_sku",
            "created_by", "created_date", "modified_by", "modified_date",
        ],
        "OTHERS ENTRY" => [
            "entry_id", "entry_type", "waybill_date", "waybill", "segment", "activity",
            "truck", "tr", "gs", "operations_ph", "customer_ph",
            "load_quantity_weight", "unit_of_measure", "kms", "deliver_from", "delivered_to",
            "driver", "driver_idNumber", "remarks", "piece_rate", "billing_sku",
            "created_by", "created_date", "modified_by", "modified_date",
        ],
        "DPC_KDs & OPM ENTRY" => [
            "entry_id", "entry_type", "segment", "activity", "waybill_date", "waybill",
            "evita_farmind", "driver", "driver_idNumber",
            "departure", "arrival", "truck", "tr", "ph",
            "13_body", "13_cover", "13_pads", "18_body", "18_cover", "18_pads",
            "13_total", "18_total", "other_body", "other_cover", "other_pads", "other_total",
            "total_load", "fgtr_no", "remarks", "dpc_date", "piece_rate", "billing_sku",
            "created_by", "created_date", "modified_by", "modified_date",
        ],
        "CARGO TRUCK ENTRY" => [
            "entry_id", "entry_type", "segment", "activity", "waybill_date", "waybill",
            "truck", "driver", "driver_idNumber", "customer_ph", "outside", "compound",
            "total_trips", "operations", "deliver_from", "delivered_to", "remarks",
            "cargo_date", "piece_rate", "billing_sku",
            "created_by", "created_date", "modified_by", "modified_date",
        ],
    ];

    return $orders[$type] ?? [];
}

/** Turn a raw operations column name into a readable header. */
function transactions_detail_header_label(string $column): string
{
    $label = str_replace("_", " ", $column);
    $label = trim(preg_replace('/\s+/', ' ', $label));
    return ucwords($label);
}

/** Sanitize + uniquify a worksheet name (Excel: <=31 chars, no : \ / ? * [ ]). */
function transactions_detail_sheet_name(string $type, array &$used): string
{
    $name = preg_replace('/[:\\\\\/\?\*\[\]]/', " ", $type);
    $name = trim(preg_replace('/\s+/', ' ', (string) $name));
    if ($name === "") {
        $name = "Sheet";
    }
    if (mb_strlen($name) > 31) {
        $name = mb_substr($name, 0, 31);
    }
    $base = $name;
    $suffix = 2;
    while (in_array(mb_strtolower($name), $used, true)) {
        $tail = " (" . $suffix . ")";
        $name = mb_substr($base, 0, 31 - mb_strlen($tail)) . $tail;
        $suffix++;
    }
    $used[] = mb_strtolower($name);
    return $name;
}

/**
 * Write a multi-sheet .xlsx workbook. Each sheet is rendered as a real Excel
 * table (banded rows + filter dropdowns) with a frozen header row. Date, time
 * and date-time values are written as typed cells with matching number formats;
 * everything else is a shared string. No external library — ZipArchive only.
 */
function transactions_detail_write_workbook(array $sheets, string $outputPath): void
{
    $outputDir = dirname($outputPath);
    if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
        throw new RuntimeException("Failed to create output directory: " . $outputDir);
    }
    @unlink($outputPath);

    // Classify every body cell up front: dates/times/datetimes become numeric
    // serials, everything else stays a string. This drives both the shared-string
    // table and the per-cell style below.
    $classified = [];
    $sharedStrings = [];
    $sharedIndex = [];
    $addString = static function (string $value) use (&$sharedStrings, &$sharedIndex): int {
        if (!array_key_exists($value, $sharedIndex)) {
            $sharedIndex[$value] = count($sharedStrings);
            $sharedStrings[] = $value;
        }
        return $sharedIndex[$value];
    };
    foreach ($sheets as $sheetPos => $sheet) {
        foreach ($sheet["headers"] as $h) {
            $addString((string) $h);
        }
        $classified[$sheetPos] = [];
        foreach ($sheet["rows"] as $rowIdx => $row) {
            $classified[$sheetPos][$rowIdx] = [];
            foreach ($row as $colIdx => $value) {
                $cell = transactions_detail_classify((string) $value);
                if ($cell["type"] === "string" && $cell["value"] !== "") {
                    $addString($cell["value"]);
                }
                $classified[$sheetPos][$rowIdx][$colIdx] = $cell;
            }
        }
    }

    $zip = new ZipArchive();
    if ($zip->open($outputPath, ZipArchive::CREATE) !== true) {
        throw new RuntimeException("Failed to create workbook file.");
    }

    // sharedStrings.xml
    $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'
        . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">';
    foreach ($sharedStrings as $value) {
        $ssXml .= '<si><t xml:space="preserve">'
            . htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8')
            . '</t></si>';
    }
    $ssXml .= '</sst>';

    // styles.xml — custom number formats for date / time / datetime, plus the
    // cellXfs that reference them. Style index: 0 default, 1 date, 2 time,
    // 3 datetime.
    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="3">'
        . '<numFmt numFmtId="164" formatCode="yyyy\\-mm\\-dd"/>'
        . '<numFmt numFmtId="165" formatCode="hh:mm:ss"/>'
        . '<numFmt numFmtId="166" formatCode="yyyy\\-mm\\-dd\\ hh:mm:ss"/>'
        . '</numFmts>'
        . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="4">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '<xf numFmtId="166" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '</cellXfs></styleSheet>';
    $styleFor = ["date" => "1", "time" => "2", "datetime" => "3"];

    // Per-sheet worksheet XML + workbook wiring. Each sheet owns one table part.
    $sheetEntriesXml = "";
    $wbRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';

    $sheetIndex = 0;
    foreach ($sheets as $sheetPos => $sheet) {
        $sheetIndex++;
        $headers = $sheet["headers"];
        $rows = $sheet["rows"];
        $colCount = max(1, count($headers));
        $lastCol = transactions_detail_col_name($colCount - 1);
        $lastRow = 1 + count($rows); // header row + data rows
        $tableRef = "A1:" . $lastCol . $lastRow;

        // Column widths sized to the header and a sample of the values.
        $widths = [];
        foreach ($headers as $i => $h) {
            $widths[$i] = strlen((string) $h);
        }
        foreach ($classified[$sheetPos] as $row) {
            foreach ($row as $colIdx => $cell) {
                $display = $cell["type"] === "string" ? $cell["value"] : ($cell["display"] ?? "");
                $widths[$colIdx] = max($widths[$colIdx] ?? 8, strlen((string) $display));
            }
        }
        $colsXml = "<cols>";
        foreach ($widths as $i => $w) {
            $n = $i + 1;
            $colsXml .= '<col min="' . $n . '" max="' . $n . '" width="'
                . number_format(min(max($w + 2, 10), 45), 2, ".", "") . '" customWidth="1"/>';
        }
        $colsXml .= "</cols>";

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="' . $tableRef . '"/>'
            . '<sheetViews><sheetView' . ($sheetIndex === 1 ? ' tabSelected="1"' : '') . ' workbookViewId="0">'
            . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            . '<selection pane="bottomLeft" activeCell="A2" sqref="A2"/>'
            . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . $colsXml
            . '<sheetData><row r="1">';
        foreach ($headers as $i => $h) {
            $col = transactions_detail_col_name($i);
            $sheetXml .= '<c r="' . $col . '1" t="s"><v>' . $sharedIndex[(string) $h] . '</v></c>';
        }
        $sheetXml .= '</row>';
        foreach ($rows as $rowIdx => $row) {
            $r = $rowIdx + 2;
            $sheetXml .= '<row r="' . $r . '">';
            foreach ($row as $colIdx => $_value) {
                $cell = $classified[$sheetPos][$rowIdx][$colIdx];
                $col = transactions_detail_col_name($colIdx);
                if ($cell["type"] === "string") {
                    if ($cell["value"] === "") {
                        continue;
                    }
                    $sheetXml .= '<c r="' . $col . $r . '" t="s"><v>' . $sharedIndex[$cell["value"]] . '</v></c>';
                } else {
                    $sheetXml .= '<c r="' . $col . $r . '" s="' . $styleFor[$cell["type"]] . '"><v>'
                        . $cell["value"] . '</v></c>';
                }
            }
            $sheetXml .= '</row>';
        }
        $sheetXml .= '</sheetData>'
            . '<tableParts count="1"><tablePart r:id="rId1"/></tableParts>'
            . '</worksheet>';

        $zip->addFromString("xl/worksheets/sheet{$sheetIndex}.xml", $sheetXml);

        // Table object for this sheet: banded rows + per-column filter dropdowns.
        $tableColumnsXml = "";
        foreach ($headers as $i => $h) {
            $tableColumnsXml .= '<tableColumn id="' . ($i + 1) . '" name="'
                . htmlspecialchars((string) $h, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"/>';
        }
        $tableXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<table xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' id="' . $sheetIndex . '" name="Table' . $sheetIndex . '" displayName="Table' . $sheetIndex . '"'
            . ' ref="' . $tableRef . '" totalsRowShown="0">'
            . '<autoFilter ref="' . $tableRef . '"/>'
            . '<tableColumns count="' . $colCount . '">' . $tableColumnsXml . '</tableColumns>'
            . '<tableStyleInfo name="TableStyleMedium2" showFirstColumn="0" showLastColumn="0"'
            . ' showRowStripes="1" showColumnStripes="0"/>'
            . '</table>';
        $zip->addFromString("xl/tables/table{$sheetIndex}.xml", $tableXml);

        // Worksheet -> table relationship.
        $zip->addFromString("xl/worksheets/_rels/sheet{$sheetIndex}.xml.rels",
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/table"'
            . ' Target="../tables/table' . $sheetIndex . '.xml"/>'
            . '</Relationships>');

        $relId = "rId" . $sheetIndex;
        $sheetEntriesXml .= '<sheet name="' . htmlspecialchars($sheet["name"], ENT_XML1 | ENT_COMPAT, 'UTF-8')
            . '" sheetId="' . $sheetIndex . '" r:id="' . $relId . '"/>';
        $wbRelsXml .= '<Relationship Id="' . $relId
            . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
            . ' Target="worksheets/sheet' . $sheetIndex . '.xml"/>';
        $contentTypes .= '<Override PartName="/xl/worksheets/sheet' . $sheetIndex
            . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/tables/table' . $sheetIndex
            . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.table+xml"/>';
    }

    // sharedStrings + styles relationships come after the sheet relationships.
    $ssRelId = "rId" . ($sheetIndex + 1);
    $stylesRelId = "rId" . ($sheetIndex + 2);
    $wbRelsXml .= '<Relationship Id="' . $ssRelId
        . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
        . '<Relationship Id="' . $stylesRelId
        . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $contentTypes .= '</Types>';

    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<bookViews><workbookView xWindow="0" yWindow="0" windowWidth="16384" windowHeight="8192"/></bookViews>'
        . '<sheets>' . $sheetEntriesXml . '</sheets></workbook>';

    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/workbook.xml', $workbookXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRelsXml);
    $zip->addFromString('xl/sharedStrings.xml', $ssXml);
    $zip->addFromString('xl/styles.xml', $stylesXml);

    if ($zip->close() !== true) {
        throw new RuntimeException("Failed to finalize workbook.");
    }
}

/**
 * Classify a raw cell value. Recognizes ISO dates, times and date-times and
 * converts them to Excel serial numbers; everything else stays a string.
 * Returns ['type' => date|time|datetime|string, 'value' => serial|string,
 * 'display' => human text for width sizing].
 */
function transactions_detail_classify(string $value): array
{
    $trimmed = trim($value);
    if ($trimmed === "") {
        return ["type" => "string", "value" => ""];
    }

    // Date-time: YYYY-MM-DD followed by a time (space or T separator).
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/', $trimmed, $m)) {
        $serial = transactions_detail_date_serial($m[1], $m[2], $m[3])
            + transactions_detail_time_fraction((int) $m[4], (int) $m[5], (int) ($m[6] ?? 0));
        if ($serial !== null) {
            return ["type" => "datetime", "value" => transactions_detail_num($serial), "display" => $trimmed];
        }
    }

    // Date only: YYYY-MM-DD.
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $trimmed, $m)) {
        $serial = transactions_detail_date_serial($m[1], $m[2], $m[3]);
        if ($serial !== null) {
            return ["type" => "date", "value" => transactions_detail_num((float) $serial), "display" => $trimmed];
        }
    }

    // Time only: HH:MM or HH:MM:SS.
    if (preg_match('/^(\d{2}):(\d{2})(?::(\d{2}))?$/', $trimmed, $m)) {
        $h = (int) $m[1];
        $min = (int) $m[2];
        $s = (int) ($m[3] ?? 0);
        if ($h < 24 && $min < 60 && $s < 60) {
            return [
                "type" => "time",
                "value" => transactions_detail_num(transactions_detail_time_fraction($h, $min, $s)),
                "display" => $trimmed,
            ];
        }
    }

    return ["type" => "string", "value" => $trimmed];
}

/** Excel serial day for a Y-M-D, or null if the date is invalid. Uses UTC. */
function transactions_detail_date_serial(string $y, string $mo, string $d): ?float
{
    if (!checkdate((int) $mo, (int) $d, (int) $y) || (int) $y < 1900) {
        return null;
    }
    $ts = gmmktime(0, 0, 0, (int) $mo, (int) $d, (int) $y);
    if ($ts === false) {
        return null;
    }
    return ($ts / 86400) + 25569;
}

/** Fraction of a day for a time of day. */
function transactions_detail_time_fraction(int $h, int $m, int $s): float
{
    return (($h * 3600) + ($m * 60) + $s) / 86400;
}

/** Render a serial as a compact decimal string for the cell <v>. */
function transactions_detail_num(float $serial): string
{
    return rtrim(rtrim(number_format($serial, 8, ".", ""), "0"), ".");
}

/** 0-based column index to Excel letters: 0->A, 25->Z, 26->AA. */
function transactions_detail_col_name(int $idx): string
{
    $col = '';
    $n = $idx + 1;
    while ($n > 0) {
        $n--;
        $col = chr(65 + ($n % 26)) . $col;
        $n = intdiv($n, 26);
    }
    return $col;
}
