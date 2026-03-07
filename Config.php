<?php
// ============================================================
// config.php — Database & App Configuration
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'u6O4181547_88acesuser');
define('DB_PASS', 'Aces@2026!');
define('DB_NAME', 'u6O4181547_88aces');

define('APP_NAME', '88 Aces Maritime Training System');
define('APP_URL',  'https://green-albatross-648026.hostingersite.com');
define('CERT_PREFIX', 'APAT');

// Paths (relative to project root)
define('ROOT_PATH',  __DIR__ . '/');
define('CERT_PATH',  ROOT_PATH . 'certificates/');
define('QR_PATH',    ROOT_PATH . 'qrcodes/');
define('UPLOAD_PATH',ROOT_PATH . 'uploads/');

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
session_start();

// Error reporting (OFF for production)
error_reporting(0);
ini_set('display_errors', 0);

// ============================================================
// Database Connection (PDO)
// ============================================================
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('Database connection failed.');
        }
    }
    return $pdo;
}

// ============================================================
// Auth Helpers
// ============================================================
function isLoggedIn(): bool {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/admin/Login.php');
        exit;
    }
}
function currentAdmin(): array {
    return $_SESSION['admin_data'] ?? [];
}

// ============================================================
// Utility Functions
// ============================================================
function sanitize(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}
function generateToken(int $length = 32): string {
    return bin2hex(random_bytes($length));
}
function generateSessionCode(): string {
    $year = date('Y');
    $db = getDB();
    $count = $db->query("SELECT COUNT(*) FROM training_sessions WHERE YEAR(created_at) = $year")->fetchColumn();
    return 'TRN-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}
function generateCertNumber(string $prefix = CERT_PREFIX): string {
    $year = date('Y');
    $db = getDB();
    $count = $db->query("SELECT COUNT(*) FROM certificates WHERE YEAR(generated_at) = $year")->fetchColumn();
    return $prefix . ' ' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}
function auditLog(string $action, string $targetType = '', int $targetId = 0, string $notes = ''): void {
    $db = getDB();
    $adminId = $_SESSION['admin_id'] ?? null;
    $stmt = $db->prepare(
        "INSERT INTO audit_log (admin_id, action, target_type, target_id, notes) VALUES (?,?,?,?,?)"
    );
    $stmt->execute([$adminId, $action, $targetType ?: null, $targetId ?: null, $notes ?: null]);
}
function jsonResponse(bool $success, string $message, array $data = []): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}
