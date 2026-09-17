<?php

/**
 * Ensure the optional dry van columns exist:
 *  - emdr_no       (PSACC domestic EMDR reference)
 *  - time_unloaded (dry van Time of Unloading; pairs with date_unloaded)
 *  - pullout_time  (dry van Pull Out Time; pairs with pullout_date)
 *  - commodity     (dry van commodity/cargo description; captured for TPD)
 */
function ensure_operations_emdr_schema(PDO $conn): void
{
    static $alreadyEnsured = false;
    if ($alreadyEnsured) {
        return;
    }

    $conn->exec("ALTER TABLE operations ADD COLUMN IF NOT EXISTS emdr_no TEXT");
    $conn->exec("ALTER TABLE operations ADD COLUMN IF NOT EXISTS time_unloaded TIME WITHOUT TIME ZONE");
    $conn->exec("ALTER TABLE operations ADD COLUMN IF NOT EXISTS pullout_time TIME WITHOUT TIME ZONE");
    $conn->exec("ALTER TABLE operations ADD COLUMN IF NOT EXISTS commodity TEXT");
    $alreadyEnsured = true;
}
