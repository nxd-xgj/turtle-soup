<?php
/**
 * 注册页
 */
$page_title = '注册';
require_once __DIR__ . '/header.php';

if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(input('username'));
    $email = trim(input('email'));
    $password = input('password');
    $confirm = input('confirm_password');

    if (empty($username) || empty($email) || empty($password)) {
        $error = '所有字段都必须填写';
    } elseif (strlen($username) < 2 || strlen($username) > 50) {
        $error = '昵称长度需在 2-50 个字符之间';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '邮箱格式不正确';
    } elseif (strlen($password) < 6) {
        $error = '密码长度不能少于 6 位';
    } elseif ($password !== $confirm) {
        $error = '两次输入的密码不一致';
    } else {
        try {
            $db = getDB();

            // 检查邮箱/用户名是否已存在
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
            $stmt->execute([$email, $username]);
            if ($stmt->fetch()) {
                $error = '该邮箱或昵称已被注册';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
                $stmt->execute([$username, $email, $hash]);
                $success = true;
            }
        } catch (PDOException $e) {
            $error = '注册失败，请稍后再试';
        }
    }
}
?>

<div class="container-sm">
    <div class="card" style="margin-top: 40px;">
        <div class="card-header" style="justify-content: center; font-size: 1.3rem;">
            🥚 创建新账号
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success">
            🎉 注册成功！<a href="/login.php">点击这里登录</a>
        </div>
        <?php elseif ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label>昵称</label>
                <input type="text" name="username" class="form-input" placeholder="你的游戏昵称（2-50字）" value="<?= h(input('username')) ?>" required>
            </div>

            <div class="form-group">
                <label>邮箱</label>
                <input type="email" name="email" class="form-input" placeholder="请输入邮箱" value="<?= h(input('email')) ?>" required>
            </div>

            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" class="form-input" placeholder="至少 6 位密码" required>
            </div>

            <div class="form-group">
                <label>确认密码</label>
                <input type="password" name="confirm_password" class="form-input" placeholder="再次输入密码" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 8px;">注册</button>
        </form>

        <p class="text-center text-muted mt-2" style="font-size: 0.85rem;">
            已有账号？<a href="/login.php">立即登录</a>
        </p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
