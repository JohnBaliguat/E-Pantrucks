<?php
session_start();

$route = $_GET["route"] ?? "login";
$userType = ucfirst(strtolower((string) ($_SESSION["user_type"] ?? "")));

function isLoggedIn(): bool
{
    return isset($_SESSION["user_id"]);
}

function requireLogin(string $route): void
{
    if (
        $route !== "login" &&
        $route !== "login-handler" &&
        $route !== "microsoft-login" &&
        $route !== "microsoft-callback" &&
        $route !== "logout" &&
        !isLoggedIn()
    ) {
        header("Location: login");
        exit();
    }
}

function redirectByRole(string $route, string $userType): ?string
{
    $dashboardRoutes = [
        "Admin" => "public/Admin/dashboard.php",
        "Subadmin" => "public/Admin/dashboard.php",
        "User" => "public/User/dashboard.php",
        "Billing" => "public/Billing/dashboard.php",
        "Billingadmin" => "public/Billing/dashboard.php",
        "Manager" => "public/Admin/analytics.php",
    ];

    $userSharedRoutes = [
        "entry" => "public/User/abcrv.php",
        "monitoring" => "public/User/monitoring.php",
        "trip-receipt-status" => "public/User/trip_receipt_status.php",
        "profile" => "public/User/profile.php",
        "cargoTruck" => "public/User/cargoTruck.php",
        "DPC_KDI" => "public/User/DPC_KDI.php",
        "others" => "public/User/others.php",
        "abcrv" => "public/User/abcrv.php",
        "doleRv" => "public/User/doleRv.php",
        "sumiRv" => "public/User/sumiRv.php",
        "tdcRv" => "public/User/tdcRv.php",
        "dryVan" => "public/User/dryVan.php",
        "records" => "public/User/records.php",
        "transmittals" => "public/User/transmittals.php",
    ];

    $billingSharedRoutes = [
        "profile" => "public/Billing/profile.php",
        "master-data" => "public/Billing/master-data.php",
        "customer-billing" => "public/Billing/customer_billing.php",
    ];

    if ($route === "dashboard") {
        return $dashboardRoutes[$userType] ?? null;
    }

    if ($route === "user-dashboard") {
        return "public/User/dashboard.php";
    }

    if ($route === "billing-dashboard") {
        return "public/Billing/dashboard.php";
    }

    // The Billing view is shared by the Billing roles and Admin. It now also
    // carries the RAW records export that used to be the separate Records page
    // (routes "billing" / "billing-raw-records"), which is retired.
    if ($route === "customer-billing") {
        return "public/Billing/customer_billing.php";
    }

    // The "For Update" queue — records billing flagged for correction (Admin + User).
    if ($route === "for-update") {
        return "public/Admin/for_update.php";
    }

    if ($userType === "User" && array_key_exists($route, $userSharedRoutes)) {
        return $userSharedRoutes[$route];
    }

    if (
        ($userType === "Billing" || $userType === "Billingadmin") &&
        array_key_exists($route, $billingSharedRoutes)
    ) {
        // Both Billing and Billing Admin may reach master-data (see isRouteAllowed()).
        return $billingSharedRoutes[$route];
    }

    return null;
}

