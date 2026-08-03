@echo off
title ZXT OJ Data Uploader
color 0a
setlocal enabledelayedexpansion

echo ================================================
echo    ZXT SUPER OJ - Data Package Uploader (Windows)
echo    Modes: HTTP (default) / SCP (--scp)
echo ================================================
echo.

set "MODE=http"
set "SERVER="
set "USERHOST="
set "DATADIR=/opt/oj-deploy/data"
set "FILE="

rem ---- Parse args: upload.bat [--scp] [-s URL|-u user@host|-d datadir] [file] ----
:parse
if "%~1"=="" goto parse_done
if /i "%~1"=="--scp" ( set "MODE=scp" & shift & goto parse )
if /i "%~1"=="-s" ( if not "%~2"=="" set "SERVER=%~2" & shift & shift & goto parse )
if /i "%~1"=="-u" ( if not "%~2"=="" set "USERHOST=%~2" & shift & shift & goto parse )
if /i "%~1"=="-d" ( if not "%~2"=="" set "DATADIR=%~2" & shift & shift & goto parse )
if "%~1"=="-h" goto help
set "FILE=%~1"
shift
goto parse
:parse_done

if "%MODE%"=="scp" goto scp_mode

rem ================= HTTP mode =================
if not defined SERVER (
  set /p "SERVER=Enter OJ server URL (Enter for http://localhost:18001): "
  if not defined SERVER set "SERVER=http://localhost:18001"
)
if defined FILE goto http_check
echo Usage: upload.bat [-s http://IP:PORT] ^<file path^>
echo   Drag the .zip file onto this window, or type the path.
set /p "FILE=Enter data package path: "
:http_check
if not exist "%FILE%" ( echo [ERROR] File not found: %FILE% & goto :eof )
echo Server: %SERVER%
echo [Uploading] %FILE% ...
echo (progress bar below, please wait...)
curl --progress-bar -F "file=@%FILE%" "%SERVER%/api/tool_upload.php"
echo.
echo.
echo [DONE] Copy the returned /tmp/... path and paste it into
echo        the "Import by Path" field on the OJ problem edit page.
pause >nul
goto :eof

rem ================= SCP mode =================
:scp_mode
if not defined USERHOST (
  set /p "USERHOST=Enter SSH user@host (e.g. root@IP): "
)
if not defined USERHOST ( echo [ERROR] user@host required for scp mode & pause >nul & goto :eof )
if defined FILE goto scp_check
set /p "FILE=Enter data package path: "
:scp_check
if not exist "%FILE%" ( echo [ERROR] File not found: %FILE% & pause >nul & goto :eof )
for %%f in ("%FILE%") do set "FNAME=%%~nxf"
echo Server data dir: %DATADIR%
echo [SCP] Uploading %FNAME% to %USERHOST%:%DATADIR%/packages/ ...
scp "%FILE%" "%USERHOST%:%DATADIR%/packages/"
if errorlevel 1 ( echo [FAILED] scp failed - check user@host and password & pause >nul & goto :eof )
echo.
echo [DONE] In OJ edit page, use "Import by Path":
echo   /data/packages/%FNAME%
pause >nul
goto :eof

:help
echo Usage:
echo   HTTP: upload.bat [-s http://IP:PORT] ^<file path^>
echo   SCP:  upload.bat --scp -u user@host [-d datadir] ^<file path^>
echo Examples:
echo   upload.bat -s http://192.168.1.100:18001 D:\data\P1000.zip
echo   upload.bat --scp -u root@192.168.1.100 D:\data\P1000.zip
echo Tip: You can also drag the .zip file onto this window
pause >nul
