<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Persona;
use App\Modules\Titulacion\TitulacionRepository;
use RuntimeException;

/**
 * Voto aprobatorio: la plataforma lo genera con los datos del trámite y lo firman, desde el enlace de su correo,
 * el asesor y los revisores (sinodales propietarios del examen previo).
 */
final class VotoAprobatorio
{
    /** Firmantes (con correo) y fecha del examen previo. */
    public static function datos(array $tramite): array
    {
        $asesor = FirmasExternas::asesores($tramite, false)[0] ?? null;
        $firmantes = [];
        if ($asesor) {
            $firmantes[] = ['rol' => 'asesor', 'nombre' => Persona::tratamiento($asesor), 'email' => $asesor['email'], 'grado' => $asesor['grado'], 'genero' => $asesor['genero'], 'base' => $asesor['nombre']];
        }
        $previo = Jurado::vigente((int) $tramite['id'], 'revision');
        $n = 0;
        if ($previo && $previo['estado'] === 'firmada') {
            foreach (Jurado::sinodales((int) $previo['id']) as $s) {
                if ($s['calidad'] !== 'propietario' || ($asesor && strtolower((string) $s['email']) === $asesor['email'])) {
                    continue;
                }
                $firmantes[] = ['rol' => 'revisor_' . (++$n), 'nombre' => Persona::tratamiento($s), 'email' => strtolower((string) $s['email']), 'grado' => $s['grado'], 'genero' => $s['genero'], 'base' => $s['nombre']];
            }
        }
        return ['firmantes' => $firmantes, 'fecha_observaciones' => $previo['fecha'] ?? '', 'fecha' => date('Y-m-d')];
    }

    /** Genera el voto y pide las firmas por correo. Devuelve el id del documento. */
    public static function solicitar(array $tramite, int $usuarioId): int
    {
        $d = self::datos($tramite);
        if (count($d['firmantes']) < 2) {
            throw new RuntimeException('Aún no hay revisores asignados con oficio firmado para tu examen previo; el voto se genera después del examen previo.');
        }
        [$bytes, $cajas] = FormatosOficiales::generar('voto', $tramite, $d);
        $ruta = DocumentosGenerados::guardar($tramite, 'voto_aprobatorio', $bytes);
        $docId = TitulacionRepository::registrarDocumento((int) $tramite['id'], 'sustentacion', 'voto_aprobatorio', 'alumno', 'en_revision', $ruta, 'voto_aprobatorio_' . $tramite['folio'] . '.pdf', $usuarioId);
        DocumentosGenerados::guardarCajas($docId, $cajas);
        $firmantes = [];
        foreach ($d['firmantes'] as $f) {
            $firmantes[] = ['rol' => str_starts_with($f['rol'], 'revisor') ? 'revisor' : 'asesor', 'nombre' => $f['base'], 'grado' => $f['grado'], 'genero' => $f['genero'], 'email' => $f['email'], 'caja' => $cajas[$f['rol']] ?? null];
        }
        FirmasExternas::solicitar($tramite, $docId, $firmantes);
        return $docId;
    }
}
