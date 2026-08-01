@echo off
title ZXT OJ Data Uploader
color 0a

echo ================================================
echo    ZXT SUPER OJ - Fast Uploader (HTTP)
echo ================================================
echo.

if "%~1"=="" goto input
set "FILE=%~1"
goto check

:input
echo Usage 1: Drag .zip file onto this window
echo Usage 2: Type full file path
echo.
set /p "FILE=Enter file path: "

:check
if not exist "%FILE%" (
  echo [ERROR] File not found: %FILE%
  echo.
  goto input
)

echo [INFO] Uploading %FILE% ...
echo.
curl -s -F "file=@%FILE%" http://156.239.236.66:1227/simple
echo.
echo.
echo [DONE] In OJ edit page, use "path import":
echo   /tmp/oj_packages/%~nx1
echo.
echo Press any key to exit...
pause >nul
