<?php
/**
 * 首页
 */
$page_title = '首页';
require_once __DIR__ . '/header.php';

$user = currentUser();

// 获取统计数据
try {
    $db = getDB();
    $totalTurtles = $db->query('SELECT COUNT(*) FROM turtles WHERE status = "published"')->fetchColumn();
    $activeRooms = $db->query('SELECT COUNT(*) FROM rooms WHERE status != "ended"')->fetchColumn();
    $totalPlayers = $db->query('SELECT COUNT(*) FROM users WHERE status = "active"')->fetchColumn();
} catch (PDOException $e) {
    $totalTurtles = $activeRooms = $totalPlayers = 0;
}

// 精选汤谱
$featured = [];
try {
    $stmt = $db->prepare('SELECT id, title, difficulty, tags, surface, play_count FROM turtles WHERE status = "published" ORDER BY play_count DESC LIMIT 6');
    $stmt->execute();
    $featured = $stmt->fetchAll();
} catch (PDOException $e) {}
?>

<!-- 英雄区 -->
<section class="hero">
    <h1>🐢 海龟汤推理馆</h1>
    <p>和朋友一起，揭开每一碗汤的谜底。<br>支持 AI 主持和真人主持双模式，免费畅玩。</p>
    <div class="hero-actions">
        <?php if ($user): ?>
        <a href="/turtle-soup/game/create.php" class="btn btn-primary btn-lg">🎮 创建房间</a>
        <a href="/turtle-soup/game/index.php" class="btn btn-secondary btn-lg">🚪 加入房间</a>
        <?php else: ?>
        <a href="/turtle-soup/register.php" class="btn btn-primary btn-lg">🚀 立即开始</a>
        <a href="/turtle-soup/login.php" class="btn btn-secondary btn-lg">🔑 登录</a>
        <?php endif; ?>
        <a href="/turtle-soup/soup/list.php" class="btn btn-secondary btn-lg">📚 浏览汤谱</a>
    </div>
</section>

<!-- 统计数据 -->
<div class="container">
    <div class="stats-grid" style="margin-bottom: 30px;">
        <div class="stat-card">
            <div class="stat-number" data-count="<?= $totalTurtles ?>"><?= $totalTurtles ?></div>
            <div class="stat-label">🐢 收录汤谱</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" data-count="<?= $activeRooms ?>"><?= $activeRooms ?></div>
            <div class="stat-label">🚪 活跃房间</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" data-count="<?= $totalPlayers ?>"><?= $totalPlayers ?></div>
            <div class="stat-label">👥 推理玩家</div>
        </div>
    </div>
</div>

<!-- 特色功能区 -->
<div class="container">
    <div class="feature-grid">
        <div class="feature-card">
            <div class="icon">🤖</div>
            <h3>AI 智能主持</h3>
            <p>接入 DeepSeek / OpenAI 大模型，自动判断你的提问，精准回答"是""否""无关"</p>
        </div>
        <div class="feature-card">
            <div class="icon">🧑‍🤝‍🧑</div>
            <h3>真人主持模式</h3>
            <p>房主手动主持，适合朋友聚会，更有互动感和临场推理乐趣</p>
        </div>
        <div class="feature-card">
            <div class="icon">💬</div>
            <h3>实时聊天推理</h3>
            <p>聊天和提问分离设计，边聊边问，协作推理，找出谜底</p>
        </div>
        <div class="feature-card">
            <div class="icon">📚</div>
            <h3>丰富汤谱库</h3>
            <p>收录许二木全系列经典汤谱，从入门到地狱难度，总有适合你的那一碗</p>
        </div>
    </div>
</div>

<!-- 精选汤谱 -->
<?php if (!empty($featured)): ?>
<div class="container mt-3">
    <h3 style="font-size: 1.2rem; margin-bottom: 18px;">🔥 热门汤谱</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px;">
        <?php foreach ($featured as $t): ?>
        <a href="/turtle-soup/soup/detail.php?id=<?= $t['id'] ?>" class="soup-card" style="display: block; color: inherit;"
           data-title="<?= h($t['title']) ?>"
           data-tags="<?= h($t['tags']) ?>"
           data-difficulty="<?= $t['difficulty'] ?>">
            <div class="soup-title"><?= h($t['title']) ?></div>
            <div class="soup-meta">
                <span class="difficulty-stars"><?= str_repeat('⭐', (int)$t['difficulty']) ?></span>
                <span>▶ <?= (int)$t['play_count'] ?> 次</span>
            </div>
            <?php if ($t['tags']): ?>
            <div style="margin-top: 4px;">
                <?php foreach (array_slice(explode(',', $t['tags']), 0, 3) as $tg): ?>
                <span class="tag"><?= h(trim($tg)) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="soup-abstract"><?= h(mb_substr($t['surface'], 0, 80)) ?>...</div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- 底部 CTA -->
<div class="container text-center mt-3">
    <div class="card" style="background: linear-gradient(135deg, rgba(255,77,106,0.08), rgba(124,77,255,0.08));">
        <h3 style="font-size: 1.3rem; margin-bottom: 8px;">🎉 准备好推理了吗？</h3>
        <p style="color: var(--text-secondary); margin-bottom: 20px;">完全免费，无需下载，打开浏览器就能玩</p>
        <?php if ($user): ?>
        <a href="/turtle-soup/game/create.php" class="btn btn-primary btn-lg">开始推理</a>
        <?php else: ?>
        <a href="/turtle-soup/register.php" class="btn btn-primary btn-lg">立即注册</a>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
