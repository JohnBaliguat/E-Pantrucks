# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

E-Pantrucks (internally "LogiTrack") is a server-rendered **PHP** web app for a trucking/logistics operation: encoding trip records ("RV" / waybills), monitoring operations, driver payroll, and — the largest and most active domain — **customer billing** (generating SAP upload files and PDF statements from trip data). It runs under XAMPP/Apache at `C:\xampp\htdocs\E-Pantrucks` and talks to PostgreSQL databases hosted on Supabase.

**Important:** the `src/` React + Vite + TypeScript code is leftover Bolt starter scaffolding (`src/App.tsx` is a placeholder). It is **not** the application and is not served in production. Ignore `npm`, `vite`, `eslint`, and `tsc` for real work — they only touch the dead React scaffold. There is no build step for the PHP app; edit a `.php` file and reload.

## Running

The app is served by Apache (XAMPP) from this directory. There is no dev server to start — open `http://localhost/E-Pantrucks/` (or the configured vhost) in a browser. `.htaccess` rewrites every non-file request to `index.php?route=<path>`, so clean URLs like `/dashboard` map to routes.

The React scaffold's scripts (`npm run dev|build|lint`, `npm run typecheck`) exist but are irrelevant to the PHP app; don't run them to validate PHP changes.

## Architecture

### Front controller + role-based routing
`index.php` is the single entry point. It reads `$_GET['route']`, enforces login (`requireLogin`), and gates access by `$_SESSION['user_type']` (`Admin`, `Subadmin`, `User`, `Billing`, `Billingadmin`, `Manager`). Two structures in `index.php` govern everything:
- `$routes` — the default route→file map (mostly `public/Admin/*.php`).
- `redirectByRole()` + `isRouteAllowed()` — override the target and the allow-list per role, so the same route (e.g. `entry`, `profile`, `customer-billing`) resolves to different files for `User` vs `Billing` vs `Admin`.

**When adding a page, you must update `index.php`** — add it to `$routes`, to the correct role's `isRouteAllowed` list, and (if role-specific) to `redirectByRole`. Otherwise it 404s or redirects to `dashboard`.

### Roles and their views
`$_SESSION['user_type']` (case-insensitive, normalized via `ucfirst(strtolower())`) is one of six roles. Landing page is the `dashboard` route resolved per role; the allow-lists below come from `isRouteAllowed()` in `index.php`.

| Role | Lands on | Can access (routes) |
|------|----------|---------------------|
| **Admin** | `public/Admin/dashboard.php` | everything: `entry`, `monitoring`, `payroll`, `payroll-driver`, `payroll-timesheet`, `performance`, `driver-performance`, `driver-trips`, `analytics`, `utilization`, `customer-billing`, `for-update`, `settings`, `users`, `activity-log`, `bbhm`, `drivers`, `records`, `transmittals`, `transactions`, the RV/entry pages (`abcrv`, `doleRv`, `sumiRv`, `tdcRv`, `dryVan`, `cargoTruck`, `DPC_KDI`, `others`), `profile` |
| **Subadmin** | `public/Admin/dashboard.php` | same reach as **Admin** but **without** `monitoring`, `records`, `transmittals`, `settings`, `users` — i.e. `entry`, `payroll`, `payroll-driver`, `payroll-timesheet`, `performance`, `driver-performance`, `driver-trips`, `analytics`, `utilization`, `customer-billing`, `for-update`, `activity-log`, `bbhm`, `drivers`, `transactions`, the RV/entry pages, `profile`. Stored as `SubAdmin` (label "Sub Admin"); a view-access role, not read-only enforcement on the pages it can open. |
| **User** | `public/User/dashboard.php` | encoder subset: `entry` (→ `public/User/abcrv.php`), `monitoring`, the RV/entry pages, `records`, `transmittals`, `for-update`, `profile` |
| **Billing** | `public/Billing/dashboard.php` | `billing-dashboard`, `customer-billing`, `master-data`, `profile` |
| **Billingadmin** | `public/Billing/dashboard.php` | same as **Billing** |
| **Manager** | `public/Admin/analytics.php` | read-only insight: `analytics`, `driver-performance`, `utilization`, `profile` |

The three page areas mirror these roles: `public/Admin/` (Admin + Subadmin + Manager), `public/User/` (User = data encoders), `public/Billing/` (Billing / Billingadmin). Several routes are **shared but resolve to different files per role** via `redirectByRole()` — e.g. `entry` and `profile` map into `public/User/` for a User but `public/Admin/` for an Admin, and `customer-billing` / `master-data` serve the Billing pages. When a route isn't in a role's allow-list the user is bounced to `dashboard`, so pages are gated by role, not just hidden in the sidebar.

