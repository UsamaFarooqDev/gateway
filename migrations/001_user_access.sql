-- =============================================================================
-- Migration 001: User Application Access + Module Permissions
-- Run this in the Supabase SQL editor (Dashboard → SQL Editor)
-- =============================================================================

-- ─── Table: user_application_access ─────────────────────────────────────────
-- Records which applications (gateway / dispatcher) each admin_user may enter.
CREATE TABLE IF NOT EXISTS public.user_application_access (
    id          uuid        PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id     uuid        NOT NULL REFERENCES public.admin_users(id) ON DELETE CASCADE,
    application text        NOT NULL,
    created_at  timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT user_application_access_unique
        UNIQUE (user_id, application),

    CONSTRAINT user_application_access_app_check
        CHECK (application IN ('gateway', 'dispatcher'))
);

-- ─── Table: user_module_permissions ─────────────────────────────────────────
-- Fine-grained CRUD permissions per user, per application, per module.
CREATE TABLE IF NOT EXISTS public.user_module_permissions (
    id          uuid        PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id     uuid        NOT NULL REFERENCES public.admin_users(id) ON DELETE CASCADE,
    application text        NOT NULL,
    module_key  text        NOT NULL,
    can_view    boolean     NOT NULL DEFAULT false,
    can_create  boolean     NOT NULL DEFAULT false,
    can_edit    boolean     NOT NULL DEFAULT false,
    can_delete  boolean     NOT NULL DEFAULT false,
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT user_module_permissions_unique
        UNIQUE (user_id, application, module_key),

    CONSTRAINT user_module_permissions_app_check
        CHECK (application IN ('gateway', 'dispatcher'))
);

-- ─── Default seeding based on existing roles ─────────────────────────────────
-- NOTE: super_admin bypasses all checks in code; these rows are reference only.

-- 1. super_admin → both applications
INSERT INTO public.user_application_access (user_id, application)
    SELECT id, 'gateway'    FROM public.admin_users WHERE role = 'super_admin' ON CONFLICT DO NOTHING;
INSERT INTO public.user_application_access (user_id, application)
    SELECT id, 'dispatcher' FROM public.admin_users WHERE role = 'super_admin' ON CONFLICT DO NOTHING;

-- super_admin: full permissions on every gateway module
DO $$
DECLARE
    v_user   RECORD;
    v_module TEXT;
    v_gateway_modules TEXT[] := ARRAY[
        'dashboard','drivers','passengers','rides','corporate','fleet',
        'finance','promotions','notifications','analytics','integrations',
        'admins','zones','support','ratings','settings','dispatcher'
    ];
BEGIN
    FOR v_user IN SELECT id FROM public.admin_users WHERE role = 'super_admin' LOOP
        FOREACH v_module IN ARRAY v_gateway_modules LOOP
            INSERT INTO public.user_module_permissions
                (user_id, application, module_key, can_view, can_create, can_edit, can_delete)
            VALUES (v_user.id, 'gateway', v_module, true, true, true, true)
            ON CONFLICT (user_id, application, module_key) DO NOTHING;
        END LOOP;
    END LOOP;
END $$;

-- super_admin: full permissions on every dispatcher module
DO $$
DECLARE
    v_user   RECORD;
    v_module TEXT;
    v_dispatcher_modules TEXT[] := ARRAY[
        'home','orders','live_orders','map','app_rides','corporate_rides','fleet','profile'
    ];
BEGIN
    FOR v_user IN SELECT id FROM public.admin_users WHERE role = 'super_admin' LOOP
        FOREACH v_module IN ARRAY v_dispatcher_modules LOOP
            INSERT INTO public.user_module_permissions
                (user_id, application, module_key, can_view, can_create, can_edit, can_delete)
            VALUES (v_user.id, 'dispatcher', v_module, true, true, true, true)
            ON CONFLICT (user_id, application, module_key) DO NOTHING;
        END LOOP;
    END LOOP;
END $$;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. dispatcher → dispatcher application; all dispatcher modules (view+create+edit)
INSERT INTO public.user_application_access (user_id, application)
    SELECT id, 'dispatcher' FROM public.admin_users WHERE role = 'dispatcher' ON CONFLICT DO NOTHING;

DO $$
DECLARE
    v_user   RECORD;
    v_module TEXT;
    v_modules TEXT[] := ARRAY['home','orders','live_orders','map','app_rides','corporate_rides','fleet','profile'];
BEGIN
    FOR v_user IN SELECT id FROM public.admin_users WHERE role = 'dispatcher' LOOP
        FOREACH v_module IN ARRAY v_modules LOOP
            INSERT INTO public.user_module_permissions
                (user_id, application, module_key, can_view, can_create, can_edit, can_delete)
            VALUES (v_user.id, 'dispatcher', v_module, true, true, true, false)
            ON CONFLICT (user_id, application, module_key) DO NOTHING;
        END LOOP;
    END LOOP;
END $$;

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. finance → gateway; dashboard + finance + analytics (view only)
INSERT INTO public.user_application_access (user_id, application)
    SELECT id, 'gateway' FROM public.admin_users WHERE role = 'finance' ON CONFLICT DO NOTHING;

DO $$
DECLARE
    v_user RECORD;
BEGIN
    FOR v_user IN SELECT id FROM public.admin_users WHERE role = 'finance' LOOP
        INSERT INTO public.user_module_permissions
            (user_id, application, module_key, can_view, can_create, can_edit, can_delete)
        VALUES
            (v_user.id, 'gateway', 'dashboard', true, false, false, false),
            (v_user.id, 'gateway', 'finance',   true, true,  true,  false),
            (v_user.id, 'gateway', 'analytics', true, false, false, false)
        ON CONFLICT (user_id, application, module_key) DO NOTHING;
    END LOOP;
END $$;

-- ─────────────────────────────────────────────────────────────────────────────
-- 4. support → gateway; dashboard + support + ratings
INSERT INTO public.user_application_access (user_id, application)
    SELECT id, 'gateway' FROM public.admin_users WHERE role = 'support' ON CONFLICT DO NOTHING;

DO $$
DECLARE
    v_user RECORD;
BEGIN
    FOR v_user IN SELECT id FROM public.admin_users WHERE role = 'support' LOOP
        INSERT INTO public.user_module_permissions
            (user_id, application, module_key, can_view, can_create, can_edit, can_delete)
        VALUES
            (v_user.id, 'gateway', 'dashboard', true, false, false, false),
            (v_user.id, 'gateway', 'support',   true, true,  true,  false),
            (v_user.id, 'gateway', 'ratings',   true, false, false, false)
        ON CONFLICT (user_id, application, module_key) DO NOTHING;
    END LOOP;
END $$;

-- ─────────────────────────────────────────────────────────────────────────────
-- 5. fleet_manager → gateway; dashboard + drivers + fleet
INSERT INTO public.user_application_access (user_id, application)
    SELECT id, 'gateway' FROM public.admin_users WHERE role = 'fleet_manager' ON CONFLICT DO NOTHING;

DO $$
DECLARE
    v_user RECORD;
BEGIN
    FOR v_user IN SELECT id FROM public.admin_users WHERE role = 'fleet_manager' LOOP
        INSERT INTO public.user_module_permissions
            (user_id, application, module_key, can_view, can_create, can_edit, can_delete)
        VALUES
            (v_user.id, 'gateway', 'dashboard', true, false, false, false),
            (v_user.id, 'gateway', 'drivers',   true, false, true,  false),
            (v_user.id, 'gateway', 'fleet',     true, false, true,  false)
        ON CONFLICT (user_id, application, module_key) DO NOTHING;
    END LOOP;
END $$;
