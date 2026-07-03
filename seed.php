<?php
/**
 * 海龟汤种子数据 — 从 data/turtles.json 读取
 * 
 * 使用方法: php seed.php
 * 也可被安装器引入: define('SEED_DATA_ONLY', true); $entries = require 'seed.php';
 */

if (!defined('SEED_DATA_ONLY')) {
    require_once __DIR__ . '/config.php';
    $db = getDB();
}

$jsonFile = __DIR__ . '/data/turtles.json';

if (!file_exists($jsonFile)) {
    $msg = "错误: data/turtles.json 不存在\n";
    if (defined('SEED_DATA_ONLY')) { return []; }
    exit($msg);
}

$json = file_get_contents($jsonFile);
$entries = json_decode($json, true);

if (!is_array($entries)) {
    $msg = "错误: data/turtles.json 解析失败\n";
    if (defined('SEED_DATA_ONLY')) { return []; }
    exit($msg);
}

if (defined('SEED_DATA_ONLY')) {
    return $entries;
}

// ---- 执行导入 ----
$total = count($entries);
$playable = count(array_filter($entries, fn($e) => $e['ai_playable'] ?? true));
$unplayable = $total - $playable;

echo "开始导入海龟汤种子数据...\n";
echo "总计: {$total} 条 (AI可玩: {$playable}, 不可玩: {$unplayable})\n\n";

$stmt = $db->prepare('INSERT INTO turtles (title, surface, bottom, difficulty, tags, ai_prompt, ai_playable, parent_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

$inserted = 0; $skipped = 0;
foreach ($entries as $entry) {
    try {
        $stmt->execute([
            $entry['title'], $entry['surface'], $entry['bottom'],
            $entry['difficulty'] ?? 1, $entry['tags'] ?? '',
            $entry['ai_prompt'] ?? null,
            $entry['ai_playable'] ?? 1,
            $entry['parent_id'] ?? null,
            'published'
        ]);
        $inserted++;
        echo "  ✓ [{$entry['title']}] 导入成功\n";
    } catch (PDOException $e) {
        $skipped++;
        echo "  ✗ [{$entry['title']}] 导入失败: {$e->getMessage()}\n";
    }
}
echo "\n导入完成! 成功: {$inserted} / 失败: {$skipped}\n";
