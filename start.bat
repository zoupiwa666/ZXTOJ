@echo off
title ZXT Super OJ - Windows Launcher
chcp 65001 >nul
echo ================================================
echo   ZXT Super OJ  Windows 启动器
echo   要求: Docker Desktop(WSL2) + WSL2
echo ================================================
echo.

where docker >nul 2>&1
if errorlevel 1 (
  echo [错误] 未检测到 Docker，请先安装 Docker Desktop
  echo         https://www.docker.com/products/docker-desktop/
  pause
  exit /b 1
)

wsl --status >nul 2>&1
if errorlevel 1 (
  echo [错误] 未检测到 WSL，请先安装 WSL2:
  echo         wsl --install
  pause
  exit /b 1
)

echo [..] 正在进入 WSL 启动 OJ...
echo [..] 项目默认位置: ~/ZXTOJ (WSL 内)
echo.
wsl -e bash -lc "cd ~/ZXTOJ 2>/dev/null && ./start.sh || { echo; echo '[错误] 项目不在 ~/ZXTOJ'; echo '       请在 WSL 内执行: git clone https://github.com/zoupiwa666/ZXTOJ.git ~/ZXTOJ'; }"
echo.
pause
