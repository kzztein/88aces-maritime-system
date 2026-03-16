<?php
require_once '../config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$showPrivacy = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If they accepted privacy notice after login
    if (isset($_POST['privacy_accepted']) && isset($_SESSION['pending_login'])) {
        $_SESSION['admin_id']   = $_SESSION['pending_login']['id'];
        $_SESSION['admin_data'] = $_SESSION['pending_login']['data'];
        unset($_SESSION['pending_login']);
        header('Location: dashboard.php');
        exit;
    }

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
            session_regenerate_id(true);
            // Store in pending — show privacy notice first
            $_SESSION['pending_login'] = [
                'id'   => $admin['id'],
                'data' => $admin,
            ];
            // Update last login
            $db->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
            auditLog('LOGIN');
            $showPrivacy = true;
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
  .logo-area { text-align: center; margin-bottom: 32px; }
  .logo-icon {
    width: 72px; height: 72px;
    background: linear-gradient(135deg, #1a4a8a, #0f2c5c);
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
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
    transition: border-color .2s; outline: none;
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

  /* Privacy Modal */
  .privacy-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.6); z-index: 999;
    align-items: center; justify-content: center;
    padding: 20px;
  }
  .privacy-overlay.show { display: flex; }
  .privacy-modal {
    background: #fff; border-radius: 16px;
    padding: 36px 32px; max-width: 520px; width: 100%;
    box-shadow: 0 24px 64px rgba(0,0,0,.4);
    max-height: 90vh; overflow-y: auto;
  }
  .privacy-modal h2 { font-size: 18px; font-weight: 700; color: #0f2c5c; margin-bottom: 4px; }
  .privacy-modal .subtitle { font-size: 13px; color: #6b7280; margin-bottom: 20px; }
  .privacy-body {
    background: #f9fafb; border-radius: 10px;
    padding: 20px; margin-bottom: 24px;
    font-size: 13px; color: #374151; line-height: 1.7;
    border: 1px solid #e5e7eb;
  }
  .privacy-body h4 { font-size: 13px; font-weight: 700; color: #0f2c5c; margin: 14px 0 6px; }
  .privacy-body h4:first-child { margin-top: 0; }
  .privacy-body ul { padding-left: 18px; }
  .privacy-body ul li { margin-bottom: 4px; }
  .privacy-actions { display: flex; gap: 10px; }
  .btn-accept {
    flex: 1; padding: 12px;
    background: linear-gradient(135deg, #1a4a8a, #0f2c5c);
    color: #fff; border: none; border-radius: 8px;
    font-size: 14px; font-weight: 600; cursor: pointer;
    font-family: inherit;
  }
  .btn-decline {
    padding: 12px 20px;
    background: transparent; border: 1.5px solid #e5e7eb;
    border-radius: 8px; font-size: 14px; font-weight: 500;
    cursor: pointer; color: #6b7280; font-family: inherit;
  }
  .privacy-icon { font-size: 36px; margin-bottom: 12px; }
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

  <form method="POST" id="loginForm" novalidate>
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

<!-- Privacy Notice Modal (shown after successful login) -->
<div class="privacy-overlay <?= $showPrivacy ? 'show' : '' ?>" id="privacyModal">
  <div class="privacy-modal">
    <div class="privacy-icon">🔒</div>
    <h2>Privacy & Confidentiality Notice</h2>
    <p class="subtitle">Please read and acknowledge before proceeding</p>
    <div class="privacy-body">
      <h4>📋 System Access & Responsibilities</h4>
      <p>By accessing the 88 Aces Maritime Training Management System, you acknowledge that:</p>
      <ul>
        <li>This system contains confidential personal information of seafarers and trainees.</li>
        <li>All data accessed must be used strictly for official training management purposes only.</li>
        <li>Unauthorized disclosure, copying, or misuse of data is strictly prohibited.</li>
      </ul>
      <h4>🛡️ Data Protection</h4>
      <p>All personal information collected and stored in this system is protected under the <strong>Data Privacy Act of 2012 (Republic Act 10173)</strong>. As a system user, you are bound to:</p>
      <ul>
        <li>Handle all personal data with strict confidentiality.</li>
        <li>Access only information necessary for your official duties.</li>
        <li>Report any suspected data breach or unauthorized access immediately.</li>
      </ul>
      <h4>📝 Audit & Monitoring</h4>
      <p>All system activities are logged and monitored. Your actions within this system are subject to review by authorized management personnel.</p>
      <h4>⚠️ Accountability</h4>
      <p>Violation of these policies may result in disciplinary action and/or legal proceedings under applicable Philippine laws.</p>
    </div>
    <div class="privacy-actions">
      <form method="POST">
        <input type="hidden" name="privacy_accepted" value="1">
        <button type="submit" class="btn-accept" style="width:100%">✅ I Understand & Accept — Proceed to Dashboard</button>
      </form>
    </div>
    <div style="margin-top:10px">
      <a href="login.php" class="btn-decline" style="display:block;text-align:center;text-decoration:none">✕ Cancel & Go Back</a>
    </div>
  </div>
</div>

</body>
</html>