function isRouteAllowed(string $route, string $userType): bool
{
    $allowedRoutes = [
        "Admin" => [
            "dashboard",
            "entry",
            "monitoring",
            "trip-receipt-status",
            "payroll",
            "payroll-driver",
            "payroll-timesheet",
            "performance",
            "driver-performance",
            "driver-trips",
            "analytics",
            "utilization",
            "customer-billing",
            "for-update",
            "settings",
            "profile",
            "users",
            "activity-log",
            "bbhm",
            "cargoTruck",
            "DPC_KDI",
            "others",
            "abcrv",
            "doleRv",
            "sumiRv",
            "tdcRv",
            "dryVan",
            "drivers",
            "records",
            "transmittals",
            "transactions",
        ],
        // Sub Admin: same reach as Admin but without Monitoring, Records,
        // Transmittals, Settings and Users.
        "Subadmin" => [
            "dashboard",
            "entry",
            "trip-receipt-status",
            "payroll",
            "payroll-driver",
            "payroll-timesheet",
            "performance",
            "driver-performance",
            "driver-trips",
            "analytics",
            "utilization",
            "customer-billing",
            "for-update",
            "profile",
            "activity-log",
            "bbhm",
            "cargoTruck",
            "DPC_KDI",
            "others",
            "abcrv",
            "doleRv",
            "sumiRv",
            "tdcRv",
            "dryVan",
            "drivers",
            "transactions",
        ],
        "User" => [
            "dashboard",
            "user-dashboard",
            "entry",
            "monitoring",
            "trip-receipt-status",
            "profile",
            "cargoTruck",
            "DPC_KDI",
            "others",
            "abcrv",
            "doleRv",
            "sumiRv",
            "tdcRv",
            "dryVan",
            "records",
            "transmittals",
            "for-update",
        ],
        "Billing" => ["dashboard", "billing-dashboard", "customer-billing", "master-data", "profile"],
        "Billingadmin" => ["dashboard", "billing-dashboard", "customer-billing", "master-data", "profile"],
        "Manager" => ["dashboard", "analytics", "driver-performance", "utilization", "profile"],
    ];

    return in_array($route, $allowedRoutes[$userType] ?? [], true);
}

requireLogin($route);

// If already logged in and trying to access login, redirect to dashboard
if ($route === "login" && isLoggedIn()) {
    header("Location: dashboard");
    exit();
}

if (
    isLoggedIn() &&
    !in_array(
        $route,
        [
            "login",
            "login-handler",
            "microsoft-login",
            "microsoft-callback",
            "logout",
        ],
        true,
    ) &&
    !isRouteAllowed($route, $userType)
) {
    header("Location: dashboard");
    exit();
}

$routes = [
    "login" => "login.php",
    "login-handler" => "login-php.php",
    "microsoft-login" => "microsoft-login.php",
    "microsoft-callback" => "microsoft-callback.php",
    "logout" => "logout.php",
    "dashboard" => "public/Admin/dashboard.php",
    "entry" => "public/Admin/abcrv.php",
    "monitoring" => "public/Admin/monitoring.php",
    "trip-receipt-status" => "public/Admin/trip_receipt_status.php",
    "payroll" => "public/Admin/payroll.php",
    "payroll-driver" => "public/Admin/payroll-driver.php",
    "payroll-timesheet" => "public/Admin/payroll-timesheet.php",
    "performance" => "public/Admin/performance.php",
    "driver-performance" => "public/Admin/driver_performance.php",
    "driver-trips" => "public/Admin/driver_trips.php",
    "analytics" => "public/Admin/analytics.php",
    "utilization" => "public/Admin/utilization.php",
    "billing" => "public/Admin/billing.php",
    "settings" => "public/Admin/settings.php",
    "profile" => "public/Admin/profile.php",
    "users" => "public/Admin/users.php",
    "activity-log" => "public/Admin/activityLog.php",
    "user-dashboard" => "public/User/dashboard.php",
    "billing-dashboard" => "public/Billing/dashboard.php",
    "bbhm" => "public/Admin/bbhm.php",
    "cargoTruck" => "public/Admin/cargoTruck.php",
    "DPC_KDI" => "public/Admin/DPC_KDI.php",
    "others" => "public/Admin/others.php",
    "abcrv" => "public/Admin/abcrv.php",
    "doleRv" => "public/Admin/doleRv.php",
    "sumiRv" => "public/Admin/sumiRv.php",
    "tdcRv" => "public/Admin/tdcRv.php",
    "dryVan" => "public/Admin/dryVan.php",
    "drivers" => "public/Admin/drivers.php",
    "records" => "public/Admin/records.php",
    "transmittals" => "public/Admin/transmittals.php",
    "transactions" => "public/Admin/transactions.php",
];

if (isLoggedIn()) {
    $roleTarget = redirectByRole($route, $userType);
    if ($roleTarget !== null) {
        require $roleTarget;
        exit();
    }
}

if (array_key_exists($route, $routes)) {
    require $routes[$route];
} else {
    http_response_code(404);
    echo "404 - Page not found";
}
?>
