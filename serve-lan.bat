@echo off
REM Starts the system so phones on the same Wi-Fi can open it and scan permit QR codes.
REM PERMIT_PUBLIC_URL in .env must match this PC's Wi-Fi IP (shown below).
cd /d "%~dp0"
echo.
echo  This PC's Wi-Fi address(es):
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4 Address"') do echo    http:%%a:8000
echo.
echo  QR codes currently point to:
findstr /b "PERMIT_PUBLIC_URL" .env
echo.
echo  If the two addresses differ, update PERMIT_PUBLIC_URL in .env, then restart this window.
echo  Press Ctrl+C to stop the server.
echo.
php artisan config:clear >nul
php artisan serve --host=0.0.0.0 --port=8000
