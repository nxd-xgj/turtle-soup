<?php
/**
 * 游戏大厅 — 房间列表
 */
$page_title = '游戏大厅';
require_once __DIR__ . '/../header.php';
requireLogin();

$user = currentUser();

try {
    $db = getDB();

    // 获取所有活跃房间
    $stmt = $db->prepare('
        SELECT r.*, u.username as host_name,
            (SELECT COUNT(*) FROM room_players WHERE room_id = r.id) as player_count,
            t.title as turtle_title
        FROM rooms r
        JOIN users u ON r.host_id = u.id
        LEFT JOIN turtles t ON r.turtle_id = t.id
        WHERE r.status != "ended"
        ORDER BY r.status = "waiting" DESC, r.created_at DESC
    ');
    $stmt->execute();
    $rooms = $stmt->fetchAll();
} catch (PDOException $e) {
    $rooms = [];
}
?>

<div class="container">
    <div class="flex-between mb-3">
        <div>
            <h2 style="font-size: 1.5rem;">🎮 游戏大厅</h2>
            <p class="text-muted text-small mt-1">选择一个房间加入，或创建自己的推理派对</p>
        </div>
        <a href="/game/create.php" class="btn btn-primary btn-lg">✨ 创建房间</a>
    </div>

    <?php if (empty($rooms)): ?>
    <div class="card text-center" style="padding: 60px 20px;">
        <div style="font-size: 3rem; margin-bottom: 16px;">🪹</div>
        <h3>还没有活跃的房间</h3>
        <p class="text-muted mt-1">快来创建第一个推理派对吧！</p>
        <a href="/game/create.php" class="btn btn-primary mt-2">创建房间</a>
    </div>
    <?php else: ?>
    <div class="room-grid">
        <?php foreach ($rooms as $room): ?>
        <a href="/game/room.php?id=<?= $room['id'] ?>" class="room-card" style="display: block; color: inherit;">
            <div class="room-title">
                <?= h($room['name']) ?>
            </div>
            <div class="room-meta">
                <span class="room-status status-<?= $room['status'] ?>">
                    <?= ['waiting' => '等待中', 'playing' => '游戏中', 'ended' => '已结束'][$room['status']] ?>
                </span>
                <span>👤 <?= h($room['host_name']) ?></span>
                <span>👥 <?= $room['player_count'] ?>/<?= $room['max_players'] ?></span>
                <span><?= $room['mode'] === 'ai' ? '🤖 AI主持' : '🧑 真人主持' ?></span>
                <?php if ($room['turtle_title']): ?>
                <span>🐢 <?= h($room['turtle_title']) ?></span>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // 每 10 秒刷新房间列表
    setInterval(() => {
        fetch(window.location.href)
            .then(resp => resp.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newGrid = doc.querySelector('.room-grid');
                const oldGrid = document.querySelector('.room-grid');
                if (newGrid && oldGrid) {
                    oldGrid.innerHTML = newGrid.innerHTML;
                    // 重新初始化 tilt 等效果
                    if (typeof window.initTiltCards === 'function') {
                        window.initTiltCards();
                    }
                }
            })
            .catch(() => {});
    }, 10000);
});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
