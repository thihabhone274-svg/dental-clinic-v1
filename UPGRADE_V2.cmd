@echo off
cd /d "%~dp0"
"%~dp0..\..\php\php.exe" scripts\upgrade_v2.php
if errorlevel 1 (echo Upgrade did not finish. Copy the message above.) else (echo Open http://localhost/dental_clinic_v2_demo/)
pause
