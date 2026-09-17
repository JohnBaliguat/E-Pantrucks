<?php

/**
 * Billing document numbers — a human-friendly, unique id printed on each
 * generated billing (file name + SAP Reference column) so a billing the
 * customer returns can be looked up, previewed and regenerated.
 *
 * Format: PREFIX-YYYY-NNN, sequence per customer per year (e.g. ABCCAT-2026-007).
 */

/** Short document-number prefix for a customer key. */
function billing_document_prefix(string $customerKey): string
{
    $map = [
        "sumifru_containerized" => "SUMICON",
        "abc_pantukan" => "ABCPAN",
        "abc_cateel" => "ABCCAT",
        "abc_donmar" => "ABCDON",
        "tdc_goodfarmer" => "TDCGOOD",
        "tdc_farmind" => "TDCFARM",
        "dict_van_shuttling" => "DICTVAN",
        "abc_pantukan_kds" => "ABCPANKD",
        "abc_donmar_kds" => "ABCDONKD",
        "abc_cateel_kds" => "ABCCATKD",
        "abc_lupon_kds" => "ABCLUPKD",
    ];
    if (isset($map[$customerKey])) {
        return $map[$customerKey];
    }
    // Fallback: uppercase alphanumerics of the key (no hyphens, so the sequence
    // is always the 3rd '-' segment), capped at 8 chars.
    $fallback = strtoupper(preg_replace('/[^A-Za-z0-9]/', "", $customerKey));
    return $fallback !== "" ? substr($fallback, 0, 8) : "BILL";
}

/**
 * Next document number for a customer (sequence per customer per year). Reads the
 * current max sequence for that prefix+year and adds 1. The document_no UNIQUE
 * index is the real guard — callers should retry on a unique violation (see
 * billing_reserve_document_no).
 */
function billing_next_document_no(PDO $conn, string $customerKey, ?string $year = null): string
{
    $prefix = billing_document_prefix($customerKey);
    $year = $year ?? date("Y");
    $stmt = $conn->prepare(
        "SELECT COALESCE(MAX(CAST(split_part(document_no, '-', 3) AS INTEGER)), 0)
         FROM billing_invoices
         WHERE document_no LIKE ?"
    );
    $stmt->execute([$prefix . "-" . $year . "-%"]);
    $next = ((int) $stmt->fetchColumn()) + 1;
    return sprintf("%s-%s-%03d", $prefix, $year, $next);
}

/**
 * The value written to the SAP "Reference" (J) column and shown on the file:
 * the descriptive reference label ONLY (e.g. "ABC Cateel KDs - 3").
 *
 * The document number is intentionally NOT prefixed here (per requirement, column J
 * carries just the label for every customer). The document number is still kept on the
 * invoice's own `document_no` column and in the file name, so lookup/returned-tracking
 * are unaffected. $documentNo is accepted for backward compatibility but unused; it is
 * used only as a fallback when the descriptive label is empty.
 */
function billing_document_reference(string $documentNo, string $descriptiveReference): string
{
    $descriptiveReference = trim($descriptiveReference);
    return $descriptiveReference !== "" ? $descriptiveReference : trim($documentNo);
}

/**
 * 3-letter Month(Year) label from the billing/document date, e.g. "Sep(2026)". Used ONLY in
 * the download FILENAME — NOT in the stored Reference (per finance: the SAP Reference column
 * stays "<prefix> - N" without the month).
 */
function billing_month_label(string $documentDate): string
{
    $ts = strtotime(trim($documentDate)) ?: time();
    return date("M", $ts) . "(" . date("Y", $ts) . ")";
}

/**
 * The running invoice series number for a customer in the billing month of $documentDate.
 * Per customer, per month, RESETS to 1 each new month. Taken as the highest number already
 * used that month + 1 (not a live COUNT), so deleting a middle invoice never reuses a number.
 */
function billing_month_seq(PDO $conn, string $customerKey, string $documentDate): int
{
    $ym = date("Y-m", strtotime(trim($documentDate)) ?: time());
    try {
        $stmt = $conn->prepare(
            "SELECT COALESCE(MAX(CAST(substring(reference FROM ' - ([0-9]+)\$') AS INTEGER)), 0)
             FROM billing_invoices
             WHERE customer_key = ? AND status <> 'deleted'
               AND to_char(document_date, 'YYYY-MM') = ?"
        );
        $stmt->execute([$customerKey, $ym]);
        return ((int) $stmt->fetchColumn()) + 1;
    } catch (Throwable $e) {
        return 1;
    }
}

/**
 * Combined "Mon(Year) - N" (label + series). Kept for callers that want both in one string.
 */
function billing_month_series(PDO $conn, string $customerKey, string $documentDate): string
{
    return billing_month_label($documentDate) . " - " . billing_month_seq($conn, $customerKey, $documentDate);
}

/**
 * Insert the Month(Year) into a (clean) reference, before its trailing " - N".
 * e.g. "Sumifru Reefer Vans - 1" + 2026-09 -> "Sumifru Reefer Vans Sep(2026) - 1".
 * Used to name the PHYSICAL file saved in the storage folder (the stored Reference / SAP
 * column / table stay clean, without the month).
 */
function billing_reference_with_month(string $reference, string $documentDate): string
{
    $reference = trim($reference);
    if ($reference === "") {
        return "";
    }
    $label = billing_month_label($documentDate);
    if (preg_match('/ - \d+$/', $reference)) {
        return preg_replace('/ - (\d+)$/', " " . $label . ' - $1', $reference);
    }
    return $reference . " " . $label;
}

/**
 * Filesystem-safe base name (no extension) for the file saved in storage/billing/ — the
 * clean reference with the Month(Year) inserted, e.g. "Sumifru Reefer Vans Sep(2026) - 1".
 */
function billing_storage_filebase(string $reference, string $documentDate): string
{
    $name = billing_reference_with_month($reference, $documentDate);
    return preg_replace('/[\/\\\\:*?"<>|]+/', "_", trim($name));
}

/**
 * The clean DOWNLOAD filename base: the reference with any " Mon(Year)" token stripped from
 * before its trailing " - N". Downloads are presented WITHOUT the month
 * ("Sumifru Reefer Vans - 1") even though the stored file carries it.
 */
function billing_reference_basename(string $reference): string
{
    $reference = trim($reference);
    if ($reference === "") {
        return "";
    }
    // Drop a 3+ letter Month(Year) token that sits right before the trailing " - N".
    return preg_replace('/ [A-Za-z]{3,}\((?:19|20)\d{2}\)( - \d+)$/', '$1', $reference);
}
