<?php
declare(strict_types=1);

namespace App\Modules\Perfil;

use App\Core\AccessControl;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use Throwable;

/**
 * Vigencia de la firma electrónica institucional de la UAEMéx (la del SICDE, con la que los sinodales firman el acta en
 * Control Escolar; se consulta en https://fise.uaemex.mx/). No es el sello que estampa Traza FI en sus documentos.
 *
 * La persona la declara en «Mi perfil» (usuarios.fise_vigencia) o, si es sinodal de posgrado, al firmar el visto bueno.
 * Cuando falta un mes o menos para que venza, recibe un aviso en su bandeja de Notificaciones, una sola vez por fecha
 * (usuarios.fise_avisada). No hay tareas programadas: el aviso lo genera pendientes(), que la plataforma consulta en
 * cada página para la insignia de Notificaciones (App\Core\Pendientes, como máximo cada 90 segundos por sesión).
 */
final class FirmaUaemex
{
    public const URL = 'https://fise.uaemex.mx/';
    public const AYUDA = 'Puedes verificar la fecha de validez de tu firma entrando a https://fise.uaemex.mx/';

    /** La ven todas las cuentas salvo los alumnos de licenciatura. */
    public static function aplica(?array $u = null): bool
    {
        $u ??= Auth::usuario();
        if (!$u) {
            return false;
        }
        return !(($u['rol'] ?? '') === 'alumno' && (string) ($u['programa_nivel'] ?? '') === 'licenciatura');
    }

    /** Fecha real AAAA-MM-DD entre 2000 y 2100. */
    public static function fechaValida(string $v): bool
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        return $d !== false && $d->format('Y-m-d') === $v && $v >= '2000-01-01' && $v <= '2100-12-31';
    }

    public static function deUsuario(int $id): ?string
    {
        $v = $id > 0 ? Database::valor('SELECT fise_vigencia FROM usuarios WHERE id = ?', [$id]) : null;
        return is_string($v) && $v !== '' ? $v : null;
    }

    /** Cuenta activa con ese correo (principal), con su vigencia declarada. @return array{id:int,fise_vigencia:?string}|null */
    public static function cuentaDe(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }
        $f = Database::una('SELECT id, fise_vigencia FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1', [$email]);
        return $f ? ['id' => (int) $f['id'], 'fise_vigencia' => $f['fise_vigencia'] !== null ? (string) $f['fise_vigencia'] : null] : null;
    }

    /**
     * Guarda (o borra, con null) la vigencia de una cuenta y lo deja en la auditoría.
     * $interna: escritura que hace la plataforma por la persona (p. ej. al firmar un visto bueno por enlace, sin sesión).
     */
    public static function guardar(int $usuarioId, ?string $fecha, string $origen, bool $interna = false): void
    {
        if ($fecha !== null && !self::fechaValida($fecha)) {
            throw new \InvalidArgumentException('Escribe una fecha válida.');
        }
        $escribir = static fn() => Database::ejecutar('UPDATE usuarios SET fise_vigencia = ? WHERE id = ?', [$fecha, $usuarioId]);
        $interna ? AccessControl::internal($escribir) : $escribir();
        Audit::record('perfil::fise_vigencia', 'correcto', 'Cuenta ' . $usuarioId . ' · ' . ($fecha ?? 'borrada') . ' · ' . $origen);
    }

    /** ¿Vence en un mes o menos (o ya venció)? */
    public static function porVencer(?string $vigencia, ?string $hoy = null): bool
    {
        if ($vigencia === null || !self::fechaValida($vigencia)) {
            return false;
        }
        $hoy ??= date('Y-m-d');
        return $vigencia <= self::masUnMes($hoy);
    }

    /** Misma fecha un mes después (31 de enero → 28 o 29 de febrero, sin desbordarse al mes siguiente). */
    public static function masUnMes(string $iso): string
    {
        $d = new \DateTimeImmutable($iso);
        $sig = $d->modify('first day of next month');
        return $sig->setDate((int) $sig->format('Y'), (int) $sig->format('n'), min((int) $d->format('j'), (int) $sig->format('t')))->format('Y-m-d');
    }

    /** «30 de junio de 2027» */
    public static function fechaLarga(string $iso): string
    {
        $meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
        return $d ? $d->format('j') . ' de ' . $meses[(int) $d->format('n')] . ' de ' . $d->format('Y') : $iso;
    }

    /**
     * Deja el aviso en la bandeja si a la vigencia le falta un mes o menos, una sola vez por fecha: el UPDATE condicional
     * marca la fecha avisada y solo quien lo logra (una fila afectada) crea la notificación.
     * @return bool true si se creó el aviso
     */
    public static function avisar(int $usuarioId, string $email, ?string $hoy = null): bool
    {
        $hoy ??= date('Y-m-d');
        $limite = self::masUnMes($hoy);
        $n = AccessControl::internal(static function () use ($usuarioId, $limite): int {
            $st = Database::pdo()->prepare(
                'UPDATE usuarios SET fise_avisada = fise_vigencia
                  WHERE id = ? AND fise_vigencia IS NOT NULL AND fise_vigencia <= ? AND (fise_avisada IS NULL OR fise_avisada <> fise_vigencia)'
            );
            $st->execute([$usuarioId, $limite]);
            return $st->rowCount();
        });
        if ($n !== 1) {
            return false;
        }
        $vigencia = (string) self::deUsuario($usuarioId);
        $vencida = $vigencia < $hoy;
        \App\Modules\Notificaciones\Notificaciones::crear(
            $email,
            ($vencida ? 'Tu firma electrónica UAEMéx venció el ' : 'Tu firma electrónica UAEMéx vence el ') . self::fechaLarga($vigencia),
            ($vencida ? 'Tu firma electrónica UAEMéx venció el ' : 'Tu firma electrónica UAEMéx vence el ') . self::fechaLarga($vigencia)
                . '; renuévala en ' . self::URL . ' y actualiza la fecha en Mi perfil. Es la firma con la que se firman las actas en el SICDE de Control Escolar.',
            url('perfil') . '#firma-uaemex',
            'perfil'
        );
        Audit::record('perfil::fise_aviso', 'correcto', 'Cuenta ' . $usuarioId . ' · vigencia ' . $vigencia);
        return true;
    }

    /**
     * Proveedor «pendientes» del módulo Perfil (module.json). No agrega renglones a la lista de pendientes: aprovecha que
     * la plataforma lo consulta al navegar para generar, una sola vez por fecha, el aviso de vigencia por vencer.
     */
    public static function pendientes(): array
    {
        $u = Auth::usuario();
        if (!$u || !self::aplica($u) || (int) ($_SESSION['_fise_aviso_en'] ?? 0) > time() - 300) {
            return [];
        }
        $_SESSION['_fise_aviso_en'] = time();
        try {
            self::avisar((int) $u['id'], (string) $u['email']);
        } catch (Throwable $e) {
            error_log('Firma UAEMéx: no se generó el aviso: ' . $e->getMessage());
        }
        return [];
    }
}
