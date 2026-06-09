<?php
session_start();

/* ================= SECURITY HEADERS ================= */
header("Content-Type: application/json");
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://app.sandbox.midtrans.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self' https://app.sandbox.midtrans.com;");

if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}

/* ================= CORS ================= */
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

/* ================= DATABASE CONFIG ================= */
$host     = 'localhost';
$dbname   = 'luxebooking';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal']);
    exit;
}

/* ================= SECURITY LOGGING ================= */
function securityLog($level, $event, $userId = null, $details = []) {
    $logDir = __DIR__ . '/../logs';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    // Jangan pernah log password, token, csrf token, atau nomor identitas
    unset($details['password']);
    unset($details['token']);
    unset($details['auth_token']);
    unset($details['csrf_token']);
    unset($details['id_number']);

    $logData = [
        'timestamp'  => date('c'),
        'level'      => $level,
        'event'      => $event,
        'user_id'    => $userId,
        'ip'         => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details'    => $details
    ];

    file_put_contents(
        $logDir . '/security.log',
        json_encode($logData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND
    );
}

/* ================= COOKIE & CSRF HELPER ================= */
function isSecureRequest() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
}

function setAuthCookie($token) {
    setcookie('auth_token', $token, [
        'expires' => time() + 86400,
        'path' => '/',
        'secure' => isSecureRequest(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function setCsrfCookie($csrfToken) {
    setcookie('csrf_token', $csrfToken, [
        'expires' => time() + 86400,
        'path' => '/',
        'secure' => isSecureRequest(),
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
}

function clearAuthCookies() {
    setcookie('auth_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isSecureRequest(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    setcookie('csrf_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isSecureRequest(),
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
}

function validateCsrfToken() {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (!in_array($method, ['POST', 'PUT', 'DELETE'])) {
        return;
    }

    $action = $_REQUEST['action'] ?? '';

    $excludedActions = ['login', 'register', 'me', 'logout'];

    if (in_array($action, $excludedActions)) {
        return;
    }

    $csrfCookie = $_COOKIE['csrf_token'] ?? '';
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (empty($csrfCookie) || empty($csrfHeader) || !hash_equals($csrfCookie, $csrfHeader)) {
        securityLog('WARN', 'CSRF_INVALID', null, [
            'action' => $action
        ]);

        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'CSRF token tidak valid'
        ]);
        exit;
    }
}

// Fungsi auth via token di body
function authenticate($pdo) {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $token = $_COOKIE['auth_token'] ?? '';

    // Fallback sementara agar kode lama masih bisa jalan jika ada token dari body
    if (empty($token)) {
        $token = $input['token'] ?? $_GET['token'] ?? '';
    }

    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token tidak ada']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.role, u.phone, u.id_number, u.joined_at
        FROM sessions s
        JOIN users u ON u.id = s.user_id
        WHERE s.token = ? AND s.expires_at > NOW()
    ");
    $stmt->execute([$token]);

    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token tidak valid']);
        exit;
    }

    return $user;
}

// KONFIGURASI MIDTRANS
// ==========================================
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $_ENV[trim($key)] = trim($value);
    }
}

loadEnv(__DIR__ . '/../.env');

define('MIDTRANS_SERVER_KEY', $_ENV['MIDTRANS_SERVER_KEY'] ?? '');
define('MIDTRANS_CLIENT_KEY', $_ENV['MIDTRANS_CLIENT_KEY'] ?? '');
define('MIDTRANS_IS_PRODUCTION', ($_ENV['MIDTRANS_IS_PRODUCTION'] ?? 'false') === 'true');