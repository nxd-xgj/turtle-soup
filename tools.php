<?php
/**
 * 🛠️ 海龟汤诊断工具 — 检查安装状态并修复
 * 访问后查看结果，完成后请删除本文件
 */
$steps = [];

// 检查 config.php
$steps[] = ['config.php 存在', file_exists(__DIR__ . '/config.php') ? '✅' : '❌ 缺失'];
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    $steps[] = ['常量 DB_HOST 已定义', defined('DB_HOST') ? '✅' : '❌'];
    $steps[] = ['DB_HOST 非 localhost', (defined('DB_HOST') && DB_HOST !== 'localhost') ? '✅ '.DB_HOST : '⚠️ localhost'];
}

// 尝试连接数据库
try {
    $db = getDB();
    $steps[] = ['数据库连接', '✅ 成功'];

    // 检查表
    $tables = ['users','turtles','rooms','room_players','messages','game_records'];
    foreach ($tables as $t) {
        $exists = $db->query("SHOW TABLES LIKE '{$t}'")->fetchColumn();
        $steps[] = ["表 {$t}", $exists ? '✅ 存在' : '❌ 缺失'];
    }

    // 检查数据
    $userCount = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $turtleCount = $db->query('SELECT COUNT(*) FROM turtles')->fetchColumn();
    $adminCount = $db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    $steps[] = ["用户数", $userCount ? "✅ {$userCount} 人" : '⚠️ 空'];
    $steps[] = ["管理员数", $adminCount ? "✅ {$adminCount} 个" : '❌ 没有管理员'];
    $steps[] = ["汤谱数", $turtleCount ? "✅ {$turtleCount} 条" : '⚠️ 空'];

    // 输出管理员列表
    if ($userCount > 0) {
        $stmt = $db->query("SELECT id, username, email, role, status FROM users");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $steps[] = ['数据库连接', '❌ ' . $e->getMessage()];
}

// 处理修复
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $db = getDB();
        if ($_POST['action'] === 'create_admin') {
            $hash = password_hash('admin123', PASSWORD_BCRYPT);
            $db->exec("DELETE FROM users WHERE role='admin'");
            $stmt = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
            $stmt->execute(['admin', 'admin@turtlesoup.local', $hash]);
            $message = '✅ 管理员已创建：admin / admin123';
        }
        if ($_POST['action'] === 'import_seed') {
            define('SEED_DATA_ONLY', true);
            $entries = require __DIR__ . '/seed.php';
            if (is_array($entries)) {
                $db->exec('DELETE FROM turtles');
                $db->exec('ALTER TABLE turtles AUTO_INCREMENT = 1');
                $st = $db->prepare("INSERT INTO turtles (title,surface,bottom,difficulty,tags,ai_prompt,ai_playable,parent_id,status) VALUES (?,?,?,?,?,?,?,?,'published')");
                $cnt = 0;
                foreach ($entries as $e) {
                    try { $st->execute([$e['title'],$e['surface'],$e['bottom'],$e['difficulty']??1,$e['tags']??'',$e['ai_prompt']??null,$e['ai_playable']??1,$e['parent_id']??null]); $cnt++; } catch(Exception $x) {}
                }
                $message = "✅ 已导入 {$cnt} 条汤谱";
            }
        }
        if ($_POST['action'] === 'create_tables') {
            $db->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, email VARCHAR(100) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, avatar VARCHAR(255) DEFAULT '', role ENUM('user','admin') DEFAULT 'user', status ENUM('active','banned') DEFAULT 'active', token_balance INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS turtles (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL, surface TEXT NOT NULL, bottom TEXT NOT NULL, clues TEXT DEFAULT NULL, difficulty TINYINT DEFAULT 1, tags VARCHAR(255) DEFAULT '', author_id INT, status ENUM('draft','published','hidden') DEFAULT 'published', play_count INT DEFAULT 0, like_count INT DEFAULT 0, rating DECIMAL(2,1) DEFAULT 0.0, ai_prompt TEXT DEFAULT NULL, ai_playable TINYINT DEFAULT 1, parent_id INT DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS rooms (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, host_id INT NOT NULL, mode ENUM('ai','human') DEFAULT 'ai', turtle_id INT, status ENUM('waiting','playing','ended') DEFAULT 'waiting', max_players INT DEFAULT 6, max_questions INT DEFAULT 20, used_questions INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS room_players (id INT AUTO_INCREMENT PRIMARY KEY, room_id INT NOT NULL, user_id INT NOT NULL, join_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY unique_room_user (room_id, user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS messages (id INT AUTO_INCREMENT PRIMARY KEY, room_id INT NOT NULL, user_id INT, username VARCHAR(50), content TEXT NOT NULL, type ENUM('chat','question','answer','system') DEFAULT 'chat', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_room_time (room_id, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS game_records (id INT AUTO_INCREMENT PRIMARY KEY, room_id INT NOT NULL, user_id INT NOT NULL, turtle_id INT NOT NULL, questions_count INT DEFAULT 0, guessed ENUM('yes','no') DEFAULT 'no', score INT DEFAULT 0, played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $message = '✅ 6 张表已创建';
        }
        $message .= ' 请刷新页面查看效果';
    } catch (Exception $e) {
        $message = '❌ ' . $e->getMessage();
    }
}
?><!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>🛠️ 诊断工具</title>
<style>body{font-family:sans-serif;background:#0a0a1a;color:#e0e0e0;padding:20px;max-width:600px;margin:0 auto}
h1{font-size:1.3rem}.box{background:rgba(20,20,50,0.7);border-radius:10px;padding:20px;margin:16px 0;border:1px solid rgba(255,255,255,0.06)}
.row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.9rem}
.btn{padding:8px 20px;border:none;border-radius:6px;cursor:pointer;font-size:0.9rem;margin:4px;background:rgba(255,77,106,0.8);color:#fff}
.btn:hover{background:#ff4d6a}.btn2{background:rgba(0,230,118,0.5)}.msg{background:rgba(0,230,118,0.12);color:#00e676;padding:10px;border-radius:6px;margin:10px 0}
.err{background:rgba(255,77,106,0.12);color:#ff4d6a;padding:10px;border-radius:6px;margin:10px 0}
.warn{background:rgba(255,145,0,0.12);color:#ff9100;padding:10px;border-radius:6px;margin:10px 0}
table{width:100%;border-collapse:collapse;font-size:0.85rem}
td,th{padding:6px 8px;border-bottom:1px solid rgba(255,255,255,0.06);text-align:left}</style></head><body>
<h1>🛠️ 海龟汤 — 诊断工具</h1>
<?php if($message): ?><div class="<?= strpos($message,'❌')!==false?'err':'msg' ?>"><?=htmlspecialchars($message)?></div><?php endif; ?>

<div class="box">
<h2>📊 系统状态</h2>
<?php foreach($steps as $s): ?>
<div class="row"><span><?=htmlspecialchars($s[1])?></span><span><?=htmlspecialchars($s[0])?></span></div>
<?php endforeach; ?>
</div>

<?php if(isset($users) && !empty($users)): ?>
<div class="box">
<h2>👤 用户列表</h2>
<table><tr><th>ID</th><th>用户名</th><th>邮箱</th><th>角色</th><th>状态</th></tr>
<?php foreach($users as $u): ?>
<tr><td><?=$u['id']?></td><td><?=htmlspecialchars($u['username'])?></td><td><?=htmlspecialchars($u['email'])?></td><td><?=$u['role']?></td><td><?=$u['status']?></td></tr>
<?php endforeach; ?>
</table></div>
<?php endif; ?>

<div class="box">
<h2>🔧 修复工具</h2>
<form method="POST" style="display:inline"><input type="hidden" name="action" value="create_admin"><button class="btn">创建管理员 (admin/admin123)</button></form>
<form method="POST" style="display:inline"><input type="hidden" name="action" value="create_tables"><button class="btn btn2">创建 6 张表</button></form>
<form method="POST" style="display:inline"><input type="hidden" name="action" value="import_seed"><button class="btn btn2">导入汤谱数据</button></form>
</div>

<p style="color:#6a6a8a;font-size:0.8rem;text-align:center">⚠️ 使用后请删除本文件</p>
</body></html>