Pages live in `public/{Admin,User,Billing}/`. Each area has its own `sidebar.php` / `navbar.php`. Sessions are established in `login-php.php` / `microsoft-*.php`; `php/session-check.php` guards direct page access.

### Pages call PHP endpoints via `fetch()`
Pages are PHP that render HTML + inline JS. The JS calls small single-purpose endpoints under:
- `php/fetch/` — reads (GET), returns JSON or a downloadable file (`get_*`, `download_*`, `export_*`).
- `php/insert/`, `php/update/`, `php/delete/` — writes.

Endpoints `include "../config/config.php"` (giving `$conn`), `require_once` the helpers they need, and echo JSON. This is the request contract — there is no framework, router library, or ORM.

### Business logic lives in `php/helpers/`
The endpoints are thin; the real logic is in `php/helpers/`. Notable clusters:
- **Billing** (the bulk): `unified_billing.php`, `build_customer_billing.php`, `build_activity_billing.php`, `build_box_banana_billing.php`, `build_raw_billing.php`, `build_billing_pdf.php` / `build_activity_pdf.php`, `billing_customers.php`, `custom_billing_customers.php`, `box_banana_customers.php`, `billing_document.php`, `billing_readiness.php`. Different customers/activities are handled as configurable variants that merge into shared builders.
- **Rates & fuel**: `fuel_rate_engine.php`, `trip_rate_lookup.php`, `rate_matrix_*.php`, `sumifru_rate.php` — rates are formula/matrix-priced (often fuel-pump-price driven), computed at billing time, not stored per trip.
- **Cross-cutting**: `auth.php`, `activity_log.php` (auto-logs each request; hooked in `config.php`), `app_settings.php` (admin toggle key/value store), `pdf_writer.php` / `xlsx_helper.php` (file output), `date_normalize.php`, `db_value.php`.

