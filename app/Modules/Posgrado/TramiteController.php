<?php
declare(strict_types=1);

namespace App\Modules\Posgrado;

use App\Core\App;
use App\Core\Audit;
use App\Core\Auth;
use App\Modules\Posgrado\Services\Formatos;
use App\Modules\Titulacion\Services\Calendario;
use InvalidArgumentException;
use Throwable;

/** Entrada «Posgrado: obtener mi grado» y el trámite del egresado (cuatro momentos). */
final class TramiteController extends PosgradoBase
{
    public function inicio(): void
    {
        $this->privado();
        if (!PosgradoAccess::veTramite()) {
            redirigir('titulacion/licenciatura');
        }
        $mis = PosgradoAccess::puedeIniciar() ? PosgradoRepository::delAlumno(Auth::id()) : [];
        $this->vista('inicio', [
            'titulo' => 'Posgrado: obtener mi grado',
            'mis' => $mis,
            'abierto' => PosgradoAccess::puedeIniciar() ? PosgradoRepository::abiertoDe(Auth::id()) : null,
            'puedeIniciar' => PosgradoAccess::puedeIniciar(),
            'esPersonal' => PosgradoAccess::esPersonal(),
            'usuario' => Auth::usuario() ?? [],
        ]);
    }

    public function iniciar(): void
    {
        if (!PosgradoAccess::puedeIniciar()) {
            $this->prohibido('Inicio de trámite de grado');
        }
        if (($abierto = PosgradoRepository::abiertoDe(Auth::id())) !== null) {
            flash('error', 'Ya tienes un trámite en proceso; continúa con él.');
            redirigir('posgrado/tramite/' . (int) $abierto['id']);
        }
        if ($this->post('acepto') !== '1') {
            flash('error', 'Confirma que leíste el aviso de privacidad para comenzar.');
            redirigir('posgrado');
        }
        $u = Auth::usuario() ?? [];
        $programa = (string) ($u['programa'] ?? '');
        $nivel = preg_match('/^Doctorado/iu', $programa) ? 'doctorado' : (preg_match('/^Especialidad/iu', $programa) ? 'especialidad' : 'maestria');
        $id = PosgradoRepository::crear(Auth::id(), $nivel, ['interesado' => [
            'nombre' => (string) ($u['nombre'] ?? ''),
            'correo' => (string) ($u['email'] ?? ''),
            'programa' => $programa,
            'programa_siglas' => (string) ($u['programa_siglas'] ?? ''),
            'nivel' => $nivel,
        ]]);
        PosgradoRepository::evento($id, Auth::id(), 'creacion', 'Trámite de evaluación de grado iniciado.');
        Audit::record('posgrado::iniciar', 'correcto', 'Trámite ' . $id);
        flash('exito', 'Listo, tu trámite está abierto. Empieza por capturar tus datos.');
        redirigir('posgrado/tramite/' . $id . '#datos');
    }

    public function ver(string $id): void
    {
        $this->privado();
        $t = $this->tramite($id);
        [$ev] = $this->evaluar($t);
        $hoy = new \DateTimeImmutable('today');
        $this->vista('tramite', [
            'titulo' => 'Mi evaluación de grado · ' . $t['folio'],
            't' => $t,
            'ev' => $ev,
            'esDueno' => PosgradoAccess::esDueno($t),
            'puedeEditar' => PosgradoAccess::puedeEditar($t),
            'esPersonal' => PosgradoAccess::esPersonal(),
            'eventos' => PosgradoRepository::eventos((int) $t['id'], !PosgradoAccess::esPersonal()),
            'fechaMinima' => Calendario::agregarHabiles($hoy, Catalogo::DIAS_MINIMOS)->format('Y-m-d'),
            'fechaSugerida' => Calendario::agregarHabiles($hoy, Catalogo::DIAS_SUGERIDOS)->format('Y-m-d'),
            'maxMb' => self::maxMb(),
        ]);
    }

    private static function maxMb(): int
    {
        try {
            return max(1, (int) \ajuste('titulacion_max_mb', '5'));
        } catch (Throwable) {
            return 5;
        }
    }

    // ------------------------------------------------------------ datos del interesado

