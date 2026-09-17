<?php
/** Download the hauling Summary Billing statement as an Excel-compatible file. */
include "../config/config.php";
require_once __DIR__ . "/../helpers/ensure_customer_billing_schema.php";
require_once __DIR__ . "/../helpers/billing_customers.php";
require_once __DIR__ . "/../helpers/build_customer_billing.php";
require_once __DIR__ . "/../helpers/build_billing_pdf.php";
require_once __DIR__ . "/../helpers/sumifru_rate.php";

ensure_customer_billing_schema($conn);
$id = (int) ($_GET["id"] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit("Missing invoice id.");
}

$invoiceStmt = $conn->prepare("SELECT customer_key, reference, date_from, date_to, forex_rate, file_name FROM billing_invoices WHERE invoice_id = ? AND status <> 'deleted' LIMIT 1");
$invoiceStmt->execute([$id]);
$invoice = $invoiceStmt->fetch(PDO::FETCH_ASSOC);
if (!$invoice) {
    http_response_code(404);
    exit("Invoice not found.");
}
$customer = billing_customer((string) $invoice["customer_key"]);
if ($customer === null) {
    http_response_code(404);
    exit("Customer configuration not found.");
}

$entryStmt = $conn->prepare("SELECT entry_id FROM billing_invoice_entries WHERE invoice_id = ?");
$entryStmt->execute([$id]);
$entryIds = array_map("intval", $entryStmt->fetchAll(PDO::FETCH_COLUMN));
$entries = billing_pdf_entries($conn, $entryIds);
$manualForexByEntry = [];
if (!empty($entryIds)) {
    $placeholders = implode(",", array_fill(0, count($entryIds), "?"));
    $manualStmt = $conn->prepare("SELECT entry_id, forex_rate FROM billing_invoice_entry_forex WHERE invoice_id = ? AND entry_id IN ($placeholders)");
    $manualStmt->execute(array_merge([$id], $entryIds));
    foreach ($manualStmt->fetchAll(PDO::FETCH_ASSOC) as $manualRow) {
        $manualForexByEntry[(int) $manualRow["entry_id"]] = (float) $manualRow["forex_rate"];
    }
}

// Keep the Excel Forex footer consistent with the PDF: every trip must have one
// effective rate, and all effective rates must be identical.
$effectiveForex = null;
foreach ($entries as $entry) {
    $entryId = (int) ($entry["entry_id"] ?? 0);
    $tripDate = billing_pdf_first(
        $entry["loaded_van_loading_start_date"] ?? "",
        $entry["waybill_date"] ?? "",
        $entry["date_hauled"] ?? "",
        $entry["pullout_location_departure_date"] ?? "",
        substr((string) ($entry["created_date"] ?? ""), 0, 10)
    );
    $lineForex = (float) ($manualForexByEntry[$entryId] ?? 0);
    if ($lineForex <= 0) {
        $lineForex = billing_forex_rate($conn, substr($tripDate, 0, 10), (string) $invoice["customer_key"]);
    }
    if ($lineForex <= 0 || ($effectiveForex !== null && abs($effectiveForex - $lineForex) > 0.000001)) {
        $effectiveForex = 0.0;
        break;
    }
    $effectiveForex = $lineForex;
}
$rate = billing_customer_rate($conn, (string) ($customer["rate_code"] ?? ""));
$ratesByEntryId = [];
if (($customer["pricing"] ?? "") === "lane_tier") {
    foreach ($entries as $entry) {
        $tripDate = billing_pdf_first(
            $entry["loaded_van_loading_start_date"] ?? "",
            $entry["waybill_date"] ?? "",
            $entry["date_hauled"] ?? "",
            $entry["pullout_location_departure_date"] ?? "",
            substr((string) ($entry["created_date"] ?? ""), 0, 10)
        );
        $ratesByEntryId[(int) $entry["entry_id"]] = sumifru_resolve_rate($conn, $entry, substr($tripDate, 0, 10))["rate"];
    }
}
$detail = billing_pdf_detail_rows($entries, $customer, $rate, $ratesByEntryId);
$columns = billing_pdf_columns();
$statementDestination = billing_pdf_destination($entries, $customer);
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$base = preg_replace('/\.xlsx$/i', '', (string) $invoice["file_name"]) ?: "Summary_Billing_" . $id;
$forex = strtoupper((string) ($customer["document_currency"] ?? "PHP")) === "USD" ? (float) ($effectiveForex ?? 0) : 0.0;
$usdTotal = $forex > 0 ? $detail["total_charge"] / $forex : 0.0;
if (!class_exists("ZipArchive")) {
    http_response_code(500);
    exit("ZipArchive extension is required for Excel export.");
}

