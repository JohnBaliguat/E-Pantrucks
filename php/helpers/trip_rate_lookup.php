<?php

function operations_lookup_piece_rate(PDO $conn, string $segment, string $activity): string
{
    $segment = trim($segment);
    $activity = trim($activity);

    if ($activity === '') {
        return '';
    }

    $sql = "SELECT \"totalRates\"
            FROM trip_rates
            WHERE activity = ?
              AND (? = '' OR segment = ?)
            ORDER BY CASE WHEN segment = ? THEN 0 ELSE 1 END, id DESC
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$activity, $segment, $segment, $segment]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return trim((string) ($row['totalRates'] ?? ''));
}
