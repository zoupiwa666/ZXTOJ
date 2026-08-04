#!/bin/bash
# =====================================================
#  ZXT Super OJ - 一键启动脚本
#  自动启动评测机 + OJ + 所有依赖
# =====================================================
set -e
cd "$(dirname "$0")"

# 参数解析
OJ_UPLOAD_PW=""
FORCE_REBUILD=0
for arg in "$@"; do
  case "$arg" in
    --rebuild) FORCE_REBUILD=1 ;;
    -h|--help)
      echo "用法: $0 [--rebuild]"
      echo "  --rebuild   强制重新构建所有镜像（zxt-oj / zxt-judge / judge-sandbox）"
      echo "  无参数       仅在镜像不存在时构建，启动已有镜像"
      exit 0 ;;
  esac
done

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
if [ "$FORCE_REBUILD" = "1" ]; then
  info " 模式: 强制重建镜像 (--rebuild)"
fi
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
if [ "$FORCE_REBUILD" = "1" ] || ! docker images judge-sandbox:latest --format "{{.ID}}" | grep -q .; then
  info "构建沙箱镜像 judge-sandbox..."
  docker build -t judge-sandbox:latest ./judge/engine || { err "沙箱镜像构建失败"; exit 1; }
else
  ok "沙箱镜像已存在"
fi

# 4. 构建评测机镜像
if [ "$FORCE_REBUILD" = "1" ] || ! docker images zxt-judge:latest --format "{{.ID}}" | grep -q .; then
  info "构建评测机镜像 zxt-judge..."
  docker build -t zxt-judge:latest ./judge || { err "评测机构建失败"; exit 1; }
else
  ok "评测机镜像已存在"
fi

# 5. 构建 OJ 镜像
if [ "$FORCE_REBUILD" = "1" ] || ! docker images zxt-oj:latest --format "{{.ID}}" | grep -q .; then
  info "构建 OJ 镜像 zxt-oj..."
  docker build -t zxt-oj:latest ./oj || { err "OJ 构建失败"; exit 1; }
else
  ok "OJ 镜像已存在"
fi

# 6. 准备数据目录和配置
mkdir -p data/problems data/packages oj-mysql
chmod 777 data data/packages oj-mysql 2>/dev/null
# 交互式配置环境变量（首次启动询问）
if [ ! -f .env ]; then
  echo ""
  echo -e "${YELLOW}========== 首次启动配置 ==========${NC}"
  echo -e "（直接回车使用默认值）"
  echo ""

  read -p "评测机端口 [默认 18000]: " inp
  JUDGE_PORT=${inp:-18000}

  read -p "OJ 端口 [默认 18001]: " inp
  OJ_PORT=${inp:-18001}

  read -p "沙箱 CPU 限制(核) [默认 0.5]: " inp
  JUDGE_CPU_LIMIT=${inp:-0.5}

  read -p "沙箱内存限制 [默认 512m]: " inp
  JUDGE_MEM_LIMIT=${inp:-512m}

  read -p "预热容器数 [默认 3]: " inp
  JUDGE_POOL_SIZE=${inp:-3}

  read -p "数据库密码 [默认 zxt_oj_pass_2026]: " inp
  DB_PASS=${inp:-zxt_oj_pass_2026}

  # 生成 .env
  cat > .env << ENVEOF
JUDGE_PORT=$JUDGE_PORT
JUDGE_CPU_LIMIT=$JUDGE_CPU_LIMIT
JUDGE_MEM_LIMIT=$JUDGE_MEM_LIMIT
JUDGE_POOL_SIZE=$JUDGE_POOL_SIZE
OJ_PORT=$OJ_PORT
JUDGE_URL=http://zxt-judge:8000
DB_PASS=$DB_PASS
ENVEOF

  echo ""
  echo -e "${GREEN}========== 配置完成 ==========${NC}"
  echo -e "  评测机端口: ${YELLOW}$JUDGE_PORT${NC}"
  echo -e "  OJ 端口:    ${YELLOW}$OJ_PORT${NC}"
  echo -e "  CPU 限制:   ${YELLOW}$JUDGE_CPU_LIMIT${NC}"
  echo -e "  内存限制:   ${YELLOW}$JUDGE_MEM_LIMIT${NC}"
  echo -e "  容器数:     ${YELLOW}$JUDGE_POOL_SIZE${NC}"
  echo -e "  数据库密码: ${YELLOW}$DB_PASS${NC}"
  echo -e "${GREEN}===============================${NC}"
  echo ""
  info "配置已保存到 .env（可随时修改）"
