-- PowerCabs Admin Panel — Supabase SQL
-- Run this in your Supabase SQL Editor (Dashboard → SQL Editor → New query)
-- This creates only the admin_users table needed for the admin panel login.
-- The rides / drivers / passengers tables already exist in your Supabase project.

-- ─── Admin Users ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS public.admin_users (
  id            UUID            NOT NULL DEFAULT gen_random_uuid(),
  name          TEXT            NOT NULL,
  email         TEXT            NOT NULL UNIQUE,
  password_hash TEXT            NOT NULL,
  role          TEXT            NOT NULL DEFAULT 'dispatcher'
                                CHECK (role IN ('super_admin','dispatcher','finance','support','fleet_manager')),
  is_active     BOOLEAN         NOT NULL DEFAULT TRUE,
  last_login    TIMESTAMPTZ     NULL,
  created_at    TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
  PRIMARY KEY (id)
);

-- RLS: only service_role can read/write admin_users (panel connects via direct DB, not REST API)
ALTER TABLE public.admin_users ENABLE ROW LEVEL SECURITY;

CREATE POLICY "admin_users_no_anon_access" ON public.admin_users
  FOR ALL USING (FALSE);  -- block all anon/auth REST access; PHP uses postgres role directly

-- ─── Seed: Default super admin ──────────────────────────────────────────────
-- Default credentials: admin@powercabs.ie / Admin@1234
-- CHANGE THE PASSWORD IMMEDIATELY after first login.
-- To generate a new hash in PHP: echo password_hash('YourPassword', PASSWORD_BCRYPT, ['cost'=>12]);
INSERT INTO public.admin_users (name, email, password_hash, role)
VALUES (
  'Super Admin',
  'admin@powercabs.ie',
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'super_admin'
)
ON CONFLICT (email) DO NOTHING;

-- ─── Notes ──────────────────────────────────────────────────────────────────
-- The admin panel connects using the postgres DATABASE PASSWORD (not the anon/service role API key).
-- Get it from: Supabase Dashboard → Settings → Database → Database password
-- Set it in: admin/config/database.php → DB_PASS constant
--
-- The existing tables used by the admin panel are:
--   public.drivers    — driver profiles (full_name, plate_no, type jsonb, is_online, etc.)
--   public.passengers — passenger profiles (name, email, phone, photo_url, etc.)
--   public.rides      — ride records (user_id → passenger, driver_id, fare_eur, status, etc.)
