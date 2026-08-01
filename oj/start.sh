#!/bin/bash
mkdir -p /var/lib/mysql /run/mysqld
chown -R mysql:mysql /var/lib/mysql /run/mysqld 2>/dev/null
chmod 777 /var/lib/mysql /run/mysqld
if [ ! -d /var/lib/mysql/mysql ]; then
  echo "[Init] 初始化 MariaDB 数据目录..."
  mariadb-install-db --user=mysql --datadir=/var/lib/mysql --skip-test-db >/dev/null 2>&1
fi
mysqld --user=mysql --skip-networking=0 --bind-address=0.0.0.0 &
sleep 10

mysql -u root -e "CREATE DATABASE IF NOT EXISTS judge_problems CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" 2>/dev/null
mysql -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '$DB_PASS'; GRANT ALL ON *.* TO 'root'@'%' IDENTIFIED BY '$DB_PASS'; FLUSH PRIVILEGES;" 2>/dev/null
if [ -f /var/www/oj/init.sql ]; then
  echo "[Init] 导入数据库..."
  mysql -u root -p$DB_PASS judge_problems < /var/www/oj/init.sql 2>/dev/null || mysql -u root judge_problems < /var/www/oj/init.sql 2>/dev/null
fi

# 生成配置
php /var/www/oj/config_gen.php

# 启动评测 Worker（后台）
export JUDGE_URL=$(php -r "require '/var/www/oj/inc/config.php'; echo \$JUDGE_URL;")
DB_PASS_ENV=$DB_PASS python3 /var/www/oj/api/oj_worker.py > /tmp/oj_worker.log 2>&1 &
echo "[Init] Worker 已启动"

php-fpm -D
nginx -g 'daemon off;'
