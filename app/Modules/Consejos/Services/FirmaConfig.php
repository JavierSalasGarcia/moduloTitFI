<?php

declare(strict_types=1);

namespace App\Modules\Consejos\Services;

use App\Core\Database;

/**
 * Qué documentos de los Consejos van con firma autógrafa y cuáles con la firma interna de la plataforma, y cuáles pasarán a
 * firma electrónica cuando se autorice. Se guarda en `ajustes` (clave `consejos_firma_<documento>`). Mientras la firma electrónica
 * no esté autorizada (ajuste `consejos_firma_electronica` = 1 y PAdES habilitado), lo que se configure como «electrónica» se
 * trata como autógrafa: se imprime, se firma a mano y se sube el escaneado.
 */
final class FirmaConfig
{
    public const MODOS = [
        'autografa' => 'Autógrafa (se imprime, se firma a mano y se sube el escaneado)',
        'interna' => 'Firma interna de la plataforma (sello con huella, registro de conformidad)',
        'electronica' => 'Firma electrónica (cuando se autorice; mientras tanto, autógrafa)',
    ];

    /** documento => [nombre, ¿ya opera con la plataforma?] */
    public const DOCUMENTOS = [
        'solicitud_solicitante' => ['Solicitud: firma de quien solicita', true],
        'solicitud_vobo' => ['Solicitud: Vo. Bo. o enterado de la Coordinación', true],
        'dictamen' => ['Oficio de dictamen', false],
        'acta_academico' => ['Acta del H. Consejo Académico', false],
        'acta_gobierno' => ['Acta del H. Consejo de Gobierno', false],
    ];

    public static function configurado(string $documento): string
    {
        $m = \ajuste('consejos_firma_' . $documento, 'autografa');
        return isset(self::MODOS[$m]) ? $m : 'autografa';
    }

    public static function electronicaAutorizada(): bool
    {
        if (\ajuste('consejos_firma_electronica', '0') !== '1') {
            return false;
        }
        return class_exists(\App\Modules\Firmas\PadesSigner::class) && \App\Modules\Firmas\PadesSigner::habilitado();
    }

    /** Modo con el que realmente se firma hoy: autografa o interna. */
    public static function efectivo(string $documento): string
    {
        $m = self::configurado($documento);
        return $m === 'interna' ? 'interna' : 'autografa';
    }

    public static function guardar(string $documento, string $modo): void
    {
        if (!isset(self::DOCUMENTOS[$documento]) || !isset(self::MODOS[$modo])) {
            return;
        }
        self::fijar('consejos_firma_' . $documento, $modo, 'Consejos: modo de firma de «' . self::DOCUMENTOS[$documento][0] . '»');
    }

    public static function autorizarElectronica(bool $si): void
    {
        self::fijar('consejos_firma_electronica', $si ? '1' : '0', 'Consejos: firma electrónica autorizada por los Consejos');
    }

    private static function fijar(string $clave, string $valor, string $descripcion): void
    {
        Database::ejecutar('INSERT INTO ajustes (clave, valor, descripcion) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)', [$clave, $valor, $descripcion]);
    }
}
