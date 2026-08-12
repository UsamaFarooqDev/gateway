<?php

class AdminUserModel
{
    public function __construct(private SupabaseDB $db) {}

    // ── Reads ──────────────────────────────────────────────────────────────────

    public function getAll(): array
    {
        $users = $this->db->select('admin_users', [
            'select' => 'id,name,email,role,is_active,last_login,created_at',
            'order'  => 'created_at.asc',
        ]);

        // Attach app access in one parallel call
        $accessRows = $this->db->select('user_application_access', ['select' => 'user_id,application']);
        $byUser = [];
        foreach ($accessRows as $r) {
            $byUser[$r['user_id']][] = $r['application'];
        }

        foreach ($users as &$u) {
            $u['apps'] = $byUser[$u['id']] ?? [];
        }

        return $users;
    }

    public function getById(string $id): ?array
    {
        $rows = $this->db->select('admin_users', [
            'select' => 'id,name,email,role,is_active,last_login,created_at',
            'id'     => 'eq.' . $id,
            'limit'  => 1,
        ]);
        return $rows[0] ?? null;
    }

    public function getPermissions(string $userId): array
    {
        $apps = $this->db->select('user_application_access', [
            'select'  => 'application',
            'user_id' => 'eq.' . $userId,
        ]);
        $mods = $this->db->select('user_module_permissions', [
            'select'  => 'application,module_key,can_view,can_create,can_edit,can_delete',
            'user_id' => 'eq.' . $userId,
        ]);
        return [
            'apps'    => array_column($apps, 'application'),
            'modules' => $mods,
        ];
    }

    // ── Writes ─────────────────────────────────────────────────────────────────

    public function create(array $data): ?array
    {
        $row = [
            'name'          => trim($data['name'] ?? ''),
            'email'         => strtolower(trim($data['email'] ?? '')),
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role'          => $data['role'] ?? 'dispatcher',
            'is_active'     => ($data['is_active'] ?? 'true') === 'true',
        ];
        return $this->db->insert('admin_users', $row);
    }

    public function update(string $id, array $data): bool
    {
        $row = [];
        if (!empty($data['name']))  $row['name']  = trim($data['name']);
        if (!empty($data['email'])) $row['email'] = strtolower(trim($data['email']));
        if (!empty($data['role']))  $row['role']  = $data['role'];
        if (isset($data['is_active'])) {
            $row['is_active'] = ($data['is_active'] === 'true' || $data['is_active'] === true);
        }
        if (empty($row)) return true;
        return $this->db->update('admin_users', $row, ['id' => 'eq.' . $id]);
    }

    public function setPassword(string $id, string $newPassword): bool
    {
        return $this->db->update(
            'admin_users',
            ['password_hash' => password_hash($newPassword, PASSWORD_BCRYPT)],
            ['id' => 'eq.' . $id]
        );
    }

    public function toggleActive(string $id, bool $active): bool
    {
        return $this->db->update('admin_users', ['is_active' => $active], ['id' => 'eq.' . $id]);
    }

    public function delete(string $id): bool
    {
        // Cascade delete handles user_application_access + user_module_permissions
        return $this->db->delete('admin_users', ['id' => 'eq.' . $id]);
    }

    // ── Permission management ─────────────────────────────────────────────────

    /** Replace all application-access rows for a user. */
    public function setAppAccess(string $userId, array $apps): void
    {
        // Remove all then re-insert
        $this->db->delete('user_application_access', ['user_id' => 'eq.' . $userId]);
        foreach (array_unique($apps) as $app) {
            if (!in_array($app, ['gateway', 'dispatcher'], true)) continue;
            $this->db->insert('user_application_access', ['user_id' => $userId, 'application' => $app]);
        }
    }

    /**
     * Upsert module permissions.
     * $perms = [
     *   ['app'=>'gateway','module'=>'drivers','view'=>true,'create'=>false,...],
     *   ...
     * ]
     */
    public function setModulePermissions(string $userId, array $perms): void
    {
        // Delete all existing module permissions for this user and replace
        $this->db->delete('user_module_permissions', ['user_id' => 'eq.' . $userId]);

        foreach ($perms as $p) {
            $app    = $p['app']    ?? '';
            $module = $p['module'] ?? '';
            if (!$app || !$module) continue;
            if (!in_array($app, ['gateway', 'dispatcher'], true)) continue;

            $this->db->insert('user_module_permissions', [
                'user_id'    => $userId,
                'application'=> $app,
                'module_key' => $module,
                'can_view'   => (bool)($p['view']   ?? false),
                'can_create' => (bool)($p['create'] ?? false),
                'can_edit'   => (bool)($p['edit']   ?? false),
                'can_delete' => (bool)($p['delete'] ?? false),
            ]);
        }
    }

    public function emailExists(string $email, string $excludeId = ''): bool
    {
        $params = ['select' => 'id', 'email' => 'eq.' . strtolower($email), 'limit' => 1];
        if ($excludeId) $params['id'] = 'neq.' . $excludeId;
        $rows = $this->db->select('admin_users', $params);
        return !empty($rows);
    }
}
