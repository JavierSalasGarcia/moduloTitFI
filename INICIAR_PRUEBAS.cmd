@echo off
setlocal
cd /d "%~dp0"
set "PHP_EXE=php"
if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"
echo Traza FI disponible en http://127.0.0.1:8090/
echo Cierra esta ventana o presiona Ctrl+C para detener el servidor.
"%PHP_EXE%" -S 127.0.0.1:8090 -t public
