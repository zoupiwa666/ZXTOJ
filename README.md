# ZXT Super OJ

一个可基于 Docker 一键部署的在线评测系统（Online Judge），支持 C/C++/Python3，内置流式评测、聊天、文章/题解、头像、用户名片等完整社区功能。

## ✨ 功能特性

### 🖥️ 评测
- C / C++(14/17/20) / Python3，沙箱容器隔离，CPU/内存限制
- **流式评测**：`compiling` 编译状态，**每测完一个测试点立即推送**到页面
- 重测 / 批量重测 / 批量删除提交记录（管理员）
- 非 AC 测试点数据下载、提交记录筛选

### 💬 社区
- **聊天**：搜索/加好友、Markdown + KaTeX、长消息折叠、@提及用户（悬停出名片）
- **文章**：Markdown 写作、默认私密、公告（管理员）、权限管理（查看/发布/修改）
- **题解**：独立题解区、提交审核（管理员）、管理员开关新题解提交
- **评论**：Markdown、分页（8条/页）、点赞/点踩、字体大小钳制
- **@提及**：`@用户名` 自动渲染为用户链接 + 名片
- **用户名片**：悬停用户名显示头像/格言/标签，点击进主页
- **名字颜色 + 标签**：管理员紫色、普通用户棕色；管理员可给权限小于自己的用户设置标签（方块底色、白字、最多5字）

### 🎨 界面
- Font Awesome 高级矢量图标（本地离线）
- 高级浮动输入框（浮动标签、聚焦光效）
- 全站头像徽章（有头像显示图片、无头像字母占位）
- 自定义网站图标 + 左上角圆形 Logo

### 📦 其他
- 数据包上传：标准上传（500MB+）/ 分片直传（断点续传）/ 路径导入 / scp 上传
- 上传工具：`upload.bat`/`upload.sh`（HTTP 或 scp 模式，自定义端口）
- 开箱即用：首次部署自动生成示例题 P1000 测试数据
- 国内加速：apt / pip 使用阿里云镜像

## 🏗️ 架构

```
┌───────────────┐  宿主端口    ┌──────────────────┐
│ OJ 容器        │ 18000 ←────→ │ 评测机容器        │
│ nginx+php      │             │ fastapi+docker    │
└──────┬────────┘             └────────┬─────────┘
       │                               │ docker.sock
       ▼                               ▼
┌───────────────┐                宿主沙箱容器
│ zxt-db 数据库  │               (judge-pool xN)
│ mariadb:11    │
└───────────────┘
```

- **数据库独立容器**（zxt-db / mariadb:11），与 web/评测机隔离，性能更好
- 题目数据目录自动定位（任意部署路径）

## 📂 目录结构

```
ZXTOJ/
├── start.sh            # 一键启动脚本（--rebuild / --reset）
├── docker-compose.yml  # 编排文件（备用）
├── .env                # 配置（端口/CPU/内存/数据库密码）
├── judge/
│   ├── Dockerfile      # 评测机镜像
│   └── engine/         # 评测机代码 + 沙箱镜像
├── oj/
│   ├── Dockerfile      # OJ 镜像 (nginx+php)
│   ├── start.sh        # OJ 容器入口（纯 web）
│   └── site/           # 前端代码（文章/聊天/题解/工具等）
├── data/problems/      # 题目数据（自动挂载 /data）
└── oj-mysql/           # 数据库数据（挂载到 zxt-db）
```

## 🚀 部署

### 全新部署

```bash
git clone https://github.com/zoupiwa666/ZXTOJ.git
cd ZXTOJ
sudo ./start.sh
```

> 首次启动交互式询问（评测机端口 / OJ 端口 / CPU / 内存 / 预热容器数 / 数据库密码），回车用默认值。之后配置保存在 `.env`。
> **自动创建上传用户 ojupload**（密码显示在启动信息，用于 scp 上传数据包）。

### 更新已有部署（重点！）

```bash
cd ZXTOJ && sudo git pull
sudo ./start.sh --rebuild    # 必须带 --rebuild，强制重建镜像
```

> ⚠️ 评测相关修复在镜像里，`git pull` 后不重建镜像会用旧版本导致评测异常（`dr status=failed`）。

### start.sh 参数

```
./start.sh            # 镜像存在则直接启动，不存在才构建
./start.sh --rebuild  # 强制重建所有镜像（zxt-oj / zxt-judge / judge-sandbox）
./start.sh --reset    # 完全重置：删容器/镜像/数据库/数据/配置，全新部署（需输入 yes 确认）
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
DB_PASS=zxt_oj_pass_2026          # 数据库密码
```

## 📖 使用指南

### 访问
- **OJ 系统**：`http://服务器IP:OJ端口`（默认 18001），初始账号 **admin / admin123**
- **评测机**：`http://服务器IP:评测端口`（默认 18000）

### 添加题目数据
1. **标准上传**：编辑页 → 导入数据包 → 选文件 → 标准上传（500MB+）
2. **直传**：分片并行 + 断点续传（网络不稳推荐）
3. **路径导入**：填服务器路径（容器内路径，如 `/data/packages/xxx.zip`）
4. **scp 上传**：`scp -P 端口 包.zip ojupload@服务器IP:/部署目录/data/packages/` → 路径导入 `/data/packages/文件名.zip`

数据包格式：
```
P1000.zip
├── config.yaml      # name / time_limit / memory_limit / test_cases / scores
├── 1.in
├── 1.out
└── ...
```

### 文章 / 题解 / 评论
- **文章**：导航「文章」→ 发布（默认私密，仅作者/管理员可见）；管理员可设置用户 查看/发布/修改 权限
- **公告**：仅管理员可发，置顶显示在首页
- **题解**：题目页「📘 题解」→ 题解区 → 提交题解（文章设为题解，需管理员审核）
- **评论**：文章下方，Markdown 渲染，点赞/点踩

### 其他
- **@提及**：在文章/评论/聊天输入 `@用户名`，存在则渲染为链接 + 名片
- **用户名片**：悬停任何用户名，显示头像/格言/标签
- **名字颜色**：管理员紫色、普通用户棕色

## 📝 注意事项

1. **评测机需要 docker.sock 权限**：容器内创建沙箱容器
2. **数据库独立**：zxt-db（mariadb:11），数据在 `oj-mysql/`；start.sh 自动确保 admin 存在
3. **题目数据**在 `data/problems/`（自动挂载，路径自动识别）
4. **示例题 P1000**：自动生成 5 个测试点，可提交 `a,b=map(int,input().split()); print(a+b)` 体验 AC
5. **停止服务**：`docker rm -f zxt-oj zxt-judge zxt-db`
6. **聊天**：每会话保留最新 10 条，单条限 3.5KB
7. **普通用户文章**：内容上限 100KB（管理员 1MB）
8. **评论/聊天 HTML**：白名单净化，禁止 script/form/iframe，字体钳制 150px