    public function datos(string $id): void
    {
        $t = $this->tramite($id);
        if (!PosgradoAccess::puedeEditar($t)) {
            $this->prohibido('Edición de trámite ajeno o cerrado');
        }
        $volver = 'posgrado/tramite/' . (int) $t['id'] . '#datos';
        $hitos = PosgradoRepository::hitos((int) $t['id']);
        if (isset($hitos['cotejo'])) {
            flash('error', 'Tus datos ya se cotejaron con tus originales. Si necesitas corregir algo, pídelo a Apoyo a Grado.');
            redirigir($volver);
        }
        $c = is_array($_POST['c'] ?? null) ? $_POST['c'] : [];
        $i = $t['datos']['interesado'] ?? [];
        $errores = [];
        $txt = static fn(string $k, int $max): string => self::linea($c[$k] ?? '', $max);

        $i['nombre'] = $txt('nombre', 120);
        $i['acentos'] = in_array($c['acentos'] ?? '', ['si', 'no'], true) ? (string) $c['acentos'] : '';
        $i['cuenta'] = preg_replace('/\D+/', '', $txt('cuenta', 12)) ?? '';
        if ($i['cuenta'] !== '' && !preg_match('/^\d{6,9}$/D', $i['cuenta'])) {
            $errores[] = 'El número de cuenta debe tener de 6 a 9 dígitos.';
            $i['cuenta'] = '';
        }
        $i['programa'] = $txt('programa', 150);
        $i['nivel'] = array_key_exists((string) ($c['nivel'] ?? ''), Catalogo::niveles()) ? (string) $c['nivel'] : '';
        $i['plan'] = $txt('plan', 120);
        $i['telefono'] = $txt('telefono', 30);
        if ($i['telefono'] !== '' && !preg_match('/^[0-9 ()+\-.]{8,30}$/D', $i['telefono'])) {
            $errores[] = 'Escribe tu teléfono solo con números (10 dígitos).';
            $i['telefono'] = '';
        }
        $i['correo'] = strtolower($txt('correo', 120));
        if ($i['correo'] !== '' && !filter_var($i['correo'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Revisa tu correo electrónico.';
            $i['correo'] = '';
        }
        $i['ocupacion'] = $txt('ocupacion', 120);
        $i['periodo_inicio'] = strtoupper($txt('periodo_inicio', 5));
        if ($i['periodo_inicio'] !== '' && !preg_match('/^(19|20)\d{2}[AB]$/D', $i['periodo_inicio'])) {
            $errores[] = 'El periodo de inicio se escribe como 2024B (año y letra A o B).';
            $i['periodo_inicio'] = '';
        }
        $i['titulo'] = $txt('titulo', 500);
        $i['modalidad'] = array_key_exists((string) ($c['modalidad'] ?? ''), Catalogo::modalidades()) ? (string) $c['modalidad'] : '';
        $i['fecha_previo'] = $txt('fecha_previo', 10);
        if ($i['fecha_previo'] !== '' && (!self::fechaValida($i['fecha_previo']) || $i['fecha_previo'] > date('Y-m-d'))) {
            $errores[] = 'La fecha del examen previo no es válida.';
            $i['fecha_previo'] = '';
        }
        foreach (['director' => true, 'codirector' => false, 'tutor' => false] as $rol => $obligatorio) {
            $i[$rol . '_grado'] = $txt($rol . '_grado', 20);
            $i[$rol . '_nombre'] = $txt($rol . '_nombre', 120);
            $i[$rol . '_correo'] = strtolower($txt($rol . '_correo', 120));
            if ($i[$rol . '_correo'] !== '' && !filter_var($i[$rol . '_correo'], FILTER_VALIDATE_EMAIL)) {
                $errores[] = 'Revisa el correo de tu ' . $rol . '.';
                $i[$rol . '_correo'] = '';
            }
            if (!$obligatorio && $i[$rol . '_nombre'] !== '' && $i[$rol . '_grado'] === '') {
                $errores[] = 'Indica el grado de tu ' . $rol . ' (por ejemplo Dr. o Mtra.).';
            }
        }
        $i['art53'] = in_array($c['art53'] ?? '', ['si', 'no'], true) ? (string) $c['art53'] : '';

        $datos = $t['datos'];
        $datos['interesado'] = $i;
        PosgradoRepository::guardarDatos((int) $t['id'], $datos);
        PosgradoRepository::evento((int) $t['id'], Auth::id(), 'captura', 'Datos del interesado guardados.');
        $t = $this->sincronizar((int) $t['id']);
        $faltan = Flujo::faltantesInteresado($t);
        if ($errores) {
            flash('error', 'Guardamos tu avance, pero revisa: ' . implode(' ', array_slice($errores, 0, 3)));
        } elseif ($faltan) {
            flash('exito', 'Guardamos tu avance. Aún faltan: ' . implode(', ', array_slice($faltan, 0, 6)) . (count($faltan) > 6 ? '…' : '') . '.');
        } else {
            flash('exito', 'Datos completos. Ya puedes descargar tus formatos y subir tus documentos.');
        }
        redirigir($volver);
    }

    // ---------------------------------------------------------- sínodo y fecha

    public function sinodo(string $id): void
    {
        $t = $this->tramite($id);
        if (!PosgradoAccess::puedeEditar($t)) {
            $this->prohibido('Edición de trámite ajeno o cerrado');
        }
        $volver = 'posgrado/tramite/' . (int) $t['id'] . '#sinodo';
        [$ev] = $this->evaluar($t);
        if (!$ev['hecho'][1]) {
            flash('error', 'Primero termina tus requisitos: el sínodo se propone después del cotejo de originales.');
            redirigir($volver);
        }
        if ($ev['hecho'][2]) {
            flash('error', 'Tu examen ya está programado. Si necesitas un cambio, pídelo a Apoyo a Grado.');
            redirigir($volver);
        }
        $entrada = is_array($_POST['m'] ?? null) ? $_POST['m'] : [];
        $miembros = [];
        $errores = [];
        for ($n = 0; $n < 7; $n++) {
            $m = is_array($entrada[$n] ?? null) ? $entrada[$n] : [];
            $correo = strtolower(self::linea($m['correo'] ?? '', 120));
            if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $errores[] = 'Revisa el correo del integrante ' . ($n + 1) . '.';
                $correo = '';
            }
            $miembros[] = [
                'nombre' => self::linea($m['nombre'] ?? '', 150),
                'tipo' => array_key_exists((string) ($m['tipo'] ?? ''), Catalogo::tiposMiembro()) ? (string) $m['tipo'] : '',
                'correo' => $correo,
                'externo' => !empty($m['externo']) ? 1 : 0,
            ];
        }
        $nombres = array_filter(array_map(static fn(array $m): string => mb_strtolower($m['nombre']), $miembros));
        if (count($nombres) !== count(array_unique($nombres))) {
            $errores[] = 'Hay un nombre repetido en tu sínodo.';
        }
        $fecha = self::linea($_POST['fecha'] ?? '', 10);
        $hora = self::linea($_POST['hora'] ?? '', 5);
        $modalidad = in_array($_POST['modalidad'] ?? '', ['presencial', 'virtual'], true) ? (string) $_POST['modalidad'] : '';
        $hoy = new \DateTimeImmutable('today');
        $minima = Calendario::agregarHabiles($hoy, Catalogo::DIAS_MINIMOS);
        if ($fecha !== '' && !self::fechaValida($fecha)) {
            $errores[] = 'La fecha no es válida.';
            $fecha = '';
        } elseif ($fecha !== '' && $fecha < $minima->format('Y-m-d')) {
            $sugerida = Calendario::agregarHabiles($hoy, Catalogo::DIAS_SUGERIDOS);
            $errores[] = 'Tu examen debe quedar al menos a ' . Catalogo::DIAS_MINIMOS . ' días hábiles de hoy: elige el ' . Flujo::fechaLarga($minima->format('Y-m-d'))
                . ' o después. Te sugerimos dejar ' . Catalogo::DIAS_SUGERIDOS . ' días hábiles, es decir, del ' . Flujo::fechaLarga($sugerida->format('Y-m-d')) . ' en adelante, para recabar las firmas con calma.';
            $fecha = '';
        } elseif ($fecha !== '' && !Calendario::esHabil(new \DateTimeImmutable($fecha))) {
            $errores[] = 'Elige un día hábil (de lunes a viernes).';
            $fecha = '';
        }
        if ($hora !== '' && (!self::horaValida($hora) || $hora < '07:00' || $hora > '20:00')) {
            $errores[] = 'Elige una hora entre las 07:00 y las 20:00.';
            $hora = '';
        }
        $datos = $t['datos'];
        $antes = $datos['sinodo'] ?? [];
        $datos['sinodo'] = ['miembros' => $miembros, 'fecha' => $fecha, 'hora' => $hora, 'modalidad' => $modalidad];
        PosgradoRepository::guardarDatos((int) $t['id'], $datos);
        $cambio = ($antes['miembros'] ?? null) !== $miembros || ($antes['fecha'] ?? '') !== $fecha || ($antes['hora'] ?? '') !== $hora;
        $retirados = [];
        if ($cambio) {
            // Los formatos firmados ya no corresponden a la nueva propuesta.
            foreach (['solicitud_firmada', 'visto_bueno_firmado'] as $clave) {
                if (isset($ev['m2docs'][$clave]['doc'])) {
                    PosgradoRepository::retirarDocumento((int) $t['id'], $clave);
                    $retirados[] = Catalogo::documentosMomento()[$clave]['nombre'];
                }
            }
        }
        PosgradoRepository::evento((int) $t['id'], Auth::id(), 'captura', 'Propuesta de sínodo y fecha guardada.');
        $t = $this->sincronizar((int) $t['id']);
        $faltan = Flujo::faltantesSinodo($t);
        $aviso = $retirados ? ' Como cambiaste la propuesta, vuelve a recabar firmas y a subir: ' . implode('; ', $retirados) . '.' : '';
        if ($errores) {
            flash('error', 'Guardamos tu avance, pero revisa: ' . implode(' ', array_slice($errores, 0, 3)) . $aviso);
        } elseif ($faltan) {
            flash('exito', 'Guardamos tu avance. Aún falta: ' . implode(', ', array_slice($faltan, 0, 5)) . '.' . $aviso);
        } else {
            flash('exito', 'Propuesta completa. Descarga la solicitud y el visto bueno para recabar las firmas.' . $aviso);
        }
        redirigir($volver);
    }

    // -------------------------------------------------------------- documentos

    public function subir(string $id, string $clave): void
    {
        $t = $this->tramite($id);
        if (!PosgradoAccess::puedeEditar($t)) {
            $this->prohibido('Subida a trámite ajeno o cerrado');
        }
        [$ev] = $this->evaluar($t);
        $req = $ev['requisitos'][$clave] ?? null;
        $mom = $ev['m2docs'][$clave] ?? null;
        if (($req === null || $req['tipo'] !== 'pdf') && $mom === null) {
            $this->noEncontrado();
        }
        $volver = 'posgrado/tramite/' . (int) $t['id'] . ($req !== null ? '#req-' : '#doc-') . $clave;
        if ($req !== null) {
            if ($ev['hecho'][1]) {
                flash('error', 'Tus requisitos ya se cotejaron; ya no se pueden reemplazar.');
                redirigir($volver);
            }
            if ($ev['faltanDatos']) {
                flash('error', 'Antes de subir documentos, completa tus datos.');
                redirigir('posgrado/tramite/' . (int) $t['id'] . '#datos');
            }
            $actual = $req['estado'];
        } else {
            $permitido = $mom['momento'] === 2
                ? ($ev['actual'] === 2 && !$ev['faltanSinodo'])
                : ($ev['actual'] === 3 && $ev['invitacionesListas']);
            if (!$permitido) {
                flash('error', $mom['momento'] === 2 ? 'Completa primero tu propuesta de sínodo y fecha.' : 'Podrás subir el acuse cuando Apoyo a Grado emita tus invitaciones.');
                redirigir($volver);
            }
            $actual = $mom['estado'];
        }
        if ($actual === 'validado') {
            flash('error', 'Ese documento ya fue validado.');
            redirigir($volver);
        }
        $maxMb = self::maxMb();
        $errorSubida = (int) ($_FILES['archivo']['error'] ?? UPLOAD_ERR_OK);
        if (!empty($_FILES['archivo']) && ((int) ($_FILES['archivo']['size'] ?? 0) > $maxMb * 1048576 || in_array($errorSubida, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true))) {
            flash('error', 'El PDF pesa más de ' . $maxMb . ' MB. Escanéalo con menor resolución o comprímelo antes de subirlo.');
            redirigir($volver);
        }
        try {
            $ruta = guardar_archivo('archivo', 'posgrado/' . (int) $t['id'], ['pdf']);
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirigir($volver);
        } catch (Throwable $e) {
            error_log('Posgrado: error al guardar archivo: ' . $e->getMessage());
            flash('error', 'No se pudo guardar el archivo. Intenta de nuevo.');
            redirigir($volver);
        }
        if ($ruta === null) {
            flash('error', 'Selecciona un archivo PDF.');
            redirigir($volver);
        }
        $absoluta = rtrim((string) App::config('archivos.storage'), '/\\') . '/' . $ruta;
        $huella = is_file($absoluta) ? (string) hash_file('sha256', $absoluta) : null;
        $nombre = mb_substr((string) ($_FILES['archivo']['name'] ?? 'documento.pdf'), 0, 255);
        PosgradoRepository::registrarDocumento((int) $t['id'], $clave, 'pdf', 'subido', $ruta, $nombre, $huella, Auth::id());
        $etiqueta = $req['nombre'] ?? $mom['nombre'];
        PosgradoRepository::evento((int) $t['id'], Auth::id(), 'documento', 'Documento enviado a validación: ' . $etiqueta);
        Audit::record('posgrado::subir', 'correcto', 'Trámite ' . (int) $t['id'] . ' · ' . $clave);
        $this->sincronizar((int) $t['id']);
        flash('exito', 'Recibimos «' . $etiqueta . '». Apoyo a Grado lo revisará.');
        redirigir($volver);
    }

    public function sicde(string $id): void
    {
        $t = $this->tramite($id);
        if (!PosgradoAccess::puedeEditar($t)) {
            $this->prohibido('Edición de trámite ajeno o cerrado');
        }
        $volver = 'posgrado/tramite/' . (int) $t['id'] . '#cierre';
        [$ev] = $this->evaluar($t);
        if (!$ev['aprobado']) {
            flash('error', 'El folio del SICDE se captura después de aprobar tu examen.');
            redirigir($volver);
        }
        $folio = strtoupper(self::linea($_POST['folio_sicde'] ?? '', 40));
        if (!preg_match('/^[A-Z0-9][A-Z0-9\-\/]{3,39}$/D', $folio)) {
            flash('error', 'Escribe el folio tal como te lo dio el SICDE (letras, números y guiones).');
            redirigir($volver);
        }
        $datos = $t['datos'];
        $datos['cierre'] = ['folio_sicde' => $folio, 'capturado_en' => date('Y-m-d H:i:s')];
        PosgradoRepository::guardarDatos((int) $t['id'], $datos);
        PosgradoRepository::evento((int) $t['id'], Auth::id(), 'sicde', 'Folio de seguimiento del SICDE registrado.');
        Audit::record('posgrado::sicde', 'correcto', 'Trámite ' . (int) $t['id']);
        $this->sincronizar((int) $t['id']);
        flash('exito', 'Folio registrado. Apoyo a Grado integrará tu expediente.');
        redirigir($volver);
    }

    // ------------------------------------------------------------ descargas

    /** Formatos del egresado generados al vuelo con sus datos (voto, datos del interesado, solicitud y visto bueno). */
    public function formato(string $id, string $clave): void
    {
        $t = $this->tramite($id);
        if (!isset(Catalogo::formatosEgresado()[$clave])) {
            $this->noEncontrado();
        }
        try {
            [$bytes, $nombre] = Formatos::generar($clave, $t, PosgradoRepository::hitos((int) $t['id']));
        } catch (Throwable $e) {
            if (!$e instanceof \RuntimeException) {
                error_log('Posgrado: no se pudo generar ' . $clave . ': ' . $e->getMessage());
            }
            flash('error', $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo generar el formato. Intenta de nuevo.');
            redirigir('posgrado/tramite/' . (int) $t['id']);
        }
        PosgradoRepository::evento((int) $t['id'], Auth::id(), 'formato', 'Formato descargado: ' . Catalogo::formatosEgresado()[$clave], false);
        $this->enviarBytes($bytes, $nombre);
    }

    /** PDF que subió el egresado: lo ve él y el personal. */
    public function documento(string $id): void
    {
        $d = ctype_digit($id) && strlen($id) < 19 ? PosgradoRepository::documento((int) $id) : null;
        if ($d === null || empty($d['ruta'])) {
            $this->noEncontrado();
        }
        $t = $this->tramite((string) $d['tramite_id']);
        Audit::record('posgrado::documento', 'atendido', 'Documento ' . (int) $d['id']);
        $this->enviarArchivo((string) $d['ruta'], $t['folio'] . '_' . $d['clave'] . '.pdf', true);
    }

    /** Oficio o constancia emitido por la plataforma. */
    public function generado(string $id): void
    {
        $g = ctype_digit($id) && strlen($id) < 19 ? PosgradoRepository::generado((int) $id) : null;
        if ($g === null) {
            $this->noEncontrado();
        }
        $t = $this->tramite((string) $g['tramite_id']);
        Audit::record('posgrado::generado', 'atendido', 'Generado ' . (int) $g['id']);
        $this->enviarArchivo((string) $g['ruta'], $g['clave'] . '_' . $t['folio'] . '.pdf', true);
    }
}
