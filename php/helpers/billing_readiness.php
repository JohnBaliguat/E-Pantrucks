<?php

/**
 * Service Material + Profit Center readiness, shared by every SAP pipeline. Both must
 * be set (effective value, after Customer SAP Codes overrides) AND exist in their
 * master table. Returns the list of MISSING items (empty = both present).
 */
function billing_customer_missing_sap_master(PDO $conn, array $customer): array
{
    $missing = [];

    // Service Material — the Material Code that goes in the SAP file. It must be assigned
    // TO THIS CUSTOMER in Master Data → Customer SAP Codes (adding it to the Service
    // Materials master list alone is not enough).
    $material = trim((string) ($customer["material_code"] ?? ""));
    if ($material === "") {
        $missing[] = "Material Code — assign one to this customer in Master Data → Customer SAP Codes";
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM service_material WHERE material_code = ?");
        $stmt->execute([$material]);
        if ((int) $stmt->fetchColumn() === 0) {
            $missing[] = "Service Material (Material Code “" . $material . "” is not in the Service Materials list)";
        }
    }

    // Profit Center — likewise assigned per-customer in Customer SAP Codes.
    $profit = trim((string) ($customer["profit_center"] ?? ""));
    if ($profit === "") {
        $missing[] = "Profit Center — assign one to this customer in Master Data → Customer SAP Codes";
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM profit_center WHERE profit_center_code = ?");
        $stmt->execute([$profit]);
        if ((int) $stmt->fetchColumn() === 0) {
            $missing[] = "Profit Center (“" . $profit . "” is not in the Profit Center list)";
        }
    }

    return $missing;
}

/**
 * Does a customer_key have an active rate matrix? When $onDate is given, require a
 * matrix VERSION covering that date with at least one active lane (dated versions);
 * otherwise just any active lane.
 */
function billing_customer_has_rate_matrix(PDO $conn, string $matrixKey, ?string $onDate = null): bool
{
    try {
        if ($onDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $onDate)) {
            $stmt = $conn->prepare(
                "SELECT COUNT(*) FROM rate_lane
                 WHERE customer_key = ? AND active = TRUE
                   AND (effective_from IS NULL OR effective_from <= ?)
                   AND (effective_to IS NULL OR effective_to >= ?)"
            );
            $stmt->execute([$matrixKey, $onDate, $onDate]);
            return ((int) $stmt->fetchColumn()) > 0;
        }
        $stmt = $conn->prepare("SELECT COUNT(*) FROM rate_lane WHERE customer_key = ? AND active = TRUE");
        $stmt->execute([$matrixKey]);
        return ((int) $stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Full readiness for the fuel-rate-matrix pipelines (Box Bananas + Sumifru): Service
 * Material + Profit Center + a Rate Matrix version effective for $onDate (the billing
 * end date). $customer is the override-applied config.
 */
function billing_customer_missing_assignments(PDO $conn, string $customerKey, array $customer, ?string $onDate = null): array
{
    $missing = billing_customer_missing_sap_master($conn, $customer);

    $matrixKey = function_exists("box_banana_matrix_key")
        ? box_banana_matrix_key($customerKey, $customer)
        : $customerKey;
    if (!billing_customer_has_rate_matrix($conn, $matrixKey, $onDate)) {
        $missing[] = $onDate !== null
            ? "Rate Matrix (no lane effective on " . $onDate . ")"
            : "Rate Matrix";
    }

    return $missing;
}
