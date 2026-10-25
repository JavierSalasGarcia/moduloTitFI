<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Database;
use App\Core\Persona;

/** Mensajes al pasante («Titulación → Mensajes») y plantilla del mensaje de cada vuelta de revisión. */
final class Mensajes
{
    public const ASUNTO = 'Resultado de la {vuelta} vuelta de revisión de su protocolo';

    public const CUERPO = "{saludo}:\n\n"
        . "Le informo que el Comité de Revisión de Protocolos de la Licenciatura en {licenciatura} completó la {vuelta} vuelta de revisión de su protocolo «{titulo}».\n\n"
        . "Le pido atender las siguientes observaciones:\n\n{observaciones}\n\n"
        . "Cuando las haya atendido, corrija su protocolo en Traza FI (Titulación → Licenciatura) y vuelva a enviarlo; el comité revisará la versión corregida.\n\n"
        . "Atentamente\n{responsable}\nResponsable del Comité de Revisión de Protocolos";

    /** Marcadores que admite la plantilla. */
    public const MARCAS = ['{saludo}', '{alumno}', '{titulo}', '{licenciatura}', '{vuelta}', '{observaciones}', '{responsable}'];

    /** @return array{asunto:string,cuerpo:string,propia:bool} */
    public static function plantilla(string $email): array
    {
        $f = Database::una('SELECT asunto, cuerpo FROM tit_plantillas_mensaje WHERE email = ?', [strtolower(trim($email))]);
        return $f ? ['asunto' => (string) $f['asunto'], 'cuerpo' => (string) $f['cuerpo'], 'propia' => true] : ['asunto' => self::ASUNTO, 'cuerpo' => self::CUERPO, 'propia' => false];
    }

    public static function guardarPlantilla(string $email, string $asunto, string $cuerpo): void
    {
        Database::ejecutar(
            'INSERT INTO tit_plantillas_mensaje (email, asunto, cuerpo) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE asunto = VALUES(asunto), cuerpo = VALUES(cuerpo)',
            [strtolower(trim($email)), mb_substr($asunto, 0, 200), mb_substr($cuerpo, 0, 6000)]
        );
    }

    public static function restablecerPlantilla(string $email): void
    {
        Database::ejecutar('DELETE FROM tit_plantillas_mensaje WHERE email = ?', [strtolower(trim($email))]);
    }

    public static function ordinal(int $n): string
    {
        return [1 => 'primera', 2 => 'segunda', 3 => 'tercera', 4 => 'cuarta', 5 => 'quinta'][$n] ?? $n . 'ª';
    }

    /** Valores de los marcadores para una vuelta (sin {observaciones}, que se arma en la pantalla). */
    public static function valores(array $ronda, array $responsable): array
    {
        $datos = json_decode((string) ($ronda['tramite_datos'] ?? ''), true) ?: [];
        $alumno = ['nombre' => (string) ($datos['datos']['nombre'] ?? $ronda['alumno_nombre'] ?? '')] + (Persona::porId((int) ($ronda['alumno_id'] ?? 0)) ?? []);
        return [
            '{saludo}' => Persona::saludo($alumno),
            '{alumno}' => (string) $alumno['nombre'],
            '{titulo}' => (string) ($ronda['titulo'] ?? ''),
            '{licenciatura}' => \App\Modules\Titulacion\Catalogo::carreras()[$ronda['carrera']]['nombre'] ?? '',
            '{vuelta}' => self::ordinal((int) ($ronda['numero'] ?? 1)),
            '{responsable}' => Persona::tratamiento($responsable),
        ];
    }

    public static function aplicar(string $texto, array $valores): string
    {
        return strtr($texto, $valores);
    }

    // ------------------------------------------------------------------ bandeja del pasante

    public static function enviar(int $tramiteId, ?int $revisionId, ?int $remitenteId, string $remitente, string $asunto, string $cuerpo): int
    {
        return Database::ejecutar(
            'INSERT INTO tit_mensajes (tramite_id, revision_id, remitente_id, remitente, asunto, cuerpo) VALUES (?, ?, ?, ?, ?, ?)',
            [$tramiteId, $revisionId, $remitenteId, mb_substr($remitente, 0, 200), mb_substr($asunto, 0, 200), mb_substr($cuerpo, 0, 20000)]
        );
    }

    public static function delAlumno(int $alumnoId): array
    {
        return Database::todas(
            'SELECT m.*, t.folio FROM tit_mensajes m JOIN tit_tramites t ON t.id = m.tramite_id WHERE t.alumno_id = ? ORDER BY m.id DESC LIMIT 200',
            [$alumnoId]
        );
    }

    public static function noLeidos(int $alumnoId): int
    {
        try {
            return (int) Database::valor('SELECT COUNT(*) FROM tit_mensajes m JOIN tit_tramites t ON t.id = m.tramite_id WHERE t.alumno_id = ? AND m.leido_en IS NULL', [$alumnoId]);
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function mensaje(int $id): ?array
    {
        return Database::una('SELECT m.*, t.folio, t.alumno_id FROM tit_mensajes m JOIN tit_tramites t ON t.id = m.tramite_id WHERE m.id = ?', [$id]);
    }

    public static function marcarLeido(int $id): void
    {
        Database::ejecutar('UPDATE tit_mensajes SET leido_en = NOW() WHERE id = ? AND leido_en IS NULL', [$id]);
    }
}
