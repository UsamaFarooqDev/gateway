-- PowerCabs Admin Panel — Supabase Views
-- Run this ONCE in: Supabase Dashboard → SQL Editor → New query
-- These views power the admin panel's driver/passenger listing with ride stats.

-- ─── Driver stats view ──────────────────────────────────────────────────────
-- Adds total_rides + total_earnings per driver (computed from completed rides).
-- The admin panel queries this view instead of the raw drivers table.
CREATE OR REPLACE VIEW public.admin_driver_stats AS
SELECT
    d.id, d.full_name, d.email, d.phone, d.no_seats,
    d.plate_no, d.profile_pic_url,
    d.vehicle_make, d.vehicle_model, d.vehicle_number,
    d.type, d.status, d.is_online,
    d.last_active, d.created_at, d.updated_at,
    d.license_url, d.vehicle_reg_url, d.insurance_url,
    d.nct_cert, d.rt_cert, d.suitability_cert,
    d.license_expiry, d.deleted_at,
    COALESCE(rs.total_rides,    0) AS total_rides,
    COALESCE(rs.total_earnings, 0) AS total_earnings
FROM public.drivers d
LEFT JOIN (
    SELECT
        driver_id,
        COUNT(*)                                             AS total_rides,
        COALESCE(SUM(COALESCE(final_fare, fare_eur)), 0)    AS total_earnings
    FROM public.rides
    WHERE status = 'completed'
    GROUP BY driver_id
) rs ON rs.driver_id = d.id;

-- Grant access (service_role already bypasses RLS, but explicit grants are good practice)
GRANT SELECT ON public.admin_driver_stats TO service_role;
GRANT SELECT ON public.admin_driver_stats TO authenticated;

-- ─── Passenger stats view ───────────────────────────────────────────────────
-- Adds total_rides, total_spent, avg_rating per passenger.
CREATE OR REPLACE VIEW public.admin_passenger_stats AS
SELECT
    p.id, p.name, p.email, p.phone, p.photo_url,
    p.status, p.created_at, p.updated_at,
    p.is_email_verified, p.stripe_customer_id, p.deleted_at,
    COALESCE(rs.total_rides, 0) AS total_rides,
    COALESCE(rs.total_spent, 0) AS total_spent,
    COALESCE(rs.avg_rating,  0) AS avg_rating
FROM public.passengers p
LEFT JOIN (
    SELECT
        user_id,
        COUNT(*)                                             AS total_rides,
        COALESCE(SUM(COALESCE(final_fare, fare_eur)), 0)    AS total_spent,
        ROUND(
            AVG(driver_rating) FILTER (WHERE driver_rating IS NOT NULL),
        1)                                                   AS avg_rating
    FROM public.rides
    WHERE status = 'completed'
    GROUP BY user_id
) rs ON rs.user_id = p.id;

GRANT SELECT ON public.admin_passenger_stats TO service_role;
GRANT SELECT ON public.admin_passenger_stats TO authenticated;

-- ─── Admin users table ───────────────────────────────────────────────────────
-- Only needed if you haven't already created it.
CREATE TABLE IF NOT EXISTS public.admin_users (
    id            UUID        NOT NULL DEFAULT gen_random_uuid(),
    name          TEXT        NOT NULL,
    email         TEXT        NOT NULL UNIQUE,
    password_hash TEXT        NOT NULL,
    role          TEXT        NOT NULL DEFAULT 'dispatcher'
                              CHECK (role IN ('super_admin','dispatcher','finance','support','fleet_manager')),
    is_active     BOOLEAN     NOT NULL DEFAULT TRUE,
    last_login    TIMESTAMPTZ NULL,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (id)
);

ALTER TABLE public.admin_users ENABLE ROW LEVEL SECURITY;
-- Block all anon/client REST access; only PHP backend with service_role reads this
CREATE POLICY IF NOT EXISTS "block_anon" ON public.admin_users FOR ALL USING (FALSE);

GRANT SELECT, INSERT, UPDATE ON public.admin_users TO service_role;

-- Default login: admin@powercabs.ie / Admin@1234  ← change immediately
INSERT INTO public.admin_users (name, email, password_hash, role)
VALUES (
    'Super Admin',
    'admin@powercabs.ie',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'super_admin'
)
ON CONFLICT (email) DO NOTHING;
