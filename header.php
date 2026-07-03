<?php
/**
 * 公共头部
 */
require_once __DIR__ . '/config.php';
$user = currentUser();
$current_page = basename($_SERVER['PHP_SELF']);
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? h($page_title) . ' — ' : '' ?><?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/turtle-soup/assets/style.css">
</head>
<body>

<nav class="navbar">
    <a href="/turtle-soup/index.php" class="navbar-brand">
        <span class="brand-icon">🐢</span>海龟汤推理馆
    </a>

    <ul class="navbar-nav">
        <li><a href="/turtle-soup/index.php" <?= $current_page === 'index.php' ? 'class="active"' : '' ?>>首页</a></li>
        <li><a href="/turtle-soup/game/index.php" <?= str_starts_with($current_page, 'game') ? 'class="active"' : '' ?>>游戏大厅</a></li>
        <li><a href="/turtle-soup/soup/list.php" <?= str_starts_with($current_page, 'soup') ? 'class="active"' : '' ?>>汤谱库</a></li>
        <?php if ($user && $user['role'] === 'admin'): ?>
        <li><a href="/turtle-soup/admin/index.php" <?= str_starts_with($current_page, 'admin') ? 'class="active"' : '' ?>>管理后台</a></li>
        <?php endif; ?>
    </ul>

    <div class="navbar-user">
        <?php if ($user): ?>
            <span>👤 <?= h($user['username']) ?></span>
            <span class="badge"><?= $user['role'] === 'admin' ? '管理员' : '玩家' ?></span>
            <a href="/turtle-soup/logout.php" class="btn btn-sm btn-secondary">退出</a>
        <?php else: ?>
            <a href="/turtle-soup/login.php" class="btn btn-sm btn-secondary">登录</a>
            <a href="/turtle-soup/register.php" class="btn btn-sm btn-primary">注册</a>
        <?php endif; ?>
    </div>
</nav>

<main>
