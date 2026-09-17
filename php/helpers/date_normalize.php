<?php

function operations_normalize_sql_date($value, ?int $defaultYear = null): ?string
{
    $trimmed = trim((string) $value);
    if ($trimmed === "") {
        return "";
    }

    $defaultYear ??= (int) date("Y");
    $normalized = preg_replace("/\s+/", "", str_replace("-", "/", $trimmed));

    if (
        preg_match("/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/", $normalized, $matches)
    ) {
        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
    } elseif (
        preg_match("/^(\d{1,2})\/(\d{1,2})(?:\/(\d{4}))?$/", $normalized, $matches)
    ) {
        $month = (int) $matches[1];
        $day = (int) $matches[2];
        $year = isset($matches[3]) && $matches[3] !== ""
            ? (int) $matches[3]
            : $defaultYear;
    } else {
        return null;
    }

    if (!checkdate($month, $day, $year)) {
        return null;
    }

    return sprintf("%04d-%02d-%02d", $year, $month, $day);
}

function operations_invalid_fields_message(array $invalidLabels, string $valueType): string
{
    if (count($invalidLabels) === 0) {
        return "";
    }

    if (count($invalidLabels) === 1) {
        return $invalidLabels[0] . " has an invalid {$valueType} value.";
    }

    return "These fields have invalid {$valueType} values: " .
        implode(", ", $invalidLabels) .
        ".";
}
