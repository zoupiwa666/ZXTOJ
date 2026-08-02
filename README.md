# ZXT Super OJ Docker 部署

## 架构

```
┌───────────────┐  宿主端口    ┌──────────────────┐
│ OJ 容器        │ 18000 ←────→ │ 评测机容器        │
│ nginx+php+mysql│             │ fastapi+docker    │
└───────────────┘             └────────┬─────────┘
                                       │ docker.sock
                                       ▼
                                  宿主沙箱容器
                                 (judge-pool xN)
```

## 目录结构

```
oj-deploy/
├── docker-compose.yml    # 编排文件
├── .env                  # 配置（端口/CPU/内存）
├── judge/
│   ├── Dockerfile        # 评测机镜像
│   └── engine/           # 评测机代码
├── oj/
│   ├── Dockerfile        # OJ 镜像 (nginx+php+mysql)
│   ├── site/             # OJ 前端代码
│   └── init.sql          # 数据库表结构
├── data/problems/        # 题目数据（挂载 /data）
└── oj-mysql/             # MySQL 数据持久化
```

## 配置 (.env)

```ini
# 评测机
JUDGE_PORT=18000       # 评测机映射宿主端口
JUDGE_CPU_LIMIT=0.5    # 沙箱容器 CPU 限制
JUDGE_MEM_LIMIT=512m   # 沙箱容器内存限制
JUDGE_POOL_SIZE=3      # 预热容器数

# OJ
OJ_PORT=18001          # OJ 访问端口
JUDGE_URL=http://127.0.0.1:18000  # OJ→评测机地址
DB_PASS=zxt_oj_pass_2026          # 内置数据库密码
```

## 部署

```bash
cd /ZXTOJ  #到项目文件夹下，这里默认ZXTOJ


# 1. 自动安装脚本
run start.sh

# 注意这里再安装完所有依赖后，会询问你环境变量配置，可以设置评测机/OJ端口，默认是18001/18000

# 2. 访问
# OJ:  http://服务器IP:你的OJ IP（默认18001）
# 评测: http://服务器IP:你的评测系统 IP（默认18000）
```

## 注意事项

1. **评测机需要 docker.sock 权限**：容器内创建沙箱容器
2. **沙箱镜像 judge-sandbox** 需在宿主提前构建
3. **题目数据**放 /opt/oj-deploy/data/problems/
4. **OJ 内置 MySQL**：数据在 oj-mysql/，容器内 root 密码=$DB_PASS
5. 首次启动会自动建库建表，然后导入现有表结构
