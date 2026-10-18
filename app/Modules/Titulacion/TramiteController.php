<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Modules\Titulacion\Services\DocumentosGenerados;
use App\Modules\Titulacion\Services\Formatos;
use InvalidArgumentException;
use Throwable;

/** Entradas «Licenciatura» y «Posgrado» y el asistente paso a paso del pasante. */
final class TramiteController extends TitulacionBase
{
    // ------------------------------------------------------------------ entradas

    public function inicio(): void
    {
        $this->privado();
        $this->vista('inicio', [
            'titulo' => 'Titulación', 'esPersonal' => TitulacionAccess::esPersonal(),
            'veLicenciatura' => TitulacionAccess::veLicenciatura(), 'vePosgrado' => TitulacionAccess::vePosgrado(),
            'esEvaluador' => TitulacionAccess::esEvaluador(), 'esEncargado' => TitulacionAccess::esEncargado(),
        ]);
    }

    public function licenciatura(): void
    {
        $this->privado();
        if (!TitulacionAccess::veLicenciatura()) {
            redirigir('titulacion/posgrado');
        }
        $mis = TitulacionAccess::puedeIniciar() ? TitulacionRepository::delAlumno(Auth::id()) : [];
        foreach ($mis as &$t) {
            $t['datos'] = json_decode((string) $t['datos'], true) ?: [];
            [$flujo] = $this->evaluar($t);
            $t['avance'] = array_sum(array_map(static fn(array $e): int => $e['situacion'] === 'completa' ? 1 : 0, $flujo['etapas']));
            $t['total'] = count($flujo['etapas']);
            $t['paso'] = $flujo['etapas'] ? ($flujo['completo'] ? null : (array_values(array_filter($flujo['etapas'], static fn(array $e): bool => $e['situacion'] === 'actual'))[0] ?? null)) : null;
        }
        unset($t);
        $this->vista('licenciatura', [
            'titulo' => 'Titulación · Licenciatura',
            'modalidades' => Catalogo::modalidades(),
            'mis' => $mis,
            'puedeIniciar' => TitulacionAccess::puedeIniciar(),
            'esPersonal' => TitulacionAccess::esPersonal(),
        ]);
    }

    public function posgrado(): void
    {
        $this->privado();
        if (!TitulacionAccess::vePosgrado()) {
            redirigir('titulacion/licenciatura');
        }
        $this->vista('posgrado', ['titulo' => 'Titulación · Posgrado']);
    }

    public function modalidad(string $clave): void
    {
        $this->privado();
        $m = Catalogo::modalidad($clave);
        if ($m === null) {
            $this->noEncontrado();
        }
        $abierto = TitulacionAccess::puedeIniciar() ? $this->tramiteAbierto() : null;
        $this->vista('modalidad', [
            'titulo' => $m['nombre'],
            'clave' => $clave,
            'm' => $m,
            'etapas' => Catalogo::etapas($clave, isset($m['variantes']) ? array_key_first($m['variantes']) : null),
            'abierto' => $abierto,
            'puedeIniciar' => TitulacionAccess::puedeIniciar(),
            'costos' => Catalogo::COSTOS,
        ]);
    }

    private function tramiteAbierto(): ?array
    {
        return Database::una(
            "SELECT id, folio, modalidad FROM tit_tramites WHERE alumno_id = ? AND nivel = 'licenciatura' AND estado = 'abierto' LIMIT 1",
            [Auth::id()]
        );
    }

    public function crear(string $clave): void
    {
        $m = Catalogo::modalidad($clave);
        if ($m === null || !TitulacionAccess::puedeIniciar()) {
            $this->noEncontrado();
        }
        if ($this->tramiteAbierto() !== null) {
            flash('error', 'Ya tienes un trámite en proceso. Termínalo o cancélalo antes de iniciar otro.');
            redirigir('titulacion/licenciatura');
        }
        $variante = null;
        if (isset($m['variantes'])) {
            $variante = $this->post('variante');
            if (!array_key_exists($variante, $m['variantes'])) {
                flash('error', 'Elige la convocatoria del EGEL.');
                redirigir('titulacion/licenciatura/modalidad/' . $clave);
            }
        }
        if ($this->post('acepto') !== '1') {
            flash('error', 'Confirma que leíste el aviso de privacidad para continuar.');
            redirigir('titulacion/licenciatura/modalidad/' . $clave);
        }
        $u = Auth::usuario();
        $inicial = ['datos' => [
            'nombre' => (string) ($u['nombre'] ?? ''),
            'correo_inst' => (string) ($u['email'] ?? ''),
            'carrera' => Catalogo::carreraPorSiglas($u['programa_siglas'] ?? null),
            'sistema' => 'fi',
        ]];
        $id = TitulacionRepository::crear(Auth::id(), $m['tipo'], $clave, $variante, $inicial);
        TitulacionRepository::evento($id, Auth::id(), 'creacion', 'Trámite iniciado: ' . $m['nombre']);
        Audit::record('titulacion::crear', 'correcto', 'Trámite ' . $id);
        flash('exito', 'Trámite iniciado. Empieza capturando tus datos.');
        redirigir('titulacion/licenciatura/tramite/' . $id);
    }

