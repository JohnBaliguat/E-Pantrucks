<?php
// Pay class calendar ported from the payroll Excel template (Calendar sheet).
// Pay classes: RD (regular day), SUN (Sunday), RH (regular holiday),
// SPH (special holiday), RHSUN / SPHSUN (holiday falling on a Sunday),
// GP (guaranteed pay for an unworked regular holiday).

// Holiday list per year. RH = regular holiday, SPH = special (non-working) holiday.
function payroll_holidays(int $year): array
{
    $holidays = [
        2026 => [
            '2026-01-01' => ['type' => 'RH', 'name' => "New Year's Day"],
            '2026-03-20' => ['type' => 'RH', 'name' => 'Eid al-Fitr (Tentative)'],
            '2026-03-31' => ['type' => 'SPH', 'name' => 'Araw ng Panabo'],
            '2026-04-02' => ['type' => 'RH', 'name' => 'Maundy Thursday'],
            '2026-04-03' => ['type' => 'RH', 'name' => 'Good Friday'],
            '2026-04-04' => ['type' => 'SPH', 'name' => 'Black Saturday'],
            '2026-04-09' => ['type' => 'RH', 'name' => 'The Day of Valor'],
            '2026-05-01' => ['type' => 'RH', 'name' => 'Labor Day'],
            '2026-05-27' => ['type' => 'RH', 'name' => 'Eid al-Adha (Tentative)'],
            '2026-06-12' => ['type' => 'RH', 'name' => 'Independence Day'],
            '2026-08-21' => ['type' => 'SPH', 'name' => 'Ninoy Aquino Day'],
            '2026-08-31' => ['type' => 'RH', 'name' => 'National Heroes Day'],
            '2026-11-01' => ['type' => 'SPH', 'name' => "All Saints' Day"],
            '2026-11-02' => ['type' => 'SPH', 'name' => "All Souls' Day"],
            '2026-11-30' => ['type' => 'RH', 'name' => 'Bonifacio Day'],
            '2026-12-08' => ['type' => 'SPH', 'name' => 'Feast of the Immaculate Conception'],
            '2026-12-24' => ['type' => 'SPH', 'name' => 'Christmas Eve'],
            '2026-12-25' => ['type' => 'RH', 'name' => 'Christmas Day'],
            '2026-12-30' => ['type' => 'RH', 'name' => 'Rizal Day'],
            '2026-12-31' => ['type' => 'SPH', 'name' => "New Year's Eve"],
        ],
    ];

    return $holidays[$year] ?? [];
}

// Pay class for a calendar date (Y-m-d), combining holiday type with Sunday.
function payroll_payclass(string $date): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        return 'RD';
    }

    $year = (int) date('Y', $ts);
    $isSunday = date('w', $ts) === '0';
    $holiday = payroll_holidays($year)[date('Y-m-d', $ts)] ?? null;

    if ($holiday !== null) {
        return $holiday['type'] . ($isSunday ? 'SUN' : '');
    }

    return $isSunday ? 'SUN' : 'RD';
}

// SAP payroll code for regular-time hours under a pay class (Calendar sheet J:N).
function payroll_sap_code(string $payClass): int
{
    $map = [
        'RD' => 90,
        'GP' => 92,
        'SUN' => 96,
        'SPH' => 98,
        'SPHSUN' => 102,
        'RH' => 106,
        'RHSUN' => 110,
    ];

    return $map[$payClass] ?? 90;
}

// Column set shown on the summary view, in SAP-code order (Excel SUMMARY header).
function payroll_sap_columns(): array
{
    return [
        90 => 'Regular Day',
        92 => 'Guaranteed Pay',
        96 => 'Sunday',
        98 => 'Special Holiday',
        102 => 'Spcl. Hol. Sunday',
        106 => 'Regular Holiday',
        110 => 'Reg. Hol. Sunday',
    ];
}

// Semi-monthly pay periods used by the Excel template: the 6th-20th and the
// 21st-5th of the following month. Returns [['label','start','end'], ...]
// covering $year, newest first.
function payroll_pay_periods(int $year): array
{
    $periods = [];

    for ($month = 1; $month <= 12; $month++) {
        $first = sprintf('%04d-%02d-06', $year, $month);
        $firstEnd = sprintf('%04d-%02d-20', $year, $month);
        $periods[] = [
            'start' => $first,
            'end' => $firstEnd,
            'label' => date('M j', strtotime($first)) . ' - ' . date('M j, Y', strtotime($firstEnd)),
        ];

        $second = sprintf('%04d-%02d-21', $year, $month);
        $secondEnd = date('Y-m-d', strtotime($second . ' +15 days'));
        // Clamp to the 5th of the following month.
        $secondEnd = date('Y-m-05', strtotime($secondEnd));
        $periods[] = [
            'start' => $second,
            'end' => $secondEnd,
            'label' => date('M j', strtotime($second)) . ' - ' . date('M j, Y', strtotime($secondEnd)),
        ];
    }

    return array_reverse($periods);
}
?>
