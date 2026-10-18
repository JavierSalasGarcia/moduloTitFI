<?php
if (PHP_SAPI !== 'cli') exit;
$pdo=new PDO('mysql:host=127.0.0.1;dbname=fi_ux_test_20260909;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$hash=password_hash('Ux-local-test-2026',PASSWORD_DEFAULT);
$pdo->prepare('UPDATE usuarios SET password_hash=?, permite_contrasena=1, logins_contrasena_restantes=100, totp_secret=NULL')->execute([$hash]);
$pdo->exec("UPDATE ajustes SET valor='' WHERE clave LIKE '%api_key%' OR clave LIKE '%token%'");
echo json_encode($pdo->query('SELECT id,email,rol FROM usuarios WHERE activo=1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),JSON_PRETTY_PRINT);
