<?php
/**
 * 房间页 — 最核心的聊天+提问+游戏界面
 */
$page_title = '推理房间';
require_once __DIR__ . '/../header.php';
requireLogin();

$user = currentUser();
$room_id = (int)($_GET['id'] ?? 0);

if (!$room_id) {
    echo '<div class="container"><div class="alert alert-error">房间不存在</div></div>';
    require_once __DIR__ . '/../footer.php';
    exit;
}

try {
    $db = getDB();

    // 房间信息
    $stmt = $db->prepare('
        SELECT r.*, u.username as host_name, t.title as turtle_title,
               t.surface as turtle_surface, t.bottom as turtle_bottom,
               t.difficulty as turtle_difficulty, t.tags as turtle_tags,
               t.ai_playable, t.parent_id, t.ai_prompt
        FROM rooms r
        JOIN users u ON r.host_id = u.id
        LEFT JOIN turtles t ON r.turtle_id = t.id
        WHERE r.id = ?
    ');
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();

    if (!$room) {
        echo '<div class="container"><div class="alert alert-error">房间不存在</div></div>';
        require_once __DIR__ . '/../footer.php';
        exit;
    }

    // 检查是否已加入（未加入则自动加入）
    $stmt = $db->prepare('SELECT * FROM room_players WHERE room_id = ? AND user_id = ?');
    $stmt->execute([$room_id, $user['id']]);
    $isPlayer = (bool)$stmt->fetch();

    if (!$isPlayer && $room['status'] !== 'ended') {
        // 检查人数
        $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM room_players WHERE room_id = ?');
        $stmt->execute([$room_id]);
        $count = $stmt->fetch()['cnt'];

        if ($count < $room['max_players']) {
            $stmt = $db->prepare('INSERT IGNORE INTO room_players (room_id, user_id) VALUES (?, ?)');
            $stmt->execute([$room_id, $user['id']]);

            $stmt = $db->prepare('INSERT INTO messages (room_id, user_id, username, content, type) VALUES (?, 0, ?, ?, ?)');
            $stmt->execute([$room_id, '系统', "{$user['username']} 加入了房间", 'system']);
            $isPlayer = true;
        }
    }

    if (!$isPlayer && $room['status'] === 'ended') {
        // 已结束的房间允许查看
        $isPlayer = true;
    }

    if (!$isPlayer) {
        echo '<div class="container"><div class="alert alert-error">房间已满，无法加入</div></div>';
        require_once __DIR__ . '/../footer.php';
        exit;
    }

    // 获取玩家列表
    $stmt = $db->prepare('
        SELECT u.id, u.username, u.role, rp.join_time
        FROM room_players rp
        JOIN users u ON rp.user_id = u.id
        WHERE rp.room_id = ?
        ORDER BY rp.join_time ASC
    ');
    $stmt->execute([$room_id]);
    $players = $stmt->fetchAll();

    // 获取最新消息
    $stmt = $db->prepare('SELECT id, room_id, user_id, username, content, type, created_at FROM messages WHERE room_id = ? ORDER BY id ASC LIMIT 100');
    $stmt->execute([$room_id]);
    $messages = $stmt->fetchAll();

    $isHost = ($user['id'] == $room['host_id']);
    $isPlaying = ($room['status'] === 'playing');

} catch (PDOException $e) {
    echo '<div class="container"><div class="alert alert-error">数据库错误</div></div>';
    require_once __DIR__ . '/../footer.php';
    exit;
}

// 获取可选汤谱（用于房主切换）
$turtles = [];
try {
    $stmt = $db->prepare('SELECT id, title, difficulty, tags, surface, ai_playable, parent_id FROM turtles WHERE status = "published" AND parent_id IS NULL ORDER BY created_at DESC LIMIT 150');
    $stmt->execute();
    $turtles = $stmt->fetchAll();
} catch (PDOException $e) {}

// 获取当前汤的子条目（如果是合集）
$children = [];
if ($room['turtle_id']) {
    try {
        $stmt = $db->prepare('SELECT id, title, difficulty, ai_playable FROM turtles WHERE parent_id = ? AND status = "published" ORDER BY id');
        $stmt->execute([$room['turtle_id']]);
        $children = $stmt->fetchAll();
    } catch (PDOException $e) {}
}
?>

<div class="container-lg">
    <!-- 顶部信息栏 -->
    <div class="card" style="margin-bottom: 14px;">
        <div class="flex-between" style="flex-wrap: wrap; gap: 12px;">
            <div class="flex-center" style="gap: 16px;">
                <h3 style="font-size: 1.2rem;"><?= h($room['name']) ?></h3>
                <span id="room-status-badge" class="room-status status-<?= $room['status'] ?>">
                    <?= ['waiting' => '等待中', 'playing' => '游戏中', 'ended' => '已结束'][$room['status']] ?>
                </span>
            </div>
            <div class="flex-center" style="gap: 14px; font-size: 0.85rem; color: var(--text-secondary);">
                <span>👑 <?= h($room['host_name']) ?></span>
                <span><?= $room['mode'] === 'ai' ? '🤖 AI主持' : '🧑 真人主持' ?></span>
                <span>🐢 <?= h($room['turtle_title'] ?: '未选汤') ?></span>
                <?php if ($room['turtle_id'] && isset($room['ai_playable']) && !$room['ai_playable']): ?>
                <span class="badge badge-warning" style="background: rgba(255,145,0,0.2); color: var(--orange); border: 1px solid rgba(255,145,0,0.4);">⚠️ AI不适配</span>
                <?php endif; ?>
                <?php if ($room['turtle_id'] && isset($room['parent_id']) && $room['parent_id']): ?>
                <span class="badge" style="background: rgba(124,77,255,0.15); color: var(--purple); border: 1px solid rgba(124,77,255,0.3);">📦 合集子条目</span>
                <?php endif; ?>
                <span>👥 <?= count($players) ?>/<?= $room['max_players'] ?> 人</span>
                <?php if ($isPlaying): ?>
                <span>❓ 已提问: <?= $room['used_questions'] ?>/<?= $room['max_questions'] ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- 房主控制面板 -->
        <?php if ($isHost): ?>
        <div style="margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border); display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <?php if ($room['status'] === 'waiting'): ?>
            <button class="btn btn-green btn-sm" onclick="startGame()">▶ 开始游戏</button>
            <?php endif; ?>

            <?php if ($isPlaying): ?>
            <button class="btn btn-danger btn-sm" onclick="endGame()">⏹ 结束游戏</button>
            <?php endif; ?>

            <?php if ($room['status'] !== 'playing'): ?>
            <select id="select-turtle" class="form-input" style="width: auto; min-width: 220px;" onchange="updateRoomTurtle()">
                <option value="0">— 选择汤谱 —</option>
                <?php foreach ($turtles as $t): ?>
                <option value="<?= $t['id'] ?>" <?= (int)$room['turtle_id'] === (int)$t['id'] ? 'selected' : '' ?>
                    <?= !$t['ai_playable'] ? 'class="ai-unplayable-opt"' : '' ?>>
                    <?= str_repeat('⭐', (int)$t['difficulty']) ?> <?= h($t['title']) ?>
                    <?= !$t['ai_playable'] ? '[⚠AI]' : '' ?>
                    <?= $t['parent_id'] ? '[📦子]' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>

            <?php if (!empty($children)): ?>
            <select id="select-child" class="form-input" style="width: auto; min-width: 200px;" onchange="updateRoomChild()">
                <option value="0">— 选择子条目 —</option>
                <?php foreach ($children as $ch): ?>
                <option value="<?= $ch['id'] ?>">
                    <?= str_repeat('⭐', (int)$ch['difficulty']) ?> <?= h($ch['title']) ?>
                    <?= !$ch['ai_playable'] ? '[⚠AI]' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <select id="select-mode" class="form-input" style="width: auto;" onchange="updateRoomMode()">
                <option value="ai" <?= $room['mode'] === 'ai' ? 'selected' : '' ?>>🤖 AI主持</option>
                <option value="human" <?= $room['mode'] === 'human' ? 'selected' : '' ?>>🧑 真人主持</option>
            </select>
            <?php endif; ?>

            <?php if ($isPlaying && $room['mode'] === 'human'): ?>
            <span class="text-muted" style="font-size: 0.85rem;">回答：</span>
            <button class="btn btn-green btn-sm host-answer-btn" data-answer="是">是</button>
            <button class="btn btn-danger btn-sm host-answer-btn" data-answer="否">否</button>
            <button class="btn btn-secondary btn-sm host-answer-btn" data-answer="无关">无关</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 布局：聊天区 + 侧边玩家列表 -->
    <div style="display: grid; grid-template-columns: 1fr 240px; gap: 16px;">
        <!-- 聊天区 -->
        <div class="chat-container">
            <div class="chat-messages" id="chat-messages">
                <?php foreach ($messages as $msg): ?>
                <div class="chat-message msg-<?= $msg['type'] ?>">
                    <?php if ($msg['type'] !== 'system'): ?>
                    <div class="msg-header">
                        <span class="msg-username"><?= h($msg['username'] ?: '系统') ?></span>
                        <span class="msg-time"><?= date('H:i', strtotime($msg['created_at'])) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="msg-body">
                        <?php if ($msg['type'] === 'answer'): ?>
                        <span class="answer-badge answer-<?= h($msg['content']) ?>"><?= h($msg['content']) ?></span>
                        <?php else: ?>
                        <?= nl2br(h($msg['content'])) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="input-area">
                <?php if ($isPlaying): ?>
                <div class="input-row">
                    <input type="text" id="ask-input" placeholder="向主持提问（消耗提问次数）" <?= $room['used_questions'] >= $room['max_questions'] ? 'disabled' : '' ?>>
                    <button class="btn btn-primary" id="ask-send" <?= $room['used_questions'] >= $room['max_questions'] ? 'disabled' : '' ?>>
                        🎯 提问 (<span id="question-remaining"><?= max(0, $room['max_questions'] - $room['used_questions']) ?></span>)
                    </button>
                </div>
                <?php endif; ?>
                <div class="input-row">
                    <input type="text" id="chat-input" placeholder="输入聊天消息...">
                    <button class="btn btn-secondary" id="chat-send">💬 发送</button>
                </div>
            </div>
        </div>

        <!-- 玩家列表 -->
        <div class="card" style="height: fit-content;">
            <div class="card-header" style="font-size: 0.95rem;">👥 玩家 (<?= count($players) ?>)</div>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach ($players as $p): ?>
                <div style="display: flex; align-items: center; gap: 8px; padding: 6px 0; font-size: 0.85rem;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: <?= $p['id'] == $room['host_id'] ? 'var(--accent)' : 'var(--green)' ?>; display: inline-block;"></span>
                    <span><?= h($p['username']) ?></span>
                    <?php if ($p['id'] == $room['host_id']): ?>
                    <span style="font-size: 0.7rem; color: var(--accent);">房主</span>
                    <?php endif; ?>
                    <?php if ($p['role'] === 'admin'): ?>
                    <span style="font-size: 0.7rem; color: var(--purple);">管理</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($room['turtle_difficulty']): ?>
            <div class="mt-2" style="padding-top: 12px; border-top: 1px solid var(--border);">
                <p style="font-size: 0.8rem; color: var(--text-muted);">难度：<span class="difficulty-stars"><?= str_repeat('⭐', (int)$room['turtle_difficulty']) ?></span></p>
                <?php if ($room['turtle_tags']): ?>
                <p style="font-size: 0.8rem; color: var(--text-muted);">标签：
                    <?php foreach (explode(',', $room['turtle_tags']) as $tag): ?>
                    <span class="tag"><?= h(trim($tag)) ?></span>
                    <?php endforeach; ?>
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 汤面展示（游戏中显示） -->
    <?php if ($isPlaying && $room['turtle_surface']): ?>
    <div class="card mt-2" style="border-left: 3px solid var(--accent);">
        <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 6px;">📜 当前汤面</p>
        <p style="font-size: 1rem; line-height: 1.8;"><?= nl2br(h($room['turtle_surface'])) ?></p>
    </div>
    <?php endif; ?>
</div>

<script>
const ROOM_ID = <?= $room_id ?>;
const MAX_QUESTIONS = <?= $room['max_questions'] ?>;
const IS_HOST = <?= $isHost ? 'true' : 'false' ?>;
const ROOM_MODE = '<?= $room['mode'] ?>';

document.addEventListener('DOMContentLoaded', () => {
    // 初始化聊天引擎
    TurtleChat.init(ROOM_ID, MAX_QUESTIONS);

    // 初始化主持人面板
    if (IS_HOST && ROOM_MODE === 'human') {
        HostPanel.init(ROOM_ID);
    }
});

// 房主操作函数
async function startGame() {
    if (!confirm('开始游戏？开始后玩家将看到汤面并可以提问。')) return;
    const resp = await fetch('/turtle-soup/api/room_action.php', {
        method: 'POST',
        body: new URLSearchParams({ room_id: ROOM_ID, action: 'start' })
    });
    const data = await resp.json();
    if (data.success) location.reload();
    else alert(data.error || '操作失败');
}

async function endGame() {
    if (!confirm('确定结束游戏？将公布汤底。')) return;
    const resp = await fetch('/turtle-soup/api/room_action.php', {
        method: 'POST',
        body: new URLSearchParams({ room_id: ROOM_ID, action: 'end' })
    });
    const data = await resp.json();
    if (data.success) location.reload();
    else alert(data.error || '操作失败');
}

async function updateRoomTurtle() {
    const turtleId = document.getElementById('select-turtle').value;
    await fetch('/turtle-soup/api/room_action.php', {
        method: 'POST',
        body: new URLSearchParams({ room_id: ROOM_ID, action: 'update', turtle_id: turtleId })
    });
    location.reload();
}

async function updateRoomMode() {
    const mode = document.getElementById('select-mode').value;
    await fetch('/turtle-soup/api/room_action.php', {
        method: 'POST',
        body: new URLSearchParams({ room_id: ROOM_ID, action: 'update', mode: mode })
    });
    location.reload();
}

async function updateRoomChild() {
    const childId = document.getElementById('select-child').value;
    if (!childId || childId === '0') return;
    await fetch('/turtle-soup/api/room_action.php', {
        method: 'POST',
        body: new URLSearchParams({ room_id: ROOM_ID, action: 'update', turtle_id: childId })
    });
    location.reload();
}
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