    // ------------------------------------------------------------------ asistente

    public function ver(string $id): void
    {
        $this->privado();
        $t = $this->tramite($id);
        [$flujo, $docs] = $this->evaluar($t);
        $claves = array_column($flujo['etapas'], 'clave');
        $pedida = (string) ($_GET['etapa'] ?? '');
        $seleccion = in_array($pedida, $claves, true) ? $pedida : ($flujo['actual'] ?? end($claves));
        $etapa = $flujo['etapas'][array_search($seleccion, $claves, true)];
        $esDueno = TitulacionAccess::esDueno($t);
        $formatos = [];
        foreach ($etapa['formatos'] ?? [] as $f) {
            $formatos[$f] = ['def' => Catalogo::formatos()[$f], 'impedimento' => Formatos::impedimento($f, $t)];
        }
        foreach ($etapa['documentos'] ?? [] as $d) {
            if (!empty($d['formato']) && !isset($formatos[$d['formato']]) && isset(Catalogo::formatos()[$d['formato']])) {
                $formatos[$d['formato']] = ['def' => Catalogo::formatos()[$d['formato']], 'impedimento' => Formatos::impedimento($d['formato'], $t)];
            }
        }
        $this->vista('tramite', [
            'linea' => $etapa['clave'] === 'protocolo' ? $this->lineaRevision($t) : null,
            'protocoloEnRevision' => (($ab = RevisionRepository::rondaAbierta((int) $t['id'])) !== null && $ab['estado'] !== 'recibida'),
            'titulo' => 'Trámite ' . $t['folio'],
            't' => $t,
            'modalidad' => Catalogo::modalidad((string) $t['modalidad']),
            'flujo' => $flujo,
            'docs' => $docs,
            'etapa' => $etapa,
            'formatos' => $formatos,
            'esDueno' => $esDueno,
            'puedeEditar' => TitulacionAccess::puedeEditar($t),
            'esPersonal' => TitulacionAccess::esPersonal(),
            'eventos' => TitulacionRepository::eventos((int) $t['id'], !TitulacionAccess::esPersonal()),
            'blanco' => Catalogo::archivosBlanco(),
        ]);
    }

    /**
     * Línea de tiempo de la revisión del protocolo, sin revelar quiénes evalúan.
     *
     * @return array{pasos: array<int,array{texto:string,clase:string}>, ronda: ?array}|null
     */
    private function lineaRevision(array $t): ?array
    {
        $rondas = RevisionRepository::rondasDeTramite((int) $t['id']);
        $r = $rondas[0] ?? null;
        $carrera = $r ? (Catalogo::carreras()[$r['carrera']]['siglas'][0] ?? '') : '';
        $orden = ['recibida' => 1, 'en_comision' => 2, 'en_evaluacion' => 3, 'por_dictaminar' => 3, 'dictaminada' => 4, 'cancelada' => 0];
        $n = $r ? ($orden[$r['estado']] ?? 0) : -1;
        $firmadas = '';
        if ($r && in_array($r['estado'], ['en_evaluacion', 'por_dictaminar'], true)) {
            $asig = array_filter(RevisionRepository::asignaciones((int) $r['id']), static fn(array $a): bool => $a['estado'] !== 'cancelada');
            $firmadas = ' (' . count(array_filter($asig, static fn(array $a): bool => $a['estado'] === 'firmada')) . ' de ' . count($asig) . ' revisiones listas)';
        }
        $marca = static fn(int $paso): string => $n > $paso ? 'hecho' : ($n === $paso ? 'ahora' : '');
        $pasos = [
            ['texto' => 'Sube tu protocolo', 'clase' => $n >= 0 ? 'hecho' : 'ahora'],
            ['texto' => 'Recibido por Titulación', 'clase' => $n < 0 ? '' : $marca(1)],
            ['texto' => 'En la comisión' . ($carrera ? ' de ' . $carrera : ''), 'clase' => $n < 0 ? '' : $marca(2)],
            ['texto' => 'En evaluación' . $firmadas, 'clase' => $n < 0 ? '' : $marca(3)],
        ];
        $fin = ['texto' => 'Dictamen', 'clase' => ''];
        if ($r && $r['estado'] === 'dictaminada') {
            $fin = $r['dictamen'] === 'aprobado' ? ['texto' => 'Protocolo aprobado', 'clase' => 'hecho'] : ['texto' => 'Con observaciones: corrige y vuelve a subir', 'clase' => 'mal'];
        }
        $pasos[] = $fin;
        return ['pasos' => $pasos, 'ronda' => $r && $r['estado'] === 'dictaminada' ? $r : null, 'numero' => $r ? (int) $r['numero'] : 0];
    }

