# ZXT Super OJ

一个可基于 Docker 一键部署的在线评测系统（Online Judge），支持 C/C++/Python3，内置聊天、头像、数据包上传等完整功能。

## ✨ 功能特性

- 🖥️ **评测**：C / C++(14/17/20) / Python3，沙箱容器隔离，支持 CPU/内存限制
- 💬 **聊天**：按用户名搜索、添加好友、实时消息（Markdown + KaTeX 渲染）
- 👤 **头像**：全站用户名旁显示头像（上传自动生成 50×50 缩略图）
- 📦 **数据包上传**：网页标准上传（500MB+）、分片直传（断点续传）、路径导入
- 🚀 **开箱即用**：首次部署自动为示例题 P1000 生成测试数据
- ⚡ **国内加速**：apt / pip 均使用阿里云镜像

## 🏗️ 架构

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

## 📂 目录结构

```
ZXTOJ/
├── start.sh            # 一键启动脚本（支持 --rebuild）
├── docker-compose.yml  # 编排文件（备用）
├── .env                # 配置（端口/CPU/内存/数据库密码）
├── judge/
│   ├── Dockerfile      # 评测机镜像
│   └── engine/         # 评测机代码（含沙箱镜像 Dockerfile）
├── oj/
│   ├── Dockerfile      # OJ 镜像 (nginx+php+mysql)
│   ├── site/           # OJ 前端代码（含聊天/头像/工具）
│   └── init.sql        # 数据库表结构
├── data/problems/      # 题目数据（自动挂载 /data）
└── oj-mysql/           # MySQL 数据持久化
```

## 🚀 部署

### 全新部署

```bash
# 1. 克隆项目（任意路径均可，脚本会自动定位）
git clone https://github.com/zoupiwa666/ZXTOJ.git
cd ZXTOJ

# 2. 一键启动（首次会自动构建所有镜像）
sudo ./start.sh
```

> 首次启动会交互式询问环境变量（评测机端口 / OJ 端口 / CPU / 内存 / 预热容器数 / 数据库密码），直接回车使用默认值即可。之后配置保存在 `.env`，再次启动不再询问。

### 更新已有部署（重点！）

```bash
cd ZXTOJ && sudo git pull

# 重要：代码更新后必须用 --rebuild 强制重建镜像，否则旧镜像不生效！
sudo ./start.sh --rebuild
```

> ⚠️ **为什么必须 --rebuild**：评测相关修复（如 DATA_HOST_DIR 挂载、空数据保护）在镜像里，`start.sh` 默认只在镜像不存在时构建。代码更新后直接 `./start.sh` 会用旧镜像，评测会报 `dr status=failed`。**每次 `git pull` 后请带 `--rebuild`**。

### start.sh 参数

```
./start.sh            # 镜像存在则直接启动，不存在才构建
./start.sh --rebuild  # 强制重新构建所有镜像（zxt-oj / zxt-judge / judge-sandbox）
./start.sh --help     # 帮助
```

## ⚙️ 配置 (.env)

```ini
# 评测机
JUDGE_PORT=18000       # 评测机映射宿主端口
JUDGE_CPU_LIMIT=0.5    # 沙箱容器 CPU 限制（核）
JUDGE_MEM_LIMIT=512m   # 沙箱容器内存限制
JUDGE_POOL_SIZE=3      # 预热容器数

# OJ
OJ_PORT=18001          # OJ 访问端口
JUDGE_URL=http://zxt-judge:8000   # OJ→评测机地址（容器网络内）
DB_PASS=zxt_oj_pass_2026          # 内置数据库密码
```

## 📖 使用指南

### 访问
- **OJ 系统**：`http://服务器IP:你的OJ端口`（默认 18001），初始账号 `admin / admin123`
- **评测机**：`http://服务器IP:你的评测端口`（默认 18000）

### 添加题目数据（三种方式）
1. **网页标准上传**：题目编辑页 → 导入数据包 → 选文件 → 「标准上传」（支持 500MB+）
2. **网页直传**：点「直传」（分片并行 + 断点续传，大文件推荐）
3. **路径导入**：把 zip 放到服务器，填路径（如 `/tmp/xxx.zip`）

数据包格式（zip）：
```
P1000.zip
├── config.yaml      # name / time_limit / memory_limit / test_cases / scores
├── 1.in
├── 1.out
└── ...
```

### 上传用户（scp 专用）

每次部署时 `start.sh` 会自动创建 **`ojupload`** 上传用户，并在启动信息里显示密码（保存在 `data/.ojupload_pw`）。用它通过 scp 传数据包最稳定（不受 HTTP 限制）：

```bash
# Linux
scp -P 端口(默认22) ./P1000.zip ojupload@服务器IP:/opt/oj-deploy/data/packages/
# Windows
scp -P 端口(默认22) D:\data\P1000.zip ojupload@服务器IP:/opt/oj-deploy/data/packages/
```

上传后到 OJ 编辑页「路径导入」填 `/data/packages/文件名.zip`。

> 该用户只对 `data/packages/` 有写权限，仅用于上传数据包；建议部署后自行修改密码（`passwd ojupload`）。

## 📝 注意事项

1. **评测机需要 docker.sock 权限**：容器内创建沙箱容器
2. **沙箱镜像 judge-sandbox** 由 start.sh 自动构建
3. **题目数据**在 `data/problems/`（自动挂载到各容器 `/data`，路径自动识别）
4. **OJ 内置 MySQL**：数据在 `oj-mysql/`，容器内 root 密码 = `$DB_PASS`
5. **示例题 P1000**：首次部署自动生成 5 个测试点，可直接提交 `a,b=map(int,input().split()); print(a+b)` 体验 AC
6. **停止服务**：`docker rm -f zxt-oj zxt-judge`
7. **聊天消息**：只保留每会话最新 10 条，单条限 3.5KB，长消息可点击展开
