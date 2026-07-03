<?php
/**
 * 海龟汤网站 — 全局配置
 * 
 * 根据环境自动切换数据库配置。
 * 正式部署时请将生产环境信息填入 config.dist.php 并重命名为 config.php
 */

// ========== 环境检测 ==========
// InfinityFree 上通常存在 /home/volXX_X/ 目录结构
$isProduction = (strpos(__DIR__, '/home/vol') === 0);

// ========== 数据库配置 ==========
if ($isProduction) {
    // 🚀 生产环境（InfinityFree）
    // 请改为你在 InfinityFree phpMyAdmin 中创建的数据库信息
    define('DB_HOST', 'sqlXXX.infinityfree.com');   // 修改为你的 DB 主机
    define('DB_NAME', 'if0_XXXXXXX_turtle_soup');   // 修改为你的 DB 名
    define('DB_USER', 'if0_XXXXXXX');                // 修改为你的 DB 用户名
    define('DB_PASS', '你的数据库密码');              // 修改为你的 DB 密码
    define('SITE_URL', 'https://turtlesoup.free.nf');
} else {
    // 🛠️ 开发环境（本地）
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'turtle_soup');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('SITE_URL', 'http://localhost/turtle-soup');
}

define('DB_CHARSET', 'utf8mb4');

// ========== 站点配置 ==========
define('SITE_NAME', '🐢 海龟汤推理馆');
define('SESSION_LIFETIME', 86400); // 24小时

// ========== AI 主持配置 ==========
define('AI_API_ENDPOINT', 'https://api.deepseek.com/v1/chat/completions');
define('AI_MODEL', 'deepseek-chat');
define('AI_MAX_TOKENS', 50);
define('AI_TEMPERATURE', 0.3);

// ========== 游戏配置 ==========
define('DEFAULT_MAX_QUESTIONS', 20);
define('DEFAULT_MAX_PLAYERS', 6);
define('POLL_INTERVAL_MS', 1500);

// ========== Session ==========
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(SESSION_LIFETIME);
    session_start();
}

// ========== PDO 数据库连接 ==========
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

// ========== 辅助函数 ==========
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

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

function isAdmin(): bool {
    $user = currentUser();
    return $user && $user['role'] === 'admin';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function input(string $key, string $default = ''): string {
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}
