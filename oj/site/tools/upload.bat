@echo off
title ZXT OJ Data Uploader
color 0a
setlocal enabledelayedexpansion

echo ================================================
echo    ZXT SUPER OJ - 数据包上传工具 (Windows)
echo ================================================
echo.

rem ---- 解析参数: upload.bat [-s http://IP:端口] [文件路径] ----
set "SERVER="
set "FILE="

:parse
if "%~1"=="" goto parse_done
if /i "%~1"=="-s" (
  if not "%~2"=="" set "SERVER=%~2"
  shift
  shift
  goto parse
)
if "%~1"=="-h" goto help
set "FILE=%~1"
shift
goto parse
:parse_done

if not defined SERVER (
  set "SERVER="
  set /p "SERVER=请输入OJ服务器地址(直接回车用 http://localhost:18001): "
  if not defined SERVER set "SERVER=http://localhost:18001"
)

if defined FILE goto check
echo 用法1: 把数据包(.zip/.tar.gz)拖到此窗口
echo 用法2: upload.bat ^<文件路径^>
echo 指定服务器: upload.bat -s http://IP:端口 ^<文件路径^>
echo.
set /p "FILE=请输入数据包路径: "

:check
if not exist "%FILE%" (
  echo [错误] 文件不存在: %FILE%
  echo.
  goto :eof
)

echo 服务器: %SERVER%
echo [上传中] %FILE% ...
curl -s -F "file=@%FILE%" "%SERVER%/api/tool_upload.php"
echo.
echo.
echo [完成] 复制上方返回的 /tmp/... 路径，到 OJ 题目编辑页「路径导入」粘贴
echo.
pause >nul
goto :eof

:help
echo 用法: upload.bat [-s http://IP:端口] [数据包路径]
echo   示例: upload.bat -s http://192.168.1.100:18001 D:\data\P1000.zip
echo   提示: 也可以直接把 .zip 文件拖到本窗口
pause >nul
