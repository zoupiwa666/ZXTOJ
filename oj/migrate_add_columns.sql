-- ZXTOJ 增量迁移：补齐 init.sql 旧版本缺失的表/列（已建库的新机器执行）
-- 用法: mariadb -uroot -pxxx < migrate_add_columns.sql   （幂等，可重复执行）
USE judge_problems;

-- 1. users.tag
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='tag');
SET @s := IF(@c=0, 'ALTER TABLE users ADD COLUMN tag VARCHAR(5) DEFAULT NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2. problems.solution_open
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='problems' AND COLUMN_NAME='solution_open');
SET @s := IF(@c=0, 'ALTER TABLE problems ADD COLUMN solution_open TINYINT(1) DEFAULT 1', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 3. chat_messages.is_read
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='chat_messages' AND COLUMN_NAME='is_read');
SET @s := IF(@c=0, 'ALTER TABLE chat_messages ADD COLUMN is_read TINYINT(1) DEFAULT 0', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 4. articles 三列
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='articles' AND COLUMN_NAME='is_solution');
SET @s := IF(@c=0, 'ALTER TABLE articles ADD COLUMN is_solution TINYINT(1) DEFAULT 0, ADD COLUMN solution_problem VARCHAR(20) DEFAULT NULL, ADD COLUMN solution_status VARCHAR(10) DEFAULT NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 4.5 users.ojcid / users.ojcid_expire（长效登录凭证）
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='ojcid');
SET @s := IF(@c=0, 'ALTER TABLE users ADD COLUMN ojcid VARCHAR(64) DEFAULT NULL, ADD COLUMN ojcid_expire TIMESTAMP NULL DEFAULT NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 5. 缺失的表
CREATE TABLE IF NOT EXISTS article_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_article (article_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS article_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    value TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vote (article_id, username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    filename VARCHAR(200) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    size BIGINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    share_token VARCHAR(32) DEFAULT NULL,
    KEY idx_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
