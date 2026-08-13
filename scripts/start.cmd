@echo off
rem videohub - start script for the video-player-php app (Windows).
rem
rem Launches PHP's built-in web server with index.php as the router script.
rem   set HOST=127.0.0.1 & set PORT=3000 & set PHP_WORKERS=4
rem
rem With multiple PHP worker processes one viewer's stream never blocks another.
setlocal
cd /d "%~dp0\.."

if "%HOST%"=="" set "HOST=127.0.0.1"
if "%PORT%"=="" set "PORT=3000"
if "%PHP_WORKERS%"=="" set "PHP_WORKERS=4"

where php >nul 2>nul || (
    echo [videohub ERROR] PHP not found. Install PHP ^(>= 8.1^) with pdo_sqlite and re-run.
    exit /b 1
)

echo [videohub] Starting Video Player on http://%HOST%:%PORT% ^(workers: %PHP_WORKERS%^)
set "PHP_CLI_SERVER_WORKERS=%PHP_WORKERS%"
php -S "%HOST%:%PORT%" index.php
endlocal
