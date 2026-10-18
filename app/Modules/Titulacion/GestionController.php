<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Modules\Titulacion\Services\DocumentosGenerados;
use App\Modules\Titulacion\Services\Jurado;
use App\Modules\Titulacion\Services\SubdireccionFirma;
use InvalidArgumentException;
use Throwable;

/** Bandeja y validación para el personal del Departamento de Evaluación Profesional. */
final class GestionController extends TitulacionBase
{
    private function soloPersonal(): void
    {
        if (!TitulacionAccess::esPersonal()) {
            $this->prohibido('Gestión de titulación');
        }
    }

    public function bandeja(): void
    {
        $this->privado();
        $this->soloPersonal();
        $filtros = [
            'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80),
            'estado' => array_key_exists((string) ($_GET['estado'] ?? ''), Catalogo::estadosTramite()) ? (string) $_GET['estado'] : '',
            'modalidad' => Catalogo::modalidad((string) ($_GET['modalidad'] ?? '')) ? (string) $_GET['modalidad'] : '',
            'pendientes' => !empty($_GET['pendientes']),
        ];
        $this->vista('gestion_bandeja', [
            'titulo' => 'Titulación · Validación de trámites',
            'filas' => TitulacionRepository::bandeja($filtros),
            'filtros' => $filtros,
            'resumen' => TitulacionRepository::resumenBandeja(),
            'modalidades' => Catalogo::modalidades(),
        ]);
    }

    public function ver(string $id): void
    {
        $this->privado();
        $t = $this->tramite($id, true);
        [$flujo, $docs] = $this->evaluar($t);
        $this->vista('gestion_tramite', [
            'titulo' => 'Expediente ' . $t['folio'],
            't' => $t,
            'modalidad' => Catalogo::modalidad((string) $t['modalidad']),
            'flujo' => $flujo,
            'docs' => $docs,
            'eventos' => TitulacionRepository::eventos((int) $t['id'], false),
            'carreras' => Catalogo::carreras(),
            'sistemas' => Catalogo::sistemas(),
            'formatos' => Catalogo::formatos(),
        ]);
    }

    public function revisar(string $id, string $docId): void
    {
        $t = $this->tramite($id, true);
        $d = ctype_digit($docId) ? TitulacionRepository::documento((int) $docId) : null;
        if ($d === null || (int) $d['tramite_id'] !== (int) $t['id'] || $d['origen'] !== 'alumno' || $d['estado'] === 'reemplazado') {
            $this->noEncontrado();
        }
        $estado = $this->post('decision');
        $comentario = trim($this->post('comentario'));
        $volver = 'titulacion/gestion/' . $t['id'] . '#doc-' . $d['id'];
        if (!in_array($estado, ['aprobado', 'rechazado'], true)) {
            flash('error', 'Elige aprobar o devolver el documento.');
            redirigir($volver);
        }
        if ($estado === 'rechazado' && mb_strlen($comentario) < 5) {
            flash('error', 'Explica al pasante qué debe corregir (mínimo 5 caracteres).');
            redirigir($volver);
        }
        if (mb_strlen($comentario) > 900) {
            flash('error', 'El comentario excede 900 caracteres.');
            redirigir($volver);
        }
        if (!empty($d['pendiente_firmas'])) {
            flash('error', 'El documento aún espera la firma del asesor.');
            redirigir($volver);
        }
        $etapaDef = Catalogo::etapa((string) $t['modalidad'], $t['variante'] ?: null, (string) $d['etapa']);
        $defDoc = $etapaDef ? Catalogo::documentoDeEtapa($etapaDef, (string) $d['clave']) : null;
        if (($defDoc['validacion'] ?? '') === 'comision' && RevisionRepository::comisionConfigurada((string) ($t['datos']['datos']['carrera'] ?? ''))) {
            flash('error', 'Este documento lo valida la comisión de revisión de protocolos.');
            redirigir($volver);
        }
        $nombre = $this->nombreDocumento($t, (string) $d['etapa'], (string) $d['clave']);
        TitulacionRepository::revisarDocumento((int) $d['id'], $estado, $comentario !== '' ? $comentario : null, Auth::id());
        TitulacionRepository::evento(
            (int) $t['id'],
            Auth::id(),
            $estado === 'aprobado' ? 'validacion' : 'observacion',
            ($estado === 'aprobado' ? 'Documento validado: ' : 'Documento con observaciones: ') . $nombre . ($comentario !== '' ? ' — ' . $comentario : '')
        );
        $tipoFirma = $estado === 'aprobado' && $defDoc ? SubdireccionFirma::tipoDeDocumento($defDoc) : null;
        if ($tipoFirma !== null) {
            $caja = !empty($defDoc['formato']) ? SubdireccionFirma::cajaDeFormato((string) $defDoc['formato']) : null;
            if ($caja !== null) {
                SubdireccionFirma::solicitar($t, (int) $d['id'], $tipoFirma, (string) $defDoc['nombre'], $caja);
            }
        }
        Audit::record('titulacion::revisar', 'correcto', 'Documento ' . (int) $d['id'] . ' ' . $estado);
        if (!$t['responsable_id']) {
            Database::ejecutar('UPDATE tit_tramites SET responsable_id = ? WHERE id = ?', [Auth::id(), (int) $t['id']]);
        }
        $this->sincronizar((int) $t['id']);
        flash('exito', $estado === 'aprobado' ? 'Documento validado.' : 'Documento devuelto al pasante con tus observaciones.');
        redirigir($volver);
    }

