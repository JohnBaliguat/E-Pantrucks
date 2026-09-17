<?php
// One-off migration: adopt the "tr = Loaded trailer, tr2 = Empty trailer"
// convention for existing DRY VAN records of a given export/domestic customer.
//
// Historically the export/domestic layout stored the Loaded trailer in tr2 and
// the Empty trailer in tr. Customers migrated to the new convention store
// Loaded->tr and Empty->tr2, so their existing rows must have tr/tr2 swapped.
//
// A given customer must be swapped exactly ONCE — re-running apply swaps back.
//
// Usage (customer name is required, quote it):
//   Dry run:  php migrate_dryvan_trailer_convention.php "PSACC DOMESTIC"
//   Apply:    php migrate_dryvan_trailer_convention.php "PSACC DOMESTIC" apply
//   Browser:  ?customer=PSACC%20DOMESTIC[&apply=1]

define("DISABLE_AUTO_ACTIVITY_LOG", true);
require_once __DIR__ . "/../config/config.php";

if (PHP_SAPI !== "cli") {
    header("Content-Type: text/plain; charset=utf-8");
}

$customer = "";
$apply = false;
if (PHP_SAPI === "cli") {
    $args = array_slice($argv, 1);
    $apply = in_array("apply", $args, true);
    foreach ($args as $a) {
        if ($a !== "apply") { $customer = $a; break; }
    }
} else {
    $customer = (string) ($_GET["customer"] ?? "");
    $apply = isset($_GET["apply"]) && $_GET["apply"] === "1";
}

$customer = trim($customer);
if ($customer === "") {
    echo "ERROR: a customer name is required (e.g. \"PSACC DOMESTIC\").\n";
    exit(1);
}

$stmt = $conn->prepare(
    "SELECT entry_id, tr, tr2 FROM operations
     WHERE entry_type = 'DRY VAN ENTRY' AND customer_ph = ?
     ORDER BY entry_id"
);
$stmt->execute([$customer]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "{$customer} trailer swap (tr <-> tr2)\n";
echo "Matching records: " . count($rows) . "\n";
echo str_repeat("-", 60) . "\n";
echo sprintf("%-10s | %-18s | %-18s\n", "entry_id", "tr (current)", "tr2 (current)");
echo str_repeat("-", 60) . "\n";
foreach ($rows as $r) {
    echo sprintf(
        "%-10s | %-18s | %-18s\n",
        $r["entry_id"],
        (string) ($r["tr"] ?? ""),
        (string) ($r["tr2"] ?? "")
    );
}
echo str_repeat("-", 60) . "\n";
echo "After the swap each row's tr and tr2 values are exchanged\n";
echo "(tr becomes the Loaded trailer, tr2 becomes the Empty trailer).\n";

// SAFETY: only swap rows that actually followed the old convention, i.e. where the
// Empty column (tr2) is non-blank. Legacy rows with tr populated but tr2 blank
// stored the single trailer in tr (the Loaded slot under the new convention), so
// swapping them would blank the Loaded column. Those are skipped and listed.
$skipped = array_filter($rows, function ($r) {
    return trim((string) ($r["tr"] ?? "")) !== "" && trim((string) ($r["tr2"] ?? "")) === "";
});
if ($skipped) {
    echo "\nSKIPPED (tr set, tr2 blank — left untouched for review): "
        . count($skipped) . " row(s)\n  entry_ids: "
        . implode(", ", array_map(fn($r) => $r["entry_id"], $skipped)) . "\n";
}
echo "\n";

if (!$apply) {
    echo "DRY RUN — no changes made. Re-run with 'apply' to perform the swap.\n";
    exit();
}

try {
    $conn->beginTransaction();
    // Postgres evaluates all SET right-hand sides against the pre-UPDATE row,
    // so this is a true atomic swap. Only rows with a non-blank tr2 are swapped.
    $upd = $conn->prepare(
        "UPDATE operations SET tr = tr2, tr2 = tr
         WHERE entry_type = 'DRY VAN ENTRY' AND customer_ph = ?
           AND NULLIF(TRIM(tr2), '') IS NOT NULL"
    );
    $upd->execute([$customer]);
    $affected = $upd->rowCount();
    $conn->commit();
    echo "APPLIED — rows updated: {$affected}\n";
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "FAILED — no changes committed: " . $e->getMessage() . "\n";
    exit(1);
}