else
  info "检测到已有 .env，使用现有配置"
fi
# 题目数据目录保留
mkdir -p data/problems
touch data/problems/.gitkeep

# 为示例题目 P1000 自动生成测试数据（仅当 1.in 或 config.yaml 缺失时生成，不会覆盖已有数据）
if [ ! -f data/problems/P1000/1.in ] || [ ! -f data/problems/P1000/config.yaml ]; then
  info "初始化示例题目 P1000 测试数据..."
  mkdir -p data/problems/P1000
  cat > data/problems/P1000/1.in <<'XEOF'
1 2
XEOF
  cat > data/problems/P1000/1.out <<'XEOF'
3
XEOF
  cat > data/problems/P1000/2.in <<'XEOF'
100 200
XEOF
  cat > data/problems/P1000/2.out <<'XEOF'
300
XEOF
  cat > data/problems/P1000/3.in <<'XEOF'
0 0
XEOF
  cat > data/problems/P1000/3.out <<'XEOF'
0
XEOF
  cat > data/problems/P1000/4.in <<'XEOF'
123456789 987654321
XEOF
  cat > data/problems/P1000/4.out <<'XEOF'
1111111110
XEOF
  cat > data/problems/P1000/5.in <<'XEOF'
1000000000 1000000000
XEOF
  cat > data/problems/P1000/5.out <<'XEOF'
2000000000
XEOF
  for i in 1 2 3 4 5; do echo 20 > data/problems/P1000/$i.score; done
  cat > data/problems/P1000/config.yaml <<'XEOF'
name: A+B Problem
time_limit: 2.0
memory_limit: 128
test_cases: 5
scores: [20, 20, 20, 20, 20]
XEOF
  chmod -R 777 data/problems/P1000
  ok "P1000 测试数据已生成（5 个测试点）"
else
  ok "P1000 测试数据已存在，跳过"
fi

# 创建专用上传用户 ojupload（用于 scp 上传数据包，任何部署机器都可用）
if ! id ojupload >/dev/null 2>&1; then
  info "创建上传用户 ojupload..."
  # 显式指定家目录 /home/ojupload，避免被建到 /root 导致 scp 无法写
  if ! useradd -m -d /home/ojupload -s /bin/bash ojupload; then
    err "创建用户 ojupload 失败！请确认用 root 权限运行本脚本（sudo ./start.sh）"
    err "手动创建方法: useradd -m -d /home/ojupload -s /bin/bash ojupload && passwd ojupload"
  else
    OJ_UPLOAD_PW="OjUp$(date +%s | tail -c 5)@$(date +%Y)"
    if echo "ojupload:$OJ_UPLOAD_PW" | chpasswd; then
      echo "$OJ_UPLOAD_PW" > data/.ojupload_pw
      chmod 600 data/.ojupload_pw
      ok "已创建上传用户 ojupload，密码: $OJ_UPLOAD_PW"
    else
      err "设置 ojupload 密码失败，请手动执行: passwd ojupload"
    fi
  fi
else
  # 修正家目录（防止家目录是 /root 或不存在导致 scp 权限问题）
  OH=$(getent passwd ojupload | cut -d: -f6)
  if [ "$OH" = "/root" ] || [ ! -d "$OH" ]; then
    info "修正 ojupload 家目录到 /home/ojupload..."
    usermod -d /home/ojupload -m ojupload 2>/dev/null || mkdir -p /home/ojupload && chown ojupload:ojupload /home/ojupload
    ok "ojupload 家目录已修正为 /home/ojupload"
  fi
  if [ -f data/.ojupload_pw ]; then
    OJ_UPLOAD_PW=$(cat data/.ojupload_pw)
    ok "上传用户 ojupload 已存在，密码: $OJ_UPLOAD_PW"
  else
    ok "上传用户 ojupload 已存在（密码未知，可执行 passwd ojupload 修改）"
  fi
