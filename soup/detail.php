<?php
/**
 * 汤谱详情页
 */
$page_title = '汤谱详情';
require_once __DIR__ . '/../header.php';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    echo '<div class="container"><div class="alert alert-error">汤谱不存在</div></div>';
    require_once __DIR__ . '/../footer.php';
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare('SELECT t.*, u.username as author_name FROM turtles t LEFT JOIN users u ON t.author_id = u.id WHERE t.id = ?');
    $stmt->execute([$id]);
    $turtle = $stmt->fetch();

    if (!$turtle) {
        echo '<div class="container"><div class="alert alert-error">汤谱不存在</div></div>';
        require_once __DIR__ . '/../footer.php';
        exit;
    }

    // 获取子条目（如果是合集）
    $children = [];
    if ($turtle['parent_id'] === null) {
        $cstmt = $db->prepare('SELECT * FROM turtles WHERE parent_id = ? AND status = "published" ORDER BY id');
        $cstmt->execute([$id]);
        $children = $cstmt->fetchAll();
    }

    // 获取父合集（如果是子条目）
    $parent = null;
    if ($turtle['parent_id']) {
        $pstmt = $db->prepare('SELECT id, title FROM turtles WHERE id = ?');
        $pstmt->execute([$turtle['parent_id']]);
        $parent = $pstmt->fetch();
    }
} catch (PDOException $e) {
    echo '<div class="container"><div class="alert alert-error">数据库错误</div></div>';
    require_once __DIR__ . '/../footer.php';
    exit;
}
?>

<div class="container">
    <!-- 返回按钮 -->
    <a href="/soup/list.php" class="btn btn-secondary btn-sm mb-2">← 返回汤谱库</a>

    <div class="card">
        <div class="flex-between" style="flex-wrap: wrap; gap: 12px;">
            <div>
                <h2 style="font-size: 1.4rem; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    🐢 <?= h($turtle['title']) ?>
                    <?php if (isset($turtle['ai_playable']) && !$turtle['ai_playable']): ?>
                    <span class="badge-inline badge-warn">⚠️ AI不适配 — 建议真人主持</span>
                    <?php endif; ?>
                    <?php if (!empty($children)): ?>
                    <span class="badge-inline badge-collection">📦 合集 · <?= count($children) ?>个子条目</span>
                    <?php endif; ?>
                    <?php if ($parent): ?>
                    <span class="badge-inline" style="background: rgba(124,77,255,0.15); color: var(--purple); border: 1px solid rgba(124,77,255,0.3); padding: 2px 12px; border-radius: 12px; font-size: 0.75rem;">
                        📦 合集子条目
                    </span>
                    <?php endif; ?>
                </h2>
            </div>
            <div class="flex-center" style="gap: 10px;">
                <span class="difficulty-stars"><?= str_repeat('⭐', (int)$turtle['difficulty']) ?></span>
                <span class="text-muted">|</span>
                <span>▶ <?= (int)$turtle['play_count'] ?> 次游玩</span>
                <span>👍 <?= (int)$turtle['like_count'] ?> 赞</span>
            </div>
        </div>

        <?php if ($parent): ?>
        <div style="margin-top: 8px;">
            <a href="/soup/detail.php?id=<?= $parent['id'] ?>" class="btn btn-sm btn-secondary">
                ← 返回合集：<?= h($parent['title']) ?>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($turtle['tags']): ?>
        <div style="margin: 12px 0;">
            <?php foreach (explode(',', $turtle['tags']) as $tag): ?>
            <a href="/soup/list.php?tag=<?= urlencode(trim($tag)) ?>" class="tag"><?= h(trim($tag)) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($turtle['author_name']): ?>
        <p class="text-muted text-small">投稿人：<?= h($turtle['author_name']) ?> · <?= date('Y-m-d', strtotime($turtle['created_at'])) ?></p>
        <?php endif; ?>
    </div>

    <!-- 汤面 -->
    <div class="card" style="border-left: 3px solid var(--orange);">
        <h3 style="font-size: 1rem; color: var(--orange); margin-bottom: 12px;">📜 汤面</h3>
        <p style="font-size: 1.05rem; line-height: 2; white-space: pre-wrap;"><?= h($turtle['surface']) ?></p>
    </div>

    <!-- 汤底（点击展开） -->
    <div class="card" style="border-left: 3px solid var(--green);">
        <div style="cursor: pointer;" onclick="toggleBottom()">
            <h3 style="font-size: 1rem; color: var(--green); margin-bottom: 0; display: flex; align-items: center; gap: 8px;">
                🔍 汤底
                <span id="bottom-toggle" style="font-size: 0.8rem; color: var(--text-muted);">点击展开</span>
            </h3>
        </div>
        <div id="bottom-content" style="display: none; margin-top: 12px;">
            <div class="alert alert-info">
                ⚠️ <strong>剧透警告：</strong>以下是完整汤底，推荐先尝试推理再看！
            </div>
            <p style="font-size: 1.05rem; line-height: 2; white-space: pre-wrap;"><?= h($turtle['bottom']) ?></p>
        </div>
    </div>

    <!-- 关键线索 -->
    <?php if (!empty($turtle['clues'])): ?>
    <div class="card" style="border-left: 3px solid var(--blue);">
        <h3 style="font-size: 1rem; color: var(--blue); margin-bottom: 12px;">💡 关键线索链</h3>
        <p style="line-height: 1.8; white-space: pre-wrap;"><?= h($turtle['clues']) ?></p>
    </div>
    <?php endif; ?>

    <!-- 操作按钮 -->
    <div class="flex-center mt-2" style="gap: 12px;">
        <a href="/game/create.php" class="btn btn-primary">🎮 用这碗汤创建房间</a>
        <button class="btn btn-secondary" onclick="likeSoup()">👍 点赞</button>
    </div>

    <!-- 合集子条目列表 -->
    <?php if (!empty($children)): ?>
    <div class="card mt-2" style="border-left: 3px solid var(--purple);">
        <h3 style="font-size: 1rem; color: var(--purple); margin-bottom: 16px;">📦 本合集包含以下子条目</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 10px;">
            <?php foreach ($children as $ch): ?>
            <a href="/soup/detail.php?id=<?= $ch['id'] ?>" class="soup-card" style="display: flex; align-items: center; gap: 10px; padding: 14px; color: inherit; text-decoration: none;">
                <span class="difficulty-stars"><?= str_repeat('⭐', (int)$ch['difficulty']) ?></span>
                <span style="flex: 1;"><?= h($ch['title']) ?></span>
                <?php if (isset($ch['ai_playable']) && !$ch['ai_playable']): ?>
                <span class="badge-inline badge-warn">⚠AI</span>
                <?php endif; ?>
                <span style="color: var(--accent);">→</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleBottom() {
    const content = document.getElementById('bottom-content');
    const toggle = document.getElementById('bottom-toggle');
    if (content.style.display === 'none') {
        content.style.display = 'block';
        content.style.animation = 'fadeSlideIn 0.4s ease';
        toggle.textContent = '点击收起';
    } else {
        content.style.display = 'none';
        toggle.textContent = '点击展开';
    }
}

async function likeSoup() {
    const resp = await fetch('/api/like_soup.php', {
        method: 'POST',
        body: new URLSearchParams({ turtle_id: <?= $id ?> })
    });
    const data = await resp.json();
    if (data.success) {
        TurtleChat.showToast('已点赞！');
        setTimeout(() => location.reload(), 500);
    } else if (data.error) {
        TurtleChat.showToast(data.error);
    }
}
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
