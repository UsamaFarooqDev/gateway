<?php
require_once 'app/models/AdminUserModel.php';
require_once 'app/helpers/Permission.php';

class AdminsController
{
    private AdminUserModel $model;

    public function __construct(private SupabaseDB $db)
    {
        $this->model = new AdminUserModel($db);
    }

    public function index(): void
    {
        // Only super_admin or users with admins:view can access this module
        Permission::requireCan('gateway', 'admins', 'view');

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
            header('Content-Type: application/json');
            $this->handlePost();
            return;
        }

        $users     = $this->model->getAll();
        $canCreate = Permission::can('gateway', 'admins', 'create');
        $canEdit   = Permission::can('gateway', 'admins', 'edit');
        $canDelete = Permission::can('gateway', 'admins', 'delete');

        $currentPage = 'admins';
        $pageTitle   = 'Admin Users';
        $pageCrumbs  = ['Admin Users'];

        require_once 'includes/header.php';
        require_once 'app/views/admins/index.php';
        require_once 'includes/footer.php';
    }

    // ── AJAX dispatcher ────────────────────────────────────────────────────────

    private function handlePost(): void
    {
        match ($_POST['action'] ?? '') {
            'create_user'       => $this->createUser(),
            'update_user'       => $this->updateUser(),
            'delete_user'       => $this->deleteUser(),
            'toggle_user'       => $this->toggleUser(),
            'get_user'          => $this->getUser(),
            'reset_password'    => $this->resetPassword(),
            'save_permissions'  => $this->savePermissions(),
            'get_permissions'   => $this->getPermissions(),
            default             => $this->json(['success' => false, 'message' => 'Unknown action.']),
        };
    }

    private function createUser(): void
    {
        Permission::requireCan('gateway', 'admins', 'create');

        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'dispatcher';

        if (!$name || !$email || !$password) {
            $this->json(['success' => false, 'message' => 'Name, email, and password are required.']);
            return;
        }
        if (strlen($password) < 8) {
            $this->json(['success' => false, 'message' => 'Password must be at least 8 characters.']);
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'message' => 'Invalid email address.']);
            return;
        }
        if (!in_array($role, ['super_admin', 'dispatcher', 'finance', 'support', 'fleet_manager'], true)) {
            $this->json(['success' => false, 'message' => 'Invalid role.']);
            return;
        }
        if ($this->model->emailExists($email)) {
            $this->json(['success' => false, 'message' => 'An account with that email already exists.']);
            return;
        }

        $user = $this->model->create([
            'name'      => $name,
            'email'     => $email,
            'password'  => $password,
            'role'      => $role,
            'is_active' => $_POST['is_active'] ?? 'true',
        ]);

        if (!$user) {
            $this->json(['success' => false, 'message' => 'Failed to create user.']);
            return;
        }

        // Set application access and module permissions if provided
        $this->applyPermissionsFromPost($user['id']);

        $this->json(['success' => true, 'message' => 'User created successfully.', 'data' => ['id' => $user['id']]]);
    }

    private function updateUser(): void
    {
        Permission::requireCan('gateway', 'admins', 'edit');

        $id = $_POST['user_id'] ?? '';
        if (!$id) { $this->json(['success' => false, 'message' => 'Missing user ID.']); return; }

        // Prevent editing own role/status inadvertently
        $data = [
            'name'      => $_POST['name']      ?? '',
            'email'     => $_POST['email']     ?? '',
            'role'      => $_POST['role']      ?? '',
            'is_active' => $_POST['is_active'] ?? 'true',
        ];

        if (!empty($data['email']) && $this->model->emailExists($data['email'], $id)) {
            $this->json(['success' => false, 'message' => 'That email is already used by another account.']);
            return;
        }

        $ok = $this->model->update($id, $data);

        // Optional password change
        $newPw = trim($_POST['password'] ?? '');
        if ($newPw !== '') {
            if (strlen($newPw) < 8) {
                $this->json(['success' => false, 'message' => 'Password must be at least 8 characters.']);
                return;
            }
            $this->model->setPassword($id, $newPw);
        }

        $this->applyPermissionsFromPost($id);

        $this->json(['success' => $ok, 'message' => $ok ? 'User updated.' : 'Update failed.']);
    }

    private function deleteUser(): void
    {
        Permission::requireCan('gateway', 'admins', 'delete');

        $id = $_POST['user_id'] ?? '';
        if (!$id) { $this->json(['success' => false, 'message' => 'Missing user ID.']); return; }
        if ($id === ($_SESSION['admin_id'] ?? '')) {
            $this->json(['success' => false, 'message' => 'You cannot delete your own account.']);
            return;
        }
        $ok = $this->model->delete($id);
        $this->json(['success' => $ok, 'message' => $ok ? 'User deleted.' : 'Delete failed.']);
    }

    private function toggleUser(): void
    {
        Permission::requireCan('gateway', 'admins', 'edit');

        $id     = $_POST['user_id'] ?? '';
        $active = ($_POST['is_active'] ?? 'true') === 'true';
        if (!$id) { $this->json(['success' => false, 'message' => 'Missing user ID.']); return; }
        if ($id === ($_SESSION['admin_id'] ?? '') && !$active) {
            $this->json(['success' => false, 'message' => 'You cannot deactivate your own account.']);
            return;
        }
        $ok = $this->model->toggleActive($id, $active);
        $this->json(['success' => $ok, 'message' => $ok ? 'Status updated.' : 'Update failed.']);
    }

    private function getUser(): void
    {
        $id  = $_POST['user_id'] ?? '';
        $row = $this->model->getById($id);
        if (!$row) { $this->json(['success' => false, 'message' => 'User not found.']); return; }
        $perms = $this->model->getPermissions($id);
        $this->json(['success' => true, 'data' => array_merge($row, $perms)]);
    }

    private function resetPassword(): void
    {
        Permission::requireCan('gateway', 'admins', 'edit');

        $id  = $_POST['user_id']  ?? '';
        $pw  = $_POST['password'] ?? '';
        if (!$id || !$pw) { $this->json(['success' => false, 'message' => 'Missing data.']); return; }
        if (strlen($pw) < 8) {
            $this->json(['success' => false, 'message' => 'Password must be at least 8 characters.']);
            return;
        }
        $ok = $this->model->setPassword($id, $pw);
        $this->json(['success' => $ok, 'message' => $ok ? 'Password reset.' : 'Reset failed.']);
    }

    private function getPermissions(): void
    {
        $id    = $_POST['user_id'] ?? '';
        $perms = $id ? $this->model->getPermissions($id) : ['apps' => [], 'modules' => []];
        $this->json(['success' => true, 'data' => $perms]);
    }

    private function savePermissions(): void
    {
        Permission::requireCan('gateway', 'admins', 'edit');

        $id = $_POST['user_id'] ?? '';
        if (!$id) { $this->json(['success' => false, 'message' => 'Missing user ID.']); return; }

        $this->applyPermissionsFromPost($id);
        $this->json(['success' => true, 'message' => 'Permissions saved.']);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /** Parse apps[] and module perms from POST and persist them. */
    private function applyPermissionsFromPost(string $userId): void
    {
        // apps[]  = ['gateway', 'dispatcher']
        $apps = array_filter((array)($_POST['apps'] ?? []), fn($a) => in_array($a, ['gateway', 'dispatcher'], true));
        $this->model->setAppAccess($userId, $apps);

        // module_perms[gateway][drivers][view] = '1'
        $rawPerms = $_POST['module_perms'] ?? [];
        $perms    = [];
        foreach (['gateway', 'dispatcher'] as $app) {
            foreach ((array)($rawPerms[$app] ?? []) as $module => $actions) {
                $perms[] = [
                    'app'    => $app,
                    'module' => $module,
                    'view'   => !empty($actions['view']),
                    'create' => !empty($actions['create']),
                    'edit'   => !empty($actions['edit']),
                    'delete' => !empty($actions['delete']),
                ];
            }
        }
        $this->model->setModulePermissions($userId, $perms);
    }

    private function json(array $data): void
    {
        echo json_encode($data);
        exit;
    }
}
