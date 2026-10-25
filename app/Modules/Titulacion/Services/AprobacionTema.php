<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Database;
use App\Core\Persona;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\TitulacionRepository;

/**
 * Cierre de la fase «Registro del protocolo»: oficio de aprobación del tema (Vo. Bo. de la Jefatura de Titulación
 * y firma de la Subdirección), avisos a quienes firman y constancias para quienes revisaron el protocolo.
 */
final class AprobacionTema
{
    /** Genera el oficio, registra la etapa como atendida y lo pone en la cola de firmas. */
    public static function emitir(array $tramite, string $fecha, string $observaciones, int $usuarioId): void
    {
        $tid = (int) $tramite['id'];
        [$bytes, $cajaSub, $cajaJefe] = Oficios::aprobacionTema($tramite, $fecha, $observaciones);
        $relativa = 'titulacion/' . $tid . '/oficio_aprobacion_tema_' . bin2hex(random_bytes(8)) . '.pdf';
        $abs = FirmaElectronica::almacen() . '/' . $relativa;
        if (!is_dir(dirname($abs))) {
            mkdir(dirname($abs), 0755, true);
        }
        file_put_contents($abs, $bytes);
        Verificacion::guardado($bytes, $relativa);
        $docId = TitulacionRepository::registrarDocumento($tid, 'protocolo', 'oficio_aprobacion_tema', 'personal', 'aprobado', $relativa, 'oficio_aprobacion_tema_' . $tramite['folio'] . '.pdf', $usuarioId);
        TitulacionRepository::guardarHito($tid, 'protocolo', ['fecha' => $fecha, 'observaciones' => $observaciones], $usuarioId);
        $nombre = Oficios::TIPOS['oficio_aprobacion_tema'];
        Database::ejecutar("UPDATE tit_firmas_subdireccion SET estado = 'cancelado' WHERE tramite_id = ? AND formato = 'oficio_aprobacion_tema' AND estado = 'pendiente'", [$tid]);
        SubdireccionFirma::solicitar($tramite, $docId, 'oficio_aprobacion_tema', $nombre . ' (Vo. Bo.)', $cajaJefe, 'jefe_titulacion');
        SubdireccionFirma::solicitar($tramite, $docId, 'oficio_aprobacion_tema', $nombre, $cajaSub, 'subdirector');

        $alumno = (string) ($tramite['datos']['datos']['nombre'] ?? '');
        $titulo = (string) ($tramite['titulo'] ?? $tramite['datos']['protocolo']['titulo'] ?? '');
        $url = Correo::enlace('titulacion/firmas');
        foreach (['jefe' => Autoridades::jefeTitulacion(), 'sub' => Autoridades::subdirector()] as $quien => $a) {
            if (!$a || $a['email'] === '') {
                continue;
            }
            Correo::enviar($a['email'], $a, 'Protocolo aprobado: oficio por firmar · ' . $tramite['folio'],
                "Se aprobó el protocolo «{$titulo}» de {$alumno}. El oficio de aprobación del tema está listo para "
                . ($quien === 'jefe' ? 'su Vo. Bo.; después pasa a la firma de la Subdirección Académica.' : 'su firma, en cuanto la Jefatura de Evaluación Profesional dé su Vo. Bo.'),
                'Abrir firmas pendientes', $url, 'oficio_aprobacion', $tid);
        }
        Constancias::revisoresProtocolo($tramite, $fecha, $usuarioId);
    }

    /** La Subdirección firmó el oficio: concluye el registro del protocolo. */
    public static function oficioFirmado(array $tramite): void
    {
        $alumno = Persona::porId((int) $tramite['alumno_id']) ?? [];
        $email = (string) (Database::valor('SELECT email FROM usuarios WHERE id = ?', [(int) $tramite['alumno_id']]) ?: '');
        Mensajes::enviar((int) $tramite['id'], null, null, 'Departamento de Evaluación Profesional', 'Oficio de aprobación del tema',
            'Su oficio de aprobación del tema y modalidad ya está firmado por la Jefatura de Evaluación Profesional (Vo. Bo.) y la Subdirección Académica. Puede descargarlo en su trámite, en la etapa «' . (Catalogo::etapa((string) $tramite['modalidad'], $tramite['variante'] ?: null, 'protocolo')['titulo'] ?? 'Protocolo') . '». Con esto concluye el registro de su protocolo.');
        if ($email !== '') {
            Correo::enviar($email, $alumno, 'Oficio de aprobación de tu tema · ' . $tramite['folio'],
                'Tu oficio de aprobación del tema y modalidad ya está firmado. Con esto concluye el registro de tu protocolo; puedes descargarlo en tu trámite.',
                'Ver mi trámite', Correo::enlace('titulacion/licenciatura/tramite/' . (int) $tramite['id'] . '?etapa=protocolo'), 'oficio_aprobacion', (int) $tramite['id'], false);
        }
    }
}
