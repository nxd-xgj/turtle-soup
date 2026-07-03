<?php
/**
 * 🐢 海龟汤推理馆 — 安装向导
 * 
 * 三步：填数据库 → 确认安装 → 完成
 * 装完后请删除本文件。
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';
$showConfigCode = '';

// ========== 安装状态检测 ==========
$installStatus = 'none';
if (file_exists(__DIR__ . '/config.php')) {
    try {
        require_once __DIR__ . '/config.php';
        if (defined('DB_HOST') && DB_HOST !== 'localhost') {
            $db = getDB();
            $c1 = (int)$db->query('SELECT COUNT(*) FROM turtles')->fetchColumn();
            $c2 = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
            if ($c1 > 0 && $c2 > 0) $installStatus = 'complete';
            else $installStatus = 'partial';
        }
    } catch (Exception $e) { $installStatus = 'db_error'; }
}

if ($installStatus === 'complete') {
    ?><!DOCTYPE html><html><head><meta charset="UTF-8"><title>已安装</title>
<style>body{font-family:sans-serif;background:#0a0a1a;color:#e0e0e0;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.card{max-width:480px;background:rgba(20,20,50,0.7);backdrop-filter:blur(20px);border-radius:16px;padding:40px;text-align:center;border:1px solid rgba(255,255,255,0.06)}
h1{margin-bottom:12px}.warn{background:rgba(255,77,106,0.12);color:#ff4d6a;padding:12px;border-radius:8px;margin:16px 0;font-size:0.9rem}
.btn{display:inline-block;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:600;margin:4px}
.btn-p{background:linear-gradient(135deg,#ff4d6a,#ff3366);color:#fff}
.btn-s{background:rgba(255,255,255,0.05);color:#e0e0e0;border:1px solid rgba(255,255,255,0.06)}
p{color:#a0a0c0;font-size:0.9rem;line-height:1.8}</style></head><body>
<div class="card"><h1>✅ 已安装</h1>
<p>海龟汤推理馆已在运行</p>
<div class="warn">⚠️ 请删除 install.php</div>
<a href="/index.php" class="btn btn-p">🏠 首页</a>
<a href="/login.php" class="btn btn-s">🔑 登录</a></div></body></html>
    <?php exit;
}
if ($installStatus === 'partial' || $installStatus === 'db_error') {
    ?><!DOCTYPE html><html><head><meta charset="UTF-8"><title>安装异常</title>
<style>body{font-family:sans-serif;background:#0a0a1a;color:#e0e0e0;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.card{max-width:500px;background:rgba(20,20,50,0.7);backdrop-filter:blur(20px);border-radius:16px;padding:40px;border:1px solid rgba(255,255,255,0.06)}
.warn{background:rgba(255,145,0,0.12);color:#ff9100;padding:12px;border-radius:8px;margin:16px 0}
.btn{display:inline-block;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:600;margin:4px;background:linear-gradient(135deg,#ff4d6a,#ff3366);color:#fff}
p{color:#a0a0c0}</style></head><body>
<div class="card"><h1>⚠️ 安装异常</h1>
<p>数据库连接或数据不完整，请检查 config.php 中的配置。</p>
<a href="?reset=1" class="btn">🔄 重新安装</a></div></body></html>
    <?php exit;
}

// 重新安装
if (isset($_GET['reset']) && file_exists(__DIR__ . '/config.php')) {
    unlink(__DIR__ . '/config.php');
    $step = 1;
}

// ========== 表单处理 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'check_db') {
        $db_host = trim($_POST['db_host']);
        $db_name = trim($_POST['db_name']);
        $db_user = trim($_POST['db_user']);
        $db_pass = $_POST['db_pass'];
        $admin_user = trim($_POST['admin_user'] ?: 'admin');
        $admin_email = trim($_POST['admin_email'] ?: 'admin@turtlesoup.local');
        $admin_pass = $_POST['admin_pass'] ?: 'admin123';

        try {
            $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
            $test = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
            // 通过隐藏字段把数据带到下一步
            $step = 2;
        } catch (PDOException $e) {
            $error = '❌ 数据库连接失败：' . $e->getMessage();
            // 保留已填字段
            $db_host_keep = $db_host;
            $db_name_keep = $db_name;
            $db_user_keep = $db_user;
        }
    }

    if ($action === 'install') {
        $db_host = trim($_POST['db_host']);
        $db_name = trim($_POST['db_name']);
        $db_user = trim($_POST['db_user']);
        $db_pass = $_POST['db_pass'];
        $admin_user = trim($_POST['admin_user'] ?: 'admin');
        $admin_email = trim($_POST['admin_email'] ?: 'admin@turtlesoup.local');
        $admin_pass = $_POST['admin_pass'] ?: 'admin123';

        try {
            $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
            $db = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // 建表
            $db->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, email VARCHAR(100) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, avatar VARCHAR(255) DEFAULT '', role ENUM('user','admin') DEFAULT 'user', status ENUM('active','banned') DEFAULT 'active', token_balance INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS turtles (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL, surface TEXT NOT NULL, bottom TEXT NOT NULL, clues TEXT DEFAULT NULL, difficulty TINYINT DEFAULT 1, tags VARCHAR(255) DEFAULT '', author_id INT, status ENUM('draft','published','hidden') DEFAULT 'published', play_count INT DEFAULT 0, like_count INT DEFAULT 0, rating DECIMAL(2,1) DEFAULT 0.0, ai_prompt TEXT DEFAULT NULL, ai_playable TINYINT DEFAULT 1, parent_id INT DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS rooms (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, host_id INT NOT NULL, mode ENUM('ai','human') DEFAULT 'ai', turtle_id INT, status ENUM('waiting','playing','ended') DEFAULT 'waiting', max_players INT DEFAULT 6, max_questions INT DEFAULT 20, used_questions INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS room_players (id INT AUTO_INCREMENT PRIMARY KEY, room_id INT NOT NULL, user_id INT NOT NULL, join_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY unique_room_user (room_id, user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS messages (id INT AUTO_INCREMENT PRIMARY KEY, room_id INT NOT NULL, user_id INT, username VARCHAR(50), content TEXT NOT NULL, type ENUM('chat','question','answer','system') DEFAULT 'chat', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_room_time (room_id, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS game_records (id INT AUTO_INCREMENT PRIMARY KEY, room_id INT NOT NULL, user_id INT NOT NULL, turtle_id INT NOT NULL, questions_count INT DEFAULT 0, guessed ENUM('yes','no') DEFAULT 'no', score INT DEFAULT 0, played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 导种子数据
            $seedFile = __DIR__ . '/seed.php';
            $imported = 0;
            if (file_exists($seedFile)) {
                $db->exec('DELETE FROM turtles');
                $db->exec('ALTER TABLE turtles AUTO_INCREMENT = 1');
                define('SEED_DATA_ONLY', true);
                $entries = require $seedFile;
                if (is_array($entries)) {
                    $st = $db->prepare('INSERT INTO turtles (title,surface,bottom,difficulty,tags,ai_prompt,ai_playable,parent_id,status) VALUES (?,?,?,?,?,?,?,?,"published")');
                    foreach ($entries as $e) {
                        try { $st->execute([$e['title'],$e['surface'],$e['bottom'],$e['difficulty']??1,$e['tags']??'',$e['ai_prompt']??null,$e['ai_playable']??1,$e['parent_id']??null]); $imported++; } catch(PDOException $x) {}
                    }
                }
            }

            // 管理员
            $hash = password_hash($admin_pass, PASSWORD_BCRYPT);
            $db->exec("DELETE FROM users WHERE role='admin'");
            $st = $db->prepare('INSERT INTO users (username,email,password,role) VALUES (?,?,?,"admin")');
            $st->execute([$admin_user, $admin_email, $hash]);

            // 写 config.php
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $cfg = '<?php
define("DB_HOST",' . var_export($db_host,true) . ');
define("DB_NAME",' . var_export($db_name,true) . ');
define("DB_USER",' . var_export($db_user,true) . ');
define("DB_PASS",' . var_export($db_pass,true) . ');
define("DB_CHARSET","utf8mb4");
define("SITE_NAME","🐢 海龟汤推理馆");
define("SITE_URL","' . $proto . '://' . $host . '");
define("SESSION_LIFETIME",86400);
define("AI_API_ENDPOINT","https://api.deepseek.com/v1/chat/completions");
define("AI_MODEL","deepseek-chat");
define("AI_MAX_TOKENS",50);
define("AI_TEMPERATURE",0.3);
define("DEFAULT_MAX_QUESTIONS",20);
define("DEFAULT_MAX_PLAYERS",6);
define("POLL_INTERVAL_MS",1500);
if(session_status()===PHP_SESSION_NONE){session_set_cookie_params(SESSION_LIFETIME);session_start();}
function getDB():PDO{static$p=null;if($p===null){$p=new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}return$p;}
function isLoggedIn():bool{return isset($_SESSION["user_id"]);}
function currentUser():?array{if(!isLoggedIn())return null;try{$db=getDB();$st=$db->prepare("SELECT * FROM users WHERE id=? AND status=\"active\"");$st->execute([$_SESSION["user_id"]]);return $st->fetch()?:null;}catch(PDOException$e){return null;}}
function isAdmin():bool{$u=currentUser();return$u&&$u["role"]==="admin";}
function requireLogin():void{if(!isLoggedIn()){header("Location:".SITE_URL."/login.php");exit;}}
function requireAdmin():void{requireLogin();if(!isAdmin()){header("Location:".SITE_URL."/index.php");exit;}}
function h(string$s):string{return htmlspecialchars($s,ENT_QUOTES,"UTF-8");}
function jsonResponse(array$data,int$code=200):void{http_response_code($code);header("Content-Type:application/json; charset=utf-8");echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;}
function input(string$key,string$default=""):string{return$_POST[$key]??$_GET[$key]??$default;}
';

            $written = file_put_contents(__DIR__ . '/config.php', $cfg);
            if ($written !== false) {
                $step = 3;
                $success = "🎉 安装完成！\n✅ 6 张表已创建\n✅ {$imported} 条汤谱已导入\n✅ 管理员已创建（{$admin_user} / {$admin_pass}）";
            } else {
                $showConfigCode = $cfg;
                $step = 3;
                $success = "⚠️ config.php 写入失败（权限限制），请手动创建：\n✅ 6 张表已创建\n✅ {$imported} 条汤谱已导入\n✅ 管理员已创建";
            }
        } catch (PDOException $e) {
            $error = '❌ 安装失败：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>🐢 安装向导</title>
<style>
body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;background:#0a0a1a;color:#e0e0e0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px}
.box{max-width:520px;width:100%;background:rgba(20,20,50,0.7);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,0.5)}
.box-wide{max-width:680px}
h1{font-size:1.3rem;margin-bottom:20px}
.bar{display:flex;gap:6px;margin-bottom:20px}
.bar div{flex:1;height:3px;border-radius:2px;background:rgba(255,255,255,0.1)}
.bar .on{background:#ff4d6a}.bar .ok{background:#00e676}
.g{margin-bottom:14px}
label{display:block;margin-bottom:4px;color:#a0a0c0;font-size:0.85rem}
input{width:100%;padding:10px 12px;background:rgba(15,15,40,0.6);border:1px solid rgba(255,255,255,0.08);border-radius:6px;color:#e0e0e0;font-size:0.92rem;outline:none}
input:focus{border-color:#ff4d6a}
.btn{padding:10px 24px;border:none;border-radius:6px;cursor:pointer;font-size:0.92rem;font-weight:600;transition:all 0.2s;text-decoration:none;display:inline-block}
.btn-p{background:linear-gradient(135deg,#ff4d6a,#ff3366);color:#fff;width:100%}
.btn-p:hover{transform:translateY(-1px)}
.btn-s{background:rgba(255,255,255,0.05);color:#e0e0e0;border:1px solid rgba(255,255,255,0.06)}
.err{background:rgba(255,77,106,0.12);color:#ff4d6a;border:1px solid rgba(255,77,106,0.2);border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:0.88rem}
.okmsg{background:rgba(0,230,118,0.12);color:#00e676;border:1px solid rgba(0,230,118,0.2);border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:0.88rem;white-space:pre-line}
.info{background:rgba(68,138,255,0.12);color:#448aff;border:1px solid rgba(68,138,255,0.2);border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:0.85rem}
.warn{background:rgba(255,145,0,0.12);color:#ff9100;border:1px solid rgba(255,145,0,0.2);border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:0.85rem}
.tip{font-size:0.8rem;color:#6a6a8a;margin-top:3px}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.fin{text-align:center;padding:10px 0}
.fin .bi{font-size:3.5rem;margin-bottom:10px}
.fin h2{font-size:1.4rem;margin-bottom:8px}
.fin p{color:#a0a0c0;line-height:1.8;font-size:0.9rem}
.btns{display:flex;gap:10px;margin-top:16px;justify-content:center;flex-wrap:wrap}
pre{background:#0a0a1a;border:1px solid rgba(255,255,255,0.06);border-radius:6px;padding:12px;font-size:0.75rem;max-height:240px;overflow:auto;color:#a0a0c0;white-space:pre-wrap;word-break:break-all;text-align:left}
@media(max-width:500px){.g2{grid-template-columns:1fr}}
</style></head><body>

<div class="box <?= $showConfigCode ? 'box-wide' : '' ?>">
<div class="bar">
<div class="<?= $step>=1 ? ($step>1?'ok':'on') : '' ?>"></div>
<div class="<?= $step>=2 ? ($step>2?'ok':'on') : '' ?>"></div>
<div class="<?= $step>=3 ? 'ok' : '' ?>"></div>
</div>

<h1>🐢 安装向导</h1>

<?php if($error): ?><div class="err"><?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if($success && $step<3): ?><div class="okmsg"><?=htmlspecialchars($success)?></div><?php endif; ?>

<?php if($step===1): ?>
<form method="POST">
<input type="hidden" name="action" value="check_db">
<div class="info">📌 填入 InfinityFree 数据库信息</div>
<div class="g"><label>数据库主机</label>
<input name="db_host" placeholder="sql123.infinityfree.com" value="<?=htmlspecialchars($_POST['db_host']??'sql')?>" required></div>
<div class="g"><label>数据库名</label>
<input name="db_name" placeholder="if0_XXXXXXX_turtle_soup" required></div>
<div class="g2">
<div class="g"><label>数据库用户</label>
<input name="db_user" placeholder="if0_XXXXXXX" required></div>
<div class="g"><label>数据库密码</label>
<input type="password" name="db_pass" placeholder="密码" required></div>
</div>
<div class="g"><label>管理员用户名</label>
<input name="admin_user" value="admin"></div>
<div class="g2">
<div class="g"><label>管理员邮箱</label>
<input name="admin_email" value="admin@turtlesoup.local"></div>
<div class="g"><label>管理员密码</label>
<input name="admin_pass" value="admin123"></div>
</div>
<button class="btn btn-p">下一步：安装</button>
</form>

<?php elseif($step===2): ?>
<form method="POST">
<input type="hidden" name="action" value="install">
<input type="hidden" name="db_host" value="<?=htmlspecialchars($_POST['db_host'])?>">
<input type="hidden" name="db_name" value="<?=htmlspecialchars($_POST['db_name'])?>">
<input type="hidden" name="db_user" value="<?=htmlspecialchars($_POST['db_user'])?>">
<input type="hidden" name="db_pass" value="<?=htmlspecialchars($_POST['db_pass'])?>">
<input type="hidden" name="admin_user" value="<?=htmlspecialchars($_POST['admin_user']?:'admin')?>">
<input type="hidden" name="admin_email" value="<?=htmlspecialchars($_POST['admin_email']?:'admin@turtlesoup.local')?>">
<input type="hidden" name="admin_pass" value="<?=htmlspecialchars($_POST['admin_pass']?:'admin123')?>">

<div class="okmsg">✅ 连接成功！即将：<br>· 建 6 张表 · 导入 120+ 条汤 · 创建管理员 · 生成配置</div>

<div class="warn">⚠️ 确认开始安装？将清空已有数据并重新创建。</div>

<button class="btn btn-p">🚀 确认安装</button>
</form>

<?php elseif($step===3): ?>
<div class="fin">
<div class="bi">🎉</div>
<h2>安装完成</h2>
<?php if($showConfigCode): ?>
<div class="warn">⚠️ config.php 写入失败，请手动创建此文件并粘贴以下代码：</div>
<pre><?=htmlspecialchars($showConfigCode)?></pre>
<?php endif; ?>
<div class="okmsg" style="text-align:left"><?=nl2br(htmlspecialchars($success))?></div>
<div class="warn">⚠️ 请立即删除 install.php！</div>
<div class="btns">
<a href="/index.php" class="btn btn-p">🏠 首页</a>
<a href="/login.php" class="btn btn-s">🔑 登录</a>
</div>
</div>
<?php endif; ?>
</div>
</body></html>
