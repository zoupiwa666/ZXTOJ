#!/bin/bash
# =====================================================
#  OJ 容器入口：数据库已独立为 zxt-db 容器，本容器只跑 web
#  生成配置（DB_HOST 环境变量指向 zxt-db）→ php-fpm → nginx
# =====================================================

# 确保题目数据/头像上传目录存在且 oj(www-data) 可写
mkdir -p /data/problems /var/www/oj/uploads/avatars
chown -R www-data:www-data /data/problems /var/www/oj/uploads 2>/dev/null

# 生成配置（DB_HOST/DB_PASS/JUDGE_URL 来自环境变量）
php /var/www/oj/config_gen.php

# 启动上传停滞监控（10s无进展自动断开卡死上传）
python3 /opt/monitor_uploads.py >/tmp/oj_upload_monitor.log 2>&1 &

php-fpm -D
nginx -g 'daemon off;'
