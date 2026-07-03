-- 海龟汤网站 数据库建表脚本
-- 适用于 MySQL 5.7+ / MariaDB 10+

CREATE DATABASE IF NOT EXISTS turtle_soup DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE turtle_soup;

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT '',
    role ENUM('user','admin') DEFAULT 'user',
    status ENUM('active','banned') DEFAULT 'active',
    token_balance INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 海龟汤表
CREATE TABLE IF NOT EXISTS turtles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    surface TEXT NOT NULL,
    bottom TEXT NOT NULL,
    clues TEXT DEFAULT NULL,
    difficulty TINYINT DEFAULT 1,
    tags VARCHAR(255) DEFAULT '',
    author_id INT,
    status ENUM('draft','published','hidden') DEFAULT 'published',
    play_count INT DEFAULT 0,
    like_count INT DEFAULT 0,
    rating DECIMAL(2,1) DEFAULT 0.0,
    ai_prompt TEXT DEFAULT NULL COMMENT '预计算的 AI 主持提示词',
    ai_playable TINYINT DEFAULT 1 COMMENT 'AI 能否主持: 1=可以 0=不适合',
    parent_id INT DEFAULT NULL COMMENT '多合一汤的子条目指向父汤 ID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 房间表
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    host_id INT NOT NULL,
    mode ENUM('ai','human') DEFAULT 'ai',
    turtle_id INT,
    status ENUM('waiting','playing','ended') DEFAULT 'waiting',
    max_players INT DEFAULT 6,
    max_questions INT DEFAULT 20,
    used_questions INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 房间玩家表
CREATE TABLE IF NOT EXISTS room_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    join_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_room_user (room_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 消息表
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT,
    username VARCHAR(50),
    content TEXT NOT NULL,
    type ENUM('chat','question','answer','system') DEFAULT 'chat',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_room_time (room_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 游戏记录表
CREATE TABLE IF NOT EXISTS game_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    turtle_id INT NOT NULL,
    questions_count INT DEFAULT 0,
    guessed ENUM('yes','no') DEFAULT 'no',
    score INT DEFAULT 0,
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 插入默认管理员（密码 admin123，上线后请立即修改）
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@turtlesoup.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- 密码提示: password