fi
# packages 目录授权 ojupload（scp 目标）
mkdir -p data/packages
chown -R ojupload:ojupload data/packages 2>/dev/null
chmod 775 data/packages 2>/dev/null
ok "数据包暂存目录已就绪: $(pwd)/data/packages"
ok "scp 目标: 用户@主机:$(pwd)/data/packages/  → 容器内 /data/packages/"

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
  -e DATA_HOST_DIR="$(pwd)/data" \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v "$DOCKER_BIN":/usr/bin/docker \
  -v "$(pwd)/data":/data \
  -v /tmp/judge_workspace:/tmp/judge_workspace \
  -v /tmp/judge_shared:/tmp/judge_shared \
  --restart unless-stopped \
  zxt-judge:latest >/dev/null
ok "评测机容器启动"

# 7.5 启动独立数据库容器 zxt-db（MariaDB）
info "启动数据库容器 zxt-db..."
if ! docker ps -a --format '{{.Names}}' | grep -qx zxt-db; then
  docker rm -f zxt-db 2>/dev/null || true
  docker run -d --name zxt-db \
    --network $NETWORK \
    -e MARIADB_ROOT_PASSWORD=$DB_PASS \
    -v "$(pwd)/oj-mysql":/var/lib/mysql \
    --restart unless-stopped \
    mariadb:11 >/dev/null
  ok "zxt-db 容器已创建"
else
  ok "zxt-db 容器已存在"
fi
# 等待数据库就绪
info "等待数据库就绪..."
DB_READY=0
for i in $(seq 1 60); do
  if docker exec zxt-db mariadb-admin ping -uroot -p$DB_PASS --silent >/dev/null 2>&1; then
    DB_READY=1; break
  fi
  sleep 2
done
[ "$DB_READY" = "1" ] && ok "数据库就绪" || err "数据库启动超时，请检查: docker logs zxt-db"
# 首次初始化：建库 + 导入表结构（已有数据则跳过）
if ! docker exec zxt-db mariadb -uroot -p$DB_PASS -e "USE judge_problems" >/dev/null 2>&1; then
  info "初始化数据库（建库+导入表结构）..."
  docker exec zxt-db mariadb -uroot -p$DB_PASS -e "CREATE DATABASE IF NOT EXISTS judge_problems CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
  docker exec -i zxt-db mariadb --force -uroot -p$DB_PASS judge_problems < oj/init.sql
  ok "数据库初始化完成"
else
  # 迁移兼容：旧库可能缺少新增表（如 problem_permissions），自动补齐
  if ! docker exec zxt-db mariadb -uroot -p$DB_PASS judge_problems -e "SHOW TABLES LIKE 'problem_permissions'" 2>/dev/null | grep -q problem_permissions; then
    info "检测到数据库缺少新表，自动导入 init.sql 补齐..."
    docker exec -i zxt-db mariadb --force -uroot -p$DB_PASS judge_problems < oj/init.sql
    ok "数据库表结构已补齐"
  fi
fi

# 8. 启动 OJ 容器
info "启动 OJ 容器..."
docker rm -f zxt-oj 2>/dev/null || true
docker run -d --name zxt-oj \
  --network $NETWORK \
  --add-host=host.docker.internal:host-gateway \
  -p $OJ_PORT:80 \
  -e JUDGE_URL=$JUDGE_URL \
  -e DB_HOST=zxt-db -e DB_PORT=3306 -e DB_USER=root -e DB_PASS=$DB_PASS \
  -v "$(pwd)/oj/site":/var/www/oj \
  -v "$(pwd)/data":/data \
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
docker exec -d zxt-oj bash -c "cd /var/www/oj && JUDGE_URL=$JUDGE_URL DB_HOST=zxt-db DB_PORT=3306 DB_USER=root DB_PASS=$DB_PASS python3 -u api/oj_worker.py > /tmp/oj_worker.log 2>&1"
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
echo "  上传账号:  ojupload / ${OJ_UPLOAD_PW:-<见上方提示>}"
echo "  scp 上传:  scp 数据包.zip ojupload@IP:$(pwd)/data/packages/" 
echo "  停止:      docker rm -f zxt-oj zxt-judge"
echo ""
