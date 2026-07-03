<?php
/**
 * 登录页
 */
$page_title = '登录';
require_once __DIR__ . '/header.php';

// 已登录则跳转
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(input('email'));
    $password = input('password');

    if (empty($email) || empty($password)) {
        $error = '请填写邮箱和密码';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'banned') {
                    $error = '该账号已被封禁';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    // 管理员跳转后台，普通用户跳首页
                    $redirect = ($user['role'] === 'admin') ? '/admin/index.php' : '/index.php';
                    header("Location: $redirect");
                    exit;
                }
            } else {
                $error = '邮箱或密码错误';
            }
        } catch (PDOException $e) {
            $error = '系统错误，请稍后再试';
        }
    }
}
?>

<div class="container-sm">
    <div class="card" style="margin-top: 40px;">
        <div class="card-header" style="justify-content: center; font-size: 1.3rem;">
            🐢 欢迎回来
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>邮箱</label>
                <input type="email" name="email" class="form-input" placeholder="请输入邮箱" value="<?= h(input('email')) ?>" required>
            </div>

            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" class="form-input" placeholder="请输入密码" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 8px;">登录</button>
        </form>

        <p class="text-center text-muted mt-2" style="font-size: 0.85rem;">
            还没有账号？<a href="/register.php">立即注册</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
