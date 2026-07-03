<?php
/**
 * API — 开始游戏 / 结束游戏 / 更新房间状态
 * POST: room_id, action (start|end|update), status, turtle_id
 * 仅房主可操作
 */
require_once __DIR__ . '/../config.php';
requireLogin();

$user = currentUser();
$room_id = (int)input('room_id');
$action = input('action');

if (!$room_id || !$action) {
    jsonResponse(['error' => '参数错误'], 400);
}

try {
    $db = getDB();

    $stmt = $db->prepare('SELECT * FROM rooms WHERE id = ?');
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();

    if (!$room) {
        jsonResponse(['error' => '房间不存在'], 404);
    }

    if ($user['id'] != $room['host_id'] && $user['role'] !== 'admin') {
        jsonResponse(['error' => '仅房主可操作'], 403);
    }

    if ($action === 'start') {
        if ($room['status'] !== 'waiting') {
            jsonResponse(['error' => '游戏已经开始或已结束'], 400);
        }
        if (!$room['turtle_id']) {
            jsonResponse(['error' => '请先选择汤谱'], 400);
        }

        $stmt = $db->prepare('UPDATE rooms SET status = "playing", used_questions = 0 WHERE id = ?');
        $stmt->execute([$room_id]);

        // 系统消息：公布汤面
        $stmt2 = $db->prepare('SELECT surface FROM turtles WHERE id = ?');
        $stmt2->execute([$room['turtle_id']]);
        $turtle = $stmt2->fetch();
        $surface = $turtle['surface'] ?? '（无汤面）';

        $stmt3 = $db->prepare('INSERT INTO messages (room_id, user_id, username, content, type) VALUES (?, 0, ?, ?, ?)');
        $stmt3->execute([$room_id, '系统', '🎮 游戏开始！', 'system']);
        $stmt3->execute([$room_id, '系统', "📜 汤面：{$surface}", 'system']);
        $stmt3->execute([$room_id, '系统', "💡 主持模式：" . ($room['mode'] === 'ai' ? 'AI主持' : '真人主持') . " | 最多提问 {$room['max_questions']} 次", 'system']);

        jsonResponse(['success' => true, 'status' => 'playing']);
    }
    elseif ($action === 'end') {
        $stmt = $db->prepare('UPDATE rooms SET status = "ended" WHERE id = ?');
        $stmt->execute([$room_id]);

        // 公布汤底
        if ($room['turtle_id']) {
            $stmt2 = $db->prepare('SELECT title, bottom FROM turtles WHERE id = ?');
            $stmt2->execute([$room['turtle_id']]);
            $turtle = $stmt2->fetch();

            $stmt3 = $db->prepare('INSERT INTO messages (room_id, user_id, username, content, type) VALUES (?, 0, ?, ?, ?)');
            $stmt3->execute([$room_id, '系统', '🏁 游戏结束！', 'system']);
            $stmt3->execute([$room_id, '系统', "🔍 汤底揭秘：{$turtle['bottom']}", 'system']);
        }

        jsonResponse(['success' => true, 'status' => 'ended']);
    }
    elseif ($action === 'update') {
        $turtle_id = (int)input('turtle_id');
        $mode = input('mode');
        $max_questions = (int)input('max_questions');

        $updates = [];
        $params = [];

        if ($turtle_id > 0) {
            $updates[] = 'turtle_id = ?';
            $params[] = $turtle_id;
        }
        if (in_array($mode, ['ai', 'human'])) {
            $updates[] = 'mode = ?';
            $params[] = $mode;
        }
        if ($max_questions >= 5 && $max_questions <= 50) {
            $updates[] = 'max_questions = ?';
            $params[] = $max_questions;
        }

        if (!empty($updates)) {
            $params[] = $room_id;
            $stmt = $db->prepare('UPDATE rooms SET ' . implode(', ', $updates) . ' WHERE id = ?');
            $stmt->execute($params);
        }

        jsonResponse(['success' => true]);
    }
    else {
        jsonResponse(['error' => '未知操作'], 400);
    }

} catch (PDOException $e) {
    jsonResponse(['error' => '服务器错误'], 500);
}
