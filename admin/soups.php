<?php
/**
 * 管理后台 — 汤谱管理
 */
$page_title = '汤谱管理';
require_once __DIR__ . '/../header.php';
requireAdmin();

$message = '';
$messageType = '';

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('action');
    $soup_id = (int)input('soup_id');

    try {
        $db = getDB();

        if ($action === 'add' || $action === 'edit') {
            $title = trim(input('title'));
            $surface = trim(input('surface'));
            $bottom = trim(input('bottom'));
            $clues = trim(input('clues'));
            $difficulty = (int)input('difficulty');
            $tags = trim(input('tags'));
            $status = input('status');

            if (empty($title) || empty($surface) || empty($bottom)) {
                $message = '标题、汤面、汤底不能为空';
                $messageType = 'error';
            } else {
                if ($action === 'add') {
                    $stmt = $db->prepare('INSERT INTO turtles (title, surface, bottom, clues, difficulty, tags, author_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$title, $surface, $bottom, $clues, max(1, min(3, $difficulty)), $tags, $_SESSION['user_id'], $status]);
                    $message = '汤谱添加成功！';
                } else {
                    $stmt = $db->prepare('UPDATE turtles SET title=?, surface=?, bottom=?, clues=?, difficulty=?, tags=?, status=? WHERE id=?');
                    $stmt->execute([$title, $surface, $bottom, $clues, max(1, min(3, $difficulty)), $tags, $status, $soup_id]);
                    $message = '汤谱更新成功！';
                }
                $messageType = 'success';
            }
        }
        elseif ($action === 'status') {
            $newStatus = input('new_status');
            $stmt = $db->prepare('UPDATE turtles SET status = ? WHERE id = ?');
            $stmt->execute([$newStatus, $soup_id]);
            $message = '状态已更新';
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        $message = '操作失败：' . $e->getMessage();
        $messageType = 'error';
    }
}

// 获取汤列表
try {
    $db = getDB();
    $stmt = $db->query('SELECT * FROM turtles ORDER BY created_at DESC');
    $turtles = $stmt->fetchAll();
} catch (PDOException $e) {
    $turtles = [];
}

// 编辑模式
$editMode = false;
$editTurtle = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($turtles as $t) {
        if ((int)$t['id'] === $editId) {
            $editMode = true;
            $editTurtle = $t;
            break;
        }
    }
}
?>

<div class="container-lg">
    <h2 style="font-size: 1.5rem; margin-bottom: 24px;">⚙️ 管理后台</h2>

    <div class="admin-layout">
        <div class="admin-sidebar">
            <a href="/turtle-soup/admin/index.php">📊 仪表盘</a>
            <a href="/turtle-soup/admin/soups.php" class="active">🐢 汤谱管理</a>
            <a href="/turtle-soup/admin/users.php">👥 用户管理</a>
        </div>

        <div>
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= h($message) ?></div>
            <?php endif; ?>

            <!-- 添加/编辑表单 -->
            <div class="card">
                <div class="card-header">
                    <?= $editMode ? '✏️ 编辑汤谱' : '➕ 新增汤谱' ?>
                    <?php if ($editMode): ?>
                    <a href="/turtle-soup/admin/soups.php" class="btn btn-sm btn-secondary">取消编辑</a>
                    <?php endif; ?>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="<?= $editMode ? 'edit' : 'add' ?>">
                    <?php if ($editMode): ?>
                    <input type="hidden" name="soup_id" value="<?= $editTurtle['id'] ?>">
                    <?php endif; ?>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label>标题</label>
                            <input type="text" name="title" class="form-input" value="<?= h($editTurtle['title'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>难度</label>
                            <select name="difficulty" class="form-input">
                                <option value="1" <?= ($editTurtle['difficulty'] ?? 1) == 1 ? 'selected' : '' ?>>⭐ 入门</option>
                                <option value="2" <?= ($editTurtle['difficulty'] ?? 1) == 2 ? 'selected' : '' ?>>⭐⭐ 进阶</option>
                                <option value="3" <?= ($editTurtle['difficulty'] ?? 1) == 3 ? 'selected' : '' ?>>⭐⭐⭐ 地狱</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>标签（逗号分隔）</label>
                        <input type="text" name="tags" class="form-input" placeholder="本格, 变格, 恐怖, 科幻..." value="<?= h($editTurtle['tags'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>汤面（谜面）</label>
                        <textarea name="surface" class="form-input" rows="3" required><?= h($editTurtle['surface'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>汤底（谜底）</label>
                        <textarea name="bottom" class="form-input" rows="4" required><?= h($editTurtle['bottom'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>关键线索链</label>
                        <textarea name="clues" class="form-input" rows="2"><?= h($editTurtle['clues'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>状态</label>
                        <select name="status" class="form-input">
                            <option value="published" <?= ($editTurtle['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>已发布</option>
                            <option value="draft" <?= ($editTurtle['status'] ?? '') === 'draft' ? 'selected' : '' ?>>草稿</option>
                            <option value="hidden" <?= ($editTurtle['status'] ?? '') === 'hidden' ? 'selected' : '' ?>>隐藏</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <?= $editMode ? '💾 保存修改' : '➕ 添加汤谱' ?>
                    </button>
                </form>
            </div>

            <!-- 汤谱列表 -->
            <div class="card">
                <div class="card-header">📋 汤谱列表（共 <?= count($turtles) ?> 碗）</div>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>标题</th>
                                <th>难度</th>
                                <th>标签</th>
                                <th>状态</th>
                                <th>游玩</th>
                                <th>点赞</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($turtles as $t): ?>
                            <tr>
                                <td><?= $t['id'] ?></td>
                                <td><a href="/turtle-soup/soup/detail.php?id=<?= $t['id'] ?>"><?= h($t['title']) ?></a></td>
                                <td><?= str_repeat('⭐', (int)$t['difficulty']) ?></td>
                                <td style="font-size: 0.78rem;"><?= h($t['tags']) ?></td>
                                <td>
                                    <span class="tag <?= $t['status'] === 'published' ? 'tag-active' : '' ?>">
                                        <?= ['published' => '已发布', 'draft' => '草稿', 'hidden' => '隐藏'][$t['status']] ?>
                                    </span>
                                </td>
                                <td><?= $t['play_count'] ?></td>
                                <td><?= $t['like_count'] ?></td>
                                <td>
                                    <div class="flex-center" style="gap: 4px;">
                                        <a href="?edit=<?= $t['id'] ?>" class="btn btn-sm btn-secondary">编辑</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('确定更改状态？')">
                                            <input type="hidden" name="action" value="status">
                                            <input type="hidden" name="soup_id" value="<?= $t['id'] ?>">
                                            <?php if ($t['status'] === 'published'): ?>
                                            <input type="hidden" name="new_status" value="hidden">
                                            <button type="submit" class="btn btn-sm btn-danger">下架</button>
                                            <?php else: ?>
                                            <input type="hidden" name="new_status" value="published">
                                            <button type="submit" class="btn btn-sm btn-green">上架</button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($turtles)): ?>
                            <tr><td colspan="8" class="text-center text-muted">还没有汤谱</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
