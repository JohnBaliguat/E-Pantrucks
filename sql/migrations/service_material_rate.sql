-- =====================================================================
-- Add an editable price (rate) to service materials so finance can set the
-- peso amount for each equipment/service charge (e.g. Genset Charges per hour).
-- Used by the Billing dashboard's Equipment Revenue calculation.
--
-- Run once in the Supabase SQL editor (or psql) against your DB.
-- =====================================================================

ALTER TABLE service_material ADD COLUMN IF NOT EXISTS rate text;
