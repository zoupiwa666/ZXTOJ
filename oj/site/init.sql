-- ZXT Super OJ 初始化数据
CREATE DATABASE IF NOT EXISTS judge_problems CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE judge_problems;

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin','admin','user') DEFAULT 'user',
    avatar VARCHAR(255) DEFAULT '',
    motto VARCHAR(200) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 邀请码
CREATE TABLE IF NOT EXISTS invite_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) UNIQUE NOT NULL,
    created_by VARCHAR(50) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    max_uses INT DEFAULT 1,
    use_count INT DEFAULT 0,
    expires_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 题目
CREATE TABLE IF NOT EXISTS problems (
    id INT AUTO_INCREMENT PRIMARY KEY,
    problem_id VARCHAR(20) UNIQUE NOT NULL,
    title VARCHAR(200) NOT NULL,
    background TEXT,
    description TEXT NOT NULL,
    input_format TEXT,
    output_format TEXT,
    hints TEXT,
    time_limit DECIMAL(5,2) DEFAULT 2.0,
    memory_limit INT DEFAULT 128,
    created_by VARCHAR(50) DEFAULT '',
    visibility ENUM('public','hidden') DEFAULT 'public',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 样例
CREATE TABLE IF NOT EXISTS problem_samples (
    id INT AUTO_INCREMENT PRIMARY KEY,
    problem_id VARCHAR(20) NOT NULL,
    sort_order INT DEFAULT 0,
    input_text TEXT NOT NULL,
    output_text TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 测试点（元数据，数据在 /data/problems/）
CREATE TABLE IF NOT EXISTS problem_testcases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    problem_id VARCHAR(20) NOT NULL,
    sort_order INT DEFAULT 0,
    input_text MEDIUMTEXT,
    output_text MEDIUMTEXT,
    score DECIMAL(5,2) DEFAULT 10.0,
    file_path VARCHAR(500) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 题目权限
CREATE TABLE IF NOT EXISTS problem_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    problem_id VARCHAR(20) NOT NULL,
    username VARCHAR(50) NOT NULL,
    granted_by VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_perm (problem_id, username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 提交记录
CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    problem_id VARCHAR(20) NOT NULL,
    language VARCHAR(20) NOT NULL,
    code LONGTEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'waiting',
    score DECIMAL(10,2) DEFAULT 0,
    max_score DECIMAL(10,2) DEFAULT 100,
    total_time DECIMAL(10,3) DEFAULT 0,
    peak_memory DECIMAL(10,2) DEFAULT 0,
    total_tests INT DEFAULT 0,
    passed_tests INT DEFAULT 0,
    details JSON,
    judge_task_id VARCHAR(64),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户组
CREATE TABLE IF NOT EXISTS user_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    created_by VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    UNIQUE KEY uk_member (group_id, username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 题单
CREATE TABLE IF NOT EXISTS lists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    visibility ENUM('public','private') DEFAULT 'public',
    tags VARCHAR(500) DEFAULT '',
    created_by VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS list_problems (
    id INT AUTO_INCREMENT PRIMARY KEY,
    list_id INT NOT NULL,
    problem_id VARCHAR(20) NOT NULL,
    sort_order INT DEFAULT 0,
    UNIQUE KEY uk_list_prob (list_id, problem_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS list_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    list_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    granted_by VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_lp (list_id, username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初始数据
INSERT INTO users (username, password_hash, role) VALUES
('admin', '$2y$10$jxbs85Pip04IApadb0S4e.JvGpGLG2R8Jd7n6VkrsNoyDKnaKYk4K', 'super_admin');

INSERT INTO problems (problem_id, title, background, description, input_format, output_format, hints, time_limit, memory_limit, created_by, visibility) VALUES
('P1000', 'A+B Problem', '这是 OJ 最经典的入门题目。', '输入两个整数 A 和 B，输出它们的和 A+B。', '一行，两个整数 A, B，空格分隔。', '一个整数，表示 A+B。', '注意数据范围，使用 64 位整数。', 2.0, 128, 'admin', 'public');

INSERT INTO problem_samples (problem_id, sort_order, input_text, output_text) VALUES
('P1000', 1, '1 2', '3'),
('P1000', 2, '100 200', '300');

INSERT INTO problem_testcases (problem_id, sort_order, score, file_path) VALUES
('P1000', 1, 20.0, '/data/problems/P1000/1'),
('P1000', 2, 20.0, '/data/problems/P1000/2'),
('P1000', 3, 20.0, '/data/problems/P1000/3'),
('P1000', 4, 20.0, '/data/problems/P1000/4'),
('P1000', 5, 20.0, '/data/problems/P1000/5');
