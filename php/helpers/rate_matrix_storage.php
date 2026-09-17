<?php

function rate_matrix_number($value): float
{
    $value = trim((string) $value);
    return ($value !== "" && is_numeric($value)) ? (float) $value : 0.0;
}

/** A YYYY-MM-DD string, or null when blank/invalid (open-ended / always-applies). */
function rate_matrix_date_or_null($value): ?string
{
    $value = trim((string) $value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}

/**
 * Replace ALL rate_lane rows for one customer with the flat, formula-priced rows from the
 * editor. Each row = one lane with its own effective date + fuel-movement formula params
 * (pump_price / price_movement + the 0.4x fuel factor). No bands/cells/versions — the
 * charged rate is computed per trip by rate_matrix_formula_price().
 *
 * $lanes is a list of assoc rows:
 *   effective_date, segment, origin, destination, dcode, base_rate,
 *   pump_price, price_movement, active (bool), sort_order
 */
function rate_matrix_replace_lanes(PDO $conn, string $customerKey, array $lanes): int
{
    // Lane identity used to carry per-lane settings across the delete+reinsert below.
    $identity = static fn(array $l): string => strtoupper(trim(implode("|", [
        trim((string) ($l["segment"] ?? "")),
        trim((string) ($l["origin"] ?? "")),
        trim((string) ($l["packing_house"] ?? "")),
        trim((string) ($l["destination"] ?? "")),
        trim((string) ($l["dcode"] ?? "")),
        (string) rate_matrix_date_or_null($l["effective_from"] ?? ""),
        (string) rate_matrix_date_or_null($l["effective_to"] ?? ""),
    ])));

    $conn->beginTransaction();
    try {
        // The editor payload doesn't carry round_mode yet, so snapshot the existing per-lane
        // rounding overrides and re-apply them to matching rows — otherwise a Master Data save
        // would silently reset a lane that rounds differently from its customer default (e.g.
        // TDC - Dole Asia DICT floors while its Dole - PW lanes round to nearest).
        $existingModes = [];
        $prev = $conn->prepare("SELECT segment, origin, packing_house, destination, dcode, effective_from::text ef, effective_to::text et, round_mode FROM rate_lane WHERE customer_key = ?");
        $prev->execute([$customerKey]);
        foreach ($prev->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $mode = strtolower(trim((string) ($r["round_mode"] ?? "")));
            if ($mode === "") {
                continue;
            }
            $existingModes[$identity([
                "segment" => $r["segment"], "origin" => $r["origin"], "packing_house" => $r["packing_house"],
                "destination" => $r["destination"], "dcode" => $r["dcode"],
                "effective_from" => $r["ef"], "effective_to" => $r["et"],
            ])] = $mode;
        }

        // Bands are children of the lanes; replace both together for this customer.
        $conn->prepare("DELETE FROM rate_lane_band WHERE customer_key = ?")->execute([$customerKey]);
        $conn->prepare("DELETE FROM rate_lane WHERE customer_key = ?")->execute([$customerKey]);

        $laneId = (int) $conn->query("SELECT COALESCE(MAX(lane_id), 0) FROM rate_lane")->fetchColumn();
        $bandId = (int) $conn->query("SELECT COALESCE(MAX(band_id), 0) FROM rate_lane_band")->fetchColumn();
        $insert = $conn->prepare(
            "INSERT INTO rate_lane
                (lane_id, customer_key, segment, origin, packing_house, destination, dcode, base_rate,
                 sort_order, active, effective_from, effective_to, pump_price, price_movement, monthly_fuel_average, round_mode)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $bandInsert = $conn->prepare(
            "INSERT INTO rate_lane_band (band_id, lane_id, customer_key, fuel_from, fuel_to, rate, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $saved = 0;
        foreach ($lanes as $i => $lane) {
            if (!is_array($lane)) {
                continue;
            }
            // Skip fully-blank rows (no route identity and no base rate).
            $dcode = trim((string) ($lane["dcode"] ?? ""));
            $dest = trim((string) ($lane["destination"] ?? ""));
            $origin = trim((string) ($lane["origin"] ?? ""));
            $base = rate_matrix_number($lane["base_rate"] ?? 0);
            if ($dcode === "" && $dest === "" && $origin === "" && $base <= 0) {
                continue;
            }

            $active = array_key_exists("active", $lane)
                ? filter_var($lane["active"], FILTER_VALIDATE_BOOLEAN)
                : true;

            // Base fuel price (pump_price) = the diesel price the base rate stays flat up to;
            // the surcharge escalates only above it. Defaults to the company baseline (50)
            // when blank, so lanes that don't set it keep the standard behaviour.
            $pumpPrice = rate_matrix_number($lane["pump_price"] ?? 0);
            if ($pumpPrice <= 0) {
                $pumpPrice = 50.0;
            }

            // Per-lane rounding override: prefer the payload (future UI control); otherwise
            // carry over the pre-save value for this lane identity. Only "down"/"nearest" are
            // stored — anything else means "use the customer default".
            $roundMode = strtolower(trim((string) ($lane["round_mode"] ?? "")));
            if (!in_array($roundMode, ["down", "nearest", "up"], true)) {
                $roundMode = $existingModes[$identity($lane)] ?? "";
            }

            $laneId++;
            $insert->execute([
                $laneId,
                $customerKey,
                trim((string) ($lane["segment"] ?? "")),
                $origin,
                trim((string) ($lane["packing_house"] ?? "")),
                $dest,
                $dcode,
                $base,
                (int) ($lane["sort_order"] ?? $i),
                $active,
                rate_matrix_date_or_null($lane["effective_from"] ?? ""),
                rate_matrix_date_or_null($lane["effective_to"] ?? ""),
                $pumpPrice,
                rate_matrix_number($lane["price_movement"] ?? 0),
                rate_matrix_number($lane["monthly_fuel_average"] ?? 0),
                $roundMode !== "" ? $roundMode : null,
            ]);

            // Optional per-lane fuel-price bands (stepped flat rate per diesel range).
            $bands = is_array($lane["bands"] ?? null) ? $lane["bands"] : [];
            foreach ($bands as $bi => $band) {
                if (!is_array($band)) {
                    continue;
                }
                $bandRate = rate_matrix_number($band["rate"] ?? 0);
                $from = trim((string) ($band["fuel_from"] ?? ""));
                $to = trim((string) ($band["fuel_to"] ?? ""));
                // Skip fully-blank band rows (no rate and no bounds).
                if ($bandRate <= 0 && $from === "" && $to === "") {
                    continue;
                }
                $bandId++;
                $bandInsert->execute([
                    $bandId,
                    $laneId,
                    $customerKey,
                    ($from !== "" && is_numeric($from)) ? (float) $from : null,
                    ($to !== "" && is_numeric($to)) ? (float) $to : null,
                    $bandRate,
                    (int) ($band["sort_order"] ?? $bi),
                ]);
            }
            $saved++;
        }

        $conn->commit();
        return $saved;
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}
