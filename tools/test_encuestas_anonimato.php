<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;

$raiz = dirname(__DIR__);
$controller = (string) file_get_contents($raiz . '/app/Modules/Encuestas/EncuestasController.php');
$detalle = (string) file_get_contents($raiz . '/app/Modules/Encuestas/views/detalle.php');
$tablero = (string) file_get_contents($raiz . '/app/Modules/Encuestas/views/tablero.php');
$module = (string) file_get_contents($raiz . '/app/Modules/Encuestas/module.php');
$schema = (string) file_get_contents($raiz . '/database/schema.sql');

function comprobar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        fwrite(STDERR, "FALLÓ: {$mensaje}\n");
        exit(1);
    }
    echo "OK: {$mensaje}\n";
}

preg_match('/CREATE TABLE `encuesta_votos` \((.*?)\) ENGINE=/s', $schema, $tablaVotos);
comprobar(isset($tablaVotos[1]), 'se encontró el esquema de votos');
comprobar(!preg_match('/usuario_id|participante_id|votante_id/i', $tablaVotos[1]), 'el voto no contiene identidad ni FK de participante');
comprobar(str_contains($tablaVotos[1], '`token` char(10) NOT NULL'), 'el recibo usado queda registrado en el voto anónimo');
comprobar(!str_contains($controller, "\$_SESSION['token_encuesta_"), 'el recibo nuevo no se guarda en sesión');
comprobar(!str_contains($detalle, 'localStorage.setItem'), 'el recibo nuevo no se guarda en el navegador');
comprobar(!str_contains($tablero, 'data-token='), 'el tablero no identifica ni resalta el voto del navegador');
comprobar(str_contains($module, "'votar', 'auditoria_anonima'"), 'la auditoría del voto omite la identidad autenticada');

echo "Anonimato de encuestas: comprobaciones completas.\n";
