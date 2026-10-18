<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
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
        $ruta = null;
        if ($entrega !== null) {
            try {
                $ruta = guardar_archivo('entrega', 'titulacion/' . (int) $t['id'], ['pdf']);
            } catch (InvalidArgumentException $e) {
                $errores[] = $e->getMessage();
            } catch (Throwable $e) {
                error_log('Titulación: error al guardar entrega: ' . $e->getMessage());
                $errores[] = 'No se pudo guardar el archivo.';
            }
            if ($ruta === null && !$etapa['entrega_ok']) {
                $errores[] = 'Sube el documento «' . $entrega['nombre'] . '» en PDF.';
            }
        }
        if ($errores) {
            flash('error', implode(' ', array_slice($errores, 0, 3)));
            redirigir($volver);
        }
        if ($entrega !== null && $ruta !== null) {
            TitulacionRepository::registrarDocumento(
                (int) $t['id'], $etapaClave, $entrega['clave'], 'personal', 'aprobado', $ruta,
                mb_substr((string) ($_FILES['entrega']['name'] ?? 'oficio.pdf'), 0, 255), Auth::id()
            );
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

    public function reabrir(string $id, string $etapaClave): void
    {
        $t = $this->tramite($id, true);
        $hitos = TitulacionRepository::hitos((int) $t['id']);
        if (!isset($hitos[$etapaClave])) {
            $this->noEncontrado();
        }
        TitulacionRepository::borrarHito((int) $t['id'], $etapaClave);
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
