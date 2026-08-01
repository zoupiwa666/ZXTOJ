#!/bin/bash
# =====================================================
#  ZXT Super OJ - 一键启动脚本
#  自动启动评测机 + OJ + 所有依赖
# =====================================================
set -e
cd "$(dirname "$0")"

# 颜色
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
ok(){ echo -e "${GREEN}[OK]${NC} $1"; }
info(){ echo -e "${YELLOW}[..]${NC} $1"; }
err(){ echo -e "${RED}[!!]${NC} $1"; }

# 加载 .env 配置
if [ -f .env ]; then
  set -a; source .env; set +a
fi
JUDGE_PORT=${JUDGE_PORT:-18000}
OJ_PORT=${OJ_PORT:-18001}
JUDGE_CPU_LIMIT=${JUDGE_CPU_LIMIT:-0.5}
JUDGE_MEM_LIMIT=${JUDGE_MEM_LIMIT:-512m}
JUDGE_POOL_SIZE=${JUDGE_POOL_SIZE:-3}
DB_PASS=${DB_PASS:-zxt_oj_pass_2026}
JUDGE_URL=${JUDGE_URL:-http://zxt-judge:8000}
NETWORK=oj-net

info "========================================"
info " ZXT Super OJ 启动程序"
info " OJ端口=$OJ_PORT 评测端口=$JUDGE_PORT CPU=$JUDGE_CPU_LIMIT 内存=$JUDGE_MEM_LIMIT"
info "========================================"

# 1. 检查 Docker
info "检查 Docker..."
if ! docker info >/dev/null 2>&1; then
  err "Docker 未运行，尝试启动..."
  systemctl start docker 2>/dev/null || service docker start 2>/dev/null || { err "无法启动 Docker"; exit 1; }
  sleep 3
fi
ok "Docker 就绪"

# 2. 创建容器网络
info "创建容器网络 $NETWORK..."
docker network create $NETWORK 2>/dev/null || ok "网络已存在"

# 3. 构建沙箱镜像
if ! docker images judge-sandbox:latest --format "{{.ID}}" | grep -q .; then
  info "构建沙箱镜像 judge-sandbox..."
  docker build -t judge-sandbox:latest ./judge/engine || { err "沙箱镜像构建失败"; exit 1; }
else
  ok "沙箱镜像已存在"
fi

# 4. 构建评测机镜像
if ! docker images zxt-judge:latest --format "{{.ID}}" | grep -q .; then
  info "构建评测机镜像 zxt-judge..."
  docker build -t zxt-judge:latest ./judge || { err "评测机构建失败"; exit 1; }
else
  ok "评测机镜像已存在"
fi

# 5. 构建 OJ 镜像
if ! docker images zxt-oj:latest --format "{{.ID}}" | grep -q .; then
  info "构建 OJ 镜像 zxt-oj..."
  docker build -t zxt-oj:latest ./oj || { err "OJ 构建失败"; exit 1; }
else
  ok "OJ 镜像已存在"
fi

# 6. 准备数据目录
mkdir -p data/problems oj-mysql
chmod 777 data oj-mysql 2>/dev/null

# 7. 启动评测机容器
info "启动评测机容器..."
docker rm -f zxt-judge 2>/dev/null || true
DOCKER_BIN=$(which docker)
docker run -d --name zxt-judge \
  --network $NETWORK \
  -p $JUDGE_PORT:8000 \
  -e JUDGE_PORT=8000 \
  -e JUDGE_CPU_LIMIT=$JUDGE_CPU_LIMIT \
  -e JUDGE_MEM_LIMIT=$JUDGE_MEM_LIMIT \
  -e JUDGE_POOL_SIZE=$JUDGE_POOL_SIZE \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v "$DOCKER_BIN":/usr/bin/docker \
  -v "$(pwd)/data":/data \
  -v /tmp/judge_workspace:/tmp/judge_workspace \
  -v /tmp/judge_shared:/tmp/judge_shared \
  --restart unless-stopped \
  zxt-judge:latest >/dev/null
ok "评测机容器启动"

# 8. 启动 OJ 容器
info "启动 OJ 容器..."
docker rm -f zxt-oj 2>/dev/null || true
docker run -d --name zxt-oj \
  --network $NETWORK \
  --add-host=host.docker.internal:host-gateway \
  -p $OJ_PORT:80 \
  -e JUDGE_URL=$JUDGE_URL \
  -e DB_HOST=127.0.0.1 -e DB_PORT=3306 -e DB_USER=root -e DB_PASS=$DB_PASS \
  -v "$(pwd)/oj-mysql":/var/lib/mysql \
  -v "$(pwd)/oj/site":/var/www/oj \
  --restart unless-stopped \
  zxt-oj:latest >/dev/null
ok "OJ 容器启动"

# 9. 等待 OJ 就绪
info "等待 OJ 启动..."
for i in $(seq 1 30); do
  if curl -s -o /dev/null -w "%{http_code}" http://localhost:$OJ_PORT/ 2>/dev/null | grep -q 200; then
    ok "OJ 就绪"
    break
  fi
  sleep 2
  [ $i -eq 30 ] && err "OJ 启动超时"
done

# 10. 确保 OJ 容器内有 python + pymysql（worker 依赖）
info "检查 OJ 容器 worker 依赖..."
docker exec zxt-oj python3 -c "import pymysql" 2>/dev/null || {
  info "安装 python3 + pymysql..."
  docker exec zxt-oj bash -c "apt-get update -qq >/dev/null 2>&1; apt-get install -y -qq python3 python3-pip >/dev/null 2>&1; pip3 install --break-system-packages -q pymysql" >/dev/null 2>&1
}
ok "worker 依赖就绪"

# 11. 启动 OJ 容器内 Worker
info "启动评测 Worker..."
docker exec zxt-oj bash -c "pkill -f oj_worker 2>/dev/null; sleep 1" || true
docker exec -d zxt-oj bash -c "cd /var/www/oj && JUDGE_URL=$JUDGE_URL DB_HOST=127.0.0.1 DB_PORT=3306 DB_USER=root DB_PASS=$DB_PASS python3 -u api/oj_worker.py > /tmp/oj_worker.log 2>&1"
sleep 2
ok "Worker 已启动"

# 12. 健康检查
info "最终健康检查..."
JUDGE_OK=$(curl -s http://localhost:$JUDGE_PORT/health 2>/dev/null | grep -o '"status":"ok"' | head -1)
OJ_OK=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:$OJ_PORT/ 2>/dev/null)
[ -n "$JUDGE_OK" ] && ok "评测机健康" || err "评测机异常"
[ "$OJ_OK" = "200" ] && ok "OJ 正常" || err "OJ 异常"

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN} 🎉 ZXT Super OJ 启动完成！${NC}"
echo -e "${GREEN}========================================${NC}"
echo "  OJ 系统:   http://服务器IP:$OJ_PORT"
echo "  评测机:    http://服务器IP:$JUDGE_PORT"
echo "  初始账号:  admin / admin123"
echo "  停止:      docker rm -f zxt-oj zxt-judge"
echo ""
