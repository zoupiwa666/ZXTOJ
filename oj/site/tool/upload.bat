@echo off
title ZXT OJ Data Uploader
color 0a
setlocal enabledelayedexpansion

echo ================================================
echo    ZXT SUPER OJ - Data Package Uploader (Windows)
echo ================================================
echo.

rem ---- Parse args: upload.bat [-s http://IP:PORT] [file path] ----
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
  set /p "SERVER=Enter OJ server URL (Enter for http://localhost:18001): "
  if not defined SERVER set "SERVER=http://localhost:18001"
)

if defined FILE goto check
echo Usage 1: Drag the data package (.zip/.tar.gz) onto this window
echo Usage 2: upload.bat ^<file path^>
echo With server: upload.bat -s http://IP:PORT ^<file path^>
echo.
set /p "FILE=Enter data package path: "

:check
if not exist "%FILE%" (
  echo [ERROR] File not found: %FILE%
  echo.
  goto :eof
)

echo Server: %SERVER%
echo [Uploading] %FILE% ...
curl -s -F "file=@%FILE%" "%SERVER%/api/tool_upload.php"
echo.
echo.
echo [DONE] Copy the returned /tmp/... path and paste it into
echo        the "Import by Path" field on the OJ problem edit page.
echo.
pause >nul
goto :eof

:help
echo Usage: upload.bat [-s http://IP:PORT] [data package path]
echo   Example: upload.bat -s http://192.168.1.100:18001 D:\data\P1000.zip
echo   Tip: You can also drag the .zip file onto this window
pause >nul
