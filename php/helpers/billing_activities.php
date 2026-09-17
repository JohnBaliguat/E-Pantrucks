<?php

/**
 * Billing activity definitions. Each customer is billed across several
 * activities (seen as separate sheets/PDFs in the templates), each with its own
 * ACTIVITY label, rate basis and statement layout:
 *
 *   hauling        - per-trip hauling charge (rate from the fuel rate matrix)
 *   chassis        - chassis detention: hours over the free window x hourly rate
 *   genset         - genset detention: same rental basis
 *   container_van  - container van detention (in excess of 48 hours): rental
 *   fuel           - fuel charges: genset fuel consumption x price per liter
 *
 * "basis":
 *   trip   - charge resolved per trip from the rate matrix (hauling)
 *   rental - charge = max(0, total_hours - free_hours) * activity_rate.rate
 *   fuel   - charge = fuel_consumption(liters) * diesel price/liter (fuel_price)
 *
 * "columns": ordered [key => header] used by both the preview table and the PDF
 * statement for that activity.
 */
function billing_activities(): array
{
    $rentalColumns = [
        "date" => "DATE",
        "ecs" => "ECS NO.",
        "truck" => "TRUCK",
        "chassis" => "CHASSIS",
        "genset" => "GENSET",
        "van" => "VAN NUMBER",
        "withdrawn" => "DATE WITHDRAWN",
        "delivered" => "DATE DELIVERED",
        "total_hours" => "TOTAL HOUR",
        "excess_hours" => "EXCESS OF 48 HOURS",
        "rate" => "RATE",
        "charge" => "RENTAL CHARGES",
    ];
    // Equipment rental statements show only the equipment being billed. This
    // keeps the Chassis and Genset PDFs focused and prevents an unrelated
    // equipment column from appearing in the customer's statement.
    $chassisRentalColumns = $rentalColumns;
    unset($chassisRentalColumns["genset"]);
    $gensetRentalColumns = $rentalColumns;
    unset($gensetRentalColumns["chassis"]);

    return [
        "hauling" => [
            "label" => "HAULING OF CONTAINERIZED BANANAS",
            "basis" => "trip",
            "short" => "Hauling",
            "columns" => [
                "date" => "DATE",
                "trip_receipt" => "TRIP RECEIPT",
                "truck" => "TRUCK",
                "trailer" => "TRAILER",
                "genset" => "GENSET",
                "van" => "VAN NUMBER",
                "route" => "DELIVERY ROUTE",
                "boxes" => "BOXES",
                "rate" => "RATE",
                "charge" => "AMOUNT",
            ],
        ],
        "chassis" => [
            "label" => "CHASSIS CHARGES",
            "basis" => "rental",
            "short" => "Chassis",
            // SAP "Sales Unit" for this activity's ZPSO line (finance template): rentals
            // bill per STD, fuel per litre (L). Overrides the customer's sales_unit.
            "sap_sales_unit" => "STD",
            "columns" => $chassisRentalColumns,
        ],
        "genset" => [
            "label" => "GENSET CHARGES",
            "basis" => "rental",
            "short" => "Genset",
            "sap_sales_unit" => "STD",
            "columns" => $gensetRentalColumns,
        ],
        "container_van" => [
            "label" => "CONTAINER VAN CHARGES ( IN EXCESS OF 48 HOURS )",
            "basis" => "rental",
            "short" => "Container Van",
            "sap_sales_unit" => "STD",
            "columns" => $rentalColumns,
        ],
        "fuel" => [
            "label" => "FUEL CHARGES",
            "basis" => "fuel",
            "short" => "Fuel",
            "sap_sales_unit" => "L",
            "columns" => [
                "date" => "DATE",
                "ecs" => "ECS",
                "chassis" => "CHASSIS",
                "genset" => "GENSET",
                "alpha" => "ALPHA",
                "number" => "VAN NUMBER",
                "hubo_start" => "HOUR METER START",
                "hubo_end" => "HOUR METER END",
                "runtime" => "ACTUAL GENSET RUNTIME",
                "consumption" => "FUEL CONSUMPTION",
                "price_liter" => "PRICE/LITER",
                "charge" => "FUEL CHARGES",
            ],
        ],
    ];
}

function billing_activity(string $code): ?array
{
    $a = billing_activities();
    return $a[$code] ?? null;
}

/** Activity codes that carry a configurable Activity Rate (rental + fuel markup). */
function billing_activity_rate_codes(): array
{
    return ["chassis", "genset", "container_van", "fuel"];
}
