@echo off
SETLOCAL

:: ============================================================
::  AuraPass-Stop.bat (Complete System Shutdown)
::  Gracefully stops Apache, MySQL, and Background Workers
:: ============================================================

SET "ROOT=%~dp0"
timeout /t 2 /nobreak >nul

:: Environment Detection
IF EXIST "%ROOT%laragon\bin\mysql" (
    SET "LARAGON_DIR=%ROOT%laragon"
) ELSE (
    SET "LARAGON_DIR=C:\laragon"
)

SET "MYSQL_BIN=%LARAGON_DIR%\bin\mysql\mysql-8.4.3-winx64\bin"
SET "APACHE_BIN=%LARAGON_DIR%\bin\apache\httpd-2.4.62-240904-win64-VS17\bin"
SET "APACHE_CONF=%LARAGON_DIR%\bin\apache\httpd-2.4.62-240904-win64-VS17\conf\httpd.conf"

echo.
echo  =============================================
echo   Initiating AuraPass System Shutdown...
echo  =============================================
echo.

:: ── 1. The Buffer ──────────────────────────────────────
:: Wait 2 seconds before killing anything. 
timeout /t 2 /nobreak >nul

:: ── 2. Force Logout (Clear Sessions) ───────────────────
:: This wipes the session files so the user must log in again on next boot.
echo  [..] Clearing active sessions (Forcing Logout)...
if exist "%ROOT%storage\framework\sessions" (
    del /S /Q "%ROOT%storage\framework\sessions\*" >nul 2>&1
)
echo  [OK] Session data purged.

:: ── 3. Stop Background Workers ──────────────────────────
echo  [..] Stopping Queue Workers & Scanners...
taskkill /F /IM php.exe >nul 2>&1
taskkill /FI "WINDOWTITLE eq AuraPass Queue Worker*" /F /T >nul 2>&1
echo  [OK] Background engines terminated.

:: ── 4. Stop Laragon GUI (Prevents auto-restarting) ──────
echo  [..] Closing Laragon Manager...
taskkill /F /IM laragon.exe >nul 2>&1

:: ── 5. Stop Apache ──────────────────────────────────────
echo  [..] Stopping Apache Web Server...
taskkill /F /IM httpd.exe >nul 2>&1
echo  [OK] Apache offline.

:: ── 6. Stop MySQL ───────────────────────────────────────
echo  [..] Stopping MySQL Database...
"%MYSQL_BIN%\mysqladmin.exe" -u root shutdown >nul 2>&1
IF %ERRORLEVEL% NEQ 0 (
    taskkill /F /IM mysqld.exe >nul 2>&1
)
echo  [OK] Database offline.

echo.
echo  =============================================
echo   AuraPass has been safely shut down.
echo  =============================================
echo.

timeout /t 2 /nobreak >nul
ENDLOCAL