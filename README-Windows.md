# ZXT Super OJ - Windows 部署指南

ZXTOJ 可在 **Windows 10/11** 上运行，推荐使用 **WSL2 + Docker Desktop** 方式（最稳定，评测沙箱的 docker.sock 依赖在 WSL2 下可用）。

## 一、环境准备

### 1. 安装 WSL2

以管理员身份打开 PowerShell，执行：

```powershell
wsl --install
```

重启电脑后安装 Ubuntu 发行版（Windows Store 或 `wsl --install -d Ubuntu`）。

### 2. 安装 Docker Desktop

1. 下载安装 [Docker Desktop](https://www.docker.com/products/docker-desktop/)
2. 设置中开启 **Use the WSL 2 based engine**
3. Settings → Resources → WSL Integration → 勾选你的 Ubuntu 发行版

### 3. 验证

```powershell
wsl
# 进入 WSL 后：
docker version      # 应能正常显示
```

## 二、部署

在 **WSL 内**（不是 Windows cmd）：

```bash
# 1. 克隆项目
git clone https://github.com/zoupiwa666/ZXTOJ.git ~/ZXTOJ
cd ~/ZXTOJ

# 2. 一键启动（和 Linux 完全一样）
sudo ./start.sh
```

> 首次启动交互式配置端口/密码，回车用默认值。之后 `sudo ./start.sh` 即可。

## 三、Windows 快捷启动（可选）

仓库根目录有 `start.bat`，双击即可进入 WSL 启动 OJ（需项目在 WSL 的 `~/ZXTOJ`）。

## 四、访问

- OJ 系统：`http://localhost:18001`
- 初始账号：`admin / admin123`

> Windows 浏览器直接访问 `localhost` 即可（WSL2 自动端口转发）。

## 五、注意事项

1. **必须在 WSL 内运行** start.sh（不要用 Git Bash / cmd 直接跑），保证 docker.sock 路径正确
2. **数据存储**：数据库在 WSL 的 `~/ZXTOJ/oj-mysql/`，删除 WSL 发行版会丢失数据
3. **端口占用**：如果 18001/18000 被占用，修改 `.env` 或首次配置时改端口
4. **WSL 内存**：默认可能限制，可在 `.wslconfig` 调整：
   ```ini
   [wsl2]
   memory=4GB
   processors=4
   ```
5. **防火墙**：如局域网其他设备无法访问，放行 18001/18000 端口

## 六、更新

```bash
cd ~/ZXTOJ && sudo git pull && sudo ./start.sh --rebuild
```
