#!/bin/bash
# =====================================================
#  ZXT Super OJ - OJ Worker 保活脚本
#  每分钟由 cron 调用：检测 zxt-oj 容器内评测 worker，
#  若不存在则自动拉起，防止提交卡在 waiting
# =====================================================
CT="zxt-oj"
LOG="/var/log/oj_worker_keepalive.log"

# 容器不在则跳过
if ! docker ps --format '{{.Names}}' | grep -qx "$CT"; then
  exit 0
fi

# worker 活着就跳过
if docker exec "$CT" sh -c "pgrep -f oj_worker >/dev/null 2>&1"; then
  exit 0
fi

# 从 .env 读取数据库密码
DB_PASS=$(grep '^DB_PASS=' /opt/oj-deploy/.env | head -1 | cut -d= -f2)
DB_PASS="${DB_PASS:-zxt_oj_pass_2026}"

echo "[$(date '+%F %T')] worker 未运行，正在拉起..." >> "$LOG"
docker exec -d "$CT" bash -c "cd /var/www/oj && JUDGE_URL=http://zxt-judge:8000 DB_HOST=zxt-db DB_PORT=3306 DB_USER=root DB_PASS=$DB_PASS python3 -u api/oj_worker.py >> /tmp/oj_worker.log 2>&1"
sleep 3
if docker exec "$CT" sh -c "pgrep -f oj_worker >/dev/null 2>&1"; then
  echo "[$(date '+%F %T')] worker 已恢复" >> "$LOG"
else
  echo "[$(date '+%F %T')] worker 拉起失败，请手动检查" >> "$LOG"
fi
