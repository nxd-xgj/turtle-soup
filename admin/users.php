<?php
/**
 * 管理后台 — 用户管理
 */
$page_title = '用户管理';
require_once __DIR__ . '/../header.php';
requireAdmin();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('action');
    $target_id = (int)input('user_id');

    try {
        $db = getDB();

        if ($action === 'ban') {
            $stmt = $db->prepare('UPDATE users SET status = "banned" WHERE id = ? AND role != "admin"');
            $stmt->execute([$target_id]);
            $message = '用户已封禁';
            $messageType = 'success';
        }
        elseif ($action === 'unban') {
            $stmt = $db->prepare('UPDATE users SET status = "active" WHERE id = ?');
            $stmt->execute([$target_id]);
            $message = '用户已解封';
            $messageType = 'success';
        }
        elseif ($action === 'set_role') {
            $newRole = input('role');
            if (in_array($newRole, ['user', 'admin'])) {
                $stmt = $db->prepare('UPDATE users SET role = ? WHERE id = ?');
                $stmt->execute([$newRole, $target_id]);
                $message = '角色已更新';
                $messageType = 'success';
            }
        }
    } catch (PDOException $e) {
        $message = '操作失败';
        $messageType = 'error';
    }
}

// 获取用户列表
try {
    $db = getDB();
    $stmt = $db->query('SELECT * FROM users ORDER BY created_at DESC');
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
}
?>

<div class="container-lg">
    <h2 style="font-size: 1.5rem; margin-bottom: 24px;">⚙️ 管理后台</h2>

    <div class="admin-layout">
        <div class="admin-sidebar">
            <a href="/admin/index.php">📊 仪表盘</a>
            <a href="/admin/soups.php">🐢 汤谱管理</a>
            <a href="/admin/users.php" class="active">👥 用户管理</a>
            <a href="/admin/ai_manual.php">🤖 AI 主持手册</a>
        </div>

        <div>
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= h($message) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">👥 用户列表（共 <?= count($users) ?> 人）</div>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>昵称</th>
                                <th>邮箱</th>
                                <th>角色</th>
                                <th>状态</th>
                                <th>注册时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><?= h($u['username']) ?></td>
                                <td><?= h($u['email']) ?></td>
                                <td>
                                    <span class="tag <?= $u['role'] === 'admin' ? 'tag-active' : '' ?>">
                                        <?= $u['role'] === 'admin' ? '管理员' : '用户' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="tag <?= $u['status'] === 'active' ? 'tag-active' : '' ?>">
                                        <?= $u['status'] === 'active' ? '正常' : '已封禁' ?>
                                    </span>
                                </td>
                                <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <div class="flex-center" style="gap: 4px;">
                                        <?php if ($u['status'] === 'active' && $u['role'] !== 'admin'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('确定封禁该用户？')">
                                            <input type="hidden" name="action" value="ban">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">封禁</button>
                                        </form>
                                        <?php elseif ($u['status'] === 'banned'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="unban">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-green">解封</button>
                                        </form>
                                        <?php endif; ?>

                                        <?php if ($u['role'] !== 'admin'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('确定提升为管理员？')">
                                            <input type="hidden" name="action" value="set_role">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="role" value="admin">
                                            <button type="submit" class="btn btn-sm btn-secondary">升管理</button>
                                        </form>
                                        <?php else: ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('确定降级为普通用户？')">
                                            <input type="hidden" name="action" value="set_role">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="role" value="user">
                                            <button type="submit" class="btn btn-sm btn-secondary">降级</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted" style="font-size: 0.8rem;">当前账号</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
