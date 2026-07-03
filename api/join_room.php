<?php
/**
 * API — 加入房间
 * POST: room_id
 */
require_once __DIR__ . '/../config.php';
requireLogin();

$user = currentUser();
$room_id = (int)input('room_id');

if (!$room_id) {
    jsonResponse(['error' => '缺少 room_id'], 400);
}

try {
    $db = getDB();

    // 查询房间
    $stmt = $db->prepare('SELECT * FROM rooms WHERE id = ?');
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();

    if (!$room) {
        jsonResponse(['error' => '房间不存在'], 404);
    }

    if ($room['status'] === 'ended') {
        jsonResponse(['error' => '房间已结束'], 400);
    }

    // 检查人数
    $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM room_players WHERE room_id = ?');
    $stmt->execute([$room_id]);
    $count = $stmt->fetch()['cnt'];

    if ($count >= $room['max_players']) {
        jsonResponse(['error' => '房间已满'], 400);
    }

    // 加入房间（忽略重复加入）
    $stmt = $db->prepare('INSERT IGNORE INTO room_players (room_id, user_id) VALUES (?, ?)');
    $stmt->execute([$room_id, $user['id']]);

    // 系统消息
    $stmt = $db->prepare('INSERT INTO messages (room_id, user_id, username, content, type) VALUES (?, 0, ?, ?, ?)');
    $stmt->execute([$room_id, '系统', "{$user['username']} 加入了房间", 'system']);

    jsonResponse(['success' => true, 'room_id' => $room_id]);

} catch (PDOException $e) {
    jsonResponse(['error' => '加入房间失败'], 500);
}
