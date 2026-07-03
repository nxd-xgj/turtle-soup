<?php
/**
 * 数据导出工具 — 把 seed.php 的汤谱数据导出为 JSON
 * 访问: https://你的域名/export_data.php
 * 然后保存返回的内容为 data/turtles.json
 */
require_once __DIR__ . '/config.php';

// 只取数据，不执行插入
define('SEED_DATA_ONLY', true);
$entries = require __DIR__ . '/seed.php';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename=turtles.json');
echo json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
