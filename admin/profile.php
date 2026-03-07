<?php
require_once '../config.php';
requireLogin();

$db    = getDB();
$admin = currentAdmin();
$msg   = '';
$error = '';
$tab   = $_GET['tab'] ?? 'profile';

// ── Handle profile photo upload ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    // ── Update profile info
    if ($postAction === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');

        if (!$fullName || !$email || !$username) {
            $error = 'All fields are required.';
        } else {
            // Check email/username not taken by another user
            $check = $db->prepare("SELECT id FROM admin_users WHERE (email=? OR username=?) AND id != ?");
            $check->execute([$email, $username, $admin['id']]);
            if ($check->fetch()) {
                $error = 'Email or username is already taken by another account.';
            } else {
                // Handle photo upload
                $photoFilename = $admin['profile_photo'] ?? null;
                if (!empty($_FILES['profile_photo']['name'])) {
                    $uploadDir = '../uploads/profiles/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','gif','webp'];
                    if (!in_array($ext, $allowed)) {
                        $error = 'Only JPG, PNG, GIF, WEBP images allowed.';
                    } elseif ($_FILES['profile_photo']['size'] > 2 * 1024 * 1024) {
                        $error = 'Image must be under 2MB.';
                    } else {
                        $photoFilename = 'profile_' . $admin['id'] . '_' . time() . '.' . $ext;
                        move_uploaded_file($_FILES['profile_photo']['tmp_name'], $uploadDir . $photoFilename);
                    }
                }

                if (!$error) {
                    $db->prepare("UPDATE admin_users SET full_name=?, email=?, username=?, profile_photo=? WHERE id=?")
                       ->execute([$fullName, $email, $username, $photoFilename, $admin['id']]);

                    // Refresh session
                    $stmt = $db->prepare("SELECT * FROM admin_users WHERE id=?");
                    $stmt->execute([$admin['id']]);
                    $_SESSION['admin_data'] = $stmt->fetch();
                    $admin = $_SESSION['admin_data'];

                    auditLog('UPDATE_PROFILE', 'admin', $admin['id']);
                    $msg = 'Profile updated successfully!';
                }
            }
        }
    }

    // ── Send email verification code
    if ($postAction === 'send_code') {
        $code = rand(100000, 999999);
        $_SESSION['pw_change_code']    = $code;
        $_SESSION['pw_change_expires'] = time() + 600; // 10 minutes

        $sent = sendVerificationEmail($admin['email'], $admin['full_name'], $code);
        if ($sent) {
            $msg  = 'A 6-digit verification code has been sent to ' . $admin['email'];
            $tab  = 'password';
        } else {
            $error = 'Failed to send email. Please check your mail settings.';
            $tab   = 'password';
        }
    }

    // ── Verify code and change password
    if ($postAction === 'change_password') {
        $code        = trim($_POST['verification_code'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        $tab         = 'password';

        if (!$code || !$newPassword || !$confirmPass) {
            $error = 'All fields are required.';
        } elseif (!isset($_SESSION['pw_change_code'])) {
            $error = 'Please request a verification code first.';
        } elseif (time() > ($_SESSION['pw_change_expires'] ?? 0)) {
            $error = 'Verification code has expired. Please request a new one.';
            unset($_SESSION['pw_change_code'], $_SESSION['pw_change_expires']);
        } elseif ((int)$code !== (int)$_SESSION['pw_change_code']) {
            $error = 'Invalid verification code.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPass) {
            $error = 'Passwords do not match.';
        } else {
            $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
            $db->prepare("UPDATE admin_users SET password=? WHERE id=?")->execute([$hashed, $admin['id']]);
            unset($_SESSION['pw_change_code'], $_SESSION['pw_change_expires']);
            auditLog('CHANGE_PASSWORD', 'admin', $admin['id']);
            $msg = 'Password changed successfully!';
            $tab = 'password';
        }
    }
}

// ── Send email function ──────────────────────────────────────
function sendVerificationEmail(string $toEmail, string $toName, int $code): bool {
    require_once '../vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'kylezsantoz2004@gmail.com';
        $mail->Password   = 'okzm wtak mcaw yipf';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('kylezsantoz2004@gmail.com', '88 Aces Maritime System');
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = '88 Aces — Password Change Verification Code';
        $mail->Body    = '
        <div style="font-family:Arial,sans-serif;max-width:500px;margin:0 auto;padding:30px;background:#f9fafb;border-radius:12px">
            <div style="text-align:center;margin-bottom:24px">
                <h2 style="color:#0f2c5c;margin:0">⚓ 88 Aces Maritime</h2>
                <p style="color:#6b7280;font-size:13px">Training Management System</p>
            </div>
            <div style="background:#fff;border-radius:10px;padding:24px;text-align:center">
                <p style="color:#374151;font-size:15px">Hello <strong>' . htmlspecialchars($toName) . '</strong>,</p>
                <p style="color:#374151;font-size:14px">Your password change verification code is:</p>
                <div style="font-size:42px;font-weight:900;letter-spacing:12px;color:#1a4a8a;padding:16px;background:#e8f0fb;border-radius:8px;margin:16px 0">' . $code . '</div>
                <p style="color:#9ca3af;font-size:12px">This code expires in <strong>10 minutes</strong>.</p>
                <p style="color:#dc2626;font-size:12px">If you did not request this, ignore this email.</p>
            </div>
            <p style="text-align:center;color:#9ca3af;font-size:11px;margin-top:20px">88 Aces Maritime Services Inc. · Pasay City, Philippines</p>
        </div>';
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ── Get initials for avatar ──────────────────────────────────
function getInitials(string $name): string {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return $initials;
}

$admin = currentAdmin();
$profilePhoto = !empty($admin['profile_photo']) ? '../uploads/profiles/' . $admin['profile_photo'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Profile — 88 Aces Maritime</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/admin.css">
<style>
  .profile-header {
    background: linear-gradient(135deg, #0f2c5c 0%, #1a4a8a 100%);
    border-radius: 16px;
    padding: 32px;
    display: flex;
    align-items: center;
    gap: 24px;
    margin-bottom: 24px;
    color: #fff;
  }
  .avatar-wrap { position: relative; flex-shrink: 0; }
  .avatar {
    width: 90px; height: 90px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    border: 3px solid rgba(255,255,255,0.5);
    display: flex; align-items: center; justify-content: center;
    font-size: 32px; font-weight: 700; color: #fff;
    overflow: hidden;
  }
  .avatar img { width: 100%; height: 100%; object-fit: cover; }
  .avatar-edit {
    position: absolute; bottom: 0; right: 0;
    width: 28px; height: 28px;
    background: #fff; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 14px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
  }
  .profile-info h2 { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
  .profile-info p  { font-size: 14px; opacity: .8; margin-bottom: 4px; }
  .role-badge {
    display: inline-block;
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 20px; padding: 3px 12px;
    font-size: 12px; font-weight: 600;
    text-transform: uppercase; letter-spacing: .05em;
  }
  .profile-tabs {
    display: flex; gap: 4px;
    background: #f3f4f6; border-radius: 10px;
    padding: 4px; margin-bottom: 24px;
    width: fit-content;
  }
  .tab-btn {
    padding: 9px 20px; border-radius: 8px;
    font-size: 14px; font-weight: 500;
    cursor: pointer; border: none;
    background: transparent; color: #6b7280;
    font-family: inherit; transition: all .2s;
  }
  .tab-btn.active {
    background: #fff; color: #1a4a8a;
    font-weight: 600;
    box-shadow: 0 1px 4px rgba(0,0,0,.1);
  }
  .strength-bar {
    height: 4px; border-radius: 2px;
    background: #e5e7eb; margin-top: 6px;
    overflow: hidden;
  }
  .strength-fill {
    height: 100%; border-radius: 2px;
    transition: width .3s, background .3s;
    width: 0;
  }
  .strength-text { font-size: 11px; margin-top: 4px; }
  .send-code-btn {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 20px; border-radius: 8px;
    background: #1a4a8a; color: #fff;
    border: none; cursor: pointer; font-family: inherit;
    font-size: 14px; font-weight: 600;
    transition: opacity .2s;
  }
  .send-code-btn:hover { opacity: .9; }
  .send-code-btn:disabled { opacity: .5; cursor: not-allowed; }
  .info-box {
    background: #dbeafe; border: 1px solid #93c5fd;
    border-radius: 8px; padding: 12px 16px;
    font-size: 13px; color: #1e40af;
    margin-bottom: 20px;
  }
  .last-login { font-size: 12px; opacity: .7; margin-top: 6px; }
  .hidden { display: none !important; }
</style>
</head>
<body>
<?php include 'partials/sidebar.php'; ?>
<main class="main-content">
  <?php include 'partials/topbar.php'; ?>
  <div class="page-body">

    <?php if ($msg): ?>
      <div class="alert alert-success">✅ <?= sanitize($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger">⚠ <?= sanitize($error) ?></div>
    <?php endif; ?>

    <!-- Profile Header -->
    <div class="profile-header">
      <div class="avatar-wrap">
        <div class="avatar">
          <?php if ($profilePhoto && file_exists($profilePhoto)): ?>
            <img src="<?= $profilePhoto ?>?v=<?= time() ?>" alt="Profile">
          <?php else: ?>
            <?= getInitials($admin['full_name'] ?? 'Admin') ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="profile-info">
        <h2><?= sanitize($admin['full_name'] ?? '') ?></h2>
        <p>📧 <?= sanitize($admin['email'] ?? '') ?></p>
        <p>👤 @<?= sanitize($admin['username'] ?? '') ?></p>
        <span class="role-badge">⭐ <?= sanitize($admin['role'] ?? 'admin') ?></span>
        <?php if (!empty($admin['last_login'])): ?>
          <p class="last-login">🕐 Last login: <?= date('F d, Y h:i A', strtotime($admin['last_login'])) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tabs -->
    <div class="profile-tabs">
      <button class="tab-btn <?= $tab === 'profile' ? 'active' : '' ?>" onclick="switchTab('profile')">👤 Edit Profile</button>
      <button class="tab-btn <?= $tab === 'password' ? 'active' : '' ?>" onclick="switchTab('password')">🔒 Change Password</button>
    </div>

    <!-- ── EDIT PROFILE TAB ── -->
    <div id="tab-profile" class="<?= $tab !== 'profile' ? 'hidden' : '' ?>">
      <div class="card">
        <div class="card-header"><h3>Edit Profile Information</h3></div>
        <div class="card-body">
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_profile">
            <div style="text-align:center;margin-bottom:28px">
              <div class="avatar" style="width:100px;height:100px;font-size:36px;margin:0 auto 12px;border:3px solid #e5e7eb;background:#e8f0fb;color:#1a4a8a">
                <?php if ($profilePhoto && file_exists($profilePhoto)): ?>
                  <img src="<?= $profilePhoto ?>?v=<?= time() ?>" alt="Profile" id="previewImg">
                <?php else: ?>
                  <span id="previewInitials"><?= getInitials($admin['full_name'] ?? 'A') ?></span>
                  <img id="previewImg" style="display:none;width:100%;height:100%;object-fit:cover">
                <?php endif; ?>
              </div>
              <label style="cursor:pointer;display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#e8f0fb;color:#1a4a8a;border-radius:8px;font-size:13px;font-weight:600">
                📷 Change Photo
                <input type="file" name="profile_photo" accept="image/*" style="display:none" onchange="previewPhoto(this)">
              </label>
              <p style="font-size:11px;color:#9ca3af;margin-top:6px">JPG, PNG, GIF, WEBP — max 2MB</p>
            </div>
            <div class="form-grid">
              <div class="form-group full">
                <label>Full Name *</label>
                <input type="text" name="full_name" value="<?= sanitize($admin['full_name'] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email" value="<?= sanitize($admin['email'] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" value="<?= sanitize($admin['username'] ?? '') ?>" required>
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary">✓ Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ── CHANGE PASSWORD TAB ── -->
    <div id="tab-password" class="<?= $tab !== 'password' ? 'hidden' : '' ?>">
      <div class="card">
        <div class="card-header"><h3>Change Password</h3></div>
        <div class="card-body">
          <div class="info-box">
            📧 A 6-digit verification code will be sent to <strong><?= sanitize($admin['email']) ?></strong> before you can change your password.
          </div>
          <div style="margin-bottom:24px">
            <p style="font-size:14px;font-weight:600;color:#374151;margin-bottom:10px">Step 1 — Request Verification Code</p>
            <form method="POST">
              <input type="hidden" name="action" value="send_code">
              <button type="submit" class="send-code-btn" id="sendCodeBtn">
                📨 Send Verification Code to My Email
              </button>
            </form>
          </div>
          <hr style="border:none;border-top:1px solid #e5e7eb;margin:20px 0">
          <p style="font-size:14px;font-weight:600;color:#374151;margin-bottom:16px">Step 2 — Enter Code & New Password</p>
          <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="form-grid">
              <div class="form-group full">
                <label>Verification Code</label>
                <input type="text" name="verification_code" maxlength="6"
                       placeholder="Enter 6-digit code from your email"
                       style="font-size:20px;letter-spacing:8px;text-align:center;font-weight:700"
                       value="<?= sanitize($_POST['verification_code'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" id="newPassword"
                       placeholder="Minimum 8 characters"
                       oninput="checkStrength(this.value)">
                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                <div class="strength-text" id="strengthText"></div>
              </div>
              <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirmPassword"
                       placeholder="Re-enter new password"
                       oninput="checkMatch()">
                <div class="strength-text" id="matchText"></div>
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary">🔒 Change Password</button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </div>
</main>

<script>
function switchTab(tab) {
  document.getElementById('tab-profile').classList.toggle('hidden', tab !== 'profile');
  document.getElementById('tab-password').classList.toggle('hidden', tab !== 'password');
  document.querySelectorAll('.tab-btn').forEach((btn, i) => {
    btn.classList.toggle('active', (i === 0 && tab === 'profile') || (i === 1 && tab === 'password'));
  });
}

function previewPhoto(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.getElementById('previewImg');
      const initials = document.getElementById('previewInitials');
      img.src = e.target.result;
      img.style.display = 'block';
      if (initials) initials.style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
  }
}

function checkStrength(password) {
  const fill = document.getElementById('strengthFill');
  const text = document.getElementById('strengthText');
  let score = 0;
  if (password.length >= 8)  score++;
  if (password.length >= 12) score++;
  if (/[A-Z]/.test(password)) score++;
  if (/[0-9]/.test(password)) score++;
  if (/[^A-Za-z0-9]/.test(password)) score++;
  const levels = [
    { w: '20%',  c: '#ef4444', t: 'Very Weak' },
    { w: '40%',  c: '#f97316', t: 'Weak' },
    { w: '60%',  c: '#eab308', t: 'Fair' },
    { w: '80%',  c: '#22c55e', t: 'Strong' },
    { w: '100%', c: '#16a34a', t: 'Very Strong' },
  ];
  const level = levels[Math.max(0, score - 1)] || levels[0];
  fill.style.width = password ? level.w : '0';
  fill.style.background = level.c;
  text.textContent = password ? level.t : '';
  text.style.color = level.c;
}

function checkMatch() {
  const pw  = document.getElementById('newPassword').value;
  const cpw = document.getElementById('confirmPassword').value;
  const txt = document.getElementById('matchText');
  if (!cpw) { txt.textContent = ''; return; }
  if (pw === cpw) {
    txt.textContent = '✓ Passwords match';
    txt.style.color = '#16a34a';
  } else {
    txt.textContent = '✗ Passwords do not match';
    txt.style.color = '#ef4444';
  }
}
</script>
</body>
</html>
