<?php

function operations_normalize_entry_type($entryType): string
{
    return strtoupper(trim((string) $entryType));
}

function operations_is_blank($value): bool
{
    if ($value === null) {
        return true;
    }

    return trim((string) $value) === "";
}

function operations_required_fields_by_type(): array
{
    return [
        "CARGO TRUCK ENTRY" => [
            "segment" => "Segment",
            "activity" => "Activity",
            "waybill_date" => "Date",
            "waybill" => "Waybill",
            "truck" => "Truck",
            "driver" => "Driver",
            "customer_ph" => "Customer/PH",
            "outside" => "Outside",
            "compound" => "Compound",
            "total_trips" => "Total Trips",
            "operations" => "Operations",
            "deliver_from" => "Deliver From",
            "delivered_to" => "Deliver To",
            "cargo_date" => "Cargo Date",
        ],
        "DPC_KDs & OPM ENTRY" => [
            "segment" => "Segment",
            "activity" => "Activity",
            "waybill_date" => "Date",
            "waybill" => "Waybill",
            "evita_farmind" => "Evita/Farmind",
            "driver" => "Driver",
            "departure" => "Departure",
            "arrival" => "Arrival",
            "truck" => "Truck",
            "ph" => "PH",
            "thirteen_body" => "13 Body",
            "thirteen_cover" => "13 Cover",
            "thirteen_pads" => "13 Pads",
            "eighteen_body" => "18 Body",
            "eighteen_cover" => "18 Cover",
            "eighteen_pads" => "18 Pads",
            "thirteen_total" => "13 Total",
            "eighteen_total" => "18 Total",
            "total_load" => "Total Load",
            "fgtr_no" => "FGTR's No.",
            "dpc_date" => "DPC Date",
        ],
        "OTHERS ENTRY" => [
            "segment" => "Segment",
            "activity" => "Activity",
            "waybill_date" => "Date",
            "waybill" => "Waybill",
            "truck" => "Truck",
            "driver" => "Driver",
            "operations_ph" => "Operations / PH",
            "load_quantity_weight" => "Load / Quantity / Weight",
            "unit_of_measure" => "Unit Of Measure",
            "deliver_from" => "Deliver From",
            "delivered_to" => "Deliver To",
        ],
        "RV ENTRY" => [
            "segment" => "Segment",
            "activity" => "Activity",
            "pullout_location_arrival_date" => "Pullout Arrival Date",
            "pullout_location_arrival_time" => "Pullout Arrival Time",
            "pullout_location_departure_date" => "Pullout Departure Date",
            "pullout_location_departure_time" => "Pullout Departure Time",
            "ph_arrival_date" => "PH Arrival Date",
            "ph_arrival_time" => "PH Arrival Time",
            "van_alpha" => "Van Alpha",
            "van_number" => "Van Number",
            "van_name" => "Van Name",
            "ph" => "PH",
            "shipper" => "Shipper",
            "ecs" => "ECS",
            "tr" => "TR",
            "gs" => "GS",
            "waybill" => "Waybill",
            "prime_mover" => "Prime Mover",
            "driver" => "Driver",
            "empty_pullout_location" => "Empty Pullout Location",
            "loaded_van_loading_start_date" => "Loading Start Date",
            "loaded_van_loading_start_time" => "Loading Start Time",
            "loaded_van_loading_finish_date" => "Loading Finish Date",
            "loaded_van_loading_finish_time" => "Loading Finish Time",
            "loaded_van_delivery_departure_date" => "Delivery Departure Date",
            "loaded_van_delivery_departure_time" => "Delivery Departure Time",
            "loaded_van_delivery_arrival_date" => "Delivery Arrival Date",
            "loaded_van_delivery_arrival_time" => "Delivery Arrival Time",
            "dr_no" => "DR No.",
            "load_description" => "Load",
            "delivered_by_prime_mover" => "Delivered By PM",
            "delivered_by_driver" => "Delivered By Driver",
            "delivered_to" => "Delivered To",
            "genset_hr_meter_start" => "HR Meter Start",
            "genset_hr_meter_end" => "HR Meter End",
            "genset_start_date" => "GS Start Date",
            "genset_start_time" => "GS Start Time",
            "genset_end_date" => "GS End Date",
            "genset_end_time" => "GS End Time",
        ],
    ];
}