    public function cotejo(string $id, string $docId): void
    {
        $t = $this->tramite($id, true);
        $d = ctype_digit($docId) ? TitulacionRepository::documento((int) $docId) : null;
        if ($d === null || (int) $d['tramite_id'] !== (int) $t['id'] || $d['origen'] !== 'alumno' || $d['estado'] !== 'aprobado' || empty($d['ruta'])) {
            $this->noEncontrado();
        }
        $volver = 'titulacion/gestion/' . $t['id'] . '#doc-' . $d['id'];
        $etapa = Catalogo::etapa((string) $t['modalidad'], $t['variante'] ?: null, (string) $d['etapa']);
        $def = $etapa ? Catalogo::documentoDeEtapa($etapa, (string) $d['clave']) : null;
        if ($def === null || empty($def['cotejo'])) {
            flash('error', 'Este documento no requiere cotejo del original.');
            redirigir($volver);
        }
        if ($this->post('accion') === 'quitar') {
            TitulacionRepository::quitarCotejo((int) $d['id']);
            TitulacionRepository::evento((int) $t['id'], Auth::id(), 'cotejo', 'Cotejo retirado: ' . $def['nombre']);
            $this->sincronizar((int) $t['id']);
            flash('exito', 'Cotejo retirado.');
            redirigir($volver);
        }
        $tipo = ($def['firma_cotejo'] ?? 'sello') === 'pades' ? 'pades' : 'sello';
        try {
            if ($tipo === 'pades') {
                [$hash, $ruta] = Services\Cotejo::pades($t, $d, Auth::id(), $this->post('codigo_2fa'));
            } else {
                [$hash, $ruta] = Services\Cotejo::sello($t, $d, Auth::usuario());
            }
        } catch (Throwable $e) {
            if (!$e instanceof \RuntimeException) {
                error_log('Titulación: error de cotejo: ' . $e->getMessage());
            }
            flash('error', $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo registrar el cotejo.');
            redirigir($volver);
        }
        TitulacionRepository::registrarCotejo((int) $d['id'], $tipo, Auth::id(), $hash, $ruta);
        TitulacionRepository::evento((int) $t['id'], Auth::id(), 'cotejo', 'Original cotejado: ' . $def['nombre'] . ' (' . ($tipo === 'pades' ? 'firma PAdES' : 'sello de cotejo') . ')');
        Audit::record('titulacion::cotejo', 'correcto', 'Documento ' . (int) $d['id'] . ' ' . $tipo);
        $this->sincronizar((int) $t['id']);
        flash('exito', 'Cotejo registrado con tu ' . ($tipo === 'pades' ? 'firma electrónica PAdES.' : 'sello de cotejo.'));
        redirigir($volver);
    }

    private function nombreDocumento(array $t, string $etapa, string $clave): string
    {
        $e = Catalogo::etapa((string) $t['modalidad'], $t['variante'] ?: null, $etapa);
        $def = $e ? Catalogo::documentoDeEtapa($e, $clave) : null;
        return $def['nombre'] ?? $clave;
    }

    public function hito(string $id, string $etapaClave): void
    {
        $t = $this->tramite($id, true);
        $volver = 'titulacion/gestion/' . $t['id'] . '#etapa-' . $etapaClave;
        if ($t['estado'] !== 'abierto') {
            flash('error', 'El trámite ya no está abierto.');
            redirigir($volver);
        }
        [$flujo, $docs] = $this->evaluar($t);
        $etapa = null;
        foreach ($flujo['etapas'] as $e) {
            if ($e['clave'] === $etapaClave) {
                $etapa = $e;
            }
        }
        if ($etapa === null || !isset($etapa['personal'])) {
            $this->noEncontrado();
        }
        if ($etapa['situacion'] !== 'actual') {
            flash('error', 'Esa etapa no está en turno: debe estar al corriente la etapa anterior.');
            redirigir($volver);
        }
        if (!$etapa['propia_ok'] || !$etapa['docs_ok']) {
            flash('error', 'Antes de completar esta etapa, el pasante debe terminar su captura y todos sus documentos deben estar validados.');
            redirigir($volver);
        }
        $entrada = $_POST['h'] ?? [];
        $entrada = is_array($entrada) ? $entrada : [];
        $datos = [];
        $errores = [];
        foreach ($etapa['personal']['campos'] ?? [] as $campo) {
            $bruto = $entrada[$campo['k']] ?? '';
            $valor = is_string($bruto) ? trim($bruto) : '';
            $valor = mb_substr($valor, 0, (int) ($campo['max'] ?? 1500));
            if (($campo['tipo'] ?? '') === 'select' && !array_key_exists($valor, $campo['opciones'] ?? [])) {
                $valor = '';
            }
            $error = Flujo::errorCampo($campo, $valor);
            if ($error !== null) {
                $errores[] = $error;
            }
            $datos[$campo['k']] = $valor;
        }
        $entrega = $etapa['personal']['entrega'] ?? null;
        if ($errores) {
            flash('error', implode(' ', array_slice($errores, 0, 3)));
            redirigir($volver);
        }
        if ($entrega !== null && !empty($entrega['asignacion'])) {
            try {
                Jurado::solicitar($t, $etapaClave, (string) $entrega['asignacion'], $datos, Auth::id());
            } catch (\RuntimeException $e) {
                flash('error', $e->getMessage());
                redirigir($volver);
            } catch (Throwable $e) {
                error_log('Titulación: no se pudo solicitar la asignación: ' . $e->getMessage());
                flash('error', 'No se pudo enviar la solicitud al Coordinador. Intenta de nuevo.');
                redirigir($volver);
            }
        }
        if ($entrega !== null && !empty($entrega['oficio'])) {
            try {
                [$ruta, $caja] = DocumentosGenerados::guardarOficio($t, (string) $entrega['clave'], $datos);
            } catch (Throwable $e) {
                error_log('Titulación: no se pudo generar el oficio: ' . $e->getMessage());
                flash('error', 'No se pudo generar el oficio. Intenta de nuevo.');
                redirigir($volver);
            }
            $docId = TitulacionRepository::registrarDocumento((int) $t['id'], $etapaClave, $entrega['clave'], 'personal', 'aprobado', $ruta, $entrega['clave'] . '_' . $t['folio'] . '.pdf', Auth::id());
            SubdireccionFirma::solicitar($t, $docId, (string) $entrega['clave'], (string) $entrega['nombre'], $caja);
        }
        TitulacionRepository::guardarHito((int) $t['id'], $etapaClave, $datos, Auth::id());
        TitulacionRepository::evento((int) $t['id'], Auth::id(), 'hito', 'Etapa completada por el departamento: ' . ($etapa['personal']['titulo'] ?? $etapa['titulo']) . $this->resumenHito($datos, $etapa));
        Audit::record('titulacion::hito', 'correcto', 'Trámite ' . $t['id'] . ' · ' . $etapaClave);
        if (!$t['responsable_id']) {
            Database::ejecutar('UPDATE tit_tramites SET responsable_id = ? WHERE id = ?', [Auth::id(), (int) $t['id']]);
        }
        $this->sincronizar((int) $t['id']);
        flash('exito', 'Etapa registrada.');
        redirigir('titulacion/gestion/' . $t['id']);
    }

    private function resumenHito(array $datos, array $etapa): string
    {
        $partes = [];
        foreach ($etapa['personal']['campos'] ?? [] as $c) {
            $v = (string) ($datos[$c['k']] ?? '');
            if ($v === '' || ($c['tipo'] ?? '') === 'area') {
                continue;
            }
            if (($c['tipo'] ?? '') === 'select') {
                $v = (string) ($c['opciones'][$v] ?? $v);
            }
            $partes[] = $c['label'] . ': ' . $v;
        }
        return $partes ? ' (' . implode('; ', $partes) . ')' : '';
    }

    public function reenviarCoordinador(string $id, string $asig): void
    {
        $t = $this->tramite($id, true);
        $ok = ctype_digit($asig) && (int) Database::valor('SELECT COUNT(*) FROM tit_asignaciones_jurado WHERE id = ? AND tramite_id = ?', [(int) $asig, (int) $t['id']]) > 0
            && Jurado::reenviarCoordinador((int) $asig);
        flash($ok ? 'exito' : 'error', $ok ? 'Se reenvió el correo al Coordinador con un enlace nuevo.' : 'No hay una solicitud pendiente.');
        redirigir('titulacion/gestion/' . $t['id']);
    }

    /** Expediente completo para auditoría: todos los PDF vigentes del trámite, en orden, con índice y huellas. */
    public function expediente(string $id): void
    {
        $t = $this->tramite($id, true);
        $base = rtrim((string) \App\Core\App::config('archivos.storage'), '/\\');
        $zip = new \ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), 'exp');
        if ($tmp === false || $zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            flash('error', 'No se pudo armar el expediente.');
            redirigir('titulacion/gestion/' . $t['id']);
        }
        $orden = array_flip(array_column(Catalogo::etapas((string) $t['modalidad'], $t['variante'] ?: null), 'clave'));
        $docs = array_values(TitulacionRepository::documentosVigentes((int) $t['id']));
        usort($docs, static fn(array $a, array $b): int => [$orden[$a['etapa']] ?? 99, (int) $a['id']] <=> [$orden[$b['etapa']] ?? 99, (int) $b['id']]);
        $indice = "Expediente de titulación {$t['folio']}\nGenerado el " . date('d/m/Y H:i') . "\n\n";
        $n = 0;
        $agregar = static function (string $ruta, string $nombre, string $detalle) use ($base, $zip, &$indice, &$n): void {
            $abs = realpath($base . '/' . $ruta);
            if ($abs === false || !is_file($abs) || !str_starts_with($abs, realpath($base) . DIRECTORY_SEPARATOR)) {
                return;
            }
            $n++;
            $archivo = sprintf('%02d_%s.pdf', $n, preg_replace('/[^A-Za-z0-9_-]+/', '_', $nombre));
            $zip->addFile($abs, $archivo);
            $indice .= $archivo . "\n  " . $detalle . "\n  SHA-256: " . hash_file('sha256', $abs) . "\n\n";
        };
        foreach ($docs as $d) {
            if (empty($d['ruta']) || $d['estado'] === 'no_aplica') {
                continue;
            }
            $detalle = 'Estado: ' . $d['estado'] . ' · subido ' . $d['creado_en'] . ' por ' . $d['subido_nombre']
                . ($d['revisor_nombre'] ? ' · validado por ' . $d['revisor_nombre'] : '')
                . ($d['subdir_en'] ? ' · firmado por la Subdirección el ' . $d['subdir_en'] . ' (' . $d['subdir_capacidad'] . ')' : '')
                . ($d['cotejo_en'] ? ' · original cotejado el ' . $d['cotejo_en'] : '');
            foreach (Services\FirmasExternas::pendientesDe((int) $d['id']) as $x) {
                $detalle .= ' · firma de ' . $x['rol'] . ' ' . $x['nombre'] . ' <' . $x['email'] . '>: ' . ($x['estado'] === 'firmada' ? 'firmada el ' . $x['firmada_en'] : 'pendiente');
            }
            $agregar((string) ($d['subdir_ruta'] ?: $d['ruta']), $d['etapa'] . '_' . $d['clave'], $detalle);
            if (!empty($d['cotejo_ruta'])) {
                $agregar((string) $d['cotejo_ruta'], $d['etapa'] . '_' . $d['clave'] . '_cotejo', 'Copia con sello de cotejo');
            }
        }
        foreach (RevisionRepository::rondasDeTramite((int) $t['id']) as $r) {
            if (!empty($r['dictamen_ruta'])) {
                $agregar((string) $r['dictamen_ruta'], 'dictamen_protocolo_ronda' . $r['numero'], 'Dictamen consolidado de la comisión · ' . ($r['dictaminado_en'] ?? ''));
            }
        }
        $zip->addFromString('00_indice.txt', $indice);
        $zip->close();
        Audit::record('titulacion::expediente', 'atendido', 'Trámite ' . $t['id']);
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);
        $this->enviarBytes($bytes, 'Expediente_' . $t['folio'] . '.zip', 'application/zip');
    }

    public function reabrir(string $id, string $etapaClave): void
    {
        $t = $this->tramite($id, true);
        $hitos = TitulacionRepository::hitos((int) $t['id']);
        if (!isset($hitos[$etapaClave])) {
            $this->noEncontrado();
        }
        TitulacionRepository::borrarHito((int) $t['id'], $etapaClave);
        $etapaDef = Catalogo::etapa((string) $t['modalidad'], $t['variante'] ?: null, $etapaClave);
        if (!empty($etapaDef['personal']['entrega']['asignacion'])) {
            Database::ejecutar("UPDATE tit_asignaciones_jurado SET estado = 'cancelada' WHERE tramite_id = ? AND etapa = ? AND estado = 'solicitada'", [(int) $t['id'], $etapaClave]);
        }
        if (!empty($etapaDef['personal']['entrega']['oficio'])) {
            Database::ejecutar("UPDATE tit_firmas_subdireccion SET estado = 'cancelado' WHERE tramite_id = ? AND formato = ? AND estado = 'pendiente'", [(int) $t['id'], $etapaDef['personal']['entrega']['clave']]);
        }
        TitulacionRepository::evento((int) $t['id'], Auth::id(), 'hito', 'Etapa reabierta por el departamento: ' . $etapaClave);
        Audit::record('titulacion::reabrir', 'correcto', 'Trámite ' . $t['id'] . ' · ' . $etapaClave);
        $this->sincronizar((int) $t['id']);
        flash('exito', 'Etapa reabierta.');
        redirigir('titulacion/gestion/' . $t['id']);
    }

    public function mensaje(string $id): void
    {
        $t = $this->tramite($id, true);
        $texto = trim($this->post('mensaje'));
        $visible = $this->post('visible') === '1';
        if ($texto === '' || mb_strlen($texto) > 480) {
            flash('error', 'Escribe un mensaje de hasta 480 caracteres.');
            redirigir('titulacion/gestion/' . $t['id']);
        }
        TitulacionRepository::evento((int) $t['id'], Auth::id(), $visible ? 'mensaje' : 'nota', $texto, $visible);
        flash('exito', $visible ? 'Mensaje enviado al pasante.' : 'Nota interna guardada.');
        redirigir('titulacion/gestion/' . $t['id']);
    }

    public function tomar(string $id): void
    {
        $t = $this->tramite($id, true);
        Database::ejecutar('UPDATE tit_tramites SET responsable_id = ? WHERE id = ?', [Auth::id(), (int) $t['id']]);
        TitulacionRepository::evento((int) $t['id'], Auth::id(), 'asignacion', 'Trámite asignado a ' . (Auth::usuario()['nombre'] ?? 'personal'), false);
        redirigir('titulacion/gestion/' . $t['id']);
    }
}
