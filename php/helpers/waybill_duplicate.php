<?php

/**
 * True if another operations row already uses this waybill (non-empty).
 * On update, pass $excludeEntryId so the current row is ignored.
 */
function operations_waybill_exists(PDO $conn, string $waybill, ?int $excludeEntryId = null): bool
{
    return operations_field_exists($conn, 'waybill', $waybill, $excludeEntryId);
}

function operations_dr_no_exists(PDO $conn, string $drNo, ?int $excludeEntryId = null): bool
{
    return operations_field_exists($conn, 'dr_no', $drNo, $excludeEntryId);
}

function operations_fgtr_no_exists(PDO $conn, string $fgtrNo, ?int $excludeEntryId = null): bool
{
    return operations_field_exists($conn, 'fgtr_no', $fgtrNo, $excludeEntryId);
}

function operations_booking_exists(PDO $conn, string $booking, ?int $excludeEntryId = null): bool
{
    return operations_field_exists($conn, 'booking', $booking, $excludeEntryId);
}

function operations_field_exists(
    PDO $conn,
    string $fieldName,
    string $fieldValue,
    ?int $excludeEntryId = null,
): bool {
    static $allowedFields = ['waybill', 'dr_no', 'fgtr_no', 'booking'];

    $fieldValue = trim($fieldValue);
    if ($fieldValue === '' || !in_array($fieldName, $allowedFields, true)) {
        return false;
    }

    if ($excludeEntryId !== null && $excludeEntryId > 0) {
        $stmt = $conn->prepare("SELECT 1 FROM operations WHERE {$fieldName} = ? AND entry_id != ? LIMIT 1");
        $stmt->execute([$fieldValue, $excludeEntryId]);
    } else {
        $stmt = $conn->prepare("SELECT 1 FROM operations WHERE {$fieldName} = ? LIMIT 1");
        $stmt->execute([$fieldValue]);
    }

    return $stmt->fetch() !== false;
}
