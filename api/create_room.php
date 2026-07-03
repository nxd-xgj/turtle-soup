<?php
/**
 * API — 创建房间
 * POST: name, mode (ai|human), turtle_id, max_players, max_questions, ai_api_key (optional)
 */
require_once __DIR__ . '/../config.php';
requireLogin();

$user = currentUser();
$name = trim(input('name'));
$mode = input('mode');
$turtle_id = (int)input('turtle_id');
$max_players = (int)(input('max_players') ?: 6);
$max_questions = (int)(input('max_questions') ?: 20);
$ai_api_key = trim(input('ai_api_key'));

if (empty($name)) {
    jsonResponse(['error' => '房间名不能为空'], 400);
}
if (!in_array($mode, ['ai', 'human'])) {
    $mode = 'ai';
}
if ($max_players < 2) $max_players = 2;
if ($max_players > 20) $max_players = 20;
if ($max_questions < 5) $max_questions = 5;
if ($max_questions > 50) $max_questions = 50;

try {
    $db = getDB();

    // 验证汤是否存在
    if ($turtle_id > 0) {
        $stmt = $db->prepare('SELECT id FROM turtles WHERE id = ? AND status = "published"');
        $stmt->execute([$turtle_id]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => '所选汤谱不存在'], 400);
        }
    }

    // 存储 AI API Key 到 session（可选）
    if (!empty($ai_api_key)) {
        $_SESSION['ai_api_key'] = $ai_api_key;
    }

    // 创建房间
    $stmt = $db->prepare('INSERT INTO rooms (name, host_id, mode, turtle_id, max_players, max_questions) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $user['id'], $mode, $turtle_id ?: null, $max_players, $max_questions]);
    $room_id = $db->lastInsertId();

    // 房主自动加入
    $stmt = $db->prepare('INSERT INTO room_players (room_id, user_id) VALUES (?, ?)');
    $stmt->execute([$room_id, $user['id']]);

    // 发送系统消息
    $stmt = $db->prepare('INSERT INTO messages (room_id, user_id, username, content, type) VALUES (?, 0, ?, ?, ?)');
    $stmt->execute([$room_id, '系统', "房间「{$name}」已创建，等待玩家加入...", 'system']);

    jsonResponse(['success' => true, 'room_id' => $room_id]);

} catch (PDOException $e) {
    jsonResponse(['error' => '创建房间失败'], 500);
}
