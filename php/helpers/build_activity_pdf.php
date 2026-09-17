<?php

require_once __DIR__ . "/pdf_writer.php";

/**
 * Generic PANABO TRUCKING SERVICES statement PDF for a billing activity.
 * Renders the standard header block (CHARGE TO / ACTIVITY / DESTINATION / SAP
 * DOC / PERIOD COVERED) then a bordered table built from $columns + $rows, with
 * a TOTAL row. Used for the chassis / genset / container-van / fuel statements.
 *
 * @param array  $header   ['charge_to','activity','destination','sap_doc','date_from','date_to']
 * @param array  $columns  ordered [key => label]
 * @param array  $rows     list of assoc rows keyed by the column keys (values pre-formatted strings)
 * @param string $totalKey the column key that holds the line charge (summed into TOTAL)
 * @param float  $total    precomputed total for the charge column
 * @param float  $forex    USD forex rate; when > 0 a FOREX rate row and a
 *                         "TOTAL CHARGES IN DOLLAR" row are added to the totals block
 * @return string PDF bytes
 */
function build_activity_pdf(array $header, array $columns, array $rows, string $totalKey, float $total, float $forex = 0.0, bool $vat = false): string
{
    $pdf = new SimplePDF();

    $keys = array_keys($columns);
    $colCount = count($keys);

    // Lay columns across the printable width (30..582 = 552pt). First column
    // (No.) is fixed narrow; the rest share the remaining width, with the date
    // and charge columns a little wider.
    $left = 30.0;
    $right = 582.0;
    $noW = 20.0;
    $avail = ($right - $left) - $noW;
    $weights = [];
    foreach ($keys as $k) {
        // The withdrawal/delivery cells include a full date, time, and AM/PM.
        // Give them additional width before resorting to smaller type.
        $weights[$k] = in_array($k, ["withdrawn", "delivered"], true)
            ? 2.0
            : (in_array($k, ["date", "charge", "van", "route"], true) ? 1.5 : 1.0);
    }
    $weightSum = array_sum($weights);
    $cols = [];
    $x = $left;
    $cols[] = ["k" => "__no", "x" => $x, "w" => $noW, "label" => ""];
    $x += $noW;
    foreach ($keys as $k) {
        $w = $avail * ($weights[$k] / $weightSum);
        $cols[] = ["k" => $k, "x" => $x, "w" => $w, "label" => $columns[$k]];
        $x += $w;
    }

    $longDate = function (string $raw): string {
        $ts = strtotime(substr(trim($raw), 0, 10));
        return $ts ? date("F d, Y", $ts) : $raw;
    };

    $drawChrome = function (SimplePDF $pdf, int $pageNo, int $pageCount) use ($header, $longDate) {
        $pdf->textCenter(306, 775, "$pageNo of $pageCount", 8);
        $pdf->text(30, 60, "PANABO TRUCKING SERVICES, INC.", 13, true);
        $pdf->text(30, 74, "Prk. 09 A. O. Floirendo 8105 City of Panabo Davao del Norte Philippines", 8);
        $pdf->text(30, 85, "TIN: VAT Reg.: 000-982-500-000", 8);
        $lx = 30;
        $vx = 120;
        $pdf->text($lx, 112, "CHARGE TO:", 8, true);
        $pdf->text($vx, 112, $header["charge_to"] ?? "", 8, true);
        $pdf->text($lx, 124, "ACTIVITY:", 8, true);
        $pdf->text($vx, 124, $header["activity"] ?? "", 8, true);
        $pdf->text($lx, 136, "DESTINATION:", 8, true);
        $pdf->text($vx, 136, $header["destination"] ?? "", 8, true);
        $pdf->text($lx, 148, "PERIOD COVERED:", 8, true);
        $pdf->text($vx, 148, $longDate($header["date_from"] ?? "") . "    to    " . $longDate($header["date_to"] ?? ""), 8, true);
    };

    $headerTop = 196.0;
    $headH = 24.0;
    $rowH = 14.0;
    $bottomLimit = 760.0;
    $rowsPerPage = max(1, (int) floor(($bottomLimit - ($headerTop + $headH)) / $rowH) - 3);
    $pageCount = max(1, (int) ceil(count($rows) / $rowsPerPage));

    $drawHeader = function (SimplePDF $pdf, array $cols, float $top) use ($right, $left, $headH) {
        $pdf->line($left, $top, $right, $top, 0.7);
        $pdf->line($left, $top + $headH, $right, $top + $headH, 0.7);
        foreach ($cols as $c) {
            $pdf->line($c["x"], $top, $c["x"], $top + $headH, 0.7);
        }
        $pdf->line($right, $top, $right, $top + $headH, 0.7);
        foreach ($cols as $c) {
            if ($c["label"] === "") {
                continue;
            }
            // Wrap by rendered width (not character count): narrow headers such
            // as DATE DELIVERED and TOTAL HOUR must never cross their borders.
            $words = explode(" ", $c["label"]);
            $lines = [];
            $cur = "";
            $available = $c["w"] - 4;
            foreach ($words as $w) {
                $try = $cur === "" ? $w : "$cur $w";
                if ($cur !== "" && $pdf->textWidth($try, 6) > $available) {
                    if ($cur !== "") {
                        $lines[] = $cur;
                    }
                    $cur = $w;
                } else {
                    $cur = $try;
                }
            }
            if ($cur !== "") {
                $lines[] = $cur;
            }
            if (count($lines) > 2) {
                $lines = [$lines[0], implode(" ", array_slice($lines, 1))];
            }
            $cx = $c["x"] + $c["w"] / 2;
            if (count($lines) === 1) {
                $width = $pdf->textWidth($lines[0], 6);
                $size = $width > $available && $width > 0 ? max(4.3, 6 * $available / $width) : 6;
                $pdf->textCenter($cx, $top + 14, $lines[0], $size, true);
            } else {
                foreach ($lines as $index => $line) {
                    $width = $pdf->textWidth($line, 6);
                    $size = $width > $available && $width > 0 ? max(4.3, 6 * $available / $width) : 6;
                    $pdf->textCenter($cx, $top + (9 + ($index * 9)), $line, $size, true);
                }
            }
        }
        return $top + $headH;
    };

    $rowIndex = 0;
    for ($p = 0; $p < $pageCount; $p++) {
        $pdf->addPage();
        $drawChrome($pdf, $p + 1, $pageCount);
        $y = $drawHeader($pdf, $cols, $headerTop);

        $pageRows = array_slice($rows, $rowIndex, $rowsPerPage);
        foreach ($pageRows as $r) {
            $rowIndex++;
            $textY = $y + 9.5;
            foreach ($cols as $c) {
                if ($c["k"] === "__no") {
                    $pdf->textCenter($c["x"] + $c["w"] / 2, $textY, sprintf("%02d.]", $rowIndex), 6.5);
                    continue;
                }
                $val = (string) ($r[$c["k"]] ?? "");
                if ($val === "") {
                    continue;
                }
                // Date/time values now include AM/PM. Scale any long cell value
                // just enough to keep it within its own column instead of
                // crossing a table border into the adjacent cell.
                $available = $c["w"] - 4;
                $width = $pdf->textWidth($val, 6.5);
                $size = ($width > $available && $width > 0)
                    ? max(4.5, 6.5 * $available / $width)
                    : 6.5;
                $rightAlign = in_array($c["k"], ["charge", "rate", "total_hours", "excess_hours", "boxes", "consumption", "price_liter", "runtime", "hubo_start", "hubo_end"], true);
                if ($rightAlign) {
                    $pdf->textRight($c["x"] + $c["w"] - 3, $textY, $val, $size);
                } else {
                    $pdf->textCenter($c["x"] + $c["w"] / 2, $textY, $val, $size);
                }
            }
            $y += $rowH;
        }

        // Borderless body: no side or column dividers, no per-row lines — only the
        // closing rule under the last row (separates the body from the totals block).
        $pdf->line($left, $y, $right, $y, 0.9);

        if ($p === $pageCount - 1) {
            // Totals block: a bold "TOTAL CHARGES>>>>>" row that carries a subtotal
            // for each measure column, then (USD customers) a FOREX rate row and a
            // dollar-total row. Identifier/rate columns are never summed.
            $summable = [
                "total_hours" => 2,
                "excess_hours" => 2,
                "no_of_days"   => 1,
                "boxes"        => 0,
                "consumption"  => 2,
            ];
            $subtotals = [];
            foreach ($summable as $k => $decimals) {
                $present = false;
                $sum = 0.0;
                foreach ($rows as $r) {
                    if (!isset($r[$k]) || $r[$k] === "") {
                        continue;
                    }
                    $present = true;
                    $sum += (float) str_replace(",", "", (string) $r[$k]);
                }
                if ($present) {
                    $subtotals[$k] = number_format($sum, $decimals, ".", ",");
                }
            }

            $mainCells = $subtotals;
            $mainCells[$totalKey] = number_format($total, 2, ".", ",");
            $totalsRows = [["label" => "TOTAL CHARGES>>>>>", "cells" => $mainCells]];
            if ($forex > 0) {
                $totalsRows[] = ["label" => "FOREX rate (USD)", "cells" => [$totalKey => number_format($forex, 3, ".", ",")]];
                $totalsRows[] = ["label" => "TOTAL CHARGES IN DOLLAR", "cells" => [$totalKey => number_format($total / $forex, 2, ".", ",")]];
            }
            // 12% VAT (PDF-only): a VAT line + VAT-inclusive amount due (the SAP amounts stay
            // VAT-exclusive). Used by DICT Industrial Waste.
            if ($vat) {
                $vatAmt = round($total * 0.12, 2);
                $totalsRows[] = ["label" => "VAT (12%)", "cells" => [$totalKey => number_format($vatAmt, 2, ".", ",")]];
                $totalsRows[] = ["label" => "TOTAL AMOUNT DUE", "cells" => [$totalKey => number_format($total + $vatAmt, 2, ".", ",")]];
            }

            foreach ($totalsRows as $tr) {
                // Outer frame + row separators only — no internal column dividers.
                $pdf->line($left, $y + $rowH, $right, $y + $rowH, 0.5);
                $pdf->line($left, $y, $left, $y + $rowH, 0.5);
                $pdf->line($right, $y, $right, $y + $rowH, 0.5);
                $pdf->text($left + 4, $y + 9.5, $tr["label"], 7.5, true);
                foreach ($cols as $c) {
                    if (!array_key_exists($c["k"], $tr["cells"])) {
                        continue;
                    }
                    $pdf->textRight($c["x"] + $c["w"] - 3, $y + 9.5, $tr["cells"][$c["k"]], 7.5, true);
                }
                $y += $rowH;
            }
        }
    }

    return $pdf->output();
}
