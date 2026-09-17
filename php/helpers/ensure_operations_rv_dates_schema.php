<?php

/**
 * Ensure the RV EMPTY-leg trip-receipt date column exists on `operations`.
 *
 * The LOADED leg's Trip Receipt Date is stored in the existing `waybill_date`
 * column (it doubles as the RV Transaction Date on the transmittal). The EMPTY leg
 * (waybill_empty) keeps its own dedicated column.
 */
function ensure_operations_rv_dates_schema(PDO $conn): void
{
    static $alreadyEnsured = false;
    if ($alreadyEnsured) {
        return;
    }

    $conn->exec("ALTER TABLE operations ADD COLUMN IF NOT EXISTS empty_trip_receipt_date DATE");
    $alreadyEnsured = true;
}