function operations_dry_van_required_fields(string $customer): array
{
    $customer = strtoupper(trim($customer));

    $configs = [
        "CITIHARDWARE IMPORTS" => [
            "customer_ph" => "Customer",
            "waybill" => "Waybill No. - Loaded",
            "date_hauled" => "Date Hauled",
            "van_alpha" => "Van Alpha",
            "van_number" => "Van Numeric",
            "shipper" => "Shipping Line",
            "pullout_location" => "Pull Out Location",
            "eir_out" => "EIR Out",
            "eir_outDate" => "EIR Out Date",
            "shipment_no" => "Shipment No.",
            "booking" => "BL No.",
            "size" => "Volume Size",
            "truck" => "Prime Mover - Loaded",
            "tr" => "Trailer No.",
            "driver" => "Driver's Name (Loaded)",
            "waybill_empty" => "Waybill No. - Empty",
            "eir_in" => "EIR In",
            "return_location" => "Return Location",
            "date_returned" => "Actual Date Returned",
            "truck2" => "Prime Mover - Empty",
            "driver_return" => "Driver's Name (Empty)",
        ],
        "TPD DRYVAN IMPORT" => [
            "customer_ph" => "Customer",
            "waybill" => "Waybill No. - Loaded",
            "date_hauled" => "Date Hauled",
            "van_alpha" => "Van Alpha",
            "van_number" => "Van Numeric",
            "shipper" => "Shipping Line",
            "pullout_location" => "Pull Out Location",
            "eir_out" => "EIR Out",
            "eir_outDate" => "EIR Out Date",
            "booking" => "BL No.",
            "size" => "Volume Size",
            "truck" => "Prime Mover - Loaded",
            "tr" => "Trailer No.",
            "driver" => "Driver's Name (Loaded)",
            "waybill_empty" => "Waybill No. - Empty",
            "eir_in" => "EIR In",
            "return_location" => "Return Location",
            "date_returned" => "Actual Date Returned",
            "truck2" => "Prime Mover - Empty",
            "driver_return" => "Driver's Name (Empty)",
        ],
        "CITIHARDWARE DOMESTIC" => [
            "customer_ph" => "Customer",
            "waybill_empty" => "Waybill No. - Empty",
            "van_alpha" => "Van Alpha",
            "van_number" => "Van Numeric",
            "shipper" => "Shipping Line",
            "pullout_date" => "Pull Out Date",
            "pullout_location" => "Pull Out Location",
            "eir_out" => "EIR Out",
            "eir_outDate" => "EIR Out Date",
            "truck2" => "Prime Mover - Empty",
            "driver_return" => "Driver's Name (Empty)",
            "waybill" => "Waybill No. - Loaded",
            "date_hauled" => "Date Hauled",
            "delivered_to" => "Delivery Location",
            "eir_in" => "EIR In",
            "tr" => "Trailer No.",
            "truck" => "Prime Mover - Loaded",
            "driver" => "Driver's Name (Loaded)",
        ],
        "PSACC DOMESTIC" => [
            "customer_ph" => "Customer",
            "waybill_empty" => "Waybill No. - Empty",
            "van_alpha" => "Van Alpha",
            "van_number" => "Van Numeric",
            "shipper" => "Shipping Line",
            "pullout_date" => "Pull Out Date",
            "pullout_location" => "Pull Out Location",
            "eir_out" => "EIR Out",
            "eir_outDate" => "EIR Out Date",
            "truck2" => "Prime Mover - Empty",
            "driver_return" => "Driver's Name (Empty)",
            "waybill" => "Waybill No. - Loaded",
            "date_hauled" => "Date Hauled",
            "delivered_to" => "Delivery Location",
            "eir_in" => "EIR In",
            "tr" => "Trailer No.",
            "truck" => "Prime Mover - Loaded",
            "driver" => "Driver's Name (Loaded)",
            "emdr_no" => "EMDR No.",
        ],
        "TPD DRYVAN EXPORT" => [
            "customer_ph" => "Customer",
            "waybill_empty" => "Waybill No. - Empty",
            "van_alpha" => "Van Alpha",
            "van_number" => "Van Numeric",
            "shipper" => "Shipping Line",
            "pullout_date" => "Pull Out Date",
            "pullout_location" => "Pull Out Location",
            "eir_out" => "EIR Out",
            "eir_outDate" => "EIR Out Date",
            "truck2" => "Prime Mover - Empty",
            "driver_return" => "Driver's Name (Empty)",
            "waybill" => "Waybill No. - Loaded",
            "date_hauled" => "Date Hauled",
            "delivered_to" => "Delivery Location",
            "eir_in" => "EIR In",
            "tr" => "Trailer No.",
            "truck" => "Prime Mover - Loaded",
            "driver" => "Driver's Name (Loaded)",
        ],
    ];

    $otherImportCustomers = [
        "ECOSSENTIAL - IMPORT",
        "PHIL CEMENT",
        "NOVOCOCONUT - IMPORT",
        "NP CHANGS - IMPORT",
        "SOLARVISTA - IMPORT",
        "FRANKLIN BAKER - IMPORT",
        "EYE CARGO - IMPORT",
        "PHIL JDU - IMPORT",
        "SOUTHERN HARVEST - IMPORT",
        "HEADSPORT - IMPORT",
        "AGRI EXIM - IMPORT",
        "SOLARIS - IMPORT",
        "HURRAH AGRO - IMPORT",
    ];

    $otherExportCustomers = [
        "ECOSSENTIAL - EXPORT",
        "NOVOCOCONUT - EXPORT",
        "FRANKLIN BAKER - EXPORT",
        "EYE CARGO - EXPORT",
        "PHIL JDU - EXPORT",
        "SOUTHERN HARVEST - EXPORT",
        "HEADSPORT - EXPORT",
        "AGRI EXIM - EXPORT",
        "SOLARIS - EXPORT",
        "SAGREX - EXPORT",
        "FIRST PANABO TROPICAL FOOD CORP. - EXPORT",
    ];

    foreach ($otherImportCustomers as $name) {
        $configs[$name] = [
            "customer_ph" => "Customer",
            "waybill" => "Waybill No. - Loaded",
            "date_hauled" => "Date Hauled",
            "van_alpha" => "Van Alpha",
            "van_number" => "Van Numeric",
            "shipper" => "Shipping Line",
            "pullout_location" => "Pull Out Location",
            "eir_out" => "EIR Out",
            "truck" => "Prime Mover - Loaded",
            "tr" => "Trailer No.",
            "driver" => "Driver's Name (Loaded)",
            "waybill_empty" => "Waybill No. - Empty",
            "date_returned" => "Actual Date Returned",
            "return_location" => "Return Location",
            "eir_in" => "EIR In",
            "truck2" => "Prime Mover - Empty",
            "driver_return" => "Driver's Name (Empty)",
        ];
    }

    foreach ($otherExportCustomers as $name) {
        $configs[$name] = [
            "customer_ph" => "Customer",
            "waybill_empty" => "Waybill No. - Empty",
            "van_alpha" => "Van Alpha",
            "van_number" => "Van Numeric",
            "shipper" => "Shipping Line",
            "pullout_date" => "Pull Out Date",
            "pullout_location" => "Pull Out Location",
            "eir_out" => "EIR Out",
            "truck2" => "Prime Mover - Empty",
            "tr" => "Trailer No. - Empty",
            "driver_return" => "Driver's Name (Empty)",
            "waybill" => "Waybill No. - Loaded",
            "date_hauled" => "Date Hauled",
            "delivered_to" => "Delivery Location",
            "eir_in" => "EIR In",
            "tr2" => "Trailer No. - Loaded",
            "truck" => "Prime Mover - Loaded",
            "driver" => "Driver's Name (Loaded)",
        ];
    }

    return $configs[$customer] ?? [
        "customer_ph" => "Customer",
        "waybill" => "Waybill",
    ];
}

