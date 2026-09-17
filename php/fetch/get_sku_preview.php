<?php
include "../config/config.php";

header('Content-Type: application/json');

$shipper   = trim((string) ($_GET['shipper'] ?? ''));
$ph        = trim((string) ($_GET['ph'] ?? ''));
// Optional actual trip locations so the preview can pick the right origin/dest
// variant (e.g. DICT→DICT = PNB-PNB) instead of the first shipper+farm SKU.
$pullout   = trim((string) ($_GET['pullout'] ?? ''));
$delivered = trim((string) ($_GET['delivered'] ?? ''));

if ($shipper === '' || $ph === '') {
    echo json_encode(['found' => false, 'sku' => '', 'kms' => '']);
    exit();
}

function normalize_ph($ph) {
    $ph = trim((string) $ph);
    if (preg_match('/^[A-Z]+0*(\d+)$/i', $ph, $m)) {
        $n = ltrim($m[1], '0');
        return $n === '' ? '0' : $n;
    }
    return $ph;
}

// Location → region code, kept in step with build_rv_route() in php/insert/rv.php.
function sku_preview_route($location) {
    $normalized = strtoupper(trim((string) $location));
    if ($normalized === '') {
        return '';
    }
    // PANABO-region locations; everything else resolves to DAVAO (DVO). Keep in
    // step with php/insert/rv.php and php/update/rv.php.
    $pnbLocations = [
        "DICT", "DICT CY", "PW", "PANABO", "TDC",
        "DOLE CY", "DOLE(PANABO WHARF)",
        "PW/DOLE", "CY/DOLE", "DOLE",
    ];
    return in_array($normalized, $pnbLocations, true) ? "PNB" : "DVO";
}

$normalizedPh = normalize_ph($ph);
$r1 = sku_preview_route($pullout);
$r2 = sku_preview_route($delivered);

$row = null;

// Region-specific match first, when both locations are known.
if ($r1 !== '' && $r2 !== '') {
    $stmt = $conn->prepare(
        "SELECT sku_name, \"sku_rountripDistance\"
         FROM sku
         WHERE LOWER(TRIM(sku_shipper_segment)) = LOWER(TRIM(?))
           AND LOWER(TRIM(sku_farm)) = LOWER(TRIM(?))
           AND UPPER(TRIM(sku_name)) LIKE ?
         LIMIT 1"
    );
    $stmt->execute([$shipper, $normalizedPh, "%-" . $r1 . "-" . $r2]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Fall back to the region-agnostic lookup (original behavior).
if ($row === null) {
    $stmt = $conn->prepare(
        "SELECT sku_name, \"sku_rountripDistance\"
         FROM sku
         WHERE LOWER(TRIM(sku_shipper_segment)) = LOWER(TRIM(?))
           AND LOWER(TRIM(sku_farm)) = LOWER(TRIM(?))
         LIMIT 1"
    );
    $stmt->execute([$shipper, $normalizedPh]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($row) {
    echo json_encode([
        'found' => true,
        'sku'   => trim((string) ($row['sku_name'] ?? '')),
        'kms'   => trim((string) ($row['sku_rountripDistance'] ?? '')),
    ]);
} else {
    echo json_encode(['found' => false, 'sku' => '', 'kms' => '']);
}
