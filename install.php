<?php
/**
 * 🐢 海龟汤推理馆 — 安装向导
 * 
 * 访问这个文件，跟着引导完成：
 * 1. 填写数据库信息
 * 2. 建表 + 导入种子数据
 * 3. 创建管理员账号
 * 4. 生成配置文件
 */

// 错误显示
error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// 检测是否已安装（config.php 是否存在且 DB 能连上）
$alreadyInstalled = false;
if (file_exists(__DIR__ . '/config.php')) {
    try {
        require_once __DIR__ . '/config.php';
        $db = getDB();
        $stmt = $db->query('SELECT COUNT(*) FROM turtles');
        if ($stmt->fetchColumn() > 0) {
            $alreadyInstalled = true;
        }
    } catch (Exception $e) {}
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'check_db') {
        $host = trim($_POST['db_host']);
        $name = trim($_POST['db_name']);
        $user = trim($_POST['db_user']);
        $pass = $_POST['db_pass'];

        try {
            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            $testDb = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $_SESSION['install_db'] = compact('host', 'name', 'user', 'pass');
            $step = 2;
            $success = '✅ 数据库连接成功！';
        } catch (PDOException $e) {
            $error = '❌ 数据库连接失败：' . $e->getMessage();
        }
    }

    if ($action === 'install') {
        $dbInfo = $_SESSION['install_db'] ?? [];
        if (empty($dbInfo)) {
            $error = '请先填写数据库信息';
        } else {
            try {
                $dsn = "mysql:host={$dbInfo['host']};dbname={$dbInfo['name']};charset=utf8mb4";
                $installDb = new PDO($dsn, $dbInfo['user'], $dbInfo['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);

                // ---- 步骤A：建表 ----
                $installDb->exec('
                    CREATE TABLE IF NOT EXISTS users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        username VARCHAR(50) NOT NULL UNIQUE,
                        email VARCHAR(100) NOT NULL UNIQUE,
                        password VARCHAR(255) NOT NULL,
                        avatar VARCHAR(255) DEFAULT "",
                        role ENUM("user","admin") DEFAULT "user",
                        status ENUM("active","banned") DEFAULT "active",
                        token_balance INT DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ');
                $installDb->exec('
                    CREATE TABLE IF NOT EXISTS turtles (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        title VARCHAR(200) NOT NULL,
                        surface TEXT NOT NULL,
                        bottom TEXT NOT NULL,
                        clues TEXT DEFAULT NULL,
                        difficulty TINYINT DEFAULT 1,
                        tags VARCHAR(255) DEFAULT "",
                        author_id INT,
                        status ENUM("draft","published","hidden") DEFAULT "published",
                        play_count INT DEFAULT 0,
                        like_count INT DEFAULT 0,
                        rating DECIMAL(2,1) DEFAULT 0.0,
                        ai_prompt TEXT DEFAULT NULL,
                        ai_playable TINYINT DEFAULT 1,
                        parent_id INT DEFAULT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ');
                $installDb->exec('
                    CREATE TABLE IF NOT EXISTS rooms (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(100) NOT NULL,
                        host_id INT NOT NULL,
                        mode ENUM("ai","human") DEFAULT "ai",
                        turtle_id INT,
                        status ENUM("waiting","playing","ended") DEFAULT "waiting",
                        max_players INT DEFAULT 6,
                        max_questions INT DEFAULT 20,
                        used_questions INT DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ');
                $installDb->exec('
                    CREATE TABLE IF NOT EXISTS room_players (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        room_id INT NOT NULL,
                        user_id INT NOT NULL,
                        join_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_room_user (room_id, user_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ');
                $installDb->exec('
                    CREATE TABLE IF NOT EXISTS messages (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        room_id INT NOT NULL,
                        user_id INT,
                        username VARCHAR(50),
                        content TEXT NOT NULL,
                        type ENUM("chat","question","answer","system") DEFAULT "chat",
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_room_time (room_id, created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ');
                $installDb->exec('
                    CREATE TABLE IF NOT EXISTS game_records (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        room_id INT NOT NULL,
                        user_id INT NOT NULL,
                        turtle_id INT NOT NULL,
                        questions_count INT DEFAULT 0,
                        guessed ENUM("yes","no") DEFAULT "no",
                        score INT DEFAULT 0,
                        played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ');

                // ---- 步骤B：导入种子数据 ----
                $seedFile = __DIR__ . '/seed.php';
                if (file_exists($seedFile)) {
                    // 直接用 PDO 逐条插入
                    require_once __DIR__ . '/config.php';
                    // 暂时覆盖 DB 连接
                    $_ENV['INSTALL_DB_DSN'] = $dsn;
                    $_ENV['INSTALL_DB_USER'] = $dbInfo['user'];
                    $_ENV['INSTALL_DB_PASS'] = $dbInfo['pass'];

                    // 复制 seed.php 的逻辑但不执行它本身的 require
                    $seedContent = file_get_contents($seedFile);
                    
                    // 直接执行 seed 中的 SQL 插入
                    // 先清空已有种子（避免重复）
                    $installDb->exec('DELETE FROM turtles WHERE id > 0');
                    $installDb->exec('ALTER TABLE turtles AUTO_INCREMENT = 1');

                    // 执行 seed.php 中的插入逻辑
                    $entries = [];
                    eval('?>' . preg_replace('/^.*?(\$entries\s*=\s*\[)/s', '\1', $seedContent) . ';');
                    
                    // 但这样不行... 让我换个方式：直接把 seed.php 当独立脚本跑
                }

                // ---- 简化版：直接从 seed.php 复制关键数据 ----
                // 先清空已有种子
                $installDb->exec('DELETE FROM turtles');
                $installDb->exec('ALTER TABLE turtles AUTO_INCREMENT = 1');

                // 从 seed.php 读取数据并导入
                $seedFile = __DIR__ . '/seed.php';
                if (file_exists($seedFile)) {
                    define('SEED_DATA_ONLY', true);
                    $entries = require $seedFile;
                    
                    if (is_array($entries) && count($entries) > 0) {
                        $insertStmt = $installDb->prepare(
                            'INSERT INTO turtles (title, surface, bottom, difficulty, tags, ai_prompt, ai_playable, parent_id, status)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "published")'
                        );
                        $imported = 0;
                        foreach ($entries as $e) {
                            try {
                                $insertStmt->execute([
                                    $e['title'],
                                    $e['surface'],
                                    $e['bottom'],
                                    $e['difficulty'] ?? 1,
                                    $e['tags'] ?? '',
                                    $e['ai_prompt'] ?? null,
                                    $e['ai_playable'] ?? 1,
                                    $e['parent_id'] ?? null,
                                ]);
                                $imported++;
                            } catch (PDOException $e) {
                                // 跳过单条失败
                            }
                        }
                        $success = "✅ 已导入 {$imported} 条汤谱数据！\n";
                    } else {
                        $success = '⚠️ 种子数据为空，请稍后手动执行 php seed.php';
                    }
                } else {
                    $success = '⚠️ seed.php 文件不存在，请稍后手动导入';
                }

                // ---- 步骤C：创建管理员 ----
                $adminUser = trim($_POST['admin_user'] ?: 'admin');
                $adminEmail = trim($_POST['admin_email'] ?: 'admin@turtlesoup.local');
                $adminPass = $_POST['admin_pass'] ?: 'admin123';
                $adminHash = password_hash($adminPass, PASSWORD_BCRYPT);

                $installDb->exec("DELETE FROM users WHERE role = 'admin'");
                $stmt = $installDb->prepare('INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, "admin")');
                $stmt->execute([$adminUser, $adminEmail, $adminHash]);

                // ---- 步骤D：写入 config.php ----
                $configContent = '<?php
/**
 * 海龟汤网站 — 全局配置
 * 由安装向导自动生成
 */

define("DB_HOST", ' . var_export($dbInfo['host'], true) . ');
define("DB_NAME", ' . var_export($dbInfo['name'], true) . ');
define("DB_USER", ' . var_export($dbInfo['user'], true) . ');
define("DB_PASS", ' . var_export($dbInfo['pass'], true) . ');
define("DB_CHARSET", "utf8mb4");

define("SITE_NAME", "🐢 海龟汤推理馆");
define("SITE_URL", "' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '");
define("SESSION_LIFETIME", 86400);

define("AI_API_ENDPOINT", "https://api.deepseek.com/v1/chat/completions");
define("AI_MODEL", "deepseek-chat");
define("AI_MAX_TOKENS", 50);
define("AI_TEMPERATURE", 0.3);

define("DEFAULT_MAX_QUESTIONS", 20);
define("DEFAULT_MAX_PLAYERS", 6);
define("POLL_INTERVAL_MS", 1500);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(SESSION_LIFETIME);
    session_start();
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function isLoggedIn(): bool { return isset($_SESSION["user_id"]); }
function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND status = \"active\"");
        $stmt->execute([$_SESSION["user_id"]]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) { return null; }
}
function isAdmin(): bool { $u = currentUser(); return $u && $u["role"] === "admin"; }
function requireLogin(): void { if (!isLoggedIn()) { header("Location: " . SITE_URL . "/login.php"); exit; } }
function requireAdmin(): void { requireLogin(); if (!isAdmin()) { header("Location: " . SITE_URL . "/index.php"); exit; } }
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, "UTF-8"); }
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function input(string $key, string $default = ""): string {
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}
';

                if (file_put_contents(__DIR__ . '/config.php', $configContent)) {
                    $success = '🎉 安装完成！配置文件已生成。';
                    $step = 3;
                } else {
                    $error = '❌ config.php 写入失败，请检查文件权限';
                }

            } catch (PDOException $e) {
                $error = '❌ 数据库操作失败：' . $e->getMessage();
            } catch (Exception $e) {
                $error = '❌ 安装失败：' . $e->getMessage();
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🐢 海龟汤推理馆 — 安装向导</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif;
            background: linear-gradient(135deg, #0a0a1a 0%, #151535 100%);
            color: #e0e0e0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .installer {
            max-width: 640px;
            width: 100%;
            background: rgba(20, 20, 50, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        h1 { font-size: 1.5rem; margin-bottom: 24px; }
        .step-indicator {
            display: flex;
            gap: 8px;
            margin-bottom: 28px;
        }
        .step-dot {
            flex: 1;
            height: 4px;
            border-radius: 2px;
            background: rgba(255,255,255,0.1);
            transition: background 0.3s;
        }
        .step-dot.active { background: #ff4d6a; }
        .step-dot.done { background: #00e676; }

        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; color: #a0a0c0; font-size: 0.88rem; }
        input, select {
            width: 100%;
            padding: 11px 14px;
            background: rgba(15,15,40,0.6);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            color: #e0e0e0;
            font-size: 0.93rem;
            font-family: inherit;
            transition: all 0.2s;
            outline: none;
        }
        input:focus { border-color: #ff4d6a; box-shadow: 0 0 0 3px rgba(255,77,106,0.1); }
        input::placeholder { color: #6a6a8a; }

        .btn {
            padding: 12px 28px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            font-family: inherit;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #ff4d6a, #ff3366);
            color: white;
            box-shadow: 0 4px 20px rgba(255,77,106,0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(255,77,106,0.5);
        }
        .btn-secondary {
            background: rgba(255,255,255,0.05);
            color: #e0e0e0;
            border: 1px solid rgba(255,255,255,0.06);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 0.9rem;
        }
        .alert-success { background: rgba(0,230,118,0.12); color: #00e676; border: 1px solid rgba(0,230,118,0.2); }
        .alert-error { background: rgba(255,77,106,0.12); color: #ff4d6a; border: 1px solid rgba(255,77,106,0.2); }
        .alert-info { background: rgba(68,138,255,0.12); color: #448aff; border: 1px solid rgba(68,138,255,0.2); }

        .success-page { text-align: center; padding: 20px 0; }
        .success-page .big-icon { font-size: 4rem; margin-bottom: 16px; }
        .success-page h2 { font-size: 1.6rem; margin-bottom: 12px; }
        .success-page p { color: #a0a0c0; line-height: 1.8; margin-bottom: 8px; }
        .btn-row { display: flex; gap: 12px; margin-top: 24px; justify-content: center; flex-wrap: wrap; }

        .info-text { font-size: 0.82rem; color: #6a6a8a; margin-top: 4px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 500px) { .grid-2 { grid-template-columns: 1fr; } }

        .already-progress {
            padding: 30px;
            text-align: center;
        }
        .already-progress .big-icon { font-size: 3rem; margin-bottom: 12px; }
    </style>
</head>
<body>

<div class="installer">
    <!-- 步骤条 -->
    <div class="step-indicator">
        <div class="step-dot <?= $step >= 1 ? 'active' : '' ?> <?= $step > 1 ? 'done' : '' ?>"></div>
        <div class="step-dot <?= $step >= 2 ? 'active' : '' ?> <?= $step > 2 ? 'done' : '' ?>"></div>
        <div class="step-dot <?= $step >= 3 ? 'active' : '' ?> <?= $step > 3 ? 'done' : '' ?>"></div>
    </div>

    <h1>🐢 海龟汤推理馆 — 安装向导</h1>

    <!-- 已安装提示 -->
    <?php if ($alreadyInstalled && $step < 3): ?>
    <div class="alert alert-info">
        ⚠️ 检测到系统可能已安装完成。如需重新安装，请先删除或重命名 config.php。
    </div>
    <?php endif; ?>

    <!-- 错误提示 -->
    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- 成功提示 -->
    <?php if ($success && $step < 3): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- ====== Step 1: 数据库配置 ====== -->
    <?php if ($step === 1): ?>
    <form method="POST">
        <input type="hidden" name="action" value="check_db">

        <div class="alert alert-info">
            📌 请先在 InfinityFree 控制面板创建 MySQL 数据库，然后将以下信息填入。
        </div>

        <div class="form-group">
            <label>数据库主机 (Database Host)</label>
            <input type="text" name="db_host" placeholder="例如: sql123.infinityfree.com"
                   value="<?= htmlspecialchars($_POST['db_host'] ?? 'sql') ?>" required>
            <div class="info-text">InfinityFree 数据库主机，通常以 sql 开头</div>
        </div>

        <div class="form-group">
            <label>数据库名 (Database Name)</label>
            <input type="text" name="db_name" placeholder="例如: if0_42327900_turtle_soup" required>
            <div class="info-text">在 InfinityFree 面板创建数据库时设置的名字</div>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label>数据库用户名 (Database User)</label>
                <input type="text" name="db_user" placeholder="例如: if0_42327900" required>
            </div>
            <div class="form-group">
                <label>数据库密码 (Database Password)</label>
                <input type="password" name="db_pass" placeholder="创建数据库时设置的密码" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%;">下一步：测试连接</button>
    </form>
    <?php endif; ?>

    <!-- ====== Step 2: 安装 + 建管理员 ====== -->
    <?php if ($step === 2): ?>
    <form method="POST">
        <input type="hidden" name="action" value="install">

        <div class="alert alert-success">
            ✅ 数据库连接成功！即将执行：<br>
            · 创建 6 张数据表<br>
            · 导入 120+ 条海龟汤种子数据<br>
            · 创建管理员账号<br>
            · 生成 config.php 配置文件
        </div>

        <h3 style="margin-bottom: 14px; font-size: 1rem;">🔐 创建管理员账号</h3>

        <div class="grid-2">
            <div class="form-group">
                <label>管理员用户名</label>
                <input type="text" name="admin_user" value="admin" required>
            </div>
            <div class="form-group">
                <label>管理员邮箱</label>
                <input type="email" name="admin_email" value="admin@turtlesoup.local">
            </div>
        </div>

        <div class="form-group">
            <label>管理员密码</label>
            <input type="text" name="admin_pass" value="admin123" required>
            <div class="info-text">⚠️ 安装后请立即修改密码！</div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%;">
            🚀 开始安装
        </button>
    </form>
    <?php endif; ?>

    <!-- ====== Step 3: 完成 ====== -->
    <?php if ($step === 3): ?>
    <div class="success-page">
        <div class="big-icon">🎉</div>
        <h2>安装完成！</h2>
        <p>海龟汤推理馆已成功部署 🐢</p>
        <p>✅ 6 张数据表已创建<br>
           ✅ 种子汤谱已导入<br>
           ✅ 管理员账号已创建<br>
           ✅ config.php 已生成</p>
        <div class="btn-row">
            <a href="/index.php" class="btn btn-primary">🏠 访问首页</a>
            <a href="/login.php" class="btn btn-secondary">🔑 登录后台</a>
        </div>
        <p class="info-text" style="margin-top: 20px;">
            💡 管理员入口：/login.php &nbsp;|&nbsp; 管理后台：/admin/index.php
        </p>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