    public function dictamen(string $id, string $ronda): void
    {
        $t = $this->tramite($id);
        $r = ctype_digit($ronda) ? RevisionRepository::ronda((int) $ronda) : null;
        if ($r === null || (int) $r['tramite_id'] !== (int) $t['id'] || $r['estado'] !== 'dictaminada' || empty($r['dictamen_ruta'])) {
            $this->noEncontrado();
        }
        Audit::record('titulacion::dictamen_descarga', 'atendido', 'Ronda ' . (int) $r['id']);
        $this->enviarArchivo((string) $r['dictamen_ruta'], $t['folio'] . '_dictamen_protocolo.pdf', true);
    }

    public function capturar(string $id, string $etapaClave): void
    {
        $t = $this->tramite($id);
        if (!TitulacionAccess::puedeEditar($t)) {
            $this->prohibido('Edición de trámite ajeno o cerrado');
        }
        [$flujo] = $this->evaluar($t);
        $etapa = $this->actual($flujo, $etapaClave, (int) $t['id'], true);
        $entrada = $_POST['c'] ?? [];
        $entrada = is_array($entrada) ? $entrada : [];
        $datos = $t['datos'];
        $guardado = $datos[$etapaClave] ?? [];
        $errores = [];
        foreach ($etapa['campos'] ?? [] as $campo) {
            $k = $campo['k'];
            if ($campo['tipo'] === 'cronograma') {
                $valor = $this->leerCronograma();
            } else {
                $bruto = $entrada[$k] ?? '';
                $valor = is_string($bruto) ? trim(preg_replace('/\r\n?/', "\n", $bruto) ?? '') : '';
                if ($campo['tipo'] !== 'area') {
                    $valor = preg_replace('/\s+/u', ' ', $valor) ?? '';
                }
                if (isset($campo['max'])) {
                    $valor = mb_substr($valor, 0, (int) $campo['max']);
                }
                if ($campo['tipo'] === 'select' && !array_key_exists($valor, $campo['opciones'] ?? [])) {
                    $valor = '';
                }
            }
            $error = Flujo::errorCampo($campo, $valor);
            if ($error !== null) {
                $errores[] = $error;
            }
            $guardado[$k] = $valor;
        }
        $datos[$etapaClave] = $guardado;
        $titulo = null;
        foreach ($etapa['campos'] ?? [] as $campo) {
            if (!empty($campo['tema']) || ($etapaClave === 'revision' && $campo['k'] === 'titulo')) {
                $titulo = (string) ($guardado[$campo['k']] ?? '');
            }
        }
        TitulacionRepository::guardarDatos((int) $t['id'], $datos, $titulo !== null && $titulo !== '' ? $titulo : null);
        TitulacionRepository::evento((int) $t['id'], Auth::id(), 'captura', 'Datos guardados en «' . $etapa['titulo'] . '»');
        $this->sincronizar((int) $t['id']);
        if ($errores) {
            flash('error', 'Guardamos tu avance, pero falta corregir: ' . implode(' ', array_slice($errores, 0, 3)));
        } else {
            flash('exito', 'Datos guardados.');
        }
        redirigir('titulacion/licenciatura/tramite/' . $t['id'] . '?etapa=' . $etapaClave);
    }

