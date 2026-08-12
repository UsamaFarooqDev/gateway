<?php

/**
 * Permission — centralised application/module access control.
 *
 * Usage:
 *   Permission::init();           // call once after session_start in index.php
 *   Permission::hasApp('gateway') // bool — does the current user have this app?
 *   Permission::can('gateway', 'drivers', 'edit') // bool
 *   Permission::requireApp('gateway')      // exits with 403 / redirect if denied
 *   Permission::requireCan('gateway', 'drivers', 'create') // same, per-action
 *   Permission::isSuperAdmin()    // bool
 *   Permission::toJs()            // JSON string for inline <script> on every page
 */
class Permission
{
    // ── Module catalogues ──────────────────────────────────────────────────────

    public const GATEWAY_MODULES = [
        'dashboard'     => 'Dashboard',
        'drivers'       => 'Driver Management',
        'passengers'    => 'Passenger Management',
        'rides'         => 'Ride Management',
        'corporate'     => 'Corporate Accounts',
        'fleet'         => 'Fleet Management',
        'finance'       => 'Finance & Payments',
        'promotions'    => 'Promotions & Pricing',
        'notifications' => 'Notifications & Alerts',
        'analytics'     => 'Analytics & Reports',
        'integrations'  => 'Integrations',
        'zones'         => 'Zones & Coverage',
        'support'       => 'Support & Disputes',
        'ratings'       => 'Ratings & Reviews',
        'settings'      => 'Settings & Config',
        'admins'        => 'Admin Users',
        'dispatcher'    => 'Dispatcher Console',
    ];

    public const DISPATCHER_MODULES = [
        'home'            => 'Dashboard',
        'orders'          => 'Create Order',
        'live_orders'     => 'Live Orders',
        'map'             => 'Live Map',
        'app_rides'       => 'App Rides',
        'corporate_rides' => 'Corporate Rides',
        'fleet'           => 'Fleet Registry',
        'profile'         => 'Profile & Settings',
    ];

    // ── Internal state ─────────────────────────────────────────────────────────

    private static bool   $initialised = false;
    private static ?array $cache       = null;   // ['apps'=>[], 'modules'=>[]]

    // ── Bootstrap ──────────────────────────────────────────────────────────────

    public static function init(): void
    {
        if (self::$initialised) return;
        self::$initialised = true;
        // Cache is lazy-loaded on first real check so init stays fast.
    }

    // ── Public API ─────────────────────────────────────────────────────────────

    public static function isSuperAdmin(): bool
    {
        return ($_SESSION['admin_role'] ?? '') === 'super_admin';
    }

    public static function hasApp(string $app): bool
    {
        if (self::isSuperAdmin()) return true;
        self::load();
        return in_array($app, self::$cache['apps'] ?? [], true);
    }

    public static function can(string $app, string $module, string $action = 'view'): bool
    {
        if (self::isSuperAdmin()) return true;
        self::load();
        $key  = $app . ':' . $module;
        $perm = self::$cache['modules'][$key] ?? null;
        if (!$perm) return false;
        return match ($action) {
            'view'   => (bool)($perm['can_view']   ?? false),
            'create' => (bool)($perm['can_create'] ?? false),
            'edit'   => (bool)($perm['can_edit']   ?? false),
            'delete' => (bool)($perm['can_delete'] ?? false),
            default  => false,
        };
    }

    /** Terminate with 403 / redirect if the user lacks application-level access. */
    public static function requireApp(string $app): void
    {
        if (!self::hasApp($app)) {
            self::deny("You do not have access to the {$app} application.");
        }
    }

    /** Terminate with 403 / redirect if the user lacks a specific module action. */
    public static function requireCan(string $app, string $module, string $action = 'view'): void
    {
        if (!self::can($app, $module, $action)) {
            self::deny("You do not have permission to {$action} {$module}.");
        }
    }

    /** All permissions as a PHP array (for building the admin permissions matrix). */
    public static function toArray(): array
    {
        if (self::isSuperAdmin()) {
            return ['super_admin' => true, 'apps' => ['gateway', 'dispatcher'], 'modules' => []];
        }
        self::load();
        return self::$cache ?? ['apps' => [], 'modules' => []];
    }

    /** Compact JSON for inline <script> — lets JS check permissions client-side. */
    public static function toJs(): string
    {
        if (self::isSuperAdmin()) {
            return json_encode(['superAdmin' => true]);
        }
        self::load();
        $out = ['superAdmin' => false, 'apps' => self::$cache['apps'] ?? []];
        // Flatten module perms for JS: { "gateway:drivers": {v,c,e,d} }
        foreach (self::$cache['modules'] ?? [] as $key => $row) {
            $out['modules'][$key] = [
                'v' => (bool)($row['can_view']   ?? false),
                'c' => (bool)($row['can_create'] ?? false),
                'e' => (bool)($row['can_edit']   ?? false),
                'd' => (bool)($row['can_delete'] ?? false),
            ];
        }
        return json_encode($out);
    }

    // ── Internals ──────────────────────────────────────────────────────────────

    private static function load(): void
    {
        if (self::$cache !== null) return;

        $uid = $_SESSION['admin_id'] ?? null;
        if (!$uid) {
            self::$cache = ['apps' => [], 'modules' => []];
            return;
        }

        try {
            $db = getDB();

            // Application-level access
            $appRows = $db->select('user_application_access', [
                'select'  => 'application',
                'user_id' => 'eq.' . $uid,
            ]);
            $apps = array_column($appRows, 'application');

            // Module-level permissions
            $modRows = $db->select('user_module_permissions', [
                'select'  => 'application,module_key,can_view,can_create,can_edit,can_delete',
                'user_id' => 'eq.' . $uid,
            ]);
            $modules = [];
            foreach ($modRows as $row) {
                $key           = $row['application'] . ':' . $row['module_key'];
                $modules[$key] = $row;
            }

            self::$cache = ['apps' => $apps, 'modules' => $modules];
        } catch (Throwable) {
            // Tables may not exist yet (migration not run). Fail open for super_admin,
            // closed for everyone else (already handled by isSuperAdmin() above).
            self::$cache = ['apps' => [], 'modules' => []];
        }
    }

    private static function deny(string $msg): never
    {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
               || !empty($_GET['action'])
               || !empty($_POST['action']);

        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }

        http_response_code(403);
        header('Location: index.php?page=dashboard&err=forbidden');
        exit;
    }
}
