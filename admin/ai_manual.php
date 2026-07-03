<?php
/**
 * 🤖 AI 主持人手册 — 使用指南
 */
$page_title = 'AI 主持手册';
require_once __DIR__ . '/../header.php';
requireAdmin();
?>

<div class="container-lg">
    <h2 style="font-size:1.5rem;margin-bottom:24px;">🤖 AI 主持人手册</h2>

    <div class="admin-layout">
        <div class="admin-sidebar">
            <a href="/admin/index.php">📊 仪表盘</a>
            <a href="/admin/soups.php">🐢 汤谱管理</a>
            <a href="/admin/users.php">👥 用户管理</a>
            <a href="/admin/ai_manual.php" class="active">🤖 AI 主持手册</a>
        </div>

        <div>
            <!-- 概览 -->
            <div class="card" style="border-left:3px solid var(--accent);">
                <h3 style="margin-bottom:12px;">🧠 AI 主持是怎么工作的</h3>
                <p>AI 主持模式使用大语言模型（如 DeepSeek、OpenAI）自动判断玩家提问。每碗汤都配有<b>预计算的主持提示词</b>，AI 主持人会根据提示词中的「汤面」「汤底」「关键判断线索」来判断玩家的提问，回答「是」「否」或「无关」。</p>
            </div>

            <!-- 提示词模板 -->
            <div class="card" style="border-left:3px solid var(--blue);">
                <h3 style="margin-bottom:12px;">📝 提示词结构</h3>
                <p style="color:var(--text-secondary);margin-bottom:12px;">每碗汤的 ai_prompt 字段包含以下内容，可在汤谱管理中编辑：</p>
                <pre style="background:#0a0a1a;border:1px solid rgba(255,255,255,0.06);border-radius:8px;padding:16px;font-size:0.82rem;line-height:1.7;overflow-x:auto;">
你是一个海龟汤游戏的主持人。你的唯一任务是根据汤底判断玩家提问，回答「是」「否」或「无关」。

【汤面（谜面，玩家可见）】
（此处展示汤面原文）

【汤底（谜底，绝密，不可泄露）】
（此处展示汤底原文）

【关键判断线索】
• 线索1：提取自汤底的关键事实
• 线索2：提取自汤底的关键事实
• 线索3：...

【主持规则】
- 只回复「是」「否」「无关」三个字中的一个
- 「是」= 提问方向与汤底关键事实一致
- 「否」= 提问方向与汤底矛盾
- 「无关」= 提问不在汤底覆盖范围
- 绝对不要透露汤底或给出提示
                </pre>
            </div>

            <!-- 配置 -->
            <div class="card" style="border-left:3px solid var(--green);">
                <h3 style="margin-bottom:12px;">⚙️ API 配置</h3>
                <p style="color:var(--text-secondary);margin-bottom:12px;">创建房间时可以在「AI API Key」处填写你的 Key：</p>
                <table class="data-table">
                    <thead><tr><th>配置项</th><th>说明</th><th>默认值</th></tr></thead>
                    <tbody>
                        <tr><td>API 端点</td><td>兼容 OpenAI API 格式的接口地址</td><td><code>https://api.deepseek.com/v1/chat/completions</code></td></tr>
                        <tr><td>模型</td><td>支持 DeepSeek、OpenAI 等模型</td><td><code>deepseek-chat</code></td></tr>
                        <tr><td>Temperature</td><td>回答随机性，越低越确定</td><td>0.3</td></tr>
                        <tr><td>无 API Key 时</td><td>自动降级为离线关键词匹配</td><td>效果有限</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- AI 不适配 -->
            <div class="card" style="border-left:3px solid var(--orange);">
                <h3 style="margin-bottom:12px;">⚠️ 哪些汤不适合 AI 主持</h3>
                <p style="color:var(--text-secondary);margin-bottom:12px;">以下类型的汤谱标记为 <span class="badge-inline badge-warn">⚠AI</span>，建议使用真人主持模式：</p>
                <table class="data-table">
                    <thead><tr><th>类型</th><th>示例</th><th>原因</th></tr></thead>
                    <tbody>
                        <tr><td>纯数字/密码谜题</td><td>《熵增》36 39 42 25</td><td>需要数学推理，AI 无法理解数字模式</td></tr>
                        <tr><td>单行笑话/脑筋急转弯</td><td>《老农》「因为我是公鸡」</td><td>只有一个反转点，AI 容易剧透或判断错误</td></tr>
                        <tr><td>历史文化知识依赖</td><td>《被困的大哥》西游记梗</td><td>需要特定文化背景知识才能推理</td></tr>
                        <tr><td>规则怪谈类</td><td>《动物园规则怪谈》《灰姑娘规则怪谈》</td><td>规则体系复杂，AI 难以准确判断每个提问</td></tr>
                        <tr><td>合集容器</td><td>各类 N 合 1</td><td>容器本身不包含迷题，需选择子条目</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- 最佳实践 -->
            <div class="card" style="border-left:3px solid var(--purple);">
                <h3 style="margin-bottom:12px;">💡 最佳实践</h3>
                <ul style="color:var(--text-secondary);line-height:2;padding-left:20px;">
                    <li><b>AI 主持 + 简单难度汤</b>：体验最佳，适合新手快速上手</li>
                    <li><b>真人主持 + 困难汤</b>：适合老手聚会，更有互动感和临场感</li>
                    <li><b>使用 DeepSeek</b>：免费且中文理解能力强，性价比最高</li>
                    <li><b>提问次数设为 20-25 次</b>：太少不够推理，太多降低挑战性</li>
                    <li><b>AI 不适配的汤请用真人主持</b>：标记了 ⚠AI 的汤谱，AI 主持效果差</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
