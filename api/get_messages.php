<?php
/**
 * API — 获取消息（轮询）
 * GET: room_id, after_id
 */
require_once __DIR__ . '/../config.php';
requireLogin();

$user = currentUser();
$room_id = (int)($_GET['room_id'] ?? 0);
$after_id = (int)($_GET['after_id'] ?? 0);

if (!$room_id) {
    jsonResponse(['error' => '缺少 room_id'], 400);
}

try {
    $db = getDB();

    // 检查是否在房间中
    $stmt = $db->prepare('SELECT * FROM room_players WHERE room_id = ? AND user_id = ?');
    $stmt->execute([$room_id, $user['id']]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => '你不在该房间中'], 403);
    }

    // 获取新消息
    $stmt = $db->prepare('SELECT id, room_id, user_id, username, content, type, created_at FROM messages WHERE room_id = ? AND id > ? ORDER BY id ASC LIMIT 50');
    $stmt->execute([$room_id, $after_id]);
    $messages = $stmt->fetchAll();

    // 获取房间状态和提问计数
    $stmt = $db->prepare('SELECT status, used_questions, max_questions FROM rooms WHERE id = ?');
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();

    jsonResponse([
        'messages' => $messages,
        'room_status' => $room['status'] ?? 'waiting',
        'question_count' => (int)($room['used_questions'] ?? 0),
        'max_questions' => (int)($room['max_questions'] ?? 20),
    ]);

} catch (PDOException $e) {
    jsonResponse(['error' => '服务器错误'], 500);
}
