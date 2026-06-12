<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $sb    = new SupabaseDB(true);   // service role bypasses RLS on admin_users
        $rows  = $sb->select('admin_users', [
            'select'    => 'id,name,email,password_hash,role',
            'email'     => 'eq.' . $email,
            'is_active' => 'eq.true',
            'limit'     => 1,
        ]);
        $admin = $rows[0] ?? null;

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_role'] = $admin['role'];

            // Update last_login (fire-and-forget)
            $sb->update('admin_users', ['last_login' => date('c')], ['id' => 'eq.' . $admin['id']]);

            header('Location: index.php');
            exit;
        }

        $error = 'Invalid email or password.';
    } else {
        $error = 'Please enter your email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — PowerCabs Admin</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/css/theme.css">
  <link rel="icon" href="assets/img/logo.png" type="image/svg+xml">
</head>
<body>

<div class="login-page">
  <div class="login-card">

    <div class="login-logo">
      <img src="assets/img/logo.png" alt="PowerCabs">
      <h1>Admin Portal</h1>
      <p>Sign in to manage your platform</p>
    </div>

    <div class="glass-card" style="padding:32px">

      <?php if ($error): ?>
      <div class="alert-error">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="mb-3">
          <label class="form-label">Email Address</label>
          <div class="glass-input-icon">
            <i class="bi bi-envelope input-icon"></i>
            <input
              type="email"
              name="email"
              class="glass-input"
              placeholder="admin@powercabs.ie"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              required
              autofocus
            >
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label">Password</label>
          <div class="glass-input-icon" style="position:relative">
            <i class="bi bi-lock input-icon"></i>
            <input
              type="password"
              name="password"
              id="passwordInput"
              class="glass-input"
              placeholder="••••••••"
              required
            >
            <button
              type="button"
              onclick="togglePwd()"
              style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-subtle);cursor:pointer;font-size:16px"
            >
              <i class="bi bi-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-primary-glass w-100" style="justify-content:center;padding:12px">
          <i class="bi bi-box-arrow-in-right"></i>
          Sign In
        </button>
      </form>

      <div class="login-divider" style="margin-top:24px">
        PowerCabs &copy; <?= date('Y') ?> &mdash; Ireland
      </div>

    </div>
  </div>
</div>

<script>
function togglePwd() {
  const input = document.getElementById('passwordInput');
  const icon  = document.getElementById('eyeIcon');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'bi bi-eye';
  }
}
</script>
</body>
</html>
