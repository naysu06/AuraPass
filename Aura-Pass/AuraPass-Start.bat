@echo off
SETLOCAL

:: ============================================================
::  AuraPass-Start.bat (Complete System Startup)
::  Starts Apache, MySQL, and Background Workers
:: ============================================================

SET "ROOT=%~dp0"

:: ── ENVIRONMENT DETECTION ───────────────────────────────
:: Check if the 'laragon' folder exists right next to this script (Production/Inno Setup)
IF EXIST "%ROOT%laragon\bin\mysql" (
    echo [..] Production Environment Detected
    SET "LARAGON_DIR=%ROOT%laragon"
) ELSE (
    :: Otherwise, assume we are in Development and Laragon is installed at C:\laragon
    echo [..] Development Environment Detected
    SET "LARAGON_DIR=C:\laragon"
)

:: Define Paths based on the detected environment
SET "MYSQL_BIN=%LARAGON_DIR%\bin\mysql\mysql-8.4.3-winx64\bin"
SET "APACHE_BIN=%LARAGON_DIR%\bin\apache\httpd-2.4.62-240904-win64-VS17\bin"
SET "APACHE_CONF=%LARAGON_DIR%\bin\apache\httpd-2.4.62-240904-win64-VS17\conf\httpd.conf"
SET "MYSQL_INI=%LARAGON_DIR%\bin\mysql\mysql-8.4.3-winx64\my.ini"
SET "PHP_BIN=%LARAGON_DIR%\bin\php\php-8.3.26-Win32-vs16-x64\php.exe" 

SET "APP_URL=http://localhost:8000" :: Change this if your APP_URL is different in .env.production
SET "LARAVEL_DIR=%ROOT%"

:: ── 1. PORTABLE SAFETY NET (Self-Healing) ───────────────
"%PHP_BIN%" "%LARAVEL_DIR%artisan" config:clear --force >nul 2>&1
"%PHP_BIN%" "%LARAVEL_DIR%artisan" storage:link --force >nul 2>&1

:: ── 2. START DATABASES & SERVERS (Background) ───────────
"%MYSQL_BIN%\mysqladmin.exe" -u root status >nul 2>&1
IF %ERRORLEVEL% NEQ 0 (
    START "" /B "%MYSQL_BIN%\mysqld.exe" --defaults-file="%MYSQL_INI%" --standalone >nul 2>&1
)

tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe" >NUL
IF %ERRORLEVEL% NEQ 0 (
    START "" /B "%APACHE_BIN%\httpd.exe" -f "%APACHE_CONF%"
)

:: ── 3. START QUEUE WORKER ───────────────────────────────
taskkill /FI "WINDOWTITLE eq AuraPass Queue Worker*" /F /T >nul 2>&1
START "AuraPass Queue Worker" /MIN cmd /c "cd /d "%LARAVEL_DIR%" && "%PHP_BIN%" artisan queue:work"

timeout /t 4 /nobreak >nul

:: ── 4. LAUNCH UI ────────────────────────────────────────
start "" "%APP_URL%/admin"
start "" "%APP_URL%/monitor"

:: ── 5. CLEAN EXIT ───────────────────────────────────────
ENDLOCAL
exit