function operations_route_by_type(): array
{
    return [
        "CARGO TRUCK ENTRY" => "cargoTruck",
        "DPC_KDs & OPM ENTRY" => "DPC_KDI",
        "DRY VAN ENTRY" => "dryVan",
        "OTHERS ENTRY" => "others",
        "RV ENTRY" => "rv",
    ];
}

/**
 * Canonical SELECT body listing every column that operations_missing_fields()
 * inspects. Endpoints that compute Complete/Pending status MUST select at least
 * these columns; otherwise a missing column reads as NULL and the entry is
 * incorrectly flagged Pending.
 *
 * Display-only columns (remarks, created_date, etc.) should be appended by
 * the calling endpoint, not added here.
 */
function operations_status_select_columns_sql(): string
{
    return '
        entry_id,
        entry_type,
        segment,
        activity,
        waybill,
        waybill_date,
        waybill_empty,
        truck,
        truck2,
        driver,
        driver_return,
        customer_ph,
        outside,
        compound,
        total_trips,
        operations,
        deliver_from,
        delivered_to,
        cargo_date,
        evita_farmind,
        departure,
        arrival,
        tr,
        tr2,
        ph,
        "13_body" AS thirteen_body,
        "13_cover" AS thirteen_cover,
        "13_pads" AS thirteen_pads,
        "18_body" AS eighteen_body,
        "18_cover" AS eighteen_cover,
        "18_pads" AS eighteen_pads,
        "13_total" AS thirteen_total,
        "18_total" AS eighteen_total,
        total_load,
        fgtr_no,
        dpc_date,
        gs,
        operations_ph,
        load_quantity_weight,
        unit_of_measure,
        pullout_location_arrival_date,
        pullout_location_arrival_time,
        pullout_location_departure_date,
        pullout_location_departure_time,
        ph_arrival_date,
        ph_arrival_time,
        van_alpha,
        van_number,
        van_name,
        shipper,
        ecs,
        eir_out,
        "eir_outDate",
        "eir_outTime",
        eir_in,
        "eir_inDate",
        pullout_location,
        pullout_date,
        prime_mover,
        date_hauled,
        date_returned,
        date_unloaded,
        departure_time,
        arrival_time,
        return_location,
        size,
        booking,
        shipment_no,
        empty_pullout_location,
        loaded_van_loading_start_date,
        loaded_van_loading_start_time,
        loaded_van_loading_finish_date,
        loaded_van_loading_finish_time,
        loaded_van_delivery_departure_date,
        loaded_van_delivery_departure_time,
        loaded_van_delivery_arrival_date,
        loaded_van_delivery_arrival_time,
        genset_shutoff_date,
        genset_shutoff_time,
        end_uploading_date,
        end_uploading_time,
        dr_no,
        load_description,
        delivered_by_prime_mover,
        delivered_by_driver,
        genset_hr_meter_start,
        genset_hr_meter_end,
        genset_start_date,
        genset_start_time,
        genset_end_date,
        genset_end_time
    ';
}

function operations_missing_fields(array $row): array
{
    $requiredByType = operations_required_fields_by_type();
    $normalizedType = operations_normalize_entry_type($row["entry_type"] ?? "");
    $required = $normalizedType === "DRY VAN ENTRY"
        ? operations_dry_van_required_fields($row["customer_ph"] ?? "")
        : ($requiredByType[$normalizedType] ?? [
        "segment" => "Segment",
        "activity" => "Activity",
        "waybill" => "Waybill",
    ]);

    $missing = [];

    foreach ($required as $field => $label) {
        if (operations_is_blank($row[$field] ?? null)) {
            $missing[] = $label;
        }
    }

    return $missing;
}
