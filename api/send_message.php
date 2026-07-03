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
                    $answer = callAILegacy($turtle['surface'], $turtle['bottom'], $turtle['clues'] ?? '', $content);
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
 * 使用预计算的 AI 提示词来回答问题
 * 提示词中已包含汤面、汤底和判断线索，只需追回玩家提问
 */
function callAIWithPrompt(string $aiPrompt, string $question): string {
    $apiKey = $_SESSION['ai_api_key'] ?? '';

    if (empty($apiKey)) {
        return simpleMatchFromPrompt($aiPrompt, $question);
    }

    // 用预计算提示词 + 用户提问组合成完整的 messages
    $systemPrompt = $aiPrompt . "\n\n现在玩家提问：「{$question}」\n请只回复「是」「否」「无关」三个字中的一个。";

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
                ['role' => 'system', 'content' => '你是一个海龟汤游戏主持人。只回复「是」「否」「无关」三个字。'],
                ['role' => 'user', 'content' => $systemPrompt],
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
 * 离线关键词匹配（从预计算提示词中提取线索）
 */
function simpleMatchFromPrompt(string $aiPrompt, string $question): string {
    $questionLower = mb_strtolower($question);

    // 从提示词中提取「关键判断线索」段落
    if (preg_match('/【关键判断线索】\s*(.+?)(?:【|$)/us', $aiPrompt, $m)) {
        $cluesText = $m[1];
        $lines = preg_split('/[\r\n]+/', trim($cluesText));
        $matchCount = 0;

        foreach ($lines as $line) {
            $line = trim($line, "- •· \t");
            if (mb_strlen($line) < 3) continue;
            $lineLower = mb_strtolower($line);

            // 提取线索中的关键词（3字以上）
            for ($i = 0; $i < mb_strlen($line) - 2; $i++) {
                $slice = mb_substr($line, $i, min(4, mb_strlen($line) - $i));
                if (mb_strlen($slice) >= 3 && mb_strpos($questionLower, mb_strtolower($slice)) !== false) {
                    $matchCount++;
                    break;
                }
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

/**
 * 旧版 AI 调用（无预计算提示词时降级使用）
 */
function callAILegacy(string $surface, string $bottom, string $clues, string $question): string {
    $apiKey = $_SESSION['ai_api_key'] ?? '';
    if (empty($apiKey)) {
        return simpleMatchLegacy($surface, $bottom, $clues, $question);
    }

    $prompt = "你是一个海龟汤游戏的主持人。\n汤面（谜面）：{$surface}\n汤底（谜底）：{$bottom}\n关键线索：{$clues}\n\n玩家提问：{$question}\n\n请判断玩家的提问，回答「是」「否」或「无关」。\n规则：\n- 如果玩家的提问与谜底的关键线索直接相关，回答「是」\n- 如果玩家的提问与谜底矛盾或明显错误，回答「否」\n- 如果玩家的提问与谜底毫无关系或者不构成有效提问，回答「无关」\n- 只回复「是」「否」「无关」三个字中的一个，不要输出任何其他内容";

    $ch = curl_init(AI_API_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => AI_MODEL,
            'messages' => [['role' => 'user', 'content' => $prompt]],
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

    return simpleMatchLegacy($surface, $bottom, $clues, $question);
}

function simpleMatchLegacy(string $surface, string $bottom, string $clues, string $question): string {
    $questionLower = mb_strtolower($question);
    $matchCount = 0;
    for ($i = 0; $i < mb_strlen($bottom) - 1; $i++) {
        $slice = mb_substr($bottom, $i, min(3, mb_strlen($bottom) - $i));
        if (mb_strlen($slice) >= 3 && mb_strpos($questionLower, mb_strtolower($slice)) !== false) {
            $matchCount++;
        }
    }
    if ($matchCount >= 2) return '是';
    if ($matchCount >= 1) return '是';
    return '无关';
}
