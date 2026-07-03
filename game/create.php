<?php
/**
 * 创建房间
 */
$page_title = '创建房间';
require_once __DIR__ . '/../header.php';
requireLogin();

$user = currentUser();
$error = '';
$success = false;

// 获取可选汤谱
try {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, title, difficulty, tags FROM turtles WHERE status = "published" ORDER BY created_at DESC LIMIT 100');
    $stmt->execute();
    $turtles = $stmt->fetchAll();
} catch (PDOException $e) {
    $turtles = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(input('name'));
    $mode = input('mode');
    $turtle_id = (int)input('turtle_id');
    $max_players = (int)(input('max_players') ?: 6);
    $max_questions = (int)(input('max_questions') ?: 20);
    $ai_api_key = trim(input('ai_api_key'));

    if (empty($name)) {
        $error = '房间名不能为空';
    } else {
        try {
            $db = getDB();
            if (!empty($ai_api_key)) {
                $_SESSION['ai_api_key'] = $ai_api_key;
            }

            $stmt = $db->prepare('INSERT INTO rooms (name, host_id, mode, turtle_id, max_players, max_questions) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $user['id'], $mode, $turtle_id ?: null, $max_players, $max_questions]);
            $room_id = $db->lastInsertId();

            // 房主加入
            $stmt = $db->prepare('INSERT INTO room_players (room_id, user_id) VALUES (?, ?)');
            $stmt->execute([$room_id, $user['id']]);

            // 系统消息
            $stmt = $db->prepare('INSERT INTO messages (room_id, user_id, username, content, type) VALUES (?, 0, ?, ?, ?)');
            $stmt->execute([$room_id, '系统', "房间「{$name}」已创建，等待玩家加入...", 'system']);

            $success = true;
            $redirect_id = $room_id;
        } catch (PDOException $e) {
            $error = '创建房间失败，请重试';
        }
    }
}
?>

<div class="container-sm">
    <div class="card" style="margin-top: 30px;">
        <div class="card-header">
            🏠 创建推理房间
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success">
            🎉 房间创建成功！<br>
            <a href="/turtle-soup/game/room.php?id=<?= $redirect_id ?>" class="btn btn-primary mt-2">进入房间</a>
        </div>
        <?php else: ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>房间名称</label>
                <input type="text" name="name" class="form-input" placeholder="给你的推理派对取个名字" value="<?= h(input('name')) ?>" required>
            </div>

            <div class="form-group">
                <label>主持模式</label>
                <select name="mode" class="form-input">
                    <option value="ai" <?= input('mode') === 'ai' ? 'selected' : '' ?>>🤖 AI 主持 — 大模型自动判断提问</option>
                    <option value="human" <?= input('mode') === 'human' ? 'selected' : '' ?>>🧑 真人主持 — 房主手动回答</option>
                </select>
            </div>

            <div class="form-group" id="ai-key-group">
                <label>AI API Key（可选，AI 主持模式需要）</label>
                <input type="password" name="ai_api_key" class="form-input" placeholder="DeepSeek / OpenAI API Key" value="<?= h(input('ai_api_key')) ?>">
                <p class="input-hint mt-1">支持 DeepSeek 和 OpenAI 兼容接口。不填则使用离线关键词匹配。</p>
            </div>

            <div class="form-group">
                <label>选择汤谱</label>
                <select name="turtle_id" class="form-input">
                    <option value="0">— 稍后选择 —</option>
                    <?php foreach ($turtles as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= (int)input('turtle_id') === (int)$t['id'] ? 'selected' : '' ?>>
                        <?= str_repeat('⭐', (int)$t['difficulty']) ?> <?= h($t['title']) ?>
                        <?= $t['tags'] ? '[' . h($t['tags']) . ']' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label>最大玩家数</label>
                    <select name="max_players" class="form-input">
                        <?php for ($i = 2; $i <= 10; $i++): ?>
                        <option value="<?= $i ?>" <?= (int)input('max_players', '6') === $i ? 'selected' : '' ?>><?= $i ?> 人</option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>最大提问次数</label>
                    <select name="max_questions" class="form-input">
                        <?php foreach ([10, 15, 20, 25, 30] as $n): ?>
                        <option value="<?= $n ?>" <?= (int)input('max_questions', '20') === $n ? 'selected' : '' ?>><?= $n ?> 次</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 8px;">
                🐢 创建房间
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
// 切换主持模式时显示/隐藏 API Key 输入
document.querySelector('select[name="mode"]')?.addEventListener('change', function() {
    const group = document.getElementById('ai-key-group');
    if (group) group.style.display = this.value === 'ai' ? '' : 'none';
});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