### Database: two Supabase Postgres DBs
- **Main app DB** — `php/config/config.php` sets global `$conn` (PDO, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`). Credentials are hardcoded there.
- **Dispatch DB** (separate, read-only lookup) — `php/config/dispatch_config.php` exposes `dispatch_db()` returning a lazily-connected PDO. Used to look up existing trip receipts/waybills to auto-fill entry forms. Kept separate so it never overwrites `$conn`.

### Schema management: idempotent "ensure" helpers, not migrations
There is no migration runner. The `sql/migrations/*.sql` files are reference/setup only. Schema is created and evolved at runtime by `php/helpers/ensure_*_schema.php` functions (e.g. `ensure_customer_billing_schema`, `ensure_rate_fuel_schema`). Each is guarded by a `static $done` flag and issues only `CREATE TABLE IF NOT EXISTS` / `ADD COLUMN IF NOT EXISTS`, so it's cheap to call at the top of every endpoint that needs those tables.

**To change the schema, edit (or add) the relevant `ensure_*_schema.php` helper** and make sure endpoints touching that data call it — do not rely on the `sql/` files being applied.

### Database tables
All 37 tables below live in the main app DB (`$conn`), schema `public` (verified against the live DB; column counts in parentheses). `operations` is the central table — every entry/RV page writes trip records to it, and billing/monitoring read from it. Most tables are created/evolved by the `ensure_*_schema.php` helpers (see above), so authoritative column lists live there, not in `sql/`.

**Operations & master data**
- `operations` (150) — core trip/waybill records; the hub every encoder page (`abcrv`, `doleRv`, `sumiRv`, `tdcRv`, `dryVan`, `cargoTruck`, `DPC_KDI`, `others`) inserts into and billing/monitoring read from. Very wide (one row per trip leg, all leg/date/equipment/status columns).
- `drivers` (7), `units` (5, trucks), `trailer` (2), `location` (6), `sku` (5), `sku_route` (7) — reference/master data.
- `equipment_sap` (6) — SAP codes mapped to equipment.
- `profit_center` (12) — finance profit-center mapping.

**Users & auditing**
- `user` (17) — accounts and credentials (auth). `user_access` (3) — per-user route access. `user_preferences` (9) — per-user settings.
- `user_activity_log` (22) — auto-written on each request via `config.php`.
- `data_update_flags` (12) — the "For Update" correction queue (records billing flags for fixing).
- `app_settings` (3) — admin key/value toggles.

**Billing**
- `billing_invoices` (18), `billing_invoice_entries` (6), `billing_invoice_entry_forex` (3) — generated customer invoices, their per-trip line entries, and locked forex per entry.
- `billing_custom_customer` (30), `billing_customer_sap` (6) — customer config added via Master Data, and per-customer SAP codes.
- `box_banana_statements` (15), `box_banana_statement_entries` (2) — the box-banana hauling statement variant.
- `raw_billing_batches` (14), `raw_billing_batch_entries` (2) — RAW export batches for finance.
- `transmittals` (15), `transmittal_entries` (2) — billing transmittals.

**Rates, fuel & forex**
- `rates` (9), `trip_rates` (6), `rate_lane` (18), `activity_rate` (6), `service_material` (12) — active rate sources (lane/activity/formula priced).
- `rate_lane_band` (7), `rate_band` (6), `rate_cell` (3), `rate_matrix_version` (6) — **legacy/retired** banded rate-matrix tables; the fuel-rate model was redesigned to a single flat formula-priced `rate_lane`, so treat these as deprecated (don't build new logic on them).
- `fuel_price` (19, pump price + dollar-conversion column), `forex_rate` (8) — inputs to fuel/forex-driven rate computation.

The **dispatch DB** (`dispatch_db()`) is separate and read-only from this app; it holds the upstream dispatch/trip-receipt data queried for waybill auto-fill.

## Conventions to follow

- **SQL**: always PDO prepared statements with bound params (existing code does this; SQL injection prevention depends on it).
- **New billing customer/activity**: prefer extending the config-driven customer/activity lists that feed the shared `build_*` helpers over writing a bespoke page — that's the established pattern (see the billing helper cluster).
- **Egress awareness**: this app polls Supabase; unfiltered/frequent reads have caused large egress bills. Keep new polling endpoints filtered, conditional (version-gated), and infrequent.
- **Activity logging** is automatic via `config.php`; define `DISABLE_AUTO_ACTIVITY_LOG` before including it only for endpoints that must opt out.
- **AJAX endpoints must `require_once __DIR__ . "/../helpers/json_error_guard.php"` as their first line** (`php/helpers/json_error_guard.php`). It forces JSON responses even on PHP fatals/warnings; without it the client parses an HTML error page and shows a misleading "Unable to reach server".
- **Bind optional date/numeric values through `db_nullable()` / `db_nullable_all()`** (`php/helpers/db_value.php`). PostgreSQL rejects `''` for date/time/numeric columns (SQLSTATE 22007 / 22P02); this turns empty strings into SQL NULL.
- **Dates from entry forms are typed as text** (`M/D`, `M/D/YYYY`, `HHMM`) — normalize on the server with `operations_normalize_sql_date()` (`php/helpers/date_normalize.php`), the counterpart to the client-side manual date/time parser. Do **not** switch entry date/time fields to native pickers.
- **Validate against masters before saving a trip**: `location_is_registered()` (`location_validation.php`) and the `master_validate_*` checks (`master_data_validate.php`, for `trip_rates` segment+activity, drivers, location, trailer, units). A location not in the `location` master is rejected because billing matches trips by Pull-Out/Warehouse/Return location — a typo silently drops the trip from billing (validation fails **open** if a master table is unavailable, so infra problems never block saves).
- **Check duplicates on insert/update** via `operations_waybill_exists()` / `_dr_no_` / `_fgtr_no_` / `_booking_exists()` (`waybill_duplicate.php`), passing `$excludeEntryId` on edit.

## Behaviors and workflows not obvious from the file tree

These cross-cutting features span several files and are easy to break during edits or a redesign.

### Entry-page contracts (all 8 encoder pages)
- **Deep-link query params**: `?load_id=<entry_id>` auto-opens a record for editing; `?flag_id=<id>` fetches the record's For-Update field notes and highlights those inputs; `?entry_date=YYYY-MM-DD` sets the working day (`entry_date_filter.php`, defaults today). Preserve these — record lists and the For-Update queue link through them.
- **Field ids/names are load-bearing** beyond validation: `operations_status.php::operations_required_fields_by_type()` computes per-type completeness (the dashboard "Complete Today" count) from them, and the For-Update highlighter binds to them.
- **In-field arithmetic** (`DPC_KDI` today): typing `2+2` resolves to `4` on input/blur/Enter and feeds running totals (`evaluateArithmetic` → `attachCalculatedInput` → `calculateDpcTotals`).
- **`dryVan` reshapes its form per customer** (`applyCustomerFormRules()`): reorders sections, shows/hides field sets, relabels fields from inline `customerConfigs` — depends on the container DOM (`#formGrid`, `#loadedExportSection`, `#emptyImportSection`) and label elements staying intact.

### "For Update" correction queue (`data_update_flags`)
Billing flags an `operations` record with a remark + **per-field notes** (`php/insert/flag_for_update.php`); Admin/User see open flags on the For-Update page and via the navbar bell, open the record in its entry form (deep-link above) with the flagged fields highlighted, fix it, and resolve (`php/update/resolve_update_flag.php`). The bell badge tracks per-user **seen/ack** state (`php/update/ack_flag_notifications.php`, keyed on `user_idNumber`; Admins ack all). `entry_update_route.php` maps a record to the right entry page (RV records route to `doleRv`/`sumiRv`/`tdcRv`/`abcrv` by segment).

### Billing invariants and pipelines
- **Generated invoices are frozen**: each entry's `rate_charge` is locked at generation, and forex can be set **per entry** (`manual_forex_by_entry` → `billing_invoice_entry_forex`). An invoice reproduces exactly even after rates/fuel/forex are later edited — never re-price a posted invoice.
- **Rebuild-on-missing-file**: if a stored xlsx is gone, downloads rebuild it from saved trips + locked charges across every pipeline (`billing_invoice_rebuild.php`); `regenerate_invoice_file.php` writes it back. Failure reasons (`out_of_range`, `unsupported`, …) are surfaced to the user.
- **SAP readiness gate** (`billing_readiness.php`): before a SAP file generates, the customer's Material Code + Profit Center must be assigned to the customer **and** exist in their master tables.
- **Per-customer SAP overrides** (`customer_sap_codes.php`): finance-maintained Sold-To/Material/Profit Center override the code-config value when non-blank — the established way to keep SAP codes out of the code arrays.
- **Document numbers** (`billing_document.php`): `PREFIX-YYYY-NNN`, sequenced per customer per year, printed on the file + SAP Reference so returned billings can be looked up/regenerated. (The prefix map is currently hardcoded.)
- **Customer-specific pipelines** beyond the `build_*` files: `dict_shuttling.php` (DICT Van Shuttling, one file per lane, trip-count priced), `abc_kds.php` (ABC KDs cartons, matrix-priced under the customer's own `matrix_key`), `dry_van.php` (flat rate from the `rates` master by `rate_code`), and `unified_billing.php` which splits customers into `sap` (flat rate_code + forex) vs `matrix` (fuel-rate-matrix) pipelines — **Sumifru appears in both**. These "resolve to 0 and report plainly rather than emit a bogus file" when a rate is missing.
- **Dashboard revenue uses the same rate matrix** as the billing statements (`dashboard_matrix_revenue.php`) — a pricing/rate change moves the dashboard too, so include it in billing regression checks.

### Bulk Excel import
Import trips, master data, and rate matrices from `.xlsx` with downloadable templates: `php/insert/import_entries.php`, `import_master_data.php`, `import_rate_matrix.php`, `import_rates.php`, `import_equipment_sap.php`; templates via `php/fetch/download_*_template.php`.

### Payroll calendar engine
`payroll_calendar.php` implements pay classes `RD / SUN / RH / SPH / RHSUN / SPHSUN / GP`, ported from the payroll Excel Calendar sheet, with a **per-year hardcoded holiday list** — it must be updated each year.

### Operations field overloads (the wide `operations` table)
`waybill_date` **doubles** as the RV Transaction Date on the transmittal; the RV empty leg has its own trip-receipt-date column (`ensure_operations_rv_dates_schema.php`). Dry-van/PSACC add `emdr_no`, `time_unloaded`, `pullout_time`, `commodity` (`ensure_operations_emdr_schema.php`). One column does not always mean one thing.

### Auth & session
Login supports local credentials **and** Microsoft OAuth SSO (`microsoft_auth.php`, `microsoft-login.php`, `microsoft-callback.php`, `.env`), the MS button shown only when configured. Session keys used across pages/navbar/ack include `user_id`, `user_idNumber`, `user_type`, `user_name`, `user_email`, `user_image` — set all of them at login.

## Secrets

`php/config/config.php` and `php/config/dispatch_config.php` contain **hardcoded live database credentials**, and `.env` holds Microsoft OAuth settings. Do not print, commit changes exposing, or forward these.
