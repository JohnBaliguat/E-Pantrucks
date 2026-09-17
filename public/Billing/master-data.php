<?php
include "php/session-check.php";
require_once dirname(__DIR__, 2) . "/php/helpers/unified_billing.php";
// Billing customer list for the per-row Customer tag on Service Materials / Profit Center.
$mdBillingCustomers = [];
foreach (unified_billing_customers() as $mdKey => $mdCfg) {
    $mdBillingCustomers[] = ["key" => $mdKey, "label" => $mdCfg["label"] ?? $mdKey];
}

// SKU + Location lists for the SKU Routes tab (dropdowns pick from existing rows).
$mdSkus = [];
$mdLocations = [];
try {
    foreach ($conn->query('SELECT sku_id, sku_name, sku_shipper_segment, sku_farm, "sku_rountripDistance" AS distance FROM sku ORDER BY sku_name')->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $mdSkus[] = [
            "id" => (int) $s["sku_id"],
            "name" => (string) $s["sku_name"],
            "segment" => (string) ($s["sku_shipper_segment"] ?? ""),
            "farm" => (string) ($s["sku_farm"] ?? ""),
            "distance" => (string) ($s["distance"] ?? ""),
        ];
    }
} catch (Throwable $e) {
    $mdSkus = [];
}
try {
    foreach ($conn->query("SELECT DISTINCT location_name FROM location WHERE COALESCE(location_name,'') <> '' ORDER BY location_name")->fetchAll(PDO::FETCH_COLUMN) as $ln) {
        $mdLocations[] = (string) $ln;
    }
} catch (Throwable $e) {
    $mdLocations = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data - DataEncode System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/DataTables/datatables.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="shortcut icon" type="image/png" href="img/e-pantrucks logo2.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <div class="wrapper">
        <?php include "sidebar.php"; ?>

        <div id="content">
            <?php include "navbar.php"; ?>

            <div class="main-content">
                <div class="content-header mb-4">
                    <h2 class="fw-bold">Billing Master Data</h2>
                    <p class="text-muted">Add, update, and delete Fuel Price, Service Materials, Profit Center, and Rates records.</p>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-6 col-xl-3">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-warning"><i class="bi bi-fuel-pump-fill"></i></div>
                            <div><h3 id="fuelPriceCount">0</h3><p>Fuel Price</p></div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-success"><i class="bi bi-box-seam"></i></div>
                            <div><h3 id="serviceMaterialCount">0</h3><p>Service Materials</p></div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-primary"><i class="bi bi-diagram-3-fill"></i></div>
                            <div><h3 id="profitCenterCount">0</h3><p>Profit Center</p></div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="stat-card-simple">
                            <div class="stat-icon-simple bg-danger"><i class="bi bi-cash-coin"></i></div>
                            <div><h3 id="ratesCount">0</h3><p>Rates</p></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#fuelPricePane" type="button">Effective-Date Fuel</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#forexPane" type="button">Forex</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#serviceMaterialPane" type="button">Service Materials</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#profitCenterPane" type="button">Profit Center</button></li>
                            <li class="nav-item" role="presentation" style="display:none;"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ratesPane" type="button">Rates (flat)</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" id="rateMatrixTabBtn" data-bs-toggle="tab" data-bs-target="#rateMatrixPane" type="button">Rate Matrix</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" id="activityRatesTabBtn" data-bs-toggle="tab" data-bs-target="#activityRatesPane" type="button">Activity Rates</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" id="locationsTabBtn" data-bs-toggle="tab" data-bs-target="#locationsPane" type="button">Locations</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#skuRoutePane" type="button">SKU Routes</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#equipmentSapPane" type="button">Equipment SAP Code</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" id="customerSapTabBtn" data-bs-toggle="tab" data-bs-target="#customerSapPane" type="button">Customer SAP Codes</button></li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="fuelPricePane">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div><h5 class="mb-0">Effective-Date Fuel</h5><div class="small text-muted">Used by customers whose fuel price follows From/To effectivity dates.</div></div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-secondary" id="fuelPriceTemplateBtn"><i class="bi bi-download me-1"></i>Template</button>
                                        <button type="button" class="btn btn-outline-secondary" id="fuelPriceImportBtn"><i class="bi bi-upload me-1"></i>Import Excel</button>
                                        <button class="btn btn-primary btn-add-item" data-entity="fuel_price"><i class="bi bi-plus-circle me-1"></i>Add Fuel Price</button>
                                    </div>
                                </div>
                                <input type="file" id="fuelPriceImportFile" accept=".xlsx" class="d-none">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle" id="fuelPriceTable">
                                        <thead class="table-light">
                                            <tr><th>ID</th><th>From</th><th>To</th><th>Updated</th><th>Petron</th><th>Shell</th><th>Caltex</th><th>Seaoil</th><th>Common</th><th>Tier %</th><th>Customers</th><th>Actions</th></tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="forexPane">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h5 class="mb-0">Forex (USD → PHP)</h5>
                                        <div class="small text-muted">The dollar conversion used to price USD billing (e.g. Sumifru). Billing uses the latest rate on/before the trip date.</div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-secondary" id="forexTemplateBtn"><i class="bi bi-download me-1"></i>Template</button>
                                        <button type="button" class="btn btn-outline-secondary" id="forexImportBtn"><i class="bi bi-upload me-1"></i>Import Excel</button>
                                        <button class="btn btn-primary btn-add-item" data-entity="forex_rate"><i class="bi bi-plus-circle me-1"></i>Add Forex Rate</button>
                                    </div>
                                </div>
                                <input type="file" id="forexImportFile" accept=".xlsx" class="d-none">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle" id="forexRateTable">
                                        <thead class="table-light">
                                            <tr><th>ID</th><th>From</th><th>To</th><th>Updated</th><th>USD → PHP Rate</th><th>Customers</th><th>Actions</th></tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="serviceMaterialPane">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Service Materials</h5>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-secondary" id="serviceMaterialTemplateBtn"><i class="bi bi-download me-1"></i>Template</button>
                                        <button type="button" class="btn btn-outline-secondary" id="serviceMaterialImportBtn"><i class="bi bi-upload me-1"></i>Import Excel</button>
                                        <button class="btn btn-primary btn-add-item" data-entity="service_material"><i class="bi bi-plus-circle me-1"></i>Add Service Material</button>
                                    </div>
                                </div>
                                <input type="file" id="serviceMaterialImportFile" accept=".xlsx" class="d-none">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle" id="serviceMaterialTable">
                                        <thead class="table-light">
                                            <tr><th>ID</th><th>Customer</th><th>Revenue Stream</th><th>Material Code</th><th>Material Description</th><th>Rate Type</th><th>Rate</th><th>Tax Class</th><th>Profit Center</th><th>Actions</th></tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="profitCenterPane">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Profit Center</h5>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-secondary" id="profitCenterTemplateBtn"><i class="bi bi-download me-1"></i>Template</button>
                                        <button type="button" class="btn btn-outline-secondary" id="profitCenterImportBtn"><i class="bi bi-upload me-1"></i>Import Excel</button>
                                        <button class="btn btn-primary btn-add-item" data-entity="profit_center"><i class="bi bi-plus-circle me-1"></i>Add Profit Center</button>
                                    </div>
                                </div>
                                <input type="file" id="profitCenterImportFile" accept=".xlsx" class="d-none">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle" id="profitCenterTable">
                                        <thead class="table-light">
                                            <tr><th>ID</th><th>Customer</th><th>Profit Center</th><th>Controlling Area</th><th>Name</th><th>Department</th><th>Segment</th><th>Actions</th></tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="ratesPane">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Rates</h5>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-secondary" id="ratesTemplateBtn"><i class="bi bi-download me-1"></i>Template</button>
                                        <button type="button" class="btn btn-outline-secondary" id="ratesImportBtn"><i class="bi bi-upload me-1"></i>Import Excel</button>
                                        <button class="btn btn-primary btn-add-item" data-entity="rates"><i class="bi bi-plus-circle me-1"></i>Add Rate</button>
                                    </div>
                                </div>
                                <input type="file" id="ratesImportFile" accept=".xlsx" class="d-none">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle" id="ratesTable">
                                        <thead class="table-light">
                                            <tr><th>ID</th><th>Origin</th><th>Packing House</th><th>Port of Destination</th><th>Rate Code</th><th>Rate</th><th>Actions</th></tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="activityRatesPane">
                                <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                                    <div>
                                        <h5 class="mb-1">Activity Rates</h5>
                                        <div class="small text-muted">Per-customer rates for the non-hauling billing activities. Chassis / Genset / Container Van charge = hours over the free window &times; rate. Fuel charge = consumption &times; the diesel price/liter from Fuel Price (the Fuel rate here is an optional surcharge, usually 0).</div>
                                    </div>
                                    <div style="min-width: 280px;">
                                        <label class="form-label small mb-1" for="arCustomer">Customer</label>
                                        <select class="form-select" id="arCustomer"></select>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle">
                                        <thead class="table-light"><tr><th>Activity</th><th style="width:150px;">Rate</th><th style="width:140px;">Free Hours</th><th style="width:180px;">SAP Material Code</th></tr></thead>
                                        <tbody id="arBody"></tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-end gap-2 mt-2">
                                    <span id="arStatus" class="small text-muted align-self-center"></span>
                                    <button type="button" class="btn btn-success" id="arSave"><i class="bi bi-save me-1"></i>Save Activity Rates</button>
                                </div>
                            </div>
                            <style>
                                #rmLanesBody tr.rm-grp-alt td { background: #f3f6f9; }
                                #rmLanesBody tr.rm-grp-start td { border-top: 2px solid #adb5bd; }
                                /* Flat, per-trip services (DICT shuttling / industrial waste): only
                                   Route + Base Rate apply, so hide the fuel-formula columns. */
                                #rmLanesTable.rm-flat .rm-fuelcol { display: none; }
                            </style>
                            <div class="tab-pane fade" id="rateMatrixPane">
                                <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                                    <div>
                                        <h5 class="mb-1">Fuel-Based Rate Matrix</h5>
                                        <div class="small text-muted">One row per lane. Escalation applies <strong>only when a Monthly Avg Fuel is set</strong> for the row — blank/0 charges the <strong>Base Rate</strong>. When set, the rate is <strong>computed</strong> from that fuel price: it rises at <strong>0.4×</strong> the fuel's movement above the Pump Price, banded to each <strong>Price Movement</strong> step —
                                            <code>rate = Base Rate × (1 + 0.4 × steps × Price Movement ÷ Pump Price)</code>, <code>steps = floor((fuel − Pump Price) ÷ Price Movement)</code>.
                                            Below the Pump Price the Base Rate is charged. Each row applies within its <strong>Effective From → To</strong> window (blank From = no lower bound, blank To = open-ended); when a lane has several rows covering a trip date, the latest <strong>Effective From</strong> wins.</div>
                                    </div>
                                    <div style="min-width: 280px;">
                                        <label class="form-label small mb-1" for="rmCustomer">Customer</label>
                                        <select class="form-select" id="rmCustomer"></select>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <h6 class="mb-0">Rate Lines</h6>
                                        <select class="form-select form-select-sm" id="rmMonthFilter" style="width:auto;" title="Show only the lanes effective in this month"></select>
                                    </div>
                                    <div class="d-flex flex-wrap justify-content-end gap-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="rmDownloadTemplate"><i class="bi bi-download me-1"></i>Template</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="rmImportExcel"><i class="bi bi-upload me-1"></i>Import Excel</button>
                                        <button type="button" class="btn btn-outline-success btn-sm" id="rmDuplicateDate" title="Copy all current lines to a new effective date"><i class="bi bi-files me-1"></i>Duplicate → new date</button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="rmAddLane"><i class="bi bi-plus-circle me-1"></i>Add Line</button>
                                    </div>
                                </div>
                                <div id="rmFuelNote" class="small text-muted mb-2"></div>
                                <div class="table-responsive mb-3" style="max-height: 460px; overflow:auto;">
                                    <table class="table table-sm table-bordered align-middle" id="rmLanesTable">
                                        <thead class="table-light"><tr>
                                            <th title="Row applies to trips on/after this date. Blank = no lower bound.">Effective From</th>
                                            <th title="Row applies to trips on/before this date. Blank = no upper bound (open-ended).">Effective To</th>
                                            <th>Segment</th><th>Origin</th><th>Packing House</th><th>Destination</th>
                                            <th title="Destination code billing matches on — auto-filled from the destination's Location Matrix">DCode</th>
                                            <th title="Rate charged while diesel stays at/below the Pump Price">Base Rate</th>
                                            <th class="rm-fuelcol" title="Base fuel price: diesel price the Base Rate stays flat up to; escalation applies above it (blank = 50)">Pump Price</th>
                                            <th class="rm-fuelcol" title="Pump-price step size for banding, e.g. 2.50">Price Movement</th>
                                            <th class="rm-fuelcol" title="Average fuel price for this Rate Matrix effective month">Monthly Avg Fuel</th>
                                            <th title="Banded fuel movement used by the formula: floor((fuel − pump) ÷ step) × step ÷ pump. The rate applies 0.4× of this percentage." class="rm-fuelcol text-end">Fuel Move %</th>
                                            <th title="The charged rate right now, computed from the current fuel price by the billing formula" class="text-end">Rate (now)</th>
                                            <th class="rm-fuelcol" title="Optional stepped rates: a flat rate per diesel-price range. When set, bands override the formula (base rate applies below the lowest band).">Fuel Bands</th>
                                            <th class="rm-fuelcol" title="How the fuel surcharge is rounded for this lane. Default = the customer's rule; Round down = floor (e.g. TDC - Dole Asia DICT); Nearest / Round up override it.">Rounding</th>
                                            <th title="Uncheck to keep the row but exclude it from pricing">Active</th>
                                            <th style="width:42px;"></th>
                                        </tr></thead>
                                        <tbody id="rmLanesBody"></tbody>
                                    </table>
                                </div>

                                <input type="file" id="rmImportFile" accept=".xlsx" class="d-none">
                                <datalist id="rmLocationList"></datalist>
                                <datalist id="rmSegmentList"></datalist>
                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <span id="rmStatus" class="small text-muted align-self-center"></span>
                                    <button type="button" class="btn btn-outline-secondary" id="rmReload"><i class="bi bi-arrow-clockwise me-1"></i>Reload</button>
                                    <button type="button" class="btn btn-success" id="rmSave"><i class="bi bi-save me-1"></i>Save Matrix</button>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="locationsPane">
                                <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                                    <div>
                                        <h5 class="mb-1">Locations</h5>
                                        <div class="small text-muted">Set each location's <strong>Location Matrix</strong> (resolves the rate lane) and its <strong>Region</strong> — DAVAO or PANABO — which drives the SAP <strong>Route</strong> from a trip's actual pull-out and delivered locations. Existing locations only; you can update these values, not add or delete locations.</div>
                                    </div>
                                    <div style="min-width: 260px;">
                                        <label class="form-label small mb-1" for="locSearch">Search location</label>
                                        <input type="text" class="form-control" id="locSearch" placeholder="Filter by name or matrix...">
                                    </div>
                                </div>
                                <datalist id="locationMatrixOptions"></datalist>
                                <div class="table-responsive" style="max-height: 460px; overflow:auto;">
                                    <table class="table table-sm table-hover align-middle" id="locationsTable">
                                        <thead class="table-light"><tr><th style="width:80px;">ID</th><th>Location</th><th style="width:280px;">Location Matrix</th><th style="width:150px;" title="DAVAO / PANABO — drives the SAP Route">Region</th></tr></thead>
                                        <tbody id="locBody"></tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-end gap-2 mt-2">
                                    <span id="locStatus" class="small text-muted align-self-center"></span>
                                    <button type="button" class="btn btn-outline-secondary" id="locReload"><i class="bi bi-arrow-clockwise me-1"></i>Reload</button>
                                    <button type="button" class="btn btn-success" id="locSave"><i class="bi bi-save me-1"></i>Update Locations</button>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="skuRoutePane">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h5 class="mb-0">SKU Routes</h5>
                                        <div class="small text-muted">Pick an existing <strong>SKU</strong>, then build its <strong>Route</strong> from three existing locations — Pullout, PH, and Delivered. Segment, Farm and Roundtrip Distance come from the chosen SKU.</div>
                                    </div>
                                    <button class="btn btn-primary btn-add-item" data-entity="sku_route"><i class="bi bi-plus-circle me-1"></i>Add SKU Route</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle" id="skuRouteTable">
                                        <thead class="table-light">
                                            <tr><th>ID</th><th>SAP Assigned No</th><th>SKU</th><th>Segment</th><th>Farm</th><th>Route (Pullout → PH → Delivered)</th><th>Roundtrip Distance</th><th>Actions</th></tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="equipmentSapPane">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h5 class="mb-0">Equipment SAP Code</h5>
                                        <div class="small text-muted">Map each equipment <strong>Unit No</strong> (by type — Prime Mover, Trailer/Chassis, Genset) to its <strong>SAP Equipment Code</strong>. Used for the PM / TR / GS columns in the SAP billing upload.</div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-secondary" id="equipmentSapImportBtn"><i class="bi bi-upload me-1"></i>Import Excel</button>
                                        <button class="btn btn-primary btn-add-item" data-entity="equipment_sap"><i class="bi bi-plus-circle me-1"></i>Add Equipment SAP Code</button>
                                    </div>
                                </div>
                                <input type="file" id="equipmentSapImportFile" accept=".xlsx" class="d-none">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle" id="equipmentSapTable">
                                        <thead class="table-light">
                                            <tr><th>ID</th><th>Type</th><th>Unit No</th><th>SAP Equipment Code</th><th>Description</th><th>Actions</th></tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="customerSapPane">
                                <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                                    <div>
                                        <h5 class="mb-1">Customer SAP Codes</h5>
                                        <div class="small text-muted">Per-customer SAP billing settings: <strong>Sold-To</strong>, the <strong>Affiliate</strong> flag (Distribution Channel <strong>30</strong> affiliate / <strong>20</strong> not), the <strong>Material Code</strong> / <strong>Profit Center</strong> picked from the Service Materials and Profit Center master tabs, and whether the customer is <strong>VAT</strong> (adds 12% on the PANABO PDF). Leave a field blank / "use default" to fall back to the built-in value. Use <strong>Add Customer</strong> to create a new billed customer — no code change needed.</div>
                                    </div>
                                    <div class="d-flex align-items-end gap-2">
                                        <div style="min-width: 220px;">
                                            <label class="form-label small mb-1" for="csapSearch">Search customer</label>
                                            <input type="text" class="form-control" id="csapSearch" placeholder="Filter by name...">
                                        </div>
                                        <button type="button" class="btn btn-primary" id="ccAddBtn"><i class="bi bi-plus-circle me-1"></i>Add Customer</button>
                                    </div>
                                </div>
                                <div id="ccStatus" class="small text-muted mb-1"></div>

                                <div class="table-responsive" style="max-height: 460px; overflow:auto;">
                                    <table class="table table-sm table-hover align-middle" id="customerSapTable">
                                        <thead class="table-light"><tr><th>Customer</th><th style="width:110px;">Affiliate<div class="text-muted fw-normal" style="font-size:11px;">Dist. Ch. 30/20</div></th><th style="width:80px;" class="text-center">VAT<div class="text-muted fw-normal" style="font-size:11px;">+12% PDF</div></th><th style="width:150px;">Sold-To</th><th style="width:240px;">Material Code</th><th style="width:250px;">Profit Center</th><th style="width:90px;" class="text-end">Actions</th></tr></thead>
                                        <tbody id="csapBody"></tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-end gap-2 mt-2">
                                    <span id="csapStatus" class="small text-muted align-self-center"></span>
                                    <button type="button" class="btn btn-outline-secondary" id="csapReload"><i class="bi bi-arrow-clockwise me-1"></i>Reload</button>
                                    <button type="button" class="btn btn-success" id="csapSave"><i class="bi bi-save me-1"></i>Save SAP Codes</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="itemModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="itemModalTitle">Add Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="itemForm">
                    <div class="modal-body">
                        <input type="hidden" id="entityInput" name="entity">
                        <input type="hidden" id="recordIdInput" name="id">
                        <div class="row g-3" id="formFields"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add / edit a user-defined flat-rate billing customer -->
    <style>
        #ccModal .modal-body { max-height: 72vh; overflow-y: auto; }
        #ccModal .cc-section {
            border-top: 1px solid #e9ecef;
            margin: 1.1rem 0 .5rem;
            padding-top: .85rem;
            letter-spacing: .04em;
        }
        #ccModal .form-label { margin-bottom: .15rem; }
    </style>
    <div class="modal fade" id="ccModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ccModalTitle">Add Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="ccForm">
                    <div class="modal-body">
                        <input type="hidden" id="ccMode" value="create">
                        <p class="small text-muted">Fields marked <span class="text-danger">*</span> are required. The rest have SAP defaults you can adjust.</p>
                        <div id="ccBuiltinHint" class="alert alert-secondary py-2 px-3 small mb-2" style="display:none;"><i class="bi bi-lock me-1"></i>This is a <strong>built-in</strong> customer — its trip selection and SAP wiring are defined in code. You can change its <strong>name</strong> and <strong>VAT</strong> flag here.</div>

                        <h6 class="text-uppercase text-muted small fw-bold mt-2">Identity &amp; Trip Selection</h6>
                        <div class="row g-2">
                            <div class="col-md-6"><label class="form-label small mb-1">Customer Name <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" data-cc="label" placeholder="e.g. Del Monte Fresh"></div>
                            <div class="col-md-6"><label class="form-label small mb-1">Customer Key <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" data-cc="customer_key" placeholder="e.g. delmonte_fresh"><div class="form-text" style="font-size:11px;">Lowercase id, no spaces. Cannot change after creation.</div></div>
                            <div class="col-md-6"><label class="form-label small mb-1">Segment</label><input type="text" class="form-control form-control-sm" data-cc="segment" list="ccSegments" placeholder="operations.segment"><datalist id="ccSegments"></datalist></div>
                            <div class="col-md-6"><label class="form-label small mb-1">Customer Match</label><input type="text" class="form-control form-control-sm" data-cc="customer_match" placeholder="substring of customer/shipper"></div>
                            <div class="col-12"><div class="form-text text-info" style="font-size:11px;"><i class="bi bi-info-circle"></i> Set a Segment and/or Customer Match — at least one is required so the customer's trips get picked up.</div></div>
                        </div>

                        <div class="alert alert-info py-2 px-3 small mt-3 mb-2"><i class="bi bi-info-circle me-1"></i>Pricing is set in <strong>Master Data → Rate Matrix</strong>: after saving, add this customer's per-lane rates there. Each trip is priced from the fuel Rate Matrix.</div>

                        <h6 class="text-uppercase text-muted small fw-bold cc-section">SAP ZPSO Header</h6>
                        <div class="row g-2">
                            <div class="col-md-3"><label class="form-label small mb-1">Currency</label><select class="form-select form-select-sm" data-cc="document_currency"><option value="PHP">PHP</option><option value="USD">USD</option></select></div>
                            <div class="col-md-3"><label class="form-label small mb-1">Order Type</label><input type="text" class="form-control form-control-sm" data-cc="order_type"></div>
                            <div class="col-md-3"><label class="form-label small mb-1">Sales Org</label><input type="text" class="form-control form-control-sm" data-cc="sales_org"></div>
                            <div class="col-md-3"><label class="form-label small mb-1">Division</label><input type="text" class="form-control form-control-sm" data-cc="division"></div>
                            <div class="col-md-3"><label class="form-label small mb-1">Sold-To</label><input type="text" class="form-control form-control-sm" data-cc="sold_to"></div>
                            <div class="col-md-3"><label class="form-label small mb-1">Tax Class</label><input type="text" class="form-control form-control-sm" data-cc="customer_tax_class"></div>
                            <div class="col-md-3"><label class="form-label small mb-1">Sales Unit</label><input type="text" class="form-control form-control-sm" data-cc="sales_unit"></div>
                            <div class="col-md-3"><label class="form-label small mb-1">Condition Type</label><input type="text" class="form-control form-control-sm" data-cc="condition_type"></div>
                            <div class="col-md-3"><label class="form-label small mb-1">Condition Unit</label><input type="text" class="form-control form-control-sm" data-cc="condition_unit"></div>
                            <div class="col-md-3"><label class="form-label small mb-1">Route</label><input type="text" class="form-control form-control-sm" data-cc="route"></div>
                            <div class="col-md-9"><label class="form-label small mb-1">Billed Services</label><input type="text" class="form-control form-control-sm" data-cc="billed_services" placeholder="e.g. Hauling Containerized Bananas"></div>
                            <div class="col-md-6"><label class="form-label small mb-1">Material Code</label><select class="form-select form-select-sm" data-cc="material_code"></select></div>
                            <div class="col-md-6"><label class="form-label small mb-1">Profit Center</label><select class="form-select form-select-sm" data-cc="profit_center"></select></div>
                            <div class="col-md-6"><div class="form-check mt-1"><input type="checkbox" class="form-check-input" id="ccAffiliate"><label class="form-check-label small" for="ccAffiliate">Affiliate customer (Distribution Channel 30; unticked = 20)</label></div></div>
                            <div class="col-md-6"><div class="form-check mt-1"><input type="checkbox" class="form-check-input" id="ccVat"><label class="form-check-label small" for="ccVat">VAT customer (adds 12% VAT on the PANABO PDF)</label></div></div>
                        </div>

                        <h6 class="text-uppercase text-muted small fw-bold cc-section">PANABO PDF Invoice</h6>
                        <div class="row g-2">
                            <div class="col-md-6"><label class="form-label small mb-1">Bill-To Name</label><input type="text" class="form-control form-control-sm" data-cc="bill_to_name"></div>
                            <div class="col-md-6"><label class="form-label small mb-1">Activity</label><input type="text" class="form-control form-control-sm" data-cc="activity" placeholder="e.g. HAULING OF CONTAINERIZED BANANAS"></div>
                            <div class="col-md-6"><label class="form-label small mb-1">Destination</label><input type="text" class="form-control form-control-sm" data-cc="destination"></div>
                            <div class="col-md-6"><label class="form-label small mb-1">Origin</label><input type="text" class="form-control form-control-sm" data-cc="origin"></div>
                            <div class="col-md-6"><label class="form-label small mb-1">Packing Station</label><input type="text" class="form-control form-control-sm" data-cc="packing_station"></div>
                            <div class="col-md-6"><label class="form-label small mb-1">Port of Loading</label><input type="text" class="form-control form-control-sm" data-cc="port_of_loading"></div>
                            <div class="col-md-12"><label class="form-label small mb-1">Reference Prefix</label><input type="text" class="form-control form-control-sm" data-cc="reference_prefix" placeholder="defaults to the customer name"></div>
                        </div>
                        <div id="ccFormStatus" class="small text-danger mt-2"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="ccSaveBtn">Save Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Fuel-price bands editor for one rate-matrix lane -->
    <div class="modal fade" id="rmBandModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Fuel-Price Bands <span id="rmBandLane" class="text-muted fw-normal small"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 px-3 small">
                        A <strong>stepped (flat) rate per diesel-price range</strong>. When any band is set, the lane is priced by bands instead of the 0.4× formula:
                        the band whose <strong>From–To</strong> range contains the trip's diesel price wins. Below the lowest band, the lane's <strong>Base Rate</strong> is charged.
                        Leave the top band's <strong>To</strong> blank so the highest diesel prices are always covered. Blank <strong>From</strong> = no lower bound.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-light"><tr>
                                <th style="width:150px;" title="Diesel price from (inclusive). Blank = no lower bound.">Diesel From</th>
                                <th style="width:150px;" title="Diesel price to (inclusive). Blank = open-ended (covers all higher prices).">Diesel To</th>
                                <th title="Flat rate charged when diesel falls in this range">Rate (₱)</th>
                                <th style="width:42px;"></th>
                            </tr></thead>
                            <tbody id="rmBandBody"></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="rmBandAdd"><i class="bi bi-plus-circle me-1"></i>Add band</button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="rmBandApply">Apply bands</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/DataTables/datatables.min.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        $(document).ready(function () {
            $("#mdnav").attr({ "class" : "nav-link active" });
        });
    </script>
    <script>
        // Billing customers for the per-row Customer tag (Service Materials / Profit Center).
        const MD_BILLING_CUSTOMERS = <?php echo json_encode($mdBillingCustomers); ?>;
        const MD_CUSTOMER_LABEL = Object.fromEntries(MD_BILLING_CUSTOMERS.map(c => [c.key, c.label]));
        const MD_CUSTOMER_OPTIONS = MD_BILLING_CUSTOMERS.map(c => ({ value: c.key, label: c.label }));

        // SKU + Location lists for the SKU Routes tab.
        const MD_SKUS = <?php echo json_encode($mdSkus); ?>;
        const MD_SKU_INFO = Object.fromEntries(MD_SKUS.map(s => [String(s.id), s]));
        const MD_SKU_OPTIONS = MD_SKUS.map(s => ({ value: String(s.id), label: s.name }));
        const MD_LOCATIONS = <?php echo json_encode($mdLocations); ?>;
        const MD_LOCATION_OPTIONS = MD_LOCATIONS.map(n => ({ value: n, label: n }));

        document.addEventListener('DOMContentLoaded', function () {
            const entityConfig = {
                fuel_price: {
                    label: 'Fuel Price',
                    idField: 'id',
                    table: new DataTable('#fuelPriceTable'),
                    fields: [
                        { name: 'effective_from', label: 'From (date)', required: true, type: 'date' },
                        { name: 'effective_to', label: 'To (date)', type: 'date' },
                        { name: 'updated_date', label: 'Updated Date — starts billing effectivity', type: 'date' },
                        { name: 'petron', label: 'Petron' },
                        { name: 'shell', label: 'Shell' },
                        { name: 'caltex', label: 'Caltex' },
                        { name: 'phoenix', label: 'Phoenix' },
                        { name: 'flying_v', label: 'Flying V' },
                        { name: 'seaoil', label: 'Seaoil' },
                        { name: 'jetti', label: 'Jetti' },
                        { name: 'my_gas', label: 'My Gas' },
                        { name: 'independent', label: 'Independent' },
                        { name: 'common_price', label: 'Common Price (blank = auto)' },
                        { name: 'fuel_tier', label: 'Fuel Tier % (e.g. 115 — Sumifru)' },
                        { name: 'average', label: 'Average (3 leading)', readonly: true },
                        { name: 'customer_keys', label: 'Customers (blank = all)', type: 'multiselect' }
                    ]
                },
                forex_rate: {
                    label: 'Forex Rate',
                    idField: 'id',
                    table: new DataTable('#forexRateTable'),
                    fields: [
                        { name: 'effective_from', label: 'From (date)', required: true, type: 'date' },
                        { name: 'effective_to', label: 'To (date)', type: 'date' },
                        { name: 'updated_date', label: 'Updated Date — starts billing effectivity', type: 'date' },
                        { name: 'rate', label: 'USD → PHP Rate', required: true },
                        { name: 'customer_keys', label: 'Customers (blank = all)', type: 'multiselect' }
                    ]
                },
                sku_route: {
                    label: 'SKU Route',
                    idField: 'id',
                    table: new DataTable('#skuRouteTable'),
                    fields: [
                        { name: 'sap_assigned_no', label: 'SAP Assigned No' },
                        { name: 'sku_id', label: 'SKU', required: true, type: 'select', options: MD_SKU_OPTIONS },
                        { name: 'pullout', label: 'Pullout Location', type: 'select', options: MD_LOCATION_OPTIONS },
                        { name: 'ph', label: 'PH', type: 'select', options: MD_LOCATION_OPTIONS },
                        { name: 'delivered', label: 'Delivered Location', type: 'select', options: MD_LOCATION_OPTIONS }
                    ]
                },
                equipment_sap: {
                    label: 'Equipment SAP Code',
                    idField: 'id',
                    table: new DataTable('#equipmentSapTable'),
                    fields: [
                        { name: 'equipment_type', label: 'Equipment Type', required: true, type: 'select', options: [
                            { value: 'PM', label: 'PM — Prime Mover' },
                            { value: 'TR', label: 'TR — Trailer / Chassis' },
                            { value: 'RV', label: 'RV — Reefer Van' },
                            { value: 'GS', label: 'GS — Genset' }
                        ] },
                        { name: 'unit_no', label: 'Unit No', required: true },
                        { name: 'sap_equipment_code', label: 'SAP Equipment Code', required: true },
                        { name: 'description', label: 'Description' }
                    ]
                },
                service_material: {
                    label: 'Service Material',
                    idField: 'id',
                    table: new DataTable('#serviceMaterialTable'),
                    fields: [
                        { name: 'revenue_stream', label: 'Revenue Stream', required: true },
                        { name: 'material_code', label: 'Material Code', required: true },
                        { name: 'material_description', label: 'Material Description', required: true },
                        { name: 'rate_type', label: 'Rate Type' },
                        { name: 'rate', label: 'Rate' },
                        { name: 'tax_class', label: 'Tax Class' },
                        { name: 'profit_center', label: 'Profit Center' },
                        { name: 'account_assignment', label: 'Account Assignment' },
                        { name: 'gen_item_category_group', label: 'Gen. Item Category Group' },
                        { name: 'item_category_group2', label: 'Item Category Group2' },
                        { name: 'customer_key', label: 'Customer', type: 'select', options: MD_CUSTOMER_OPTIONS }
                    ]
                },
                profit_center: {
                    label: 'Profit Center',
                    idField: 'id',
                    table: new DataTable('#profitCenterTable'),
                    fields: [
                        { name: 'profit_center_code', label: 'Profit Center', required: true },
                        { name: 'controlling_area', label: 'Controlling Area' },
                        { name: 'valid_from_date', label: 'Valid From Date' },
                        { name: 'valid_to_date', label: 'Valid To Date' },
                        { name: 'name', label: 'Name', required: true },
                        { name: 'long_text', label: 'Long Text' },
                        { name: 'person_responsible', label: 'Person Responsible' },
                        { name: 'department', label: 'Department' },
                        { name: 'profit_center_group', label: 'Profit Center Group' },
                        { name: 'segment', label: 'Segment' },
                        { name: 'customer_key', label: 'Customer', type: 'select', options: MD_CUSTOMER_OPTIONS }
                    ]
                },
                rates: {
                    label: 'Rate',
                    idField: 'id',
                    table: new DataTable('#ratesTable'),
                    fields: [
                        { name: 'origin', label: 'Origin', required: true },
                        { name: 'packing_house', label: 'Packing House' },
                        { name: 'port_of_destination', label: 'Port of Destination' },
                        { name: 'loc_code', label: 'Loc. Code' },
                        { name: 'rate_code', label: 'Rate Code' },
                        { name: 'rate', label: 'Rate' }
                    ]
                }
            };

            const modal = new bootstrap.Modal(document.getElementById('itemModal'));
            const entityInput = document.getElementById('entityInput');
            const recordIdInput = document.getElementById('recordIdInput');
            const formFields = document.getElementById('formFields');
            const itemModalTitle = document.getElementById('itemModalTitle');
            const itemForm = document.getElementById('itemForm');
            // Keep a multiselect's hidden input (comma-separated keys) in sync with its checkboxes.
            formFields.addEventListener('change', function (e) {
                const cb = e.target.closest('.ms-cb');
                if (!cb) return;
                const field = cb.dataset.field;
                const hidden = document.getElementById(field);
                if (!hidden) return;
                const vals = Array.from(formFields.querySelectorAll(`.ms-cb[data-field="${field}"]:checked`)).map(c => c.value);
                hidden.value = vals.join(',');
            });
            const fuelPriceTemplateBtn = document.getElementById('fuelPriceTemplateBtn');
            const fuelPriceImportBtn = document.getElementById('fuelPriceImportBtn');
            const fuelPriceImportFile = document.getElementById('fuelPriceImportFile');
            const serviceMaterialTemplateBtn = document.getElementById('serviceMaterialTemplateBtn');
            const serviceMaterialImportBtn = document.getElementById('serviceMaterialImportBtn');
            const serviceMaterialImportFile = document.getElementById('serviceMaterialImportFile');
            const profitCenterTemplateBtn = document.getElementById('profitCenterTemplateBtn');
            const profitCenterImportBtn = document.getElementById('profitCenterImportBtn');
            const profitCenterImportFile = document.getElementById('profitCenterImportFile');
            const ratesTemplateBtn = document.getElementById('ratesTemplateBtn');
            const ratesImportBtn = document.getElementById('ratesImportBtn');
            const ratesImportFile = document.getElementById('ratesImportFile');
            const equipmentSapImportBtn = document.getElementById('equipmentSapImportBtn');
            const equipmentSapImportFile = document.getElementById('equipmentSapImportFile');

            function parseNumber(value) {
                const parsed = Number.parseFloat(String(value ?? '').trim());
                return Number.isFinite(parsed) ? parsed : 0;
            }

            function formatComputedNumber(value) {
                const rounded = Math.round(value * 100) / 100;
                return String(rounded);
            }

            function updateComputedFields() {
                if (entityInput.value === 'fuel_price') {
                    const petronInput = document.getElementById('petron');
                    const shellInput = document.getElementById('shell');
                    const caltexInput = document.getElementById('caltex');
                    const averageInput = document.getElementById('average');
                    if (!petronInput || !shellInput || !caltexInput || !averageInput) {
                        return;
                    }
                    const values = [petronInput.value, shellInput.value, caltexInput.value]
                        .map(value => value.trim())
                        .filter(value => value !== '' && Number.isFinite(Number.parseFloat(value)))
                        .map(parseNumber);
                    averageInput.value = values.length
                        ? formatComputedNumber(values.reduce((sum, value) => sum + value, 0) / values.length)
                        : '';
                }
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function encodeRow(row) {
                return encodeURIComponent(JSON.stringify(row));
            }

            function actionButtons(entity, row) {
                return `
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary btn-edit-item" data-entity="${entity}" data-row="${encodeRow(row)}"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-outline-danger btn-delete-item" data-entity="${entity}" data-id="${row[entityConfig[entity].idField]}"><i class="bi bi-trash"></i></button>
                    </div>
                `;
            }

            // Render a customer_key tag: the billing customer label, or a muted dash.
            function customerTag(key) {
                key = String(key || '').trim();
                if (key === '') { return '<span class="text-muted">—</span>'; }
                return `<span class="badge text-bg-light border">${escapeHtml(MD_CUSTOMER_LABEL[key] || key)}</span>`;
            }

            // Render a comma-separated customer_keys list as labelled badges ("All" when blank).
            function mdCustomerTags(keys) {
                const list = String(keys || '').split(',').map(s => s.trim()).filter(Boolean);
                if (!list.length) return '<span class="badge bg-light text-dark border">All</span>';
                return list.map(k => `<span class="badge bg-info-subtle text-dark border me-1">${escapeHtml(MD_CUSTOMER_LABEL[k] || k)}</span>`).join('');
            }

            function renderTable(entity, rows) {
                const config = entityConfig[entity];
                const tableRows = rows.map(row => {
                    if (entity === 'fuel_price') {
                        return [
                            `<strong>#${row.id}</strong>`,
                            escapeHtml(row.effective_from || row.price_date || ''),
                            escapeHtml(row.effective_to || '-'),
                            escapeHtml(row.updated_date || '-'),
                            escapeHtml(row.petron || '-'),
                            escapeHtml(row.shell || '-'),
                            escapeHtml(row.caltex || '-'),
                            escapeHtml(row.seaoil || '-'),
                            escapeHtml(row.common_price || '-'),
                            escapeHtml(row.fuel_tier || '-'),
                            mdCustomerTags(row.customer_keys),
                            actionButtons(entity, row)
                        ];
                    }

                    if (entity === 'forex_rate') {
                        return [
                            `<strong>#${row.id}</strong>`,
                            escapeHtml(row.effective_from || '-'),
                            escapeHtml(row.effective_to || '-'),
                            escapeHtml(row.updated_date || '-'),
                            escapeHtml(row.rate || '-'),
                            mdCustomerTags(row.customer_keys),
                            actionButtons(entity, row)
                        ];
                    }

                    if (entity === 'equipment_sap') {
                        return [
                            `<strong>#${row.id}</strong>`,
                            `<span class="badge bg-light text-dark border">${escapeHtml(row.equipment_type || '-')}</span>`,
                            escapeHtml(row.unit_no || '-'),
                            escapeHtml(row.sap_equipment_code || '-'),
                            escapeHtml(row.description || '-'),
                            actionButtons(entity, row)
                        ];
                    }

                    if (entity === 'sku_route') {
                        const sku = MD_SKU_INFO[String(row.sku_id)] || {};
                        const routeParts = [row.pullout, row.ph, row.delivered].filter(Boolean).map(escapeHtml);
                        const route = routeParts.length ? routeParts.join(' <i class="bi bi-arrow-right-short"></i> ') : '<span class="text-muted">—</span>';
                        return [
                            `<strong>#${row.id}</strong>`,
                            escapeHtml(row.sap_assigned_no || '-'),
                            escapeHtml(sku.name || ('SKU #' + (row.sku_id || '?'))),
                            escapeHtml(sku.segment || '-'),
                            escapeHtml(sku.farm || '-'),
                            route,
                            escapeHtml(sku.distance || '-'),
                            actionButtons(entity, row)
                        ];
                    }

                    if (entity === 'service_material') {
                        return [
                            `<strong>#${row.id}</strong>`,
                            customerTag(row.customer_key),
                            escapeHtml(row.revenue_stream || ''),
                            escapeHtml(row.material_code || ''),
                            escapeHtml(row.material_description || ''),
                            escapeHtml(row.rate_type || '-'),
                            escapeHtml(row.rate || '-'),
                            escapeHtml(row.tax_class || '-'),
                            escapeHtml(row.profit_center || '-'),
                            actionButtons(entity, row)
                        ];
                    }

                    if (entity === 'profit_center') {
                        return [
                            `<strong>#${row.id}</strong>`,
                            customerTag(row.customer_key),
                            escapeHtml(row.profit_center_code || ''),
                            escapeHtml(row.controlling_area || '-'),
                            escapeHtml(row.name || ''),
                            escapeHtml(row.department || '-'),
                            escapeHtml(row.segment || '-'),
                            actionButtons(entity, row)
                        ];
                    }

                    return [
                        `<strong>#${row.id}</strong>`,
                        escapeHtml(row.origin || ''),
                        escapeHtml(row.packing_house || '-'),
                        escapeHtml(row.port_of_destination || '-'),
                        escapeHtml(row.rate_code || '-'),
                        escapeHtml(row.rate || '-'),
                        actionButtons(entity, row)
                    ];
                });

                config.table.clear();
                config.table.rows.add(tableRows);
                config.table.draw();
            }

            function updateCounts(data) {
                document.getElementById('fuelPriceCount').textContent = String((data.fuel_price || []).length);
                document.getElementById('serviceMaterialCount').textContent = String((data.service_material || []).length);
                document.getElementById('profitCenterCount').textContent = String((data.profit_center || []).length);
                document.getElementById('ratesCount').textContent = String((data.rates || []).length);
            }

            async function loadData() {
                const response = await fetch('php/fetch/get_settings_data.php', { cache: 'no-store' });
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to load master data.');
                }

                Object.keys(entityConfig).forEach(entity => {
                    renderTable(entity, data.data[entity] || []);
                });
                updateCounts(data.data || {});
            }

            // Keep the user on the current tab and refresh only the table that
            // changed after an add, edit, or delete.
            async function refreshEntity(entity) {
                const response = await fetch('php/fetch/get_settings_data.php', { cache: 'no-store' });
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.message || 'Failed to refresh master data.');
                }
                renderTable(entity, data.data[entity] || []);
                updateCounts(data.data || {});
            }

            function showImportResult(data) {
                return Swal.fire({
                    title: data.success ? 'Import complete' : 'Import finished',
                    html: `
                        <div class="text-start">
                            <div>${escapeHtml(data.message || 'Import finished.')}</div>
                            ${data.errors && data.errors.length ? `<hr><div class="small text-danger">${data.errors.map(escapeHtml).join('<br>')}</div>` : ''}
                        </div>
                    `,
                    icon: data.success ? 'success' : 'warning',
                    confirmButtonColor: data.success ? '#198754' : '#f0ad4e'
                });
            }

            function registerMasterDataImport(entity, templateBtn, importBtn, importFile) {
                templateBtn.addEventListener('click', function () {
                    const params = new URLSearchParams({ entity });
                    window.location.href = 'php/fetch/download_master_data_template.php?' + params.toString();
                });

                importBtn.addEventListener('click', function () {
                    importFile.value = '';
                    importFile.click();
                });

                importFile.addEventListener('change', async function () {
                    if (!importFile.files.length) {
                        return;
                    }

                    const fd = new FormData();
                    fd.append('entity', entity);
                    fd.append('xlsx_file', importFile.files[0]);
                    importBtn.disabled = true;
                    templateBtn.disabled = true;

                    try {
                        const res = await fetch('php/insert/import_master_data.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        await showImportResult(data);
                        if (data.imported > 0) {
                            await loadData();
                        }
                    } catch (error) {
                        await Swal.fire({
                            title: 'Error',
                            text: error.message,
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    } finally {
                        importBtn.disabled = false;
                        templateBtn.disabled = false;
                        importFile.value = '';
                    }
                });
            }

            registerMasterDataImport('fuel_price', fuelPriceTemplateBtn, fuelPriceImportBtn, fuelPriceImportFile);
            registerMasterDataImport('forex_rate',
                document.getElementById('forexTemplateBtn'),
                document.getElementById('forexImportBtn'),
                document.getElementById('forexImportFile'));
            registerMasterDataImport('service_material', serviceMaterialTemplateBtn, serviceMaterialImportBtn, serviceMaterialImportFile);
            registerMasterDataImport('profit_center', profitCenterTemplateBtn, profitCenterImportBtn, profitCenterImportFile);

            equipmentSapImportBtn.addEventListener('click', function () {
                equipmentSapImportFile.value = '';
                equipmentSapImportFile.click();
            });

            equipmentSapImportFile.addEventListener('change', async function () {
                if (!equipmentSapImportFile.files.length) return;
                const fd = new FormData();
                fd.append('xlsx_file', equipmentSapImportFile.files[0]);
                equipmentSapImportBtn.disabled = true;
                try {
                    const res = await fetch('php/insert/import_equipment_sap.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    await showImportResult(data);
                    if ((data.imported || 0) + (data.updated || 0) > 0) await loadData();
                } catch (error) {
                    await Swal.fire({ title: 'Error', text: error.message, icon: 'error', confirmButtonColor: '#dc3545' });
                } finally {
                    equipmentSapImportBtn.disabled = false;
                    equipmentSapImportFile.value = '';
                }
            });

            ratesTemplateBtn.addEventListener('click', function () {
                window.location.href = 'php/fetch/download_rates_template.php';
            });

            ratesImportBtn.addEventListener('click', function () {
                ratesImportFile.value = '';
                ratesImportFile.click();
            });

            ratesImportFile.addEventListener('change', async function () {
                if (!ratesImportFile.files.length) {
                    return;
                }

                const fd = new FormData();
                fd.append('xlsx_file', ratesImportFile.files[0]);
                ratesImportBtn.disabled = true;
                ratesTemplateBtn.disabled = true;

                try {
                    const res = await fetch('php/insert/import_rates.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    await showImportResult(data);
                    if (data.imported > 0) {
                        await loadData();
                    }
                } catch (error) {
                    await Swal.fire({
                        title: 'Error',
                        text: error.message,
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                } finally {
                    ratesImportBtn.disabled = false;
                    ratesTemplateBtn.disabled = false;
                    ratesImportFile.value = '';
                }
            });

            function openForm(entity, row = null) {
                const config = entityConfig[entity];
                entityInput.value = entity;
                recordIdInput.value = row ? String(row[config.idField]) : '';
                itemModalTitle.textContent = `${row ? 'Edit' : 'Add'} ${config.label}`;
                formFields.innerHTML = config.fields.map(field => {
                    const current = row ? (row[field.name] ?? '') : '';
                    let control;
                    if (field.type === 'select') {
                        const cur = String(current);
                        const opts = ['<option value="">— none —</option>']
                            .concat((field.options || []).map(o =>
                                `<option value="${escapeHtml(o.value)}" ${String(o.value) === cur ? 'selected' : ''}>${escapeHtml(o.label)}</option>`));
                        control = `<select class="form-select" id="${field.name}" name="${field.name}" ${field.required ? 'required' : ''}>${opts.join('')}</select>`;
                    } else if (field.type === 'multiselect') {
                        // Comma-separated keys stored in a hidden input; checkboxes drive it.
                        const selected = String(current).split(',').map(s => s.trim()).filter(Boolean);
                        const boxes = (MD_CUSTOMER_OPTIONS || []).map(o =>
                            `<label class="d-block small mb-1"><input type="checkbox" class="form-check-input me-1 ms-cb" data-field="${field.name}" value="${escapeHtml(o.value)}" ${selected.includes(String(o.value)) ? 'checked' : ''}>${escapeHtml(o.label)}</label>`
                        ).join('');
                        control = `<input type="hidden" id="${field.name}" name="${field.name}" value="${escapeHtml(String(current))}">
                            <div class="border rounded p-2" style="max-height:170px;overflow:auto;">${boxes || '<span class="text-muted small">No customers</span>'}</div>
                            <div class="form-text">Leave all unchecked = applies to every customer.</div>`;
                    } else {
                        control = `<input type="${field.type || 'text'}" class="form-control" id="${field.name}" name="${field.name}" value="${escapeHtml(current)}" ${field.required ? 'required' : ''} ${field.readonly ? 'readonly' : ''}>`;
                    }
                    return `
                    <div class="col-md-6">
                        <label class="form-label" for="${field.name}">${escapeHtml(field.label)}</label>
                        ${control}
                    </div>`;
                }).join('');

                // SKU Routes: show the chosen SKU's Segment / Farm / Roundtrip Distance
                // (read-only, straight from the SKU) and keep it live as the SKU changes.
                if (entity === 'sku_route') {
                    const info = document.createElement('div');
                    info.className = 'col-12';
                    info.innerHTML = '<div class="alert alert-light border small mb-0" id="skuRouteInfo"></div>';
                    formFields.appendChild(info);
                    const skuSel = document.getElementById('sku_id');
                    const paint = () => {
                        const s = MD_SKU_INFO[String(skuSel.value)] || {};
                        document.getElementById('skuRouteInfo').innerHTML = skuSel.value
                            ? `<strong>Segment:</strong> ${escapeHtml(s.segment || '-')} &nbsp;·&nbsp; <strong>Farm:</strong> ${escapeHtml(s.farm || '-')} &nbsp;·&nbsp; <strong>Roundtrip Distance:</strong> ${escapeHtml(s.distance || '-')} <span class="text-muted">— from the SKU</span>`
                            : 'Pick a SKU to see its Segment, Farm and Roundtrip Distance.';
                    };
                    skuSel.addEventListener('change', paint);
                    paint();
                }

                updateComputedFields();
                modal.show();
            }

            async function saveItem(event) {
                event.preventDefault();
                const isEdit = recordIdInput.value.trim() !== '';
                const entity = entityInput.value;
                const endpoint = isEdit ? 'php/update/settings_item.php' : 'php/insert/settings_item.php';
                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: new FormData(itemForm)
                });
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Save failed.');
                }

                modal.hide();
                itemForm.reset();
                await refreshEntity(entity);
                await Swal.fire({
                    title: 'Success',
                    text: data.message,
                    icon: 'success',
                    confirmButtonColor: '#0d6efd'
                });
            }

            async function deleteItem(entity, id) {
                const confirmResult = await Swal.fire({
                    title: 'Delete item?',
                    text: 'This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    confirmButtonColor: '#dc3545'
                });

                if (!confirmResult.isConfirmed) {
                    return;
                }

                const payload = new FormData();
                payload.append('entity', entity);
                payload.append('id', String(id));

                const response = await fetch('php/delete/settings_item.php', {
                    method: 'POST',
                    body: payload
                });
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Delete failed.');
                }

                await refreshEntity(entity);
                await Swal.fire({
                    title: 'Deleted',
                    text: data.message,
                    icon: 'success',
                    confirmButtonColor: '#0d6efd'
                });
            }

            document.querySelectorAll('.btn-add-item').forEach(button => {
                button.addEventListener('click', function () {
                    openForm(button.dataset.entity);
                });
            });

            document.addEventListener('click', async function (event) {
                const editButton = event.target.closest('.btn-edit-item');
                const deleteButton = event.target.closest('.btn-delete-item');

                if (editButton) {
                    openForm(editButton.dataset.entity, JSON.parse(decodeURIComponent(editButton.dataset.row)));
                }

                if (deleteButton) {
                    try {
                        await deleteItem(deleteButton.dataset.entity, deleteButton.dataset.id);
                    } catch (error) {
                        Swal.fire({
                            title: 'Error',
                            text: error.message,
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                }
            });

            itemForm.addEventListener('submit', async function (event) {
                try {
                    updateComputedFields();
                    await saveItem(event);
                } catch (error) {
                    Swal.fire({
                        title: 'Error',
                        text: error.message,
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
            });

            document.addEventListener('input', function (event) {
                if (event.target && ['petron', 'shell', 'caltex'].includes(event.target.id)) {
                    updateComputedFields();
                }
            });

            loadData().catch(error => {
                Swal.fire({
                    title: 'Error',
                    text: error.message,
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            });
        });
    </script>
    <script>
        // ---- Rate Matrix editor (single flat, formula-priced table) ----
        document.addEventListener('DOMContentLoaded', function () {
            const customerSel = document.getElementById('rmCustomer');
            const lanesBody = document.getElementById('rmLanesBody');
            const statusEl = document.getElementById('rmStatus');
            const downloadTemplateBtn = document.getElementById('rmDownloadTemplate');
            const importExcelBtn = document.getElementById('rmImportExcel');
            const importFileInput = document.getElementById('rmImportFile');
            let customersLoaded = false;

            // In-memory model: one array of lane rows, each with the formula params.
            let lanes = [];
            let rmMonthSelected = null; // selected month (YYYY-MM) for the grid month filter; null = pick default
            let locations = [];       // [{name, matrix}] for the origin/destination dropdowns
            let locByUpper = {};      // NAME(upper) -> location_matrix code (for auto DCode)
            let rmCurrentFuel = null; // current diesel price (₱), for the live "Rate (now)" column
            let rmFuelDate = '';

            // Customer-level default rounding mode for the fuel surcharge: 'down' (truncate),
            // 'up' (ceil) or 'nearest'. Mirrors rate_matrix_customer_round_mode() in PHP:
            // ABC Lupon - Dole Asia floors; ABC Cateel rounds up; everyone else (incl. TDC -
            // Dole Asia, Sumifru) rounds to nearest. This is the FALLBACK: a lane's own
            // `round_mode` (the Rounding dropdown) overrides it in both this preview
            // (rmRateDisplay) and billing, so the grid now matches billing exactly.
            function rmRoundMode() {
                const k = customerSel.value || '';
                if (k.startsWith('dole_asia') && k !== 'dole_asia_tdc') return 'down';
                if (k === 'abc_cateel') return 'up';
                return 'nearest';
            }
            // Billing formula mirror: round|roundup|roundoff(Base × 40% × movement%) + Base,
            // movement banded to the Price Movement step. Must match rate_matrix_formula_price()
            // in PHP. `mode` is 'down' | 'up' | 'nearest'.
            function rmComputeRate(base, pump, step, fuel, mode) {
                base = Number.parseFloat(base);
                if (!Number.isFinite(base)) return null;
                pump = Number.parseFloat(pump);
                step = Number.parseFloat(step);
                fuel = Number.parseFloat(fuel);
                if (!Number.isFinite(pump) || pump <= 0 || !Number.isFinite(step) || step <= 0
                    || !Number.isFinite(fuel) || fuel <= pump) {
                    return Math.round(base); // below pump / missing params -> base rate (whole)
                }
                const steps = Math.floor((fuel - pump) / step);
                if (steps <= 0) return Math.round(base);
                const movementPct = (steps * step) / pump;
                // Surcharge on its own, then added to the base. The 1e-9 epsilon guards an exact-
                // integer surcharge from float underflow so 'down' doesn't drop a peso and 'up'
                // doesn't add one. (base 16,456 @40% ->nearest 19,089; Dole 22,139 @55% ->down
                // 27,009; Cateel 35,292 @55% ->up 43,057.)
                const surcharge = base * 0.4 * movementPct;
                let s;
                if (mode === 'down') s = Math.floor(surcharge + 1e-9);
                else if (mode === 'up') s = Math.ceil(surcharge - 1e-9);
                else s = Math.round(surcharge);
                // Surcharge rounded per mode; final charged rate is always a whole peso amount.
                return Math.round(s + base);
            }
            // Banded (stepped) price for a lane at a fuel price, or null when the lane has no
            // bands (caller uses the formula). Mirrors rate_lane_banded_price() in PHP.
            function rmBandedRate(l, fuel) {
                const bands = Array.isArray(l.bands) ? l.bands : [];
                if (!bands.length) return null;
                const f = Number.parseFloat(fuel);
                if (!Number.isFinite(f)) return null;
                for (const b of bands) {
                    const from = (b.fuel_from === '' || b.fuel_from === null || b.fuel_from === undefined) ? null : Number.parseFloat(b.fuel_from);
                    const to = (b.fuel_to === '' || b.fuel_to === null || b.fuel_to === undefined) ? null : Number.parseFloat(b.fuel_to);
                    if ((from === null || f >= from) && (to === null || f <= to)) return Number.parseFloat(b.rate);
                }
                return Number.parseFloat(l.base_rate); // below the lowest band -> base rate
            }
            // The pinned Monthly Avg Fuel for a lane, or null. Escalation applies only when
            // this is set; a blank monthly average charges the base rate (user rule).
            function rmPinnedFuel(l) {
                const f = (l.rate_fuel !== undefined && l.rate_fuel !== null && l.rate_fuel !== '')
                    ? l.rate_fuel
                    : (l.monthly_fuel_average !== undefined && l.monthly_fuel_average !== null && l.monthly_fuel_average !== '' && Number.parseFloat(l.monthly_fuel_average) > 0
                        ? l.monthly_fuel_average : null);
                const n = Number.parseFloat(f);
                return (f !== null && Number.isFinite(n) && n > 0) ? n : null;
            }
            // The read-only cell content for a lane's current charged rate.
            function rmRateDisplay(l) {
                const fuel = rmPinnedFuel(l);
                if (fuel === null) {
                    // No pinned Monthly Avg Fuel -> base rate (escalation only when fuel is set).
                    const base = Number.parseFloat(l.base_rate);
                    return Number.isFinite(base) ? Math.round(base).toLocaleString('en-US', { maximumFractionDigits: 0 }) : '<span class="text-muted">—</span>';
                }
                const banded = rmBandedRate(l, fuel);
                // Per-lane round_mode overrides the customer default, so the preview matches billing.
                const laneMode = String(l.round_mode || '').toLowerCase();
                const mode = (laneMode === 'down' || laneMode === 'up' || laneMode === 'nearest') ? laneMode : rmRoundMode();
                const r = banded !== null ? banded : rmComputeRate(l.base_rate, l.pump_price, l.price_movement, fuel, mode);
                if (r === null || !Number.isFinite(Number(r))) return '<span class="text-muted">—</span>';
                // Rate (now) is always a whole peso amount.
                return Math.round(Number(r)).toLocaleString('en-US', { maximumFractionDigits: 0 });
            }

            // The fuel movement % behind the current Rate (now). For BANDED lanes the formula
            // doesn't apply, so the movement is the actual banded rate over base (0% when the
            // band charges the base rate, e.g. the flat Mati lanes) — NOT the formula %.
            function rmMovementDisplay(l) {
                const fuel = rmPinnedFuel(l);
                if (fuel === null) return '0.00%'; // no pinned fuel -> base rate, no movement
                const banded = rmBandedRate(l, fuel);
                if (banded !== null) {
                    const base = Number.parseFloat(l.base_rate);
                    if (!Number.isFinite(base) || base <= 0) return '0.00%';
                    return (((banded / base) - 1) * 100).toLocaleString('en-US', {
                        minimumFractionDigits: 2, maximumFractionDigits: 2
                    }) + '%';
                }
                const pump = Number.parseFloat(l.pump_price);
                const step = Number.parseFloat(l.price_movement);
                const fuelNumber = Number.parseFloat(fuel);
                if (!Number.isFinite(pump) || pump <= 0 || !Number.isFinite(step) || step <= 0
                    || !Number.isFinite(fuelNumber) || fuelNumber <= pump) {
                    return '0.00%';
                }
                const steps = Math.floor((fuelNumber - pump) / step);
                if (steps <= 0) return '0.00%';
                return ((steps * step / pump) * 100).toLocaleString('en-US', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2
                }) + '%';
            }

            // Existing locations feed the Origin/Destination dropdowns; a location's
            // Location Matrix code becomes the lane DCode (what billing matches on).
            async function loadLocations() {
                if (locations.length) return;
                try {
                    const res = await fetch('php/fetch/get_location_matrix.php', { cache: 'no-store' });
                    const data = await res.json();
                    if (data && data.success) {
                        locations = (data.locations || []).map(l => ({ name: l.location_name || '', matrix: l.location_matrix || '' }));
                        locByUpper = {};
                        locations.forEach(l => { if (l.name) locByUpper[l.name.toUpperCase()] = l.matrix || ''; });
                        // Datalist suggestions for the Origin / Packing House / Destination text inputs.
                        const locList = document.getElementById('rmLocationList');
                        if (locList) locList.innerHTML = locations.map(l => `<option value="${esc(l.name)}">`).join('');
                        const segList = document.getElementById('rmSegmentList');
                        if (segList) segList.innerHTML = Object.values(window.__rmCustomers || {}).map(v => `<option value="${esc(v)}">`).join('');
                    }
                } catch (e) { /* suggestions simply have fewer options */ }
            }

            function esc(v) {
                return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }
            function setStatus(msg, kind) {
                statusEl.className = 'small align-self-center ' + (kind || 'text-muted');
                statusEl.textContent = msg || '';
            }

            // ---- Month filter: show only the lanes effective in the selected month ----
            // A lane is shown for month YYYY-MM when its [effective_from, effective_to] window
            // overlaps that month (blank bound = open). Blank-on-both lanes apply to every month.
            function laneMonthMatch(l, ym) {
                if (!ym || ym === 'ALL') return true;
                const from = (l.effective_from || '').substring(0, 10);
                const to = (l.effective_to || '').substring(0, 10);
                const mStart = ym + '-01';
                const mEnd = ym + '-31';
                return (!from || from <= mEnd) && (!to || to >= mStart);
            }
            // Distinct effective-from months present in the current lanes (YYYY-MM, ascending).
            function laneMonths() {
                const set = new Set();
                lanes.forEach(l => { const m = (l.effective_from || '').substring(0, 7); if (m) set.add(m); });
                return Array.from(set).sort();
            }
            function currentYM() {
                const d = new Date();
                return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
            }
            // Populate the month dropdown; default to the current month when it has lanes,
            // else the most recent month on/before today, else the latest month. Keeps the
            // user's current selection if it is still valid.
            function buildMonthFilter() {
                const sel = document.getElementById('rmMonthFilter');
                if (!sel) return;
                const months = laneMonths();
                const cym = currentYM();
                let def;
                if (lanes.some(l => laneMonthMatch(l, cym))) def = cym;
                else if (months.length) { const past = months.filter(m => m <= cym); def = past.length ? past[past.length - 1] : months[months.length - 1]; }
                else def = 'ALL';
                // Make sure the default month is selectable even if no lane starts in it.
                const optionMonths = months.slice();
                if (def !== 'ALL' && !optionMonths.includes(def)) { optionMonths.push(def); optionMonths.sort(); }
                if (!rmMonthSelected || (rmMonthSelected !== 'ALL' && !optionMonths.includes(rmMonthSelected))) {
                    rmMonthSelected = def;
                }
                const fmt = (m) => { const [y, mo] = m.split('-'); return new Date(Number(y), Number(mo) - 1, 1).toLocaleString('en-US', { month: 'long', year: 'numeric' }); };
                sel.innerHTML = '<option value="ALL">All months</option>' + optionMonths.map(m => `<option value="${m}">${fmt(m)}</option>`).join('');
                sel.value = rmMonthSelected;
            }
            function monthFilterValue() { return rmMonthSelected || 'ALL'; }

            // Flat, per-trip services price by route + base rate only (no fuel formula), so the
            // grid hides the fuel columns for them.
            const RM_FLAT_CUSTOMERS = ['dict_van_shuttling', 'dict_industrial_waste'];
            function renderLanes() {
                buildMonthFilter();
                const rmTable = document.getElementById('rmLanesTable');
                if (rmTable) rmTable.classList.toggle('rm-flat', RM_FLAT_CUSTOMERS.includes(customerSel.value || ''));
                const ym = monthFilterValue();
                // Shade alternate lane groups (rows sharing DCode|Destination|Segment) and
                // draw a divider where each new group starts, so versions cluster visually.
                let prevKey = null, groupIdx = -1, shown = 0;
                lanesBody.innerHTML = lanes.map((l, i) => {
                    if (!laneMonthMatch(l, ym)) return '';
                    const key = laneSortKey(l);
                    const isNewGroup = key !== prevKey;
                    if (isNewGroup) { groupIdx++; prevKey = key; }
                    const cls = (groupIdx % 2 === 1 ? ' rm-grp-alt' : '') + (isNewGroup && shown > 0 ? ' rm-grp-start' : '');
                    shown++;
                    return `
                    <tr data-i="${i}" class="${cls.trim()}">
                        <td><input type="date" class="form-control form-control-sm rm-lane-from" value="${esc(l.effective_from)}" title="Blank = no lower bound"></td>
                        <td><input type="date" class="form-control form-control-sm rm-lane-to" value="${esc(l.effective_to)}" title="Blank = open-ended"></td>
                        <td><input type="text" class="form-control form-control-sm rm-lane-seg" list="rmSegmentList" placeholder="optional" value="${esc(l.segment)}" style="min-width:110px;"></td>
                        <td><input type="text" class="form-control form-control-sm rm-lane-org" list="rmLocationList" value="${esc(l.origin)}" style="min-width:150px;"></td>
                        <td><input type="text" class="form-control form-control-sm rm-lane-ph" list="rmLocationList" value="${esc(l.packing_house)}" style="min-width:150px;"></td>
                        <td><input type="text" class="form-control form-control-sm rm-lane-dst" list="rmLocationList" value="${esc(l.destination)}" style="min-width:150px;"></td>
                        <td><input type="text" class="form-control form-control-sm rm-lane-dc" value="${esc(l.dcode)}" title="Auto-filled from the destination's Location Matrix; editable" style="min-width:100px;"></td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm rm-lane-base" value="${esc(l.base_rate)}" placeholder="₱ rate" style="min-width:100px;"></td>
                        <td class="rm-fuelcol"><input type="number" step="0.01" class="form-control form-control-sm rm-lane-pump" value="${esc(l.pump_price)}" placeholder="50" title="Base fuel price: the diesel price the Base Rate stays flat up to; escalation applies above it. Blank/0 = 50." style="min-width:90px;"></td>
                        <td class="rm-fuelcol"><input type="number" step="0.01" class="form-control form-control-sm rm-lane-step" value="${esc(l.price_movement)}" placeholder="e.g. 2.50" style="min-width:90px;"></td>
                        <td class="rm-fuelcol"><input type="number" step="0.01" class="form-control form-control-sm rm-lane-fuel" value="${esc(l.monthly_fuel_average)}" placeholder="e.g. 84.29" style="min-width:100px;"></td>
                        <td class="text-end text-muted rm-lane-movement rm-fuelcol" style="white-space:nowrap;">${rmMovementDisplay(l)}</td>
                        <td class="text-end fw-semibold rm-lane-rate" style="white-space:nowrap;">${rmRateDisplay(l)}</td>
                        <td class="text-center rm-fuelcol">${(() => { const n = Array.isArray(l.bands) ? l.bands.length : 0; return `<button type="button" class="btn btn-sm ${n ? 'btn-primary' : 'btn-outline-secondary'} rm-bands-btn" title="Edit fuel-price bands">${n ? n + ' band' + (n > 1 ? 's' : '') : 'Formula'}</button>`; })()}</td>
                        <td class="text-center rm-fuelcol" style="white-space:nowrap;">${(() => {
                            const eff = (String(l.round_mode || '').toLowerCase()) || rmRoundMode();
                            const trunc = eff === 'down';
                            return `<input type="hidden" class="rm-lane-round" value="${esc(String(l.round_mode || '').toLowerCase())}">
                            <div class="btn-group btn-group-sm" role="group" title="How this lane's rate is rounded">
                                <button type="button" class="btn ${trunc ? 'btn-outline-secondary' : 'btn-success'} rm-round-up" title="Rounding — round to nearest peso"><i class="bi bi-arrow-up"></i></button>
                                <button type="button" class="btn ${trunc ? 'btn-warning' : 'btn-outline-secondary'} rm-round-down" title="Truncate — round down (drop the centavos)"><i class="bi bi-arrow-down"></i></button>
                            </div>`;
                        })()}</td>
                        <td class="text-center"><input type="checkbox" class="form-check-input rm-lane-active" ${l.active ? 'checked' : ''}></td>
                        <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm rm-del-lane"><i class="bi bi-x"></i></button></td>
                    </tr>`;
                }).join('');
            }

            // Pull edits out of the DOM back into the model (so nothing is lost on re-render).
            function syncFromDom() {
                lanesBody.querySelectorAll('tr').forEach(tr => {
                    const i = Number(tr.dataset.i);
                    lanes[i].effective_from = tr.querySelector('.rm-lane-from').value;
                    lanes[i].effective_to = tr.querySelector('.rm-lane-to').value;
                    lanes[i].segment = tr.querySelector('.rm-lane-seg').value;
                    lanes[i].origin = tr.querySelector('.rm-lane-org').value;
                    lanes[i].packing_house = tr.querySelector('.rm-lane-ph').value;
                    lanes[i].destination = tr.querySelector('.rm-lane-dst').value;
                    lanes[i].dcode = tr.querySelector('.rm-lane-dc').value;
                    lanes[i].base_rate = tr.querySelector('.rm-lane-base').value;
                    lanes[i].pump_price = tr.querySelector('.rm-lane-pump').value;
                    lanes[i].price_movement = tr.querySelector('.rm-lane-step').value;
                    lanes[i].monthly_fuel_average = tr.querySelector('.rm-lane-fuel').value;
                    const roundSel = tr.querySelector('.rm-lane-round');
                    if (roundSel) lanes[i].round_mode = roundSel.value;
                    lanes[i].active = tr.querySelector('.rm-lane-active').checked;
                });
            }

            // Group identity billing uses to treat rows as versions of the same lane.
            function laneSortKey(l) {
                return [(l.origin || ''), (l.packing_house || ''), (l.destination || ''), (l.dcode || ''), (l.segment || '')]
                    .map(v => String(v).toUpperCase()).join('|');
            }
            // Sort by effective date first (blank baseline first, then ascending), then by lane
            // group — so all lines for the same period sit together (e.g. all July lanes, then
            // all August lanes).
            function sortLanes() {
                lanes.sort((a, b) => {
                    const da = a.effective_from || '', db = b.effective_from || '';
                    if (da !== db) {
                        if (da === '') return -1;
                        if (db === '') return 1;
                        return da < db ? -1 : 1;
                    }
                    const ka = laneSortKey(a), kb = laneSortKey(b);
                    return ka < kb ? -1 : (ka > kb ? 1 : 0);
                });
            }

            function normalizeLane(l) {
                return {
                    effective_from: (l.effective_from || '').substring(0, 10),
                    effective_to: (l.effective_to || '').substring(0, 10),
                    segment: l.segment || '',
                    origin: l.origin || '',
                    packing_house: l.packing_house || '',
                    destination: l.destination || '',
                    dcode: l.dcode || '',
                    base_rate: l.base_rate ?? '',
                    pump_price: l.pump_price ?? '',
                    price_movement: l.price_movement ?? '',
                    monthly_fuel_average: l.monthly_fuel_average ?? '',
                    round_mode: (l.round_mode || '').toLowerCase(),
                    rate_fuel: l.rate_fuel ?? null,
                    rate_fuel_date: l.rate_fuel_date || '',
                    active: (l.active === undefined || l.active === null) ? true
                        : (l.active === true || l.active === 't' || l.active === '1' || l.active === 1),
                    bands: Array.isArray(l.bands) ? l.bands.map((b, bi) => ({
                        fuel_from: (b.fuel_from ?? '') === null ? '' : String(b.fuel_from ?? ''),
                        fuel_to: (b.fuel_to ?? '') === null ? '' : String(b.fuel_to ?? ''),
                        rate: b.rate ?? '',
                        sort_order: bi,
                    })) : [],
                };
            }

            async function loadMatrix() {
                const customer = customerSel.value;
                const params = new URLSearchParams();
                if (customer) params.set('customer', customer);
                const res = await fetch('php/fetch/get_rate_matrix.php?' + params.toString(), { cache: 'no-store' });
                const data = await res.json();
                if (!data.success) { setStatus(data.message || 'Load failed', 'text-danger'); return; }

                if (!customersLoaded) {
                    window.__rmCustomers = data.customers || {};
                    customerSel.innerHTML = Object.entries(data.customers || {})
                        .map(([k, label]) => `<option value="${esc(k)}">${esc(label)}</option>`).join('');
                    customersLoaded = true;
                    await loadLocations();
                    if (!customer && customerSel.value) { return loadMatrix(); }
                }

                rmCurrentFuel = (data.current_fuel !== undefined && data.current_fuel !== null && data.current_fuel !== '')
                    ? Number(data.current_fuel) : null;
                rmFuelDate = data.fuel_date || '';
                updateFuelNote();
                lanes = (data.lanes || []).map(normalizeLane);
                rmMonthSelected = null; // recompute the default month for this customer
                sortLanes();
                renderLanes();
                setStatus(`${lanes.length} rate line(s) loaded.`, 'text-muted');
            }

            // Note above the table: escalation applies only when a Monthly Avg Fuel is pinned.
            function updateFuelNote() {
                const el = document.getElementById('rmFuelNote');
                if (!el) return;
                el.innerHTML = '<i class="bi bi-info-circle me-1"></i>A lane escalates <strong>only when you set its <span class="text-nowrap">Monthly Avg Fuel</span></strong>. Left blank/0, the lane charges its <strong>Base Rate</strong> (Fuel Move % 0.00%) in both this preview and billing — set the month\'s diesel price to make it escalate.';
            }

            // Recompute a single row's "Rate (now)" cell when its base/pump/step changes.
            function refreshRateCell(tr) {
                const cell = tr.querySelector('.rm-lane-rate');
                const movementCell = tr.querySelector('.rm-lane-movement');
                if (!cell || !movementCell) return;
                const lane = {
                    ...lanes[Number(tr.dataset.i)],
                    base_rate: tr.querySelector('.rm-lane-base').value,
                    pump_price: tr.querySelector('.rm-lane-pump').value,
                    price_movement: tr.querySelector('.rm-lane-step').value,
                    monthly_fuel_average: tr.querySelector('.rm-lane-fuel').value,
                    round_mode: tr.querySelector('.rm-lane-round') ? tr.querySelector('.rm-lane-round').value : (lanes[Number(tr.dataset.i)].round_mode || ''),
                    rate_fuel: tr.querySelector('.rm-lane-fuel').value || lanes[Number(tr.dataset.i)].rate_fuel,
                };
                cell.innerHTML = rmRateDisplay(lane);
                movementCell.innerHTML = rmMovementDisplay(lane);
            }

            customerSel.addEventListener('change', function () { loadMatrix(); });
            document.getElementById('rmReload').addEventListener('click', function () { loadMatrix(); });

            downloadTemplateBtn.addEventListener('click', function () {
                const customer = customerSel.value;
                if (!customer) { setStatus('Pick a customer first.', 'text-danger'); return; }
                window.location.href = 'php/fetch/download_rate_matrix_template.php?' + new URLSearchParams({ customer }).toString();
            });
            importExcelBtn.addEventListener('click', function () {
                if (!customerSel.value) { setStatus('Pick a customer first.', 'text-danger'); return; }
                importFileInput.value = '';
                importFileInput.click();
            });
            importFileInput.addEventListener('change', async function () {
                if (!importFileInput.files.length) return;
                const customer = customerSel.value;
                if (!customer) { setStatus('Pick a customer first.', 'text-danger'); return; }

                const confirmed = await Swal.fire({
                    title: 'Import matrix?',
                    text: 'This will replace the current rate lines for the selected customer.',
                    icon: 'warning', showCancelButton: true, confirmButtonText: 'Import', confirmButtonColor: '#198754'
                });
                if (!confirmed.isConfirmed) { importFileInput.value = ''; return; }

                const fd = new FormData();
                fd.append('customer', customer);
                fd.append('xlsx_file', importFileInput.files[0]);
                setStatus('Importing...', 'text-muted');
                importExcelBtn.disabled = true; downloadTemplateBtn.disabled = true;
                try {
                    const res = await fetch('php/insert/import_rate_matrix.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Import failed');
                    setStatus(data.message, 'text-success');
                    await loadMatrix();
                } catch (err) {
                    setStatus(err.message, 'text-danger');
                } finally {
                    importExcelBtn.disabled = false; downloadTemplateBtn.disabled = false; importFileInput.value = '';
                }
            });

            document.getElementById('rmAddLane').addEventListener('click', function () {
                syncFromDom();
                // Seed a new line into the month currently being viewed so it stays visible.
                const ym = monthFilterValue();
                const seed = (ym && ym !== 'ALL') ? { active: true, effective_from: ym + '-01' } : { active: true };
                lanes.push(normalizeLane(seed));
                renderLanes();
            });

            // Month filter dropdown: capture edits in the current view, then re-render the
            // selected month only (default view is the current month with lane data).
            document.getElementById('rmMonthFilter').addEventListener('change', function () {
                syncFromDom();
                rmMonthSelected = this.value;
                renderLanes();
            });

            // First day of the month AFTER the given ISO date (default new-window start).
            function nextMonthFirst(iso) {
                const d = iso ? new Date(iso) : new Date();
                const n = new Date(d.getFullYear(), d.getMonth() + 1, 1);
                return `${n.getFullYear()}-${String(n.getMonth() + 1).padStart(2, '0')}-01`;
            }
            // The day BEFORE an ISO date (to close the previous window's To without overlap).
            function dayBefore(iso) {
                const d = new Date(iso);
                d.setDate(d.getDate() - 1);
                return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            }

            // Duplicate to a NEW effective window — clone ONE line per unique route (its newest
            // version, same route + formula), set the copies' Effective From to the chosen date,
            // and close the originals' Effective To the day before (no overlap). So a lane that
            // already has several dated versions is copied once, not once per version. Edit the
            // copies' rates, then Save Matrix.
            document.getElementById('rmDuplicateDate').addEventListener('click', async function () {
                syncFromDom();
                if (!lanes.length) {
                    setStatus('Add or load lines first, then duplicate them for a new window.', 'text-danger');
                    return;
                }
                const suggested = nextMonthFirst(lanes.map(l => l.effective_from).filter(Boolean).sort().pop() || '');
                const { value: date } = await Swal.fire({
                    title: 'Duplicate lines to a new effective window',
                    input: 'date',
                    inputValue: suggested,
                    inputLabel: 'Effective From — copies apply from this date; the current lines are closed the day before',
                    showCancelButton: true,
                    confirmButtonText: 'Duplicate',
                    confirmButtonColor: '#198754',
                    inputValidator: v => (!v ? 'Pick an Effective From date.' : undefined)
                });
                if (!date) return;
                const prevTo = dayBefore(date);
                // Close open-ended originals so the two windows don't overlap.
                lanes.forEach(l => { if (!l.effective_to) l.effective_to = prevTo; });
                // Copy ONE line per unique route (Segment|Origin|Packing House|Destination|DCode),
                // taking each route's NEWEST-dated version — so a lane that already has several
                // dated versions isn't duplicated once per version.
                const latestByRoute = new Map();
                lanes.forEach(l => {
                    const key = laneSortKey(l);
                    const cur = latestByRoute.get(key);
                    if (!cur || String(l.effective_from || '') >= String(cur.effective_from || '')) {
                        latestByRoute.set(key, l);
                    }
                });
                const copies = Array.from(latestByRoute.values())
                    .map(l => normalizeLane({ ...l, effective_from: date, effective_to: '' }));
                lanes = lanes.concat(copies);
                rmMonthSelected = date.substring(0, 7); // jump the view to the new month so the copies show
                sortLanes();
                renderLanes();
                setStatus(`Copied ${copies.length} unique lane(s) starting ${date}; previous lines closed ${prevTo}. Adjust the copied rates, then Save Matrix.`, 'text-success');
            });

            // Picking a destination auto-fills that lane's DCode from the location's
            // Location Matrix code (what billing matches on), so users never touch DCode.
            lanesBody.addEventListener('change', function (e) {
                const sel = e.target.closest('.rm-lane-dst'); if (!sel) return;
                syncFromDom();
                const i = Number(sel.closest('tr').dataset.i);
                const code = locByUpper[String(lanes[i].destination || '').toUpperCase()];
                if (code) { lanes[i].dcode = code; renderLanes(); }
            });
            lanesBody.addEventListener('click', function (e) {
                const del = e.target.closest('.rm-del-lane');
                if (del) { syncFromDom(); lanes.splice(Number(del.closest('tr').dataset.i), 1); renderLanes(); return; }
                const bandsBtn = e.target.closest('.rm-bands-btn');
                if (bandsBtn) { syncFromDom(); rmOpenBands(Number(bandsBtn.closest('tr').dataset.i)); }
            });

            // ---- Fuel-price bands modal (per lane) ----
            const rmBandModalEl = document.getElementById('rmBandModal');
            const rmBandModal = new bootstrap.Modal(rmBandModalEl);
            const rmBandBody = document.getElementById('rmBandBody');
            let rmBandLaneIdx = -1;
            let rmBandRows = []; // working copy [{fuel_from, fuel_to, rate}]

            function rmRenderBands() {
                rmBandBody.innerHTML = rmBandRows.length ? rmBandRows.map((b, i) => `
                    <tr data-b="${i}">
                        <td><input type="number" step="0.01" class="form-control form-control-sm rm-band-from" value="${esc(b.fuel_from ?? '')}" placeholder="no lower bound"></td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm rm-band-to" value="${esc(b.fuel_to ?? '')}" placeholder="open-ended"></td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm rm-band-rate" value="${esc(b.rate ?? '')}" placeholder="₱ flat rate"></td>
                        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger rm-band-del"><i class="bi bi-x"></i></button></td>
                    </tr>`).join('') : '<tr><td colspan="4" class="text-muted small">No bands — this lane uses the 0.4× formula. Add a band to switch to stepped pricing.</td></tr>';
            }
            function rmSyncBandsFromDom() {
                rmBandBody.querySelectorAll('tr[data-b]').forEach(tr => {
                    const i = Number(tr.dataset.b);
                    rmBandRows[i] = {
                        fuel_from: tr.querySelector('.rm-band-from').value,
                        fuel_to: tr.querySelector('.rm-band-to').value,
                        rate: tr.querySelector('.rm-band-rate').value,
                    };
                });
            }
            function rmOpenBands(i) {
                rmBandLaneIdx = i;
                const l = lanes[i];
                rmBandRows = (Array.isArray(l.bands) ? l.bands : []).map(b => ({
                    fuel_from: (b.fuel_from ?? '') === null ? '' : String(b.fuel_from ?? ''),
                    fuel_to: (b.fuel_to ?? '') === null ? '' : String(b.fuel_to ?? ''),
                    rate: b.rate ?? '',
                }));
                document.getElementById('rmBandLane').textContent =
                    '— ' + [l.origin, l.destination].filter(Boolean).join(' → ') + ' (base ₱' + (l.base_rate || '0') + ')';
                rmRenderBands();
                rmBandModal.show();
            }
            document.getElementById('rmBandAdd').addEventListener('click', function () {
                rmSyncBandsFromDom();
                rmBandRows.push({ fuel_from: '', fuel_to: '', rate: '' });
                rmRenderBands();
            });
            rmBandBody.addEventListener('click', function (e) {
                const del = e.target.closest('.rm-band-del'); if (!del) return;
                rmSyncBandsFromDom();
                rmBandRows.splice(Number(del.closest('tr').dataset.b), 1);
                rmRenderBands();
            });
            document.getElementById('rmBandApply').addEventListener('click', function () {
                rmSyncBandsFromDom();
                // Keep only rows with a rate or a bound; sort by lower bound.
                const clean = rmBandRows
                    .filter(b => String(b.rate).trim() !== '' || String(b.fuel_from).trim() !== '' || String(b.fuel_to).trim() !== '')
                    .sort((a, b) => (Number.parseFloat(a.fuel_from) || -Infinity) - (Number.parseFloat(b.fuel_from) || -Infinity))
                    .map((b, i) => ({ fuel_from: b.fuel_from, fuel_to: b.fuel_to, rate: b.rate, sort_order: i }));
                if (rmBandLaneIdx >= 0 && lanes[rmBandLaneIdx]) lanes[rmBandLaneIdx].bands = clean;
                rmBandModal.hide();
                renderLanes();
            });
            // Live-update the "Rate (now)" cell as Base Rate / Pump Price / Price Movement change.
            lanesBody.addEventListener('input', function (e) {
                if (e.target.closest('.rm-lane-base, .rm-lane-pump, .rm-lane-step, .rm-lane-fuel')) {
                    const tr = e.target.closest('tr');
                    if (tr) refreshRateCell(tr);
                }
            });
            // Per-lane rounding arrows: ▲ = Rounding (nearest / customer default), ▼ = Truncate
            // (round down). Clicking one sets the lane's round_mode, restyles both arrows, and
            // live-updates the Rate (now) cell.
            lanesBody.addEventListener('click', function (e) {
                const up = e.target.closest('.rm-round-up');
                const down = e.target.closest('.rm-round-down');
                if (!up && !down) return;
                const tr = e.target.closest('tr');
                if (!tr) return;
                const i = Number(tr.dataset.i);
                const def = rmRoundMode();
                // Truncate = explicit 'down'. Rounding = clear the override when the customer
                // default already rounds (blank → default), else force 'nearest'.
                const val = down ? 'down' : (def === 'down' ? 'nearest' : '');
                lanes[i].round_mode = val;
                const hidden = tr.querySelector('.rm-lane-round');
                if (hidden) hidden.value = val;
                const trunc = (val || def) === 'down';
                const upBtn = tr.querySelector('.rm-round-up');
                const dnBtn = tr.querySelector('.rm-round-down');
                if (upBtn) upBtn.className = 'btn ' + (trunc ? 'btn-outline-secondary' : 'btn-success') + ' rm-round-up';
                if (dnBtn) dnBtn.className = 'btn ' + (trunc ? 'btn-warning' : 'btn-outline-secondary') + ' rm-round-down';
                refreshRateCell(tr);
            });

            document.getElementById('rmSave').addEventListener('click', async function () {
                syncFromDom();
                const customer = customerSel.value;
                if (!customer) { setStatus('Pick a customer first.', 'text-danger'); return; }
                setStatus('Saving...', 'text-muted');
                const fd = new FormData();
                fd.append('customer', customer);
                fd.append('matrix', JSON.stringify({ lanes }));
                try {
                    const res = await fetch('php/insert/save_rate_matrix.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Save failed');
                    setStatus(data.message, 'text-success');
                    await loadMatrix();
                } catch (err) {
                    setStatus(err.message, 'text-danger');
                }
            });

            // Lazy-load the first time the tab is shown.
            document.getElementById('rateMatrixTabBtn').addEventListener('shown.bs.tab', function () {
                if (!customersLoaded) loadMatrix().catch(e => setStatus(e.message, 'text-danger'));
            });
        });
    </script>
    <script>
        // ---- Activity Rates editor (independent module) ----
        document.addEventListener('DOMContentLoaded', function () {
            const sel = document.getElementById('arCustomer');
            const body = document.getElementById('arBody');
            const statusEl = document.getElementById('arStatus');
            let loaded = false;
            let activities = [];

            function esc(v) {
                return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }
            function setStatus(m, k) { statusEl.className = 'small align-self-center ' + (k || 'text-muted'); statusEl.textContent = m || ''; }

            function render(rates) {
                body.innerHTML = activities.map(a => {
                    const r = rates[a.code] || {};
                    const isFuel = a.basis === 'fuel';
                    return `<tr data-code="${esc(a.code)}">
                        <td><div class="fw-semibold">${esc(a.short)}</div><div class="text-muted small">${esc(a.label)}</div></td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm ar-rate" value="${esc(r.rate ?? '')}" placeholder="${isFuel ? 'surcharge (opt)' : 'per hour'}"></td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm ar-free" value="${esc(r.free_hours ?? '')}" placeholder="48" ${isFuel ? 'disabled' : ''}></td>
                        <td><input type="text" class="form-control form-control-sm ar-material" value="${esc(r.material_code ?? '')}" placeholder="uses customer default"></td>
                    </tr>`;
                }).join('');
            }

            async function load() {
                const params = new URLSearchParams();
                if (sel.value) params.set('customer', sel.value);
                const res = await fetch('php/fetch/get_activity_rates.php?' + params.toString(), { cache: 'no-store' });
                const data = await res.json();
                if (!data.success) { setStatus(data.message || 'Load failed', 'text-danger'); return; }
                activities = data.activities || [];
                if (!loaded) {
                    sel.innerHTML = Object.entries(data.customers || {}).map(([k, label]) => `<option value="${esc(k)}">${esc(label)}</option>`).join('');
                    loaded = true;
                    if (!sel.value) return;
                    if (sel.value && !params.get('customer')) return load();
                }
                render(data.rates || {});
                setStatus('Loaded.', 'text-muted');
            }

            sel.addEventListener('change', load);
            document.getElementById('arSave').addEventListener('click', async function () {
                const rates = {};
                body.querySelectorAll('tr').forEach(tr => {
                    rates[tr.dataset.code] = {
                        rate: tr.querySelector('.ar-rate').value,
                        free_hours: tr.querySelector('.ar-free').value,
                        material_code: tr.querySelector('.ar-material').value
                    };
                });
                setStatus('Saving...', 'text-muted');
                const fd = new FormData();
                fd.append('customer', sel.value);
                fd.append('rates', JSON.stringify(rates));
                try {
                    const res = await fetch('php/insert/save_activity_rates.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Save failed');
                    setStatus(data.message, 'text-success');
                    await load();
                } catch (e) { setStatus(e.message, 'text-danger'); }
            });

            document.getElementById('activityRatesTabBtn').addEventListener('shown.bs.tab', function () {
                if (!loaded) load().catch(e => setStatus(e.message, 'text-danger'));
            });
        });
    </script>
    <script>
        // ---- Locations editor: update location_matrix only (autofill from rate matrix) ----
        document.addEventListener('DOMContentLoaded', function () {
            const body = document.getElementById('locBody');
            const dataList = document.getElementById('locationMatrixOptions');
            const searchEl = document.getElementById('locSearch');
            const statusEl = document.getElementById('locStatus');
            let loaded = false;
            let rows = [];

            function esc(v) {
                return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }
            function setStatus(m, k) { statusEl.className = 'small align-self-center ' + (k || 'text-muted'); statusEl.textContent = m || ''; }

            function render() {
                const q = (searchEl.value || '').trim().toLowerCase();
                const shown = rows.filter(r => !q
                    || String(r.location_name || '').toLowerCase().includes(q)
                    || String(r.location_matrix || '').toLowerCase().includes(q)
                    || String(r.region || '').toLowerCase().includes(q));
                const regionOpts = (sel) => ['', 'DAVAO', 'PANABO']
                    .map(v => `<option value="${v}"${String(sel || '').toUpperCase() === v ? ' selected' : ''}>${v || '—'}</option>`).join('');
                body.innerHTML = shown.map(r => `
                    <tr data-id="${esc(r.location_id)}">
                        <td class="text-muted">#${esc(r.location_id)}</td>
                        <td>${esc(r.location_name)}</td>
                        <td><input type="text" class="form-control form-control-sm loc-matrix" list="locationMatrixOptions"
                            value="${esc(r.location_matrix ?? '')}" placeholder="rate-matrix origin / destination"></td>
                        <td><select class="form-select form-select-sm loc-region">${regionOpts(r.region)}</select></td>
                    </tr>`).join('');
                if (!shown.length) body.innerHTML = '<tr><td colspan="4" class="text-muted">No locations match.</td></tr>';
            }

            async function load() {
                setStatus('Loading...');
                const res = await fetch('php/fetch/get_location_matrix.php', { cache: 'no-store' });
                const data = await res.json();
                if (!data.success) { setStatus(data.message || 'Load failed', 'text-danger'); return; }
                rows = data.locations || [];
                dataList.innerHTML = (data.suggestions || []).map(s => `<option value="${esc(s)}"></option>`).join('');
                render();
                setStatus(rows.length + ' location(s).');
                loaded = true;
            }

            // Track edits on the row objects so search-filtering never loses unsaved changes.
            body.addEventListener('input', function (e) {
                const tr = e.target.closest('tr'); if (!tr) return;
                const rec = rows.find(r => String(r.location_id) === String(tr.dataset.id));
                if (!rec) return;
                if (e.target.closest('.loc-matrix')) rec.location_matrix = e.target.value;
                if (e.target.closest('.loc-region')) rec.region = e.target.value;
            });
            body.addEventListener('change', function (e) {
                const sel = e.target.closest('.loc-region'); if (!sel) return;
                const tr = e.target.closest('tr'); if (!tr) return;
                const rec = rows.find(r => String(r.location_id) === String(tr.dataset.id));
                if (rec) rec.region = sel.value;
            });

            searchEl.addEventListener('input', render);
            document.getElementById('locReload').addEventListener('click', load);

            document.getElementById('locSave').addEventListener('click', async function () {
                const payload = {};
                const regions = {};
                rows.forEach(r => {
                    payload[r.location_id] = (r.location_matrix ?? '').trim();
                    regions[r.location_id] = (r.region ?? '').trim();
                });
                setStatus('Saving...');
                const fd = new FormData();
                fd.append('locations', JSON.stringify(payload));
                fd.append('regions', JSON.stringify(regions));
                try {
                    const res = await fetch('php/insert/save_location_matrix.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Save failed');
                    setStatus(data.message, 'text-success');
                    await load();
                } catch (e) { setStatus(e.message, 'text-danger'); }
            });

            document.getElementById('locationsTabBtn').addEventListener('shown.bs.tab', function () {
                if (!loaded) load().catch(e => setStatus(e.message, 'text-danger'));
            });
        });
    </script>
    <script>
        // ---- Customer SAP Codes editor (Sold-To + Affiliate → Distribution Channel) ----
        document.addEventListener('DOMContentLoaded', function () {
            const body = document.getElementById('csapBody');
            const searchEl = document.getElementById('csapSearch');
            const statusEl = document.getElementById('csapStatus');
            let loaded = false;
            let customers = [];   // [{key,label,group}]
            let values = {};      // {key:{stored,default,affiliate}}
            let materials = [];   // [{code,label}] from Service Materials
            let profitCenters = []; // [{code,label}] from Profit Center

            function esc(v) {
                return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }
            function setStatus(m, k) { statusEl.className = 'small align-self-center ' + (k || 'text-muted'); statusEl.textContent = m || ''; }

            // Build a <select> for a master-data code list. `selected` pre-selects the effective
            // value; `def` is the code default (labels the "use default" option). If the selected
            // value isn't in the list (e.g. a legacy code), it is added so nothing is silently lost.
            function codeSelect(field, options, selected, def) {
                const defLabel = def ? `Use default (${esc(def)})` : 'Use default (blank)';
                let opts = `<option value="">${defLabel}</option>`;
                const known = new Set(options.map(o => o.code));
                if (selected && !known.has(selected)) {
                    opts += `<option value="${esc(selected)}" selected>${esc(selected)} (not in list)</option>`;
                }
                opts += options.map(o =>
                    `<option value="${esc(o.code)}" ${o.code === selected ? 'selected' : ''}>${esc(o.label)}</option>`
                ).join('');
                return `<td><select class="form-select form-select-sm csap-sel" data-field="${field}">${opts}</select></td>`;
            }

            function render() {
                const q = (searchEl.value || '').trim().toLowerCase();
                const shown = customers.filter(c => !q
                    || String(c.label || '').toLowerCase().includes(q)
                    || String(c.group || '').toLowerCase().includes(q));
                body.innerHTML = shown.map(c => {
                    const v = values[c.key] || { stored: {}, default: {}, affiliate: false };
                    // Sold-To: free text, pre-filled with effective value.
                    const soldStored = v.stored.sold_to ?? '';
                    const soldDef = v.default.sold_to ?? '';
                    const soldVal = soldStored !== '' ? soldStored : soldDef;
                    const soldPh = soldDef !== '' ? 'default: ' + soldDef : 'blank until set';
                    const soldCell = `<td><input type="text" class="form-control form-control-sm csap-in" data-field="sold_to" value="${esc(soldVal)}" placeholder="${esc(soldPh)}"></td>`;
                    // Material Code + Profit Center: dropdowns from the master tabs.
                    const matSel = (v.stored.material_code || '') || (v.default.material_code || '');
                    const pcSel = (v.stored.profit_center || '') || (v.default.profit_center || '');
                    const matCell = codeSelect('material_code', materials, matSel, v.default.material_code || '');
                    const pcCell = codeSelect('profit_center', profitCenters, pcSel, v.default.profit_center || '');
                    const affChecked = v.affiliate ? 'checked' : '';
                    const affCell = `<td class="text-center"><div class="form-check d-inline-block"><input type="checkbox" class="form-check-input csap-aff" ${affChecked}></div></td>`;
                    // VAT: editable only for DB-backed (Box Banana) customers; others show a dash.
                    const vatCell = v.vat_editable
                        ? `<td class="text-center"><div class="form-check d-inline-block"><input type="checkbox" class="form-check-input csap-vat" ${v.is_vat ? 'checked' : ''}></div></td>`
                        : `<td class="text-center text-muted small">—</td>`;
                    // Actions: DB-backed rows can be edited; only non-built-in ones can be deleted.
                    let actions = '';
                    if (v.custom) {
                        actions += `<button type="button" class="btn btn-sm btn-outline-secondary csap-edit" data-key="${esc(c.key)}" title="Edit customer"><i class="bi bi-pencil"></i></button>`;
                        if (!v.builtin) {
                            actions += ` <button type="button" class="btn btn-sm btn-outline-danger csap-del" data-key="${esc(c.key)}" data-label="${esc(c.label)}" title="Delete customer"><i class="bi bi-trash"></i></button>`;
                        }
                    }
                    const actCell = `<td class="text-end text-nowrap">${actions || '<span class="text-muted small">—</span>'}</td>`;
                    const badge = v.builtin ? ' <span class="badge bg-light text-muted border" style="font-weight:500;">built-in</span>' : (v.custom ? ' <span class="badge bg-info-subtle text-info-emphasis border" style="font-weight:500;">added</span>' : '');
                    const grp = c.group ? `<div class="text-muted small">${esc(c.group)}</div>` : '';
                    return `<tr data-key="${esc(c.key)}"><td><div class="fw-semibold">${esc(c.label)}${badge}</div>${grp}</td>${affCell}${vatCell}${soldCell}${matCell}${pcCell}${actCell}</tr>`;
                }).join('');
            }

            async function load() {
                const res = await fetch('php/fetch/get_customer_sap.php', { cache: 'no-store' });
                const data = await res.json();
                if (!data.success) { setStatus(data.message || 'Load failed', 'text-danger'); return; }
                customers = data.customers || [];
                values = data.values || {};
                materials = data.materials || [];
                profitCenters = data.profit_centers || [];
                loaded = true;
                render();
                setStatus('Loaded.', 'text-muted');
            }

            searchEl.addEventListener('input', render);
            document.getElementById('csapReload').addEventListener('click', function () {
                load().catch(e => setStatus(e.message, 'text-danger'));
            });

            // Expose a reload hook so the Add/Edit/Delete modal (separate script) can
            // refresh this table after it changes the customer list.
            window.csapReload = () => load().catch(e => setStatus(e.message, 'text-danger'));

            // Edit / delete a DB-backed customer straight from the main table.
            body.addEventListener('click', function (e) {
                const editBtn = e.target.closest('.csap-edit');
                const delBtn = e.target.closest('.csap-del');
                if (editBtn && window.ccOpenEdit) {
                    window.ccOpenEdit(editBtn.dataset.key);
                } else if (delBtn && window.ccDelete) {
                    window.ccDelete(delBtn.dataset.key, delBtn.dataset.label);
                }
            });

            document.getElementById('csapSave').addEventListener('click', async function () {
                const payload = {};
                body.querySelectorAll('tr').forEach(tr => {
                    const row = {};
                    tr.querySelectorAll('.csap-in').forEach(inp => { row[inp.dataset.field] = inp.value.trim(); });
                    tr.querySelectorAll('.csap-sel').forEach(sel => { row[sel.dataset.field] = sel.value; });
                    const aff = tr.querySelector('.csap-aff');
                    row.affiliate = aff && aff.checked ? '1' : '0';
                    const vat = tr.querySelector('.csap-vat');
                    if (vat) { row.is_vat = vat.checked ? '1' : '0'; }
                    payload[tr.dataset.key] = row;
                });
                setStatus('Saving...');
                const fd = new FormData();
                fd.append('values', JSON.stringify(payload));
                try {
                    const res = await fetch('php/insert/save_customer_sap.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Save failed');
                    setStatus(data.message, 'text-success');
                    await load();
                } catch (e) { setStatus(e.message, 'text-danger'); }
            });

            document.getElementById('customerSapTabBtn').addEventListener('shown.bs.tab', function () {
                if (!loaded) load().catch(e => setStatus(e.message, 'text-danger'));
            });
        });
    </script>

    <script>
        // ---- Add / edit / delete billed customers (modal). The list itself is rendered
        //      by the Customer SAP Codes table above; this script only drives the modal
        //      and exposes ccOpenEdit / ccDelete for that table's action buttons. ----
        document.addEventListener('DOMContentLoaded', function () {
            const statusEl = document.getElementById('ccStatus');
            const formStatus = document.getElementById('ccFormStatus');
            const modalEl = document.getElementById('ccModal');
            const modal = new bootstrap.Modal(modalEl);
            let loaded = false;
            let rows = [];      // stored customer rows (for edit prefill)
            let defaults = {};
            let materials = [], profitCenters = [], segments = [];

            function esc(v) {
                return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }
            function setStatus(m, k) { statusEl.className = 'small mb-1 ' + (k || 'text-muted'); statusEl.textContent = m || ''; }
            const field = sel => modalEl.querySelector(`[data-cc="${sel}"]`);

            function optionList(items, placeholder) {
                let html = `<option value="">${esc(placeholder)}</option>`;
                html += items.map(o => `<option value="${esc(o.code)}">${esc(o.label)}</option>`).join('');
                return html;
            }

            function fillPickers() {
                field('material_code').innerHTML = optionList(materials, '(none)');
                field('profit_center').innerHTML = optionList(profitCenters, '(none)');
                document.getElementById('ccSegments').innerHTML = segments.map(s => `<option value="${esc(s)}">`).join('');
            }

            async function load() {
                const res = await fetch('php/fetch/get_custom_customers.php', { cache: 'no-store' });
                const data = await res.json();
                if (!data.success) { setStatus(data.message || 'Load failed', 'text-danger'); return; }
                rows = data.customers || [];
                defaults = data.defaults || {};
                materials = data.materials || [];
                profitCenters = data.profit_centers || [];
                segments = data.segments || [];
                loaded = true;
                fillPickers();
            }
            async function ensureLoaded() { if (!loaded) await load(); }

            function truthy(v) { return v === true || v === 't' || v === '1' || v === 1 || String(v).toLowerCase() === 'true'; }

            function openModal(row) {
                formStatus.textContent = '';
                const isEdit = !!row;
                const isBuiltin = isEdit && truthy(row.builtin);
                document.getElementById('ccMode').value = isEdit ? 'update' : 'create';
                document.getElementById('ccModalTitle').textContent = isEdit
                    ? (isBuiltin ? 'Edit Built-in Customer' : 'Edit Customer') : 'Add Customer';
                fillPickers();
                const src = row || defaults;
                modalEl.querySelectorAll('[data-cc]').forEach(el => {
                    const k = el.dataset.cc;
                    el.value = (src[k] != null ? src[k] : (defaults[k] != null ? defaults[k] : '')) || '';
                    // Built-in customers keep their code-defined pipeline wiring: only the
                    // display name and the VAT flag are editable here.
                    el.disabled = isBuiltin && k !== 'label';
                });
                // Key is immutable once created.
                field('customer_key').readOnly = isEdit;
                document.getElementById('ccAffiliate').checked = row ? String(row.distribution_channel) === '30' : false;
                document.getElementById('ccAffiliate').disabled = isBuiltin;
                document.getElementById('ccVat').checked = row ? truthy(row.is_vat) : false;
                const hint = document.getElementById('ccBuiltinHint');
                if (hint) hint.style.display = isBuiltin ? '' : 'none';
                modal.show();
            }

            document.getElementById('ccAddBtn').addEventListener('click', async () => {
                await ensureLoaded(); openModal(null);
            });

            // Called by the Customer SAP Codes table's edit button.
            window.ccOpenEdit = async function (key) {
                await ensureLoaded();
                const row = rows.find(r => r.customer_key === key);
                if (row) openModal(row);
                else Swal.fire('Not editable', 'This customer is not database-backed.', 'info');
            };

            // Called by the Customer SAP Codes table's delete button.
            window.ccDelete = function (key, label) {
                Swal.fire({
                    title: 'Delete customer?',
                    text: `Remove "${label || key}"? This does not touch existing invoices.`,
                    icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', confirmButtonText: 'Delete'
                }).then(async res => {
                    if (!res.isConfirmed) return;
                    const fd = new FormData(); fd.append('customer_key', key);
                    const r = await fetch('php/delete/delete_custom_customer.php', { method: 'POST', body: fd });
                    const d = await r.json();
                    if (!d.success) { Swal.fire('Cannot delete', d.message || 'Failed', 'error'); return; }
                    setStatus(d.message, 'text-success');
                    await load();
                    if (window.csapReload) window.csapReload();
                });
            };

            document.getElementById('ccForm').addEventListener('submit', async function (e) {
                e.preventDefault();
                formStatus.className = 'small text-danger mt-2';
                const payload = {};
                modalEl.querySelectorAll('[data-cc]').forEach(el => { payload[el.dataset.cc] = el.value.trim(); });
                payload.affiliate = document.getElementById('ccAffiliate').checked ? '1' : '0';
                payload.is_vat = document.getElementById('ccVat').checked ? '1' : '0';
                const fd = new FormData();
                fd.append('mode', document.getElementById('ccMode').value);
                fd.append('customer', JSON.stringify(payload));
                const btn = document.getElementById('ccSaveBtn');
                btn.disabled = true;
                try {
                    const res = await fetch('php/insert/save_custom_customer.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.success) { formStatus.textContent = data.message || 'Save failed'; return; }
                    modal.hide();
                    setStatus(data.message, 'text-success');
                    await load();
                    if (window.csapReload) window.csapReload();
                } catch (err) {
                    formStatus.textContent = err.message;
                } finally { btn.disabled = false; }
            });

            document.getElementById('customerSapTabBtn').addEventListener('shown.bs.tab', function () {
                if (!loaded) load().catch(e => setStatus(e.message, 'text-danger'));
            });
        });
    </script>
</body>
</html>
