<?php
/**
 * 管理后台 — 仪表盘
 */
$page_title = '管理后台';
require_once __DIR__ . '/../header.php';
requireAdmin();

try {
    $db = getDB();

    $totalUsers = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $totalTurtles = $db->query('SELECT COUNT(*) FROM turtles')->fetchColumn();
    $totalRooms = $db->query('SELECT COUNT(*) FROM rooms')->fetchColumn();
    $totalMessages = $db->query('SELECT COUNT(*) FROM messages')->fetchColumn();
    $todayRooms = $db->query('SELECT COUNT(*) FROM rooms WHERE DATE(created_at) = CURDATE()')->fetchColumn();
    $activeUsers = $db->query('SELECT COUNT(*) FROM users WHERE status = "active"')->fetchColumn();
} catch (PDOException $e) {
    $totalUsers = $totalTurtles = $totalRooms = $totalMessages = $todayRooms = $activeUsers = 0;
}

$current_admin_page = 'dashboard';
?>

<div class="container-lg">
    <h2 style="font-size: 1.5rem; margin-bottom: 24px;">⚙️ 管理后台</h2>

    <div class="admin-layout">
        <div class="admin-sidebar">
            <a href="/admin/index.php" class="active">📊 仪表盘</a>
            <a href="/admin/soups.php">🐢 汤谱管理</a>
            <a href="/admin/users.php">👥 用户管理</a>
        </div>

        <div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number" data-count="<?= $totalUsers ?>"><?= $totalUsers ?></div>
                    <div class="stat-label">总用户数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" data-count="<?= $activeUsers ?>"><?= $activeUsers ?></div>
                    <div class="stat-label">活跃用户</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" data-count="<?= $totalTurtles ?>"><?= $totalTurtles ?></div>
                    <div class="stat-label">汤谱总数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" data-count="<?= $totalRooms ?>"><?= $totalRooms ?></div>
                    <div class="stat-label">总房间数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" data-count="<?= $todayRooms ?>"><?= $todayRooms ?></div>
                    <div class="stat-label">今日创建</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" data-count="<?= $totalMessages ?>"><?= $totalMessages ?></div>
                    <div class="stat-label">总消息数</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">🚀 快速操作</div>
                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <a href="/admin/soups.php" class="btn btn-primary">管理汤谱</a>
                    <a href="/admin/users.php" class="btn btn-secondary">管理用户</a>
                    <a href="/game/index.php" class="btn btn-secondary">查看大厅</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
