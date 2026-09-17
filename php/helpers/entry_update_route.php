<?php

/**
 * The app route for the entry form that owns a given operations record, so the
 * For Update queue can link straight to it. RV entries live on segment-specific
 * pages (doleRv / sumiRv / tdcRv / abcrv); the rest map one-to-one by type.
 * The queue appends ?load_id=<entry_id> so the page auto-opens that record.
 */
function entry_update_route(string $entryType, string $segment = ""): string
{
    $entryType = trim($entryType);
    $seg = strtolower(trim($segment));

    if ($entryType === "RV ENTRY") {
        if (strpos($seg, "dole") !== false) {
            return "doleRv";
        }
        if (strpos($seg, "sumi") !== false) {
            return "sumiRv";
        }
        if (strpos($seg, "tdc") !== false) {
            return "tdcRv";
        }
        return "abcrv"; // ABCRV, Hustling, and any other RV segment
    }

    $map = [
        "DRY VAN ENTRY" => "dryVan",
        "DPC_KDs & OPM ENTRY" => "DPC_KDI",
        "CARGO TRUCK ENTRY" => "cargoTruck",
        "OTHERS ENTRY" => "others",
        "Others ENTRY" => "others",
    ];

    return $map[$entryType] ?? "abcrv";
}
