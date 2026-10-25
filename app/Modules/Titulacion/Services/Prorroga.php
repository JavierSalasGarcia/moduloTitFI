<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Modules\Titulacion\TitulacionRepository;

/** Prórroga del tema: al validarse la solicitud (pasante y asesor), se genera el oficio de aprobación que firma la Subdirección. */
final class Prorroga
{
    public static function aprobar(array $tramite, int $usuarioId): void
    {
        $tid = (int) $tramite['id'];
        $fecha = (string) (TitulacionRepository::hitos($tid)['protocolo']['datos']['fecha'] ?? date('Y-m-d'));
        $vigencia = max(1, (int) \ajuste('titulacion_vigencia_protocolo', '2'));
        $anios = max(1, (int) \ajuste('titulacion_prorroga_anios', '1'));
        $desde = (new \DateTimeImmutable($fecha))->modify('+' . $vigencia . ' years');
        $hasta = $desde->modify('+' . $anios . ' years')->modify('-1 day');
        [$bytes, $caja] = Oficios::aprobacionProrroga($tramite, $desde->format('Y-m-d'), $hasta->format('Y-m-d'));
        $ruta = DocumentosGenerados::guardar($tramite, 'oficio_prorroga', $bytes);
        $docId = TitulacionRepository::registrarDocumento($tid, 'revision', 'oficio_prorroga', 'personal', 'aprobado', $ruta, 'oficio_prorroga_' . $tramite['folio'] . '.pdf', $usuarioId);
        SubdireccionFirma::solicitar($tramite, $docId, 'oficio_prorroga', Oficios::TIPOS['oficio_prorroga'], $caja, 'subdirector');
    }
}
