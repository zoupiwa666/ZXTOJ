<?php
// 根据环境变量生成 inc/config.php
$judgeUrl = getenv('JUDGE_URL') ?: 'http://127.0.0.1:18000';
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

$config = <<<PHP
<?php
session_start();

\$DB_HOST = '$dbHost';
\$DB_PORT = $dbPort;
\$DB_USER = '$dbUser';
\$DB_PASS = '$dbPass';
\$DB_NAME = 'judge_problems';
\$JUDGE_URL = '$judgeUrl';

try {
    \$pdo = new PDO("mysql:host=\$DB_HOST;port=\$DB_PORT;dbname=\$DB_NAME;charset=utf8", \$DB_USER, \$DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
} catch (PDOException \$e) {
    die('数据库连接失败');
}
PHP;

file_put_contents('/var/www/oj/inc/config.php', $config);
echo "[Config] judge_url=$judgeUrl db=$dbHost:$dbPort\n";
