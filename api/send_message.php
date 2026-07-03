<?php
/**
 * API — 发送消息
 * POST: room_id, content, type (chat|question|answer|system)
 */
require_once __DIR__ . '/../config.php';
requireLogin();

$user = currentUser();
$room_id = (int)input('room_id');
$content = trim(input('content'));
$type = input('type');

if (!$room_id || empty($content) || !in_array($type, ['chat', 'question', 'answer', 'system'])) {
    jsonResponse(['error' => '参数错误'], 400);
}

try {
    $db = getDB();

    // 检查是否在房间中
    $stmt = $db->prepare('SELECT * FROM room_players WHERE room_id = ? AND user_id = ?');
    $stmt->execute([$room_id, $user['id']]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => '你不在该房间中'], 403);
    }

    // 获取房间信息
    $stmt = $db->prepare('SELECT * FROM rooms WHERE id = ?');
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();
    if (!$room) {
        jsonResponse(['error' => '房间不存在'], 404);
    }

    // 插入聊天/提问消息
    if ($type === 'chat' || $type === 'question') {
        $stmt = $db->prepare('INSERT INTO messages (room_id, user_id, username, content, type) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$room_id, $user['id'], $user['username'], $content, $type]);
        $msg_id = $db->lastInsertId();

        // 如果是提问，消耗次数
        if ($type === 'question') {
            $stmt = $db->prepare('UPDATE rooms SET used_questions = used_questions + 1 WHERE id = ?');
            $stmt->execute([$room_id]);
        }

        // AI 主持模式：自动回答
        if ($type === 'question' && $room['mode'] === 'ai' && $room['turtle_id']) {
            // 获取汤数据（含预计算的 ai_prompt）
            $stmt = $db->prepare('SELECT * FROM turtles WHERE id = ?');
            $stmt->execute([$room['turtle_id']]);
            $turtle = $stmt->fetch();

            if ($turtle) {
                // 优先使用预计算提示词
                if (!empty($turtle['ai_prompt'])) {
                    $answer = callAIWithPrompt($turtle['ai_prompt'], $content);
                } else {
                    $answer = callAIWithPrompt($turtle['ai_prompt'] ?? '', $content);
                }

                $stmt = $db->prepare('INSERT INTO messages (room_id, user_id, username, content, type) VALUES (?, 0, ?, ?, ?)');
                $stmt->execute([$room_id, 'AI主持', $answer, 'answer']);
            }
        }

        jsonResponse(['success' => true, 'message_id' => $msg_id]);
    }

    // 真人主持回答（仅房主可用）
    if ($type === 'answer') {
        if ($user['id'] != $room['host_id']) {
            jsonResponse(['error' => '仅房主可发送回答'], 403);
        }
        if (!in_array($content, ['是', '否', '无关'])) {
            jsonResponse(['error' => '回答必须是 是/否/无关'], 400);
        }

        $stmt = $db->prepare('INSERT INTO messages (room_id, user_id, username, content, type) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$room_id, $user['id'], $user['username'] . '(主持)', $content, 'answer']);

        jsonResponse(['success' => true, 'message_id' => $db->lastInsertId()]);
    }

    if ($type === 'system') {
        if ($user['id'] != $room['host_id'] && $user['role'] !== 'admin') {
            jsonResponse(['error' => '仅房主可发送系统消息'], 403);
        }

        $stmt = $db->prepare('INSERT INTO messages (room_id, user_id, username, content, type) VALUES (?, 0, ?, ?, ?)');
        $stmt->execute([$room_id, '系统', $content, 'system']);

        jsonResponse(['success' => true, 'message_id' => $db->lastInsertId()]);
    }

} catch (PDOException $e) {
    jsonResponse(['error' => '服务器错误'], 500);
}

/**
 * 海龟汤 AI 主持人系统
 * 
 * 采用「系统角色设定 + 游戏数据」双提示方式：
 * - 系统消息（固定）：设定 AI 为海龟汤主持人，规定回答规则
 * - 用户消息（动态）：包含 汤面、汤底、关键点 + 玩家当前提问
 */

// AI 主持人的系统角色设定（固定不变）
define('AI_HOST_SYSTEM_PROMPT', '你是一个海龟汤游戏主持人。你的任务是引导玩家通过提问还原故事真相。

【你的权限】
- 你掌握汤面和汤底的全部信息
- 只回答「是」「否」「无关」三个字之一
- 绝不主动透露汤底或给出提示

【回答规则】
- 「是」：提问方向正确，与汤底关键信息一致
- 「否」：提问方向错误，与汤底矛盾或不可能
- 「无关」：提问内容与汤底无关，或无法用是否判断

【游戏结束条件】
- 当玩家提出的问题触及了「关键点」（故事的核心反转），回答「是」，并附加一句「🎉 你猜中了关键点！游戏结束！」然后公布汤底
- 如果玩家主动要求放弃，直接公布汤底');

/**
 * 使用 AI 主持（系统提示词 + 游戏数据方式）
 */
function callAIWithPrompt(string $aiPrompt, string $question): string {
    $apiKey = $_SESSION['ai_api_key'] ?? '';

    if (empty($apiKey)) {
        return simpleMatchFromPrompt($aiPrompt, $question);
    }

    // 从 ai_prompt 中提取汤面、汤底、关键点
    $surface = '';
    $bottom = '';
    $keyPoint = '';

    if (preg_match('/【汤面】\s*(.+?)(?:\n【汤底】|$)/us', $aiPrompt, $m)) {
        $surface = trim($m[1]);
    }
    if (preg_match('/【汤底】\s*(.+?)(?:\n【关键点】|$)/us', $aiPrompt, $m)) {
        $bottom = trim($m[1]);
    }
    if (preg_match('/【关键点】\s*(.+?)$/us', $aiPrompt, $m)) {
        $keyPoint = trim($m[1]);
    }

    // 如果 ai_prompt 已经是旧格式（包含【关键判断线索】），降级处理
    if (empty($surface) && empty($bottom)) {
        return callAILegacy($aiPrompt, $question);
    }

    $gameData = "【汤面】\n{$surface}\n\n【汤底（绝密）】\n{$bottom}\n\n【关键点】\n{$keyPoint}\n\n玩家提问：{$question}";

    $ch = curl_init(AI_API_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => AI_MODEL,
            'messages' => [
                ['role' => 'system', 'content' => AI_HOST_SYSTEM_PROMPT],
                ['role' => 'user', 'content' => $gameData],
            ],
            'max_tokens' => 150,
            'temperature' => AI_TEMPERATURE,
        ], JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        $answer = trim($data['choices'][0]['message']['content'] ?? '');

        // 检查是否包含标准回答
        if (in_array($answer, ['是', '否', '无关'])) {
            return $answer;
        }

        // 检查是否包含 "🎉 你猜中了关键点" 等游戏结束信息
        if (mb_strpos($answer, '猜中') !== false || mb_strpos($answer, '🎉') !== false) {
            return '是';
        }
    }

    return simpleMatchFromPrompt($aiPrompt, $question);
}

/**
 * 旧格式兼容：ai_prompt 是完整提示词（含规则）
 */
function callAILegacy(string $aiPrompt, string $question): string {
    $apiKey = $_SESSION['ai_api_key'] ?? '';
    if (empty($apiKey)) {
        return simpleMatchFromPrompt($aiPrompt, $question);
    }

    $prompt = $aiPrompt . "\n\n玩家提问：「{$question}」\n请只回复「是」「否」「无关」三个字中的一个。";

    $ch = curl_init(AI_API_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => AI_MODEL,
            'messages' => [
                ['role' => 'system', 'content' => AI_HOST_SYSTEM_PROMPT],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => AI_MAX_TOKENS,
            'temperature' => AI_TEMPERATURE,
        ], JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        $answer = trim($data['choices'][0]['message']['content'] ?? '');
        if (in_array($answer, ['是', '否', '无关'])) {
            return $answer;
        }
    }

    return simpleMatchFromPrompt($aiPrompt, $question);
}

/**
 * 离线关键词匹配
 */
function simpleMatchFromPrompt(string $aiPrompt, string $question): string {
    $questionLower = mb_strtolower($question);

    // 从提示词中提取关键点或关键判断线索
    $cluesText = '';

    if (preg_match('/【关键点】\s*(.+?)$/us', $aiPrompt, $m)) {
        $cluesText = $m[1];
    } elseif (preg_match('/【关键判断线索】\s*(.+?)(?:【|$)/us', $aiPrompt, $m)) {
        $cluesText = $m[1];
    }

    if ($cluesText) {
        $lines = preg_split('/[\r\n]+/', trim($cluesText));
        $matchCount = 0;
        $clueKeywords = [];

        foreach ($lines as $line) {
            $line = trim($line, "- •· \t");
            if (mb_strlen($line) < 3) continue;
            $lineLower = mb_strtolower($line);

            for ($i = 0; $i < mb_strlen($line) - 2; $i++) {
                $slice = mb_substr($line, $i, min(4, mb_strlen($line) - $i));
                if (mb_strlen($slice) >= 3) {
                    $clueKeywords[] = mb_strtolower($slice);
                }
            }
        }

        $clueKeywords = array_unique($clueKeywords);
        foreach ($clueKeywords as $kw) {
            if (mb_strpos($questionLower, $kw) !== false) {
                $matchCount++;
            }
        }

        if ($matchCount >= 2) return '是';
        if ($matchCount >= 1) return '是';
    }

    // 检查明显否定词
    $negatives = ['不', '没有', '不是', '没', '无', '假', '错'];
    foreach ($negatives as $neg) {
        if (mb_strpos($question, $neg) === 0 || mb_strpos($question, ' ' . $neg) !== false) {
            return '无关';
        }
    }

    return '无关';
}

