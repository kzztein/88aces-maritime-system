<?php
require_once '../Config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: Dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Please enter both username and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM admin_users WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            // Regenerate session ID on login
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_data'] = $admin;

            // Update last login
            $db->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
            auditLog('LOGIN');

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — 88 Aces Maritime</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #0f2c5c 0%, #1a4a8a 50%, #0d3060 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }
  .login-card {
    background: #fff;
    border-radius: 16px;
    padding: 48px 40px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 24px 64px rgba(0,0,0,0.3);
  }
  .logo-area {
    text-align: center;
    margin-bottom: 32px;
  }
  .logo-icon {
    width: 72px; height: 72px;
    background: linear-gradient(135deg, #1a4a8a, #0f2c5c);
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
  }
  .logo-icon svg { width: 36px; height: 36px; fill: white; }
  .logo-area h1 { font-size: 20px; font-weight: 700; color: #0f2c5c; }
  .logo-area p  { font-size: 13px; color: #6b7280; margin-top: 4px; }
  .form-group { margin-bottom: 20px; }
  .form-group label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
  .form-group input {
    width: 100%; padding: 12px 14px; border: 1.5px solid #e5e7eb;
    border-radius: 8px; font-size: 14px; font-family: inherit;
    transition: border-color .2s;
    outline: none;
  }
  .form-group input:focus { border-color: #1a4a8a; box-shadow: 0 0 0 3px rgba(26,74,138,.1); }
  .btn-login {
    width: 100%; padding: 13px;
    background: linear-gradient(135deg, #1a4a8a, #0f2c5c);
    color: #fff; border: none; border-radius: 8px;
    font-size: 15px; font-weight: 600; cursor: pointer;
    font-family: inherit; transition: opacity .2s;
  }
  .btn-login:hover { opacity: .9; }
  .error-msg {
    background: #fef2f2; border: 1px solid #fecaca;
    border-radius: 8px; padding: 12px 14px;
    color: #dc2626; font-size: 13px; margin-bottom: 20px;
  }
  .footer-note { text-align: center; margin-top: 24px; font-size: 12px; color: #9ca3af; }
</style>
</head>
<body>
<div class="login-card">
  <div class="logo-area">
    <div class="logo-icon">
      <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
    </div>
    <h1>88 Aces Maritime</h1>
    <p>Training Management System</p>
  </div>

  <?php if ($error): ?>
    <div class="error-msg">⚠ <?= sanitize($error) ?></div>
  <?php endif; ?>

  <form method="POST" novalidate>
    <div class="form-group">
      <label for="username">Username or Email</label>
      <input type="text" id="username" name="username" placeholder="Enter your username"
             value="<?= sanitize($_POST['username'] ?? '') ?>" required autofocus>
    </div>
    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" placeholder="Enter your password" required>
    </div>
    <button type="submit" class="btn-login">Sign In</button>
  </form>
  <p class="footer-note">Admin access only · 88 Aces Maritime Services Inc.</p>
</div>
</body>
</html>
