<?php
/**
 * 汤谱库 — 列表 + 搜索
 */
$page_title = '汤谱库';
require_once __DIR__ . '/../header.php';

try {
    $db = getDB();

    // 获取所有标签用于筛选
    $stmt = $db->query('SELECT DISTINCT tags FROM turtles WHERE status = "published" AND tags != ""');
    $allTagsRaw = $stmt->fetchAll();
    $allTags = [];
    foreach ($allTagsRaw as $row) {
        foreach (explode(',', $row['tags']) as $tag) {
            $tag = trim($tag);
            if ($tag) $allTags[$tag] = ($allTags[$tag] ?? 0) + 1;
        }
    }
    arsort($allTags);

    // 获取汤列表
    $difficulty = (int)($_GET['difficulty'] ?? 0);
    $search = trim($_GET['search'] ?? '');
    $tag = trim($_GET['tag'] ?? '');
    $sort = $_GET['sort'] ?? 'newest';

    $sql = 'SELECT * FROM turtles WHERE status = "published" AND parent_id IS NULL';
    $params = [];

    if ($difficulty > 0) {
        $sql .= ' AND difficulty = ?';
        $params[] = $difficulty;
    }
    if ($search) {
        $sql .= ' AND (title LIKE ? OR tags LIKE ? OR surface LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    if ($tag) {
        $sql .= ' AND tags LIKE ?';
        $params[] = "%{$tag}%";
    }

    switch ($sort) {
        case 'popular': $sql .= ' ORDER BY play_count DESC'; break;
        case 'rated': $sql .= ' ORDER BY rating DESC'; break;
        default: $sql .= ' ORDER BY created_at DESC';
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $turtles = $stmt->fetchAll();

} catch (PDOException $e) {
    $turtles = [];
    $allTags = [];
}
?>

<div class="container">
    <div class="flex-between mb-3" style="flex-wrap: wrap; gap: 14px;">
        <h2 style="font-size: 1.5rem;">📚 汤谱库</h2>
        <span class="text-muted">共 <?= count($turtles) ?> 碗汤</span>
    </div>

    <!-- 搜索与筛选 -->
    <div class="card" style="margin-bottom: 20px;">
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px; align-items: end;">
            <div class="form-group" style="margin: 0;">
                <label>🔍 关键词搜索</label>
                <input type="text" id="soup-search" class="form-input" placeholder="搜索汤名、标签..." value="<?= h($search) ?>">
            </div>
            <div class="form-group" style="margin: 0;">
                <label>🏷️ 标签筛选</label>
                <select id="tag-filter" class="form-input">
                    <option value="">全部标签</option>
                    <?php foreach ($allTags as $t => $cnt): ?>
                    <option value="<?= h($t) ?>" <?= $tag === $t ? 'selected' : '' ?>><?= h($t) ?> (<?= $cnt ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label>📊 难度</label>
                <select id="diff-filter" class="form-input">
                    <option value="">全部难度</option>
                    <option value="1" <?= $difficulty === 1 ? 'selected' : '' ?>>⭐ 入门</option>
                    <option value="2" <?= $difficulty === 2 ? 'selected' : '' ?>>⭐⭐ 进阶</option>
                    <option value="3" <?= $difficulty === 3 ? 'selected' : '' ?>>⭐⭐⭐ 地狱</option>
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label>📈 排序</label>
                <select id="sort-filter" class="form-input">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>最新发布</option>
                    <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>最多游玩</option>
                    <option value="rated" <?= $sort === 'rated' ? 'selected' : '' ?>>最高评分</option>
                </select>
            </div>
        </div>
    </div>

    <!-- 汤谱列表 -->
    <?php if (empty($turtles)): ?>
    <div class="card text-center" style="padding: 50px;">
        <div style="font-size: 3rem;">🍲</div>
        <p class="text-muted mt-2">没有找到匹配的汤谱</p>
    </div>
    <?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px;">
        <?php foreach ($turtles as $t): ?>
        <a href="/soup/detail.php?id=<?= $t['id'] ?>" class="soup-card"
           data-title="<?= h($t['title']) ?>"
           data-tags="<?= h($t['tags']) ?>"
           data-difficulty="<?= $t['difficulty'] ?>"
           style="display: block; color: inherit;">
            <div class="soup-title">
                <?= h($t['title']) ?>
                <?php if (isset($t['ai_playable']) && !$t['ai_playable']): ?>
                <span class="badge-inline badge-warn">⚠AI</span>
                <?php endif; ?>
            </div>
            <div class="soup-meta">
                <span class="difficulty-stars"><?= str_repeat('⭐', (int)$t['difficulty']) ?></span>
                <span>▶ <?= (int)($t['play_count'] ?? 0) ?> 次</span>
                <span>👍 <?= (int)($t['like_count'] ?? 0) ?></span>
                <?php
                // 检查是否有子条目（合集标记）
                $childCount = 0;
                try {
                    $cstmt = $db->prepare('SELECT COUNT(*) FROM turtles WHERE parent_id = ? AND status = "published"');
                    $cstmt->execute([$t['id']]);
                    $childCount = (int)$cstmt->fetchColumn();
                } catch (PDOException $e) {}
                ?>
                <?php if ($childCount > 0): ?>
                <span class="badge-inline badge-collection">📦 <?= $childCount ?>合1</span>
                <?php endif; ?>
            </div>
            <?php if ($t['tags']): ?>
            <div style="margin-top: 6px;">
                <?php foreach (explode(',', $t['tags']) as $tg): ?>
                <span class="tag"><?= h(trim($tg)) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="soup-abstract"><?= h(mb_substr($t['surface'], 0, 100)) ?><?= mb_strlen($t['surface']) > 100 ? '...' : '' ?></div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // URL 参数联动筛选
    function applyFilters() {
        const search = document.getElementById('soup-search')?.value || '';
        const tag = document.getElementById('tag-filter')?.value || '';
        const diff = document.getElementById('diff-filter')?.value || '';
        const sort = document.getElementById('sort-filter')?.value || 'newest';

        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (tag) params.set('tag', tag);
        if (diff) params.set('difficulty', diff);
        if (sort && sort !== 'newest') params.set('sort', sort);

        window.location.href = '/soup/list.php' + (params.toString() ? '?' + params.toString() : '');
    }

    ['soup-search', 'tag-filter', 'diff-filter', 'sort-filter'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            if (el.tagName === 'SELECT') {
                el.addEventListener('change', applyFilters);
            } else {
                let timer;
                el.addEventListener('input', () => {
                    clearTimeout(timer);
                    timer = setTimeout(applyFilters, 500);
                });
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
