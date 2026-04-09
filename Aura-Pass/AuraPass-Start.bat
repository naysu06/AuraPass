@echo off
SETLOCAL

:: ============================================================
::  AuraPass-Start.bat (Complete System Startup)
::  Starts Apache, MySQL, and Background Workers
:: ============================================================

SET "ROOT=%~dp0"

:: ── ENVIRONMENT DETECTION ───────────────────────────────
IF EXIST "%ROOT%laragon\bin\mysql" (
    echo [..] Production Environment Detected
    SET "LARAGON_DIR=%ROOT%laragon"
) ELSE (
    echo [..] Development Environment Detected
    SET "LARAGON_DIR=C:\laragon"
)

:: Define Paths
SET "MYSQL_BIN=%LARAGON_DIR%\bin\mysql\mysql-8.4.3-winx64\bin"
SET "APACHE_BIN=%LARAGON_DIR%\bin\apache\httpd-2.4.62-240904-win64-VS17\bin"
SET "APACHE_CONF=%LARAGON_DIR%\bin\apache\httpd-2.4.62-240904-win64-VS17\conf\httpd.conf"
SET "MYSQL_INI=%LARAGON_DIR%\bin\mysql\mysql-8.4.3-winx64\my.ini"
SET "PHP_BIN=%LARAGON_DIR%\bin\php\php-8.3.26-Win32-vs16-x64\php.exe" 

:: Note: Change to http://localhost:8000 if not using a custom vhost
SET "APP_URL=http://localhost:8000"
SET "LARAVEL_DIR=%ROOT%"

:: ── 1. START DATABASES & SERVERS (Background) ───────────
:: Start MySQL if not running
"%MYSQL_BIN%\mysqladmin.exe" -u root status >nul 2>&1
IF %ERRORLEVEL% NEQ 0 (
    echo [..] Starting Database...
    START "" /B "%MYSQL_BIN%\mysqld.exe" --defaults-file="%MYSQL_INI%" --standalone >nul 2>&1
)

:: Start Apache if not running
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe" >NUL
IF %ERRORLEVEL% NEQ 0 (
    echo [..] Starting Web Server...
    START "" /B "%APACHE_BIN%\httpd.exe" -f "%APACHE_CONF%"
)

:: CRITICAL: Wait for MySQL to fully initialize before running Artisan commands
:: This prevents the "Connection Refused" errors in your logs.
echo [..] Waiting for system stability...
timeout /t 6 /nobreak >nul

:: ── 2. PORTABLE SAFETY NET (Self-Healing) ───────────────
echo [..] Optimizing Environment...
"%PHP_BIN%" "%LARAVEL_DIR%artisan" config:clear >nul 2>&1
"%PHP_BIN%" "%LARAVEL_DIR%artisan" cache:clear >nul 2>&1
"%PHP_BIN%" "%LARAVEL_DIR%artisan" storage:link --force >nul 2>&1

:: Re-cache for production speed only after the paths have been cleared/reset
"%PHP_BIN%" "%LARAVEL_DIR%artisan" config:cache >nul 2>&1

:: ── 3. START BACKGROUND WORKERS (The "Gym" Stack) ────────
echo [..] Starting Background Services...

:: Kill any existing AuraPass workers to prevent duplicate tasks
taskkill /FI "WINDOWTITLE eq AuraPass Queue*" /F /T >nul 2>&1
taskkill /FI "WINDOWTITLE eq AuraPass Scheduler*" /F /T >nul 2>&1

:: Launch the Queue and Scheduler in minimized windows
START "AuraPass Queue" /MIN cmd /c "cd /d "%LARAVEL_DIR%" && "%PHP_BIN%" artisan queue:work"
START "AuraPass Scheduler" /MIN cmd /c "cd /d "%LARAVEL_DIR%" && "%PHP_BIN%" artisan schedule:work"

:: Final wait to ensure background jobs are ready before opening browser
timeout /t 2 /nobreak >nul

:: ── 4. LAUNCH UI ────────────────────────────────────────
echo [..] Launching AuraPass UI...
start "" "%APP_URL%/admin"
start "" "%APP_URL%/monitor"

:: ── 5. CLEAN EXIT ───────────────────────────────────────
echo [OK] AuraPass is now running.
ENDLOCAL
exit