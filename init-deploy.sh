#!/bin/bash
# ==================================================
#   本地部署脚本 — 在 InfinityFree 上初始化
#   用法: bash init-deploy.sh
# ==================================================
echo "===== 海龟汤推理馆 — 初始化部署 ====="
echo ""
echo "1️⃣  请先通过 InfinityFree 面板创建 MySQL 数据库"
echo "   创建后会得到类似这样的信息："
echo "   - 数据库主机: sqlXXX.infinityfree.com"
echo "   - 数据库名:   if0_XXXXXXX_xxx"
echo "   - 用户名:     if0_XXXXXXX"
echo "   - 密码:       [你设置的密码]"
echo ""
echo "2️⃣  将以上信息填入 config.php 的 DB 配置"
echo ""
echo "3️⃣  上传所有文件到 htdocs 目录"
echo ""
echo "4️⃣  访问 https://你的域名.free.nf/db.sql"
echo "   复制内容到 phpMyAdmin 执行建表"
echo ""
echo "5️⃣  在 phpMyAdmin 中执行:"
echo "   php seed.php"
echo ""
echo "6️⃣  访问网站首页，使用 admin@turtlesoup.local / password 登录"
echo ""
echo "===== 完成! ====="
