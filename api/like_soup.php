<?php
/**
 * API — 点赞汤谱
 * POST: turtle_id
 */
require_once __DIR__ . '/../config.php';
requireLogin();

$user = currentUser();
$turtle_id = (int)input('turtle_id');

if (!$turtle_id) {
    jsonResponse(['error' => '参数错误'], 400);
}

try {
    $db = getDB();
    $stmt = $db->prepare('UPDATE turtles SET like_count = like_count + 1 WHERE id = ?');
    $stmt->execute([$turtle_id]);
    jsonResponse(['success' => true]);
} catch (PDOException $e) {
    jsonResponse(['error' => '点赞失败'], 500);
}
