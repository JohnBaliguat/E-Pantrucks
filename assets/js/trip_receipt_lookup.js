/**
 * Trip Receipt / Waybill lookup against the dispatch database.
 *
 * When a user finishes typing a trip receipt number in an entry form, this
 * checks php/fetch/lookup_trip_receipt.php. If the number already exists as a
 * completed trip it pops an alert and auto-fills the matching form fields.
 * If it isn't found (or the dispatch DB is unreachable) it still lets the user
 * proceed -- the alert is informational only, never blocking.
 *
 * Usage on a page (after SweetAlert is loaded):
 *   attachTripReceiptLookup({ input: 'waybill' });
 *   attachTripReceiptLookup({ input: 'waybill_empty' });
 *
 * Options:
 *   input        : id (string) or element of the trip-receipt field (required)
 *   map          : optional { recordField: elementId } object. When given, ONLY
 *                  those fields are filled. Use this to scope a lookup to one
 *                  leg of a two-leg form (e.g. the empty vs. loaded waybill) so
 *                  one leg's lookup never overwrites the other leg's fields.
 *   exclude      : optional array of field ids the default autofill must NEVER
 *                  set (e.g. ['customer_ph'] on Dry Van, where the encoder's
 *                  chosen customer is authoritative). Ignored when `map` or
 *                  `applyAutofill` is supplied.
 *   applyAutofill: optional custom fn(record) to fill fields. Overrides `map`.
 *                  Defaults to a generic mapping that fills any matching field.
 *   isComplete   : optional fn(value)->bool deciding when to fire. Default: a
 *                  6-digit number.
 */
(function (global) {
    "use strict";

    function setField(id, value) {
        if (value === undefined || value === null) return;
        var text = String(value).trim();
        if (text === "") return;
        var el = document.getElementById(id);
        if (!el) return;
        el.value = text;
        // Notify any listeners (totals, validation) without opening the
        // autocomplete dropdowns that react to the 'input' event.
        el.dispatchEvent(new Event("change", { bubbles: true }));
    }

    // Fill only the fields named in a { recordField: elementId } map.
    function applyWithMap(map, record) {
        if (!record || !map) return;
        Object.keys(map).forEach(function (recKey) {
            setField(map[recKey], record[recKey]);
        });
    }

    // Generic mapping from the dispatch record to common entry-form field ids.
    // Each field is only set if that input actually exists on the page.
    // `exclude` is a { fieldId: true } set of ids the caller never wants filled
    // (e.g. Dry Van keeps the encoder's chosen customer_ph authoritative).
    function defaultApplyAutofill(record, exclude) {
        if (!record) return;
        exclude = exclude || {};
        function fill(id, value) {
            if (exclude[id]) return;
            setField(id, value);
        }
        fill("driver", record.driver);
        fill("truck", record.truck);
        fill("prime_mover", record.truck);
        fill("tr", record.trailer);
        fill("gs", record.genset);
        fill("ecs", record.ecs);
        fill("van_alpha", record.container_alpha);
        fill("van_number", record.container_number);
        fill("customer_ph", record.customer);
        fill("ph", record.ph);
        fill("deliver_from", record.deliver_from);
        fill("deliver_to", record.deliver_to);
    }

    function alertCompleted(tr) {
        if (typeof Swal === "undefined") return;
        Swal.fire({
            icon: "success",
            title: "Completed Trip",
            html: "Trip Receipt <strong>" + tr + "</strong> is a <strong>completed trip</strong>.<br>" +
                  "The available information has been auto-filled. Please review before saving.",
            confirmButtonText: "OK",
        });
    }

    function alertFoundInProgress(tr, status) {
        if (typeof Swal === "undefined") return;
        Swal.fire({
            icon: "info",
            title: "Trip Found",
            html: "Trip Receipt <strong>" + tr + "</strong> exists in dispatch records " +
                  "(status: <strong>" + (status || "in progress") + "</strong>).<br>" +
                  "The available information has been auto-filled. You may still proceed.",
            confirmButtonText: "OK",
        });
    }

    function alertNotFound(tr) {
        if (typeof Swal === "undefined") return;
        Swal.fire({
            icon: "info",
            title: "No Matching Trip",
            html: "No dispatch record found for Trip Receipt <strong>" + tr + "</strong>.<br>" +
                  "You can still proceed with this entry.",
            confirmButtonText: "OK",
        });
    }

    function attachTripReceiptLookup(opts) {
        opts = opts || {};
        var input = typeof opts.input === "string"
            ? document.getElementById(opts.input)
            : opts.input;
        if (!input) return;

        var exclude = {};
        (opts.exclude || []).forEach(function (id) { exclude[id] = true; });
        var applyAutofill = opts.applyAutofill
            || (opts.map
                ? function (record) { applyWithMap(opts.map, record); }
                : function (record) { defaultApplyAutofill(record, exclude); });
        var isComplete = opts.isComplete || function (v) {
            return /^\d{6}$/.test(String(v).trim());
        };

        var lastChecked = "";
        var busy = false;

        async function run() {
            var value = String(input.value || "").trim();
            if (value === "") { lastChecked = ""; return; }
            if (busy || value === lastChecked) return;
            if (!isComplete(value)) return;

            lastChecked = value;
            busy = true;
            var data = null;
            try {
                var res = await fetch(
                    "php/fetch/lookup_trip_receipt.php?tr=" + encodeURIComponent(value),
                    { cache: "no-store" }
                );
                data = await res.json();
            } catch (e) {
                busy = false;
                return; // network/DB error: stay silent, allow proceed
            }
            busy = false;

            if (!data || !data.success) return; // dispatch DB unavailable: proceed silently

            if (data.found) {
                if (opts.autofill !== false) {
                    try { applyAutofill(data.record || {}); } catch (e) { /* ignore */ }
                }
                if (data.completed) {
                    alertCompleted(value);
                } else {
                    alertFoundInProgress(value, data.status);
                }
            } else {
                alertNotFound(value);
            }
        }

        // Fire when the field looks complete while typing, and again on blur.
        input.addEventListener("input", function () {
            var v = String(input.value || "").trim();
            if (v === "") { lastChecked = ""; return; }
            if (isComplete(v)) run();
        });
        input.addEventListener("blur", run);
    }

    global.attachTripReceiptLookup = attachTripReceiptLookup;
})(window);