$col = static function (int $number): string {
    $name = "";
    while ($number > 0) {
        $number--;
        $name = chr(65 + ($number % 26)) . $name;
        $number = intdiv($number, 26);
    }
    return $name;
};
$xml = static function ($value): string { return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, "UTF-8"); };
$cell = static function (string $ref, $value, int $style = 0) use ($xml): string {
    if ($value === null || $value === "") return '<c r="' . $ref . '" s="' . $style . '"/>';
    return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . $xml($value) . '</t></is></c>';
};
$rows = [];
$addRow = static function (array $values, int $style = 0) use (&$rows, $cell, $col): void {
    $rowNumber = count($rows) + 1;
    $cells = "";
    foreach ($values as $index => $value) $cells .= $cell($col($index + 1) . $rowNumber, $value, $style);
    $rows[] = '<row r="' . $rowNumber . '">' . $cells . '</row>';
};
$addRow(["PANABO TRUCKING SERVICES, INC.", "", "", "", "", "", "", "", "", "", "", ""], 1);
$addRow(["Prk. 09 A. O. Floirendo 8105 City of Panabo Davao del Norte Philippines"]);
$addRow(["TIN: VAT Reg.: 000-982-500-000"]);
$addRow([]);
$addRow(["CHARGE TO:", $customer["bill_to_name"] ?? ""], 1);
$addRow(["ACTIVITY:", $customer["activity"] ?? ""], 1);
$addRow(["DESTINATION:", $statementDestination], 1);
$addRow(["SAP DOC. NO.", ""], 1);
$addRow(["PERIOD COVERED:", billing_pdf_long_date($invoice["date_from"]) . " to " . billing_pdf_long_date($invoice["date_to"])], 1);
$addRow([]);
$addRow(array_merge([""], array_values($columns)), 2);
foreach ($detail["rows"] as $index => $row) $addRow(array_merge([str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT)], array_map(static fn($key) => $row[$key] ?? "", array_keys($columns))), 3);
$footerStart = count($rows) + 1;
$addRow(array_merge(["Total charges in Peso"], array_fill(0, 9, ""), [billing_pdf_money($detail["total_boxes"], 0), billing_pdf_money($detail["total_charge"], 2)]), 4);
if ($forex > 0) {
    $addRow(array_merge(["FOREX rate (USD)"], array_fill(0, 10, ""), [billing_pdf_money($forex, 3)]), 4);
    $addRow(array_merge(["Total charges in Dollar (US)"], array_fill(0, 10, ""), [billing_pdf_money($usdTotal, 2)]), 4);
}
$mergeCells = ['A1:L1', 'A' . $footerStart . ':J' . $footerStart];
if ($forex > 0) {
    $mergeCells[] = 'A' . ($footerStart + 1) . ':K' . ($footerStart + 1);
    $mergeCells[] = 'A' . ($footerStart + 2) . ':K' . ($footerStart + 2);
}
$mergeXml = '<mergeCells count="' . count($mergeCells) . '">' . implode('', array_map(static fn($ref) => '<mergeCell ref="' . $ref . '"/>', $mergeCells)) . '</mergeCells>';
$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"/></sheetViews><cols>'
    . '<col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="13" customWidth="1"/><col min="3" max="3" width="14" customWidth="1"/><col min="4" max="5" width="10" customWidth="1"/><col min="6" max="6" width="9" customWidth="1"/><col min="7" max="7" width="15" customWidth="1"/><col min="8" max="10" width="16" customWidth="1"/><col min="11" max="11" width="11" customWidth="1"/><col min="12" max="12" width="16" customWidth="1"/></cols><sheetData>' . implode("", $rows) . '</sheetData>' . $mergeXml . '</worksheet>';
$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="10"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF2F2F2"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/></border><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border></borders><cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyFont="1"/><xf numFmtId="0" fontId="1" fillId="1" borderId="1" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1"/><xf numFmtId="0" fontId="1" fillId="0" borderId="1" applyFont="1" applyBorder="1"/></cellXfs></styleSheet>';
$tmp = tempnam(sys_get_temp_dir(), "summary_") . ".xlsx";
$zip = new ZipArchive();
$zip->open($tmp, ZipArchive::CREATE);
$zip->addFromString("[Content_Types].xml", '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
$zip->addFromString("_rels/.rels", '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
$zip->addFromString("xl/workbook.xml", '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Summary Billing" sheetId="1" r:id="rId1"/></sheets></workbook>');
$zip->addFromString("xl/_rels/workbook.xml.rels", '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
$zip->addFromString("xl/styles.xml", $styles);
$zip->addFromString("xl/worksheets/sheet1.xml", $sheet);
$zip->close();
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header('Content-Disposition: attachment; filename="' . $base . '_Summary_Billing.xlsx"');
header("Content-Length: " . filesize($tmp));
readfile($tmp);
@unlink($tmp);
