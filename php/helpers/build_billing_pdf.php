<?php

require_once __DIR__ . "/pdf_writer.php";

/** Fetch the operation records for a billing invoice's trips. */
function billing_pdf_entries(PDO $conn, array $entryIds): array
{
    $ids = array_values(array_unique(array_filter(array_map("intval", $entryIds), fn($v) => $v > 0)));
    if (empty($ids)) {
        return [];
    }
    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    // Billing trip date = COALESCE(waybill_date, loaded_van_delivery_departure_date), the same
    // basis the invoice selection/ordering uses (see box_banana_fetch_entries). Exposed as
    // `trip_date` so billing_pdf_detail_rows renders and the rows sort by it, keeping the PDF's
    // DATE column aligned with the SAP/preview.
    $sql = "SELECT entry_id, ph, delivered_by_prime_mover, prime_mover, truck, van_alpha, van_number, waybill, tr, gs,
                   load_description, total_load, destination, delivered_to,
                   empty_pullout_location, pullout_location,
                   waybill_date, date_hauled, loaded_van_loading_start_date,
                   loaded_van_delivery_departure_date,
                   pullout_location_departure_date, created_date,
                   (COALESCE(waybill_date, loaded_van_delivery_departure_date))::text AS trip_date
            FROM operations
            WHERE entry_id IN ($placeholders)
            ORDER BY COALESCE(waybill_date, loaded_van_delivery_departure_date) ASC, waybill ASC, entry_id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function billing_pdf_digits($value): string
{
    $text = trim((string) ($value ?? ""));
    if ($text === "") {
        return "";
    }
    $digits = preg_replace('/\D+/', "", $text);
    return $digits !== "" ? $digits : $text;
}

/**
 * Trailer / chassis identifier for the PDF's TRAILER column. Chassis are encoded as a
 * "D"-series (D11, D20) and that "D" is part of the identity, so "D11" stays "D11"
 * (leading zeros on the number trimmed); anything without a "D" prefix reduces to its
 * digits exactly like billing_pdf_digits(). Mirrors billing_trailer_label().
 */
function billing_pdf_trailer($value): string
{
    $s = strtoupper(trim((string) ($value ?? "")));
    $s = preg_replace('/[\s\-_]+/', "", $s);
    if ($s === "") {
        return "";
    }
    if (preg_match('/^D0*(\d+)$/', $s, $m)) {
        return "D" . $m[1];
    }
    return billing_pdf_digits($value);
}

function billing_pdf_first(...$values): string
{
    foreach ($values as $v) {
        $t = trim((string) ($v ?? ""));
        if ($t !== "" && !str_starts_with($t, "0000-")) {
            return $t;
        }
    }
    return "";
}

function billing_pdf_fmt_date(string $raw): string
{
    $raw = substr(trim($raw), 0, 10);
    $ts = strtotime($raw);
    return $ts ? date("m/d/Y", $ts) : "";
}

function billing_pdf_long_date(string $raw): string
{
    $ts = strtotime(substr(trim($raw), 0, 10));
    return $ts ? date("F d, Y", $ts) : $raw;
}

function billing_pdf_money(float $value, int $decimals = 2): string
{
    return number_format($value, $decimals, ".", ",");
}

/**
 * Overlay LOCKED per-entry charges onto already-built PANABO detail rows so a
 * downloaded PDF reflects edits made in the Charges modal. $rows and $entryIds are
 * positional; $storedCharges maps entry_id => peso charge (only the entries that
 * have a locked value — others keep their recomputed charge). Returns the rows with
 * the $chargeKey cell replaced and a recomputed total.
 */
function billing_pdf_apply_stored_charges(array $rows, array $entryIds, string $chargeKey, array $storedCharges): array
{
    $total = 0.0;
    foreach ($rows as $i => $row) {
        $entryId = (int) ($entryIds[$i] ?? 0);
        if (isset($storedCharges[$entryId])) {
            $charge = (float) $storedCharges[$entryId];
            $rows[$i][$chargeKey] = billing_pdf_money($charge, 2);
        } else {
            $charge = (float) preg_replace('/[^0-9.\-]/', "", (string) ($row[$chargeKey] ?? "0"));
        }
        $total += $charge;
    }
    return ["rows" => $rows, "total" => round($total, 2)];
}

/**
 * Statement DESTINATION header. Two styles:
 *  - New (customer has `pdf_origin`): "<pdf_origin> to <distinct delivered locations|…>",
 *    e.g. "TDC Packing Station to DICT CY|PW|Davao". The right side is the distinct
 *    ports the trips actually delivered to.
 *  - Legacy: "<distinct PHs> to <configured destination>".
 */
function billing_pdf_destination(array $entries, array $customer): string
{
    // Some customers use one approved statement route regardless of the
    // individual delivery-location spellings stored on their trip records.
    $fixedDestination = trim((string) ($customer["pdf_destination"] ?? ""));
    if ($fixedDestination !== "") {
        return $fixedDestination;
    }

    $pdfOrigin = trim((string) ($customer["pdf_origin"] ?? ""));
    if ($pdfOrigin !== "") {
        $ports = [];
        foreach ($entries as $entry) {
            $port = billing_pdf_first($entry["delivered_to"] ?? "", $entry["destination"] ?? "");
            if ($port !== "" && !in_array($port, $ports, true)) {
                $ports[] = $port;
            }
        }
        return $ports === [] ? $pdfOrigin : $pdfOrigin . " to " . implode("|", $ports);
    }

    $destination = trim((string) ($customer["destination"] ?? ""));
    $packingHouses = [];
    foreach ($entries as $entry) {
        $ph = trim((string) ($entry["ph"] ?? ""));
        if ($ph !== "" && !in_array($ph, $packingHouses, true)) {
            $packingHouses[] = $ph;
        }
    }
    if ($packingHouses === []) {
        return $destination;
    }
    $origin = implode(" / ", $packingHouses);
    return $destination === "" ? $origin : $origin . " to " . $destination;
}

/**
 * Ordered statement columns for this customer's own trip detail: [key => label].
 * Shared by the PANABO PDF and the Billing page's preview table, so both show the
 * same fields.
 */
function billing_pdf_columns(): array
{
    return [
        "date" => "DATE",
        "tr" => "TRIP RECEIPT",
        "truck" => "TRUCK",
        "trailer" => "TRAILER",
        "genset" => "GENSET",
        "vanA" => "VAN",
        "vanN" => "NUMBER",
        "origin" => "ORIGIN",
        "packing" => "PACKING STATION",
        "port" => "PORT OF LOADING",
        "boxes" => "BOXES",
        "charge" => "HAULING CHARGE",
    ];
}

/**
 * Per-trip detail rows for the SAP (Sumifru) pipeline — one assoc row per trip,
 * keyed by billing_pdf_columns(). Extracted from build_billing_pdf so the Billing
 * page preview can show exactly the same detail the statement prints.
 *
 * @param array $entries          operation rows; either billing_pdf_entries()
 *                                (PDF rebuild) or box_banana_fetch_entries()
 *                                (preview — those carry a resolved `trip_date`)
 * @param array $customer         billing_customers config entry
 * @param float $rate             fallback PHP hauling charge per trip
 * @param array $ratesByEntryId   entry_id => PHP charge for that trip
 * @return array ['rows','entry_ids','total_boxes','total_charge']
 */
function billing_pdf_detail_rows(array $entries, array $customer, float $rate, array $ratesByEntryId = []): array
{
    $rows = [];
    $entryIds = [];
    $totalBoxes = 0.0;
    $totalCharge = 0.0;

    foreach ($entries as $e) {
        $boxesRaw = billing_pdf_first($e["load_description"] ?? "", $e["total_load"] ?? "");
        $boxesNum = is_numeric($boxesRaw) ? (float) $boxesRaw : 0.0;
        $totalBoxes += $boxesNum;
        $entryId = (int) ($e["entry_id"] ?? 0);
        $entryRate = $ratesByEntryId[$entryId] ?? $rate;
        $totalCharge += $entryRate;
        $entryIds[] = $entryId;
        // `trip_date` is already resolved when the rows come from the billing
        // preview fetch; the PDF rebuild passes the raw operational columns.
        $dateRaw = billing_pdf_first(
            $e["trip_date"] ?? "",
            $e["loaded_van_loading_start_date"] ?? "",
            $e["waybill_date"] ?? "",
            $e["date_hauled"] ?? "",
            $e["pullout_location_departure_date"] ?? "",
            substr((string) ($e["created_date"] ?? ""), 0, 10)
        );
        $rows[] = [
            "date" => billing_pdf_fmt_date($dateRaw),
            "tr" => billing_pdf_first($e["waybill"] ?? "", $e["tr"] ?? ""),
            "truck" => billing_pdf_digits(billing_pdf_first($e["delivered_by_prime_mover"] ?? "", $e["truck"] ?? "")),
            // Packing house number for the statement's PH column (e.g. "PH16" -> "16").
            "ph" => (ltrim(preg_replace('/\D/', '', (string) ($e["ph"] ?? "")), '0') ?: trim((string) ($e["ph"] ?? ""))),
            "trailer" => billing_pdf_trailer($e["tr"] ?? ""),
            "genset" => billing_pdf_digits($e["gs"] ?? ""),
            "vanA" => trim((string) ($e["van_alpha"] ?? "")),
            "vanN" => trim((string) ($e["van_number"] ?? "")),
            // Per-trip route: ORIGIN = pull-out location, PACKING STATION = PH,
            // PORT OF LOADING = delivered location. A static value in the customer
            // config still wins (kept for back-compat) — else fall back to the trip.
            "origin" => billing_pdf_first($customer["origin"] ?? "", $e["empty_pullout_location"] ?? "", $e["pullout_location"] ?? ""),
            "packing" => billing_pdf_first($customer["packing_station"] ?? "", $e["ph"] ?? ""),
            "port" => billing_pdf_first($customer["port_of_loading"] ?? "", $e["delivered_to"] ?? "", $e["destination"] ?? ""),
            "boxes" => $boxesNum > 0 ? billing_pdf_money($boxesNum, 0) : ($boxesRaw !== "" ? $boxesRaw : ""),
            "charge" => billing_pdf_money($entryRate, 2),
        ];
    }

    return [
        "rows" => $rows,
        "entry_ids" => $entryIds,
        "total_boxes" => round($totalBoxes, 2),
        "total_charge" => round($totalCharge, 2),
    ];
}

/**
 * Build the printable PANABO TRUCKING SERVICES invoice PDF.
 *
 * @param array  $entries  operation rows (from billing_pdf_entries)
 * @param array  $customer billing_customers config entry
 * @param float  $rate     PHP hauling charge per trip
 * @param float  $forex    USD forex rate
 * @param array  $meta     ['date_from','date_to','sap_doc']
 * @param array  $ratesByEntryId  optional map entry_id => PHP charge for that trip
 *                                 (used by matrix-priced customers); when a trip
 *                                 is absent the flat $rate is used.
 * @param bool   $showUsd  show the FOREX / USD total rows (false for PHP billing)
 * @return string PDF bytes
 */
function build_billing_pdf(array $entries, array $customer, float $rate, float $forex, array $meta, array $ratesByEntryId = [], bool $showUsd = true): string
{
    $pdf = new SimplePDF();

    // Dole Asia's approved statement has a compact reefer-equipment layout:
    // Date, TR, Truck, Trailer, Genset, Van Number, Boxes, Amount.  Keep this
    // as a customer layout instead of changing the wider Sumifru statement.
    if (($customer["pdf_layout"] ?? "") === "dole_asia") {
        return build_dole_asia_billing_pdf($pdf, $entries, $customer, $rate, $forex, $meta, $ratesByEntryId, $showUsd);
    }

    // Column layout (top-left origin). x = left edge of each column.
    $cols = [
        ["k" => "no",      "x" => 30,  "w" => 20,  "h1" => "",        "h2" => "",         "align" => "c"],
        ["k" => "date",    "x" => 50,  "w" => 54,  "h1" => "DATE",    "h2" => "",         "align" => "c"],
        ["k" => "tr",      "x" => 104, "w" => 52,  "h1" => "TRIP",    "h2" => "RECEIPT",  "align" => "c"],
        ["k" => "truck",   "x" => 156, "w" => 34,  "h1" => "TRUCK",   "h2" => "",         "align" => "c"],
        ["k" => "trailer", "x" => 190, "w" => 38,  "h1" => "TRAILER", "h2" => "",         "align" => "c"],
        ["k" => "vanA",    "x" => 228, "w" => 36,  "h1" => "VAN",     "h2" => "",         "align" => "c"],
        ["k" => "vanN",    "x" => 264, "w" => 56,  "h1" => "NUMBER",  "h2" => "",         "align" => "c"],
        ["k" => "origin",  "x" => 320, "w" => 54,  "h1" => "ORIGIN",  "h2" => "",         "align" => "c"],
        ["k" => "packing", "x" => 374, "w" => 54,  "h1" => "PACKING", "h2" => "STATION",  "align" => "c"],
        ["k" => "port",    "x" => 428, "w" => 52,  "h1" => "PORT OF", "h2" => "LOADING",  "align" => "c"],
        ["k" => "boxes",   "x" => 480, "w" => 40,  "h1" => "BOXES",   "h2" => "",         "align" => "r"],
        ["k" => "charge",  "x" => 520, "w" => 62,  "h1" => "HAULING", "h2" => "CHARGE",   "align" => "r"],
    ];
    $tableLeft = 30;
    $tableRight = 582;

    // Customer-specific statement layouts may hide non-billing fields. Reflow
    // the remaining columns across the complete table width so no blank gaps
    // remain (ABC Pantukan omits Origin and Packing Station).
    $hiddenColumns = array_fill_keys(array_map("strval", (array) ($customer["pdf_hide_columns"] ?? [])), true);
    if ($hiddenColumns !== []) {
        $cols = array_values(array_filter($cols, static fn(array $column): bool => !isset($hiddenColumns[$column["k"]])));
        $noWidth = 20.0;
        $remainingWidth = ($tableRight - $tableLeft) - $noWidth;
        $weightTotal = array_sum(array_map(static fn(array $column): float => $column["k"] === "no" ? 0.0 : (float) $column["w"], $cols));
        $x = $tableLeft;
        foreach ($cols as &$column) {
            $column["x"] = $x;
            $column["w"] = $column["k"] === "no"
                ? $noWidth
                : ($weightTotal > 0 ? $remainingWidth * ((float) $column["w"] / $weightTotal) : 0.0);
            $x += $column["w"];
        }
        unset($column);
    }

    // Precompute row data (shared with the Billing page preview).
    $detail = billing_pdf_detail_rows($entries, $customer, $rate, $ratesByEntryId);
    $rows = $detail["rows"];
    $totalBoxes = $detail["total_boxes"];
    $totalCharge = $detail["total_charge"];
    $statementDestination = billing_pdf_destination($entries, $customer);

    $rowH = 14.0;
    $headerTop = 165.0;          // y where the table header row starts (tight under the header block)
    $bottomLimit = 740.0;        // leave room at page bottom
    $rowsPerPage = (int) floor(($bottomLimit - ($headerTop + 22)) / $rowH);
    if ($rowsPerPage < 1) {
        $rowsPerPage = 1;
    }
    $totalRowsNeeded = count($rows) + 3; // + totals block (3 rows) on last page
    $pageCount = max(1, (int) ceil(count($rows) / $rowsPerPage));
    // Ensure the totals block fits; if last page is full, add a page.
    if ((count($rows) % $rowsPerPage) === 0 && count($rows) > 0) {
        // totals go on a fresh page only if no room — keep simple: they fit under
    }

    $drawPageChrome = function (SimplePDF $pdf, int $pageNo, int $pageCount, array $customer, array $meta) use ($statementDestination) {
        $pdf->textCenter(306, 775, "$pageNo of $pageCount", 8);
        $pdf->text(30, 60, "PANABO TRUCKING SERVICES, INC.", 13, true);
        $pdf->text(30, 74, "Prk. 09 A. O. Floirendo 8105 City of Panabo Davao del Norte Philippines", 8);
        $pdf->text(30, 85, "TIN: VAT Reg.: 000-982-500-000", 8);

        $labelX = 30;
        $valX = 120;
        $pdf->text($labelX, 112, "CHARGE TO:", 8, true);
        $pdf->text($valX, 112, $customer["bill_to_name"] ?? "", 8, true);
        $pdf->text($labelX, 124, "ACTIVITY:", 8, true);
        $pdf->text($valX, 124, $customer["activity"] ?? "", 8, true);
        $pdf->text($labelX, 136, "DESTINATION:", 8, true);
        $pdf->text($valX, 136, $statementDestination, 8, true);
        $pdf->text($labelX, 148, "PERIOD COVERED:", 8, true);
        $pdf->text($valX, 148, billing_pdf_long_date($meta["date_from"]) . "    to    " . billing_pdf_long_date($meta["date_to"]), 8, true);
    };

    $drawHeaderRow = function (SimplePDF $pdf, array $cols, float $top, float $tableLeft, float $tableRight) {
        $h = 22.0;
        // outer + column borders for the header
        $pdf->line($tableLeft, $top, $tableRight, $top, 0.7);
        $pdf->line($tableLeft, $top + $h, $tableRight, $top + $h, 0.7);
        foreach ($cols as $c) {
            $pdf->line($c["x"], $top, $c["x"], $top + $h, 0.7);
        }
        $pdf->line($tableRight, $top, $tableRight, $top + $h, 0.7);
        foreach ($cols as $c) {
            $cx = $c["x"] + $c["w"] / 2;
            if ($c["h2"] !== "") {
                $pdf->textCenter($cx, $top + 9, $c["h1"], 6.5, true);
                $pdf->textCenter($cx, $top + 18, $c["h2"], 6.5, true);
            } elseif ($c["h1"] !== "") {
                $pdf->textCenter($cx, $top + 13.5, $c["h1"], 6.5, true);
            }
        }
        return $top + $h;
    };

    $rowIndex = 0;
    for ($p = 0; $p < $pageCount; $p++) {
        $pdf->addPage();
        $drawPageChrome($pdf, $p + 1, $pageCount, $customer, $meta);
        $y = $drawHeaderRow($pdf, $cols, $headerTop, $tableLeft, $tableRight);

        $pageRows = array_slice($rows, $rowIndex, $rowsPerPage);
        foreach ($pageRows as $r) {
            $rowIndex++;
            // cells (borderless rows — dividers drawn once after the loop)
            $textY = $y + 9.5;
            foreach ($cols as $c) {
                $val = $c["k"] === "no" ? sprintf("%02d.]", $rowIndex) : (string) ($r[$c["k"]] ?? "");
                if ($val === "") {
                    continue;
                }
                // Shrink the font just enough that a long value (e.g. a delivered-location
                // like "DOLE(PANABO WHARF)") stays inside its column instead of spilling over
                // the neighbouring cell. Width scales linearly with size, so fit exactly;
                // floor at 4pt so it stays legible.
                $avail = $c["w"] - 4;
                $w7 = $pdf->textWidth($val, 7);
                $fit = ($w7 > $avail && $w7 > 0) ? max(4.0, 7.0 * $avail / $w7) : 7.0;
                if ($c["align"] === "r") {
                    $pdf->textRight($c["x"] + $c["w"] - 3, $textY, $val, $fit);
                } elseif ($c["align"] === "c") {
                    $pdf->textCenter($c["x"] + $c["w"] / 2, $textY, $val, $fit);
                } else {
                    $pdf->text($c["x"] + 2, $textY, $val, $fit);
                }
            }
            $y += $rowH;
        }

        // Borderless body: no side or column dividers, no per-row lines — only the
        // closing rule under the last row (separates the body from the totals block).
        $pdf->line($tableLeft, $y, $tableRight, $y, 0.9);

        // Totals block on the last page.
        if ($p === $pageCount - 1) {
            $boxesCol = null;
            $chargeCol = null;
            foreach ($cols as $c) {
                if ($c["k"] === "boxes") $boxesCol = $c;
                if ($c["k"] === "charge") $chargeCol = $c;
            }
            $usd = $forex > 0 ? $totalCharge / $forex : 0.0;
            $totalsRows = [
                ["label" => "Total charges in Peso", "boxes" => billing_pdf_money($totalBoxes, 0), "right" => billing_pdf_money($totalCharge, 2)],
            ];
            if ($showUsd) {
                $totalsRows[] = ["label" => "FOREX rate (USD)", "boxes" => "", "right" => billing_pdf_money($forex, 3)];
                $totalsRows[] = ["label" => "Total charges in Dollar (US)", "boxes" => "", "right" => billing_pdf_money($usd, 2)];
            }
            foreach ($totalsRows as $tr) {
                // Outer frame + row separators only — no internal column dividers.
                $pdf->line($tableLeft, $y + $rowH, $tableRight, $y + $rowH, 0.4);
                $pdf->line($tableLeft, $y, $tableLeft, $y + $rowH, 0.4);
                $pdf->line($tableRight, $y, $tableRight, $y + $rowH, 0.4);
                $textY = $y + 9.5;
                $pdf->text($tableLeft + 3, $textY, $tr["label"], 7.5, true);
                if ($tr["boxes"] !== "") {
                    $pdf->textRight($boxesCol["x"] + $boxesCol["w"] - 3, $textY, $tr["boxes"], 7.5, true);
                }
                $pdf->textRight($chargeCol["x"] + $chargeCol["w"] - 3, $textY, $tr["right"], 7.5, true);
                $y += $rowH;
            }
        }
    }

    return $pdf->output();
}

/** Build Dole Asia's approved compact reefer-van billing statement. */
function build_dole_asia_billing_pdf(SimplePDF $pdf, array $entries, array $customer, float $rate, float $forex, array $meta, array $ratesByEntryId, bool $showUsd): string
{
    // TDC - Dole Asia's approved statement adds a PH (packing house) column next to Truck;
    // the columns are re-flowed to fit it within the same 30..582 table width. Other
    // dole_asia customers (ABC Lupon) keep the original 10-column layout.
    if (!empty($customer["show_ph"])) {
        $cols = [
            ["k" => "no",      "x" => 30,  "w" => 22, "h1" => "",       "h2" => "",        "align" => "c"],
            ["k" => "date",    "x" => 52,  "w" => 62, "h1" => "DATE",   "h2" => "",        "align" => "c"],
            ["k" => "tr",      "x" => 114, "w" => 56, "h1" => "TRIP",   "h2" => "RECEIPT", "align" => "c"],
            ["k" => "truck",   "x" => 170, "w" => 40, "h1" => "TRUCK",  "h2" => "",        "align" => "c"],
            ["k" => "ph",      "x" => 210, "w" => 28, "h1" => "PH",     "h2" => "",        "align" => "c"],
            ["k" => "trailer", "x" => 238, "w" => 42, "h1" => "TRAILER","h2" => "",        "align" => "c"],
            ["k" => "genset",  "x" => 280, "w" => 42, "h1" => "GENSET", "h2" => "",        "align" => "c"],
            ["k" => "vanA",    "x" => 322, "w" => 38, "h1" => "VAN",    "h2" => "",        "align" => "c"],
            ["k" => "vanN",    "x" => 360, "w" => 68, "h1" => "NUMBER", "h2" => "",        "align" => "c"],
            ["k" => "boxes",   "x" => 428, "w" => 60, "h1" => "BOXES",  "h2" => "",        "align" => "r"],
            ["k" => "charge",  "x" => 488, "w" => 94, "h1" => "AMOUNT", "h2" => "",        "align" => "r"],
        ];
    } else {
        $cols = [
            ["k" => "no",      "x" => 30,  "w" => 22, "h1" => "",       "h2" => "",        "align" => "c"],
            ["k" => "date",    "x" => 52,  "w" => 65, "h1" => "DATE",   "h2" => "",        "align" => "c"],
            ["k" => "tr",      "x" => 117, "w" => 58, "h1" => "TRIP",   "h2" => "RECEIPT", "align" => "c"],
            ["k" => "truck",   "x" => 175, "w" => 41, "h1" => "TRUCK",  "h2" => "",        "align" => "c"],
            ["k" => "trailer", "x" => 216, "w" => 43, "h1" => "TRAILER","h2" => "",        "align" => "c"],
            ["k" => "genset",  "x" => 259, "w" => 44, "h1" => "GENSET", "h2" => "",        "align" => "c"],
            ["k" => "vanA",    "x" => 303, "w" => 40, "h1" => "VAN",    "h2" => "",        "align" => "c"],
            ["k" => "vanN",    "x" => 343, "w" => 76, "h1" => "NUMBER", "h2" => "",        "align" => "c"],
            ["k" => "boxes",   "x" => 419, "w" => 64, "h1" => "BOXES",  "h2" => "",        "align" => "r"],
            ["k" => "charge",  "x" => 483, "w" => 99, "h1" => "AMOUNT", "h2" => "",        "align" => "r"],
        ];
    }
    $left = 30.0;
    $right = 582.0;
    $headerTop = 165.0;          // tight under the header block (was 210)
    $headerH = 22.0;
    $rowH = 14.0;
    $bottomLimit = 740.0;
    $rowsPerPage = max(1, (int) floor(($bottomLimit - ($headerTop + $headerH + (3 * $rowH))) / $rowH));

    $detail = billing_pdf_detail_rows($entries, $customer, $rate, $ratesByEntryId);
    $rows = $detail["rows"];
    $pageCount = max(1, (int) ceil(count($rows) / $rowsPerPage));
    $destination = billing_pdf_destination($entries, $customer);

    $drawChrome = static function (SimplePDF $doc, int $pageNo, int $count) use ($customer, $meta, $destination): void {
        $doc->textCenter(306, 775, "$pageNo of $count", 8);
        $doc->text(30, 60, "PANABO TRUCKING SERVICES, INC.", 13, true);
        $doc->text(30, 74, "Prk. 09 A. O. Floirendo 8105 City of Panabo Davao del Norte Philippines", 8);
        $doc->text(30, 85, "TIN: VAT Reg.: 000-982-500-000", 8);
        $doc->text(30, 112, "CHARGE TO:", 8, true);
        $doc->text(120, 112, $customer["bill_to_name"] ?? "", 8, true);
        $doc->text(30, 124, "ACTIVITY:", 8, true);
        $doc->text(120, 124, $customer["activity"] ?? "", 8, true);
        $doc->text(30, 136, "DESTINATION:", 8, true);
        $doc->text(120, 136, $destination, 8, true);
        $doc->text(30, 148, "PERIOD COVERED:", 8, true);
        $doc->text(120, 148, billing_pdf_long_date((string) ($meta["date_from"] ?? "")) . "    to    " . billing_pdf_long_date((string) ($meta["date_to"] ?? "")), 8, true);
    };
    $drawHeader = static function (SimplePDF $doc, array $columns, float $top) use ($left, $right, $headerH): float {
        $doc->line($left, $top, $right, $top, 0.7);
        $doc->line($left, $top + $headerH, $right, $top + $headerH, 0.7);
        foreach ($columns as $column) $doc->line($column["x"], $top, $column["x"], $top + $headerH, 0.7);
        $doc->line($right, $top, $right, $top + $headerH, 0.7);
        foreach ($columns as $column) {
            if ($column["h1"] === "") continue;
            $center = $column["x"] + ($column["w"] / 2);
            if ($column["h2"] !== "") {
                $doc->textCenter($center, $top + 9, $column["h1"], 6.5, true);
                $doc->textCenter($center, $top + 18, $column["h2"], 6.5, true);
            } else {
                $doc->textCenter($center, $top + 13.5, $column["h1"], 6.5, true);
            }
        }
        return $top + $headerH;
    };

    $rowIndex = 0;
    for ($page = 0; $page < $pageCount; $page++) {
        $pdf->addPage();
        $drawChrome($pdf, $page + 1, $pageCount);
        $y = $drawHeader($pdf, $cols, $headerTop);
        foreach (array_slice($rows, $rowIndex, $rowsPerPage) as $row) {
            $rowIndex++;
            foreach ($cols as $column) {
                $value = $column["k"] === "no" ? sprintf("%02d.]", $rowIndex) : (string) ($row[$column["k"]] ?? "");
                if ($value === "") continue;
                $available = $column["w"] - 4;
                $width = $pdf->textWidth($value, 7);
                $size = $width > $available && $width > 0 ? max(4.0, 7.0 * $available / $width) : 7.0;
                $textY = $y + 9.5;
                if ($column["align"] === "r") $pdf->textRight($column["x"] + $column["w"] - 3, $textY, $value, $size);
                else $pdf->textCenter($column["x"] + ($column["w"] / 2), $textY, $value, $size);
            }
            $y += $rowH;
        }

        // Borderless body: no side or column dividers, no per-row lines — only the
        // closing rule under the last row (separates the body from the totals block).
        $pdf->line($left, $y, $right, $y, 0.9);

        if ($page === $pageCount - 1) {
            $usd = $forex > 0 ? $detail["total_charge"] / $forex : 0.0;
            $totals = [["TOTAL CHARGES>>>>>", billing_pdf_money($detail["total_boxes"], 0), billing_pdf_money($detail["total_charge"], 2)]];
            // VAT customers (Master Data → Customer SAP Codes): show a 12% VAT line and the
            // VAT-inclusive amount due. This is a PDF-only presentation — the SAP export and
            // the dashboard/preview figures stay net.
            if (!empty($customer["is_vat"])) {
                $vat = $detail["total_charge"] * 0.12;
                $totals[] = ["VAT (12%)", "", billing_pdf_money($vat, 2)];
                $totals[] = ["TOTAL AMOUNT DUE", "", billing_pdf_money($detail["total_charge"] + $vat, 2)];
            }
            if ($showUsd) {
                $totals[] = ["FOREX rate (USD)", "", billing_pdf_money($forex, 3)];
                $totals[] = ["TOTAL CHARGES IN DOLLAR", "", billing_pdf_money($usd, 2)];
            }
            foreach ($totals as [$label, $boxes, $amount]) {
                // Outer frame + row separators only — no internal column dividers.
                $pdf->line($left, $y + $rowH, $right, $y + $rowH, 0.5);
                $pdf->line($left, $y, $left, $y + $rowH, 0.5);
                $pdf->line($right, $y, $right, $y + $rowH, 0.5);
                $pdf->text($left + 3, $y + 9.5, $label, 7.5, true);
                if ($boxes !== "") $pdf->textRight(480, $y + 9.5, $boxes, 7.5, true);
                $pdf->textRight(579, $y + 9.5, $amount, 7.5, true);
                $y += $rowH;
            }
        }
    }
    return $pdf->output();
}