    /** @return array<int,array{a:string,b:array<int,int>}> */
    private function leerCronograma(): array
    {
        $actividades = $_POST['crono_a'] ?? [];
        $meses = $_POST['crono_b'] ?? [];
        $filas = [];
        if (!is_array($actividades)) {
            return [];
        }
        foreach (array_slice(array_values($actividades), 0, 8, true) as $i => $a) {
            $a = is_string($a) ? trim(preg_replace('/\s+/u', ' ', $a) ?? '') : '';
            if ($a === '') {
                continue;
            }
            $b = [];
            foreach ((array) ($meses[$i] ?? []) as $m) {
                $m = (int) $m;
                if ($m >= 1 && $m <= 12) {
                    $b[$m] = $m;
                }
            }
            sort($b);
            $filas[] = ['a' => mb_substr($a, 0, 120), 'b' => array_values($b)];
        }
        return $filas;
    }

    public function formato(string $id, string $formato): void
    {
        $t = $this->tramite($id);
        if (!isset(Catalogo::formatos()[$formato])) {
            $this->noEncontrado();
        }
        try {
            [$bytes, $nombre, $mime] = Formatos::generar($formato, $t);
        } catch (Throwable $e) {
            if (!$e instanceof \RuntimeException) {
                error_log('Titulación: no se pudo generar ' . $formato . ': ' . $e->getMessage());
            }
            flash('error', $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo generar el formato. Intenta de nuevo.');
            redirigir('titulacion/licenciatura/tramite/' . $t['id']);
        }
        TitulacionRepository::evento((int) $t['id'], Auth::id(), 'formato', 'Formato generado: ' . Catalogo::formatos()[$formato]['nombre'], false);
        $this->enviarBytes($bytes, $nombre, $mime);
    }

    public function art5(string $id): void
    {
        $t = $this->tramite($id);
        if (!TitulacionAccess::puedeEditar($t)) {
            $this->prohibido('Edición de trámite ajeno o cerrado');
        }
        $motivos = trim((string) ($_POST['motivos'] ?? ''));
        if (mb_strlen($motivos) < 20 || mb_strlen($motivos) > 900) {
            flash('error', 'Explica tus motivos con entre 20 y 900 caracteres.');
            redirigir('titulacion/licenciatura/tramite/' . $t['id'] . '#art5');
        }
        $datos = $t['datos'];
        $datos['art5']['motivos'] = $motivos;
        TitulacionRepository::guardarDatos((int) $t['id'], $datos);
        flash('exito', 'Motivos guardados. Ya puedes descargar la solicitud del Art. 5.');
        redirigir('titulacion/licenciatura/tramite/' . $t['id'] . '#art5');
    }

    // ----------------------------------------------------------------- documentos

    public function subir(string $id, string $etapaClave, string $docClave): void
    {
        $t = $this->tramite($id);
        if (!TitulacionAccess::puedeEditar($t)) {
            $this->prohibido('Subida a trámite ajeno o cerrado');
        }
        $volver = 'titulacion/licenciatura/tramite/' . $t['id'] . '?etapa=' . $etapaClave;
        [$flujo, $docs] = $this->evaluar($t);
        $etapa = $this->actual($flujo, $etapaClave, (int) $t['id']);
        $def = Catalogo::documentoDeEtapa($etapa, $docClave);
        if ($def === null || $def['origen'] !== 'alumno' || !empty($def['generado'])) {
            $this->noEncontrado();
        }
        if (!$etapa['propia_ok']) {
            flash('error', 'Completa primero los datos de esta etapa.');
            redirigir($volver);
        }
        $actual = $docs[$etapaClave . '|' . $docClave] ?? null;
        if ($actual && $actual['estado'] === 'aprobado') {
            flash('error', 'Ese documento ya fue validado.');
            redirigir($volver);
        }
        $esProtocolo = $etapaClave === 'protocolo' && $docClave === 'protocolo_firmado';
        if ($esProtocolo) {
            $abierta = RevisionRepository::rondaAbierta((int) $t['id']);
            if ($abierta && $abierta['estado'] !== 'recibida') {
                flash('error', 'Tu protocolo ya está en revisión de la comisión; no puedes reemplazarlo hasta recibir el dictamen.');
                redirigir($volver);
            }
        }
        try {
            $ruta = guardar_archivo('archivo', 'titulacion/' . (int) $t['id'], ['pdf']);
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirigir($volver);
        } catch (Throwable $e) {
            error_log('Titulación: error al guardar archivo: ' . $e->getMessage());
            flash('error', 'No se pudo guardar el archivo. Intenta de nuevo.');
            redirigir($volver);
        }
        if ($ruta === null) {
            flash('error', 'Selecciona un archivo PDF.');
            redirigir($volver);
        }
        $nombre = (string) ($_FILES['archivo']['name'] ?? 'documento.pdf');
        $docId = TitulacionRepository::registrarDocumento((int) $t['id'], $etapaClave, $docClave, 'alumno', 'en_revision', $ruta, mb_substr($nombre, 0, 255), Auth::id());
        if ($esProtocolo) {
            try {
                Services\RevisionProtocolo::nuevaRonda($t, $docId);
            } catch (Throwable $e) {
                error_log('Titulación: no se pudo abrir la revisión del protocolo: ' . $e->getMessage());
            }
        }
        TitulacionRepository::evento((int) $t['id'], Auth::id(), 'documento', 'Documento enviado a validación: ' . $def['nombre']);
        Audit::record('titulacion::subir', 'correcto', 'Trámite ' . $t['id'] . ' · ' . $docClave);
        $this->sincronizar((int) $t['id']);
        flash('exito', 'Documento recibido. Personal de Titulación lo validará.');
        redirigir($volver);
    }

    /** Firma electrónicamente el formato generado con los datos del pasante y lo envía a validación (sin descargar ni subir nada). */
    public function generar(string $id, string $etapaClave, string $docClave): void
    {
        $t = $this->tramite($id);
        if (!TitulacionAccess::puedeEditar($t)) {
            $this->prohibido('Firma en trámite ajeno o cerrado');
        }
        $volver = 'titulacion/licenciatura/tramite/' . $t['id'] . '?etapa=' . $etapaClave;
        [$flujo, $docs] = $this->evaluar($t);
        $etapa = $this->actual($flujo, $etapaClave, (int) $t['id']);
        $def = Catalogo::documentoDeEtapa($etapa, $docClave);
        if ($def === null || $def['origen'] !== 'alumno' || empty($def['generado']) || empty($def['formato'])) {
            $this->noEncontrado();
        }
        if (!$etapa['propia_ok']) {
            flash('error', 'Completa primero los datos de esta etapa.');
            redirigir($volver);
        }
        $actual = $docs[$etapaClave . '|' . $docClave] ?? null;
        if ($actual && in_array($actual['estado'], ['aprobado', 'en_revision'], true)) {
            flash('error', 'Ese documento ya fue enviado.');
            redirigir($volver);
        }
        try {
            $ruta = DocumentosGenerados::firmarPorPasante($t, (string) $def['formato'], (string) ($t['datos']['datos']['nombre'] ?? Auth::usuario()['nombre'] ?? ''));
        } catch (Throwable $e) {
            if (!$e instanceof \RuntimeException) {
                error_log('Titulación: no se pudo firmar ' . $docClave . ': ' . $e->getMessage());
            }
            flash('error', $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo generar el documento. Intenta de nuevo.');
            redirigir($volver);
        }
        TitulacionRepository::registrarDocumento((int) $t['id'], $etapaClave, $docClave, 'alumno', 'en_revision', $ruta, $def['formato'] . '_' . $t['folio'] . '.pdf', Auth::id());
        TitulacionRepository::evento((int) $t['id'], Auth::id(), 'documento', 'Documento firmado por el pasante y enviado a validación: ' . $def['nombre']);
        Audit::record('titulacion::firma_pasante', 'correcto', 'Trámite ' . $t['id'] . ' · ' . $docClave);
        $this->sincronizar((int) $t['id']);
        flash('exito', 'Documento firmado y enviado. Personal de Titulación lo validará.');
        redirigir($volver);
    }

    public function noAplica(string $id, string $etapaClave, string $docClave): void
    {
        $t = $this->tramite($id);
        if (!TitulacionAccess::puedeEditar($t)) {
            $this->prohibido('Edición de trámite ajeno o cerrado');
        }
        $volver = 'titulacion/licenciatura/tramite/' . $t['id'] . '?etapa=' . $etapaClave;
        [$flujo, $docs] = $this->evaluar($t);
        $etapa = $this->actual($flujo, $etapaClave, (int) $t['id']);
        $def = Catalogo::documentoDeEtapa($etapa, $docClave);
        if ($def === null || $def['origen'] !== 'alumno' || $def['condicion'] === null) {
            $this->noEncontrado();
        }
        $actual = $docs[$etapaClave . '|' . $docClave] ?? null;
        if ($actual && $actual['estado'] === 'aprobado') {
            redirigir($volver);
        }
        TitulacionRepository::registrarDocumento((int) $t['id'], $etapaClave, $docClave, 'alumno', 'no_aplica', null, null, Auth::id());
        TitulacionRepository::evento((int) $t['id'], Auth::id(), 'documento', 'Indicó que no le aplica: ' . $def['nombre']);
        $this->sincronizar((int) $t['id']);
        flash('exito', 'Registrado. Si el personal considera que sí aplica, te lo indicará aquí.');
        redirigir($volver);
    }

    public function confirmar(string $id, string $etapaClave): void
    {
        $t = $this->tramite($id);
        if (!TitulacionAccess::puedeEditar($t)) {
            $this->prohibido('Edición de trámite ajeno o cerrado');
        }
        [$flujo] = $this->evaluar($t);
        $etapa = $this->actual($flujo, $etapaClave, (int) $t['id']);
        if ($etapa['tipo'] !== 'confirmacion') {
            $this->noEncontrado();
        }
        $datos = $t['datos'];
        $datos['_confirmacion'][$etapaClave] = date('Y-m-d H:i:s');
        TitulacionRepository::guardarDatos((int) $t['id'], $datos);
        TitulacionRepository::evento((int) $t['id'], Auth::id(), 'confirmacion', $etapa['confirmar'] ?? 'Etapa confirmada');
        $this->sincronizar((int) $t['id']);
        flash('exito', 'Listo. Continúa con la siguiente etapa.');
        redirigir('titulacion/licenciatura/tramite/' . $t['id']);
    }

    /** La etapa debe existir y estar abierta; con $permitirCompleta también acepta una ya terminada. */
    private function actual(array $flujo, string $clave, int $tramiteId, bool $permitirCompleta = false): array
    {
        foreach ($flujo['etapas'] as $e) {
            if ($e['clave'] === $clave) {
                if ($e['situacion'] !== 'actual' && !($permitirCompleta && $e['situacion'] === 'completa')) {
                    flash('error', $e['situacion'] === 'bloqueada' ? 'Esa etapa aún no está disponible.' : 'Esa etapa ya fue completada.');
                    redirigir('titulacion/licenciatura/tramite/' . $tramiteId);
                }
                return $e;
            }
        }
        $this->noEncontrado();
    }

    public function archivo(string $id, string $docId): void
    {
        $t = $this->tramite($id);
        $d = ctype_digit($docId) ? TitulacionRepository::documento((int) $docId) : null;
        if ($d === null || (int) $d['tramite_id'] !== (int) $t['id'] || empty($d['ruta'])) {
            $this->noEncontrado();
        }
        Audit::record('titulacion::archivo', 'atendido', 'Documento ' . (int) $d['id']);
        if (isset($_GET['cotejo'])) {
            if (empty($d['cotejo_ruta'])) {
                $this->noEncontrado();
            }
            $this->enviarArchivo((string) $d['cotejo_ruta'], $t['folio'] . '_' . $d['clave'] . '_cotejo.pdf', true);
        }
        $this->enviarArchivo((string) ($d['subdir_ruta'] ?: $d['ruta']), $t['folio'] . '_' . $d['clave'] . '.pdf', isset($_GET['ver']));
    }

    public function blanco(string $clave): void
    {
        $lista = Catalogo::archivosBlanco();
        if (!isset($lista[$clave])) {
            $this->noEncontrado();
        }
        $archivo = __DIR__ . '/plantillas/blanco/' . $lista[$clave][1];
        if (!is_file($archivo)) {
            $this->noEncontrado();
        }
        $this->privado();
        $ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($ext === 'pdf' ? 'application/pdf' : Formatos::MIME_DOCX));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . basename($archivo) . '"');
        header('Content-Length: ' . filesize($archivo));
        readfile($archivo);
        exit;
    }

    public function cancelar(string $id): void
    {
        $t = $this->tramite($id);
        if (!TitulacionAccess::puedeEditar($t)) {
            $this->prohibido('Cancelación de trámite ajeno o cerrado');
        }
        Database::ejecutar("UPDATE tit_tramites SET estado = 'cancelado' WHERE id = ?", [(int) $t['id']]);
        TitulacionRepository::evento((int) $t['id'], Auth::id(), 'cancelacion', 'El pasante canceló el trámite');
        flash('exito', 'Trámite cancelado. Puedes iniciar otro cuando quieras.');
        redirigir('titulacion/licenciatura');
    }
}
