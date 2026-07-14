# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**gateway** is the PHP admin panel for PowerCabs, an Irish ride-hailing platform. It manages drivers, passengers, rides, finance, analytics, and platform settings by calling the Supabase backend directly via REST API.

---

## Architecture

### Request Flow

All requests enter through `index.php`, which:
1. Guards with `$_SESSION['admin_id']` (set by `login.php`)
2. Sanitises the `?page=` query param
3. Instantiates the matching controller and calls `->index()`
4. Falls back to a view-only path or a "coming soon" placeholder for unimplemented modules

There is no `.htaccess` URL rewriting — all navigation uses `?page=<slug>` query params.

### Database Layer — `SupabaseDB` (`config/database.php`)

This repo does **not** use MySQL or PDO. All data access goes through the `SupabaseDB` class, which calls the Supabase REST API via PHP cURL:

- `select(table, params)` — GET with PostgREST filter params (e.g. `'status' => 'eq.approved'`)
- `selectParallel(queries)` — fires multiple SELECTs concurrently via `curl_multi`
- `update(table, data, filters)` — PATCH
- `insert(table, data)` — POST returning representation
- `delete(table, filters)` — DELETE
- `rpc(fn, params)` / `rpcWithStatus(fn, params)` — Postgres RPC functions
- `createAuthUser()` / `deleteAuthUser()` — Supabase Auth Admin API
- `uploadFile()` — Supabase Storage API

`getDB()` returns a singleton `SupabaseDB(useServiceRole: true)`. The service role key is defined in `config/database.php` as a constant.

PostgREST filter syntax: pass values as `'column' => 'operator.value'` (e.g. `'deleted_at' => 'is.null'`, `'status' => 'eq.approved'`). Multiple values for the same key use an array.

### Caching — `Cache` (`config/cache.php`)

File-based cache stored in `sys_get_temp_dir()/pc_cache/`. Default TTL is 30 seconds. Use `Cache::get($key)`, `Cache::set($key, $value, $ttl)`, `Cache::forget($key)`. The Dashboard and Analytics controllers use this to avoid hammering Supabase on repeated page loads.

### MVC Pattern

- **Controllers** (`app/controllers/`) — thin; handle request dispatch (GET vs POST, AJAX vs full page), set `$currentPage`, `$pageTitle`, `$pageCrumbs`, then `require` the view
- **Models** (`app/models/`) — all Supabase queries live here; return plain PHP arrays
- **Views** (`app/views/<module>/index.php`) — render HTML; receive data as PHP variables from the controller

AJAX actions are handled inline in the controller's `index()` method by checking `$_GET['action']` or `$_POST['action']` before rendering the page.

### Includes

- `includes/header.php` — DOCTYPE, Bootstrap/icons/theme CSS, opens `<div class="admin-shell">`, includes sidebar
- `includes/sidebar.php` — glassmorphism sidebar with role-aware nav; reads `$currentPage` for active state
- `includes/footer.php` — closing tags, Bootstrap JS, Chart.js, `assets/js/app.js`
- `includes/invoice_modal.php` — reusable invoice modal markup included by Finance views

### Config Files

- `config/finance_adjustments.json` — persists manual finance adjustments (written/read by `FinanceController`)
- `config/integrations.json` — persists integration settings

---

## Module Status

Controllers exist for: Dashboard, Drivers, Passengers, Rides, Fleet, Finance, Promotions, Notifications, Analytics, Integrations.

Stub views (no controller yet): dispatcher, corporate, zones, support, ratings, settings, admins.

---

## UI Design Rules — STRICTLY FOLLOW

- Background: `#1A1A2E` with subtle radial gradient
- Glass cards: `background: rgba(255,255,255,0.05)`, `backdrop-filter: blur(12px)`, `border: 1px solid rgba(255,255,255,0.1)`, `border-radius: 16px`
- Primary accent: `#F37A20` (orange) — buttons, active states, highlights, chart colors
- Buttons: `linear-gradient(135deg, #F37A20, #e06010)`, `box-shadow: 0 4px 15px rgba(243,122,32,0.3)`
- Text: `#FFFFFF` primary, `rgba(255,255,255,0.6)` secondary/muted
- Inputs: `background: rgba(255,255,255,0.07)`, orange focus border
- Transitions: `transition: all 0.3s ease`
- Icons: Bootstrap Icons (`bi-*`)
- Charts: Chart.js
- AJAX: vanilla `fetch()` — no jQuery

All custom styles live in `assets/css/theme.css`. Global JS utilities in `assets/js/app.js`.

---

## PHP Code Conventions

- PHP 8 — typed properties, constructor promotion, match expressions
- All output: `htmlspecialchars()` to prevent XSS
- AJAX responses: `header('Content-Type: application/json'); echo json_encode($data); exit;`
- Success: `['success' => true, 'data' => [...]]`; Error: `['success' => false, 'message' => '...']`
- Timezone: `Europe/Dublin` (set globally in `index.php`)
- Soft-delete pattern: set `deleted_at` + `status = 'inactive'`; always filter `'deleted_at' => 'is.null'` in queries

---

## Key Supabase Tables

- `drivers` — profiles, vehicle info, docs; soft-deleted via `deleted_at`
- `driver_extras` — IBAN and additional driver metadata
- `passengers` — soft-deleted via `deleted_at`
- `rides` — status enum includes `completed`, `cancelled`, etc.; `fare_eur`/`final_fare` columns
- `admin_users` — `id, name, email, password_hash, role, is_active, last_login`
- `pricing_config` — `driver_commission_pct`, `is_active`
- Vehicle type stored as jsonb array: 4 seats → `[economy]`, 5+ → `[economy, economy_xl]`

---

## Admin Roles

- `super_admin` — full access
- `dispatcher` — Dispatcher Console + Ride Management
- `finance` — Finance & Payments + Reports
- `support` — Support & Disputes + Ratings
- `fleet_manager` — Fleet + Driver Management

Role is stored in `$_SESSION['admin_role']` after login.

---

## Running Locally

Serve from `C:\MAMP\bin\php\php8.3.1` via MAMP. The panel is PHP-only — no build step, no Composer dependencies. Browse to the configured MAMP vhost root pointing at this directory.

To run a quick PHP syntax check:
```powershell
php -l app/controllers/DriversController.php
```
