<?php

/**
 * Per-customer billing configuration for the SAP ZPSO sales-order upload.
 *
 * Each customer maps the constant SAP header values plus how to select and
 * price their trips. Add a new entry here when a new customer template
 * arrives — the generator and the Billing page pick these up automatically.
 *
 * Field meanings (columns of the SAP upload file):
 *   order_type, sales_org, distribution_channel, division, sold_to,
 *   customer_tax_class, document_currency, billed_services, material_code,
 *   sales_unit, condition_type, condition_unit, profit_center, route
 * Selection / pricing:
 *   segment          - operations.segment that belongs to this customer
 *   customer_match   - substring matched against customer_ph/shipper/operations_ph
 *   rate_code        - the rate_code in the `rates` master data to price each trip
 *   reference_prefix - label used for the Reference column / batch name
 */
function billing_customers_config(): array
{
    return [
        "sumifru_containerized" => [
            "label" => "Sumifru Containerized",
            "order_type" => "ZPSO",
            "sales_org" => "3200",
            "distribution_channel" => "20",
            "division" => "31",
            "sold_to" => "10003345",
            "customer_tax_class" => "0",
            "document_currency" => "USD",
            "billed_services" => "Hauling Containerized Bananas",
            "material_code" => "FS00000002",
            "sales_unit" => "TRP",
            "condition_type" => "PR00",
            "condition_unit" => "1000",
            "profit_center" => "3200010020",
            "route" => "218",
            "segment" => "SumiRV",
            "customer_match" => "SUMIFRU",
            "rate_code" => "SUMICON",
            // Pricing: lane (STS/STP/PTP) + fuel-period tier (115/110/105) resolved
            // live from the `rates` master data; see sumifru_rate.php.
            "pricing" => "lane_tier",
            "reference_prefix" => "Sumifru Reefer Vans",
            // Printable PDF invoice (PANABO TRUCKING SERVICES) fields:
            "bill_to_name" => "SUMIFRU SINGAPORE PTE LTD",
            "activity" => "HAULING OF CONTAINERIZED BANANAS",
            "destination" => "TDC PH06 to DICT CY | SUMIFRU",
            // Fixed PANABO-PDF statement header route (overrides the trip-derived one).
            "pdf_destination" => "TDC PH06|PH10 to DICT CY|SUMIFRU",
            "origin" => "SUMIFRU",
            "packing_station" => "TADECO",
            "port_of_loading" => "SUMIFRU",
        ],
    ];
}

function billing_customer(string $key): ?array
{
    $config = billing_customers_config();
    return $config[$key] ?? null;
}
