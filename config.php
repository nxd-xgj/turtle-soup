<?php
/**
 * 海龟汤网站 — 全局配置
 */

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'turtle_soup');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// 站点配置
define('SITE_NAME', '🐢 海龟汤推理馆');
define('SITE_URL', 'http://localhost/turtle-soup');
define('SESSION_LIFETIME', 86400); // 24小时

// AI 主持配置（用户可自行在房间设置中覆盖）
define('AI_API_ENDPOINT', 'https://api.deepseek.com/v1/chat/completions');
define('AI_MODEL', 'deepseek-chat');
define('AI_MAX_TOKENS', 50);
define('AI_TEMPERATURE', 0.3);

// 游戏配置
define('DEFAULT_MAX_QUESTIONS', 20);
define('DEFAULT_MAX_PLAYERS', 6);
define('POLL_INTERVAL_MS', 1500);

// 启动 Session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(SESSION_LIFETIME);
    session_start();
}

// PDO 数据库连接
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

// 检查用户是否登录
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

// 获取当前用户信息
function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ? AND status = "active"');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

// 检查是否为管理员
function isAdmin(): bool {
    $user = currentUser();
    return $user && $user['role'] === 'admin';
}

// 需要登录的页面保护
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

// 需要管理员权限的保护
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}

// HTML 转义快捷函数
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// JSON 响应快捷函数
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取 GET/POST 参数
function input(string $key, string $default = ''): string {
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}
