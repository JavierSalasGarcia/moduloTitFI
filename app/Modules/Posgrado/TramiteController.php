<?php
declare(strict_types=1);

namespace App\Modules\Posgrado;

use App\Core\App;
use App\Core\Audit;
use App\Core\Auth;
use App\Modules\Posgrado\Services\Abandono;
use App\Modules\Posgrado\Services\Firmas;
use App\Modules\Posgrado\Services\Formatos;
use App\Modules\Titulacion\Services\Calendario;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** Entrada «Posgrado: obtener mi grado» y el trámite del egresado (cuatro momentos). */
final class TramiteController extends PosgradoBase
{
    public function inicio(): void
    {
        $this->privado();
        if (!PosgradoAccess::veTramite()) {
            if (PosgradoAccess::tieneFirmas()) {
                redirigir('posgrado/firmas');
            }
            $this->noEncontrado();
        }
        $mis = PosgradoAccess::puedeIniciar() ? PosgradoRepository::delAlumno(Auth::id()) : [];
        $this->vista('inicio', [
            'titulo' => 'Posgrado: obtener mi grado',
            'mis' => $mis,
            'abierto' => PosgradoAccess::puedeIniciar() ? PosgradoRepository::abiertoDe(Auth::id()) : null,
            'modalidades' => Catalogo::modalidades(),
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
        if ($this->post('acepto') !== '1') {
            flash('error', 'Confirma que leíste el aviso de privacidad para comenzar.');
            redirigir('posgrado');
        }
        // Solo un trámite activo a la vez: el que hay se borra o queda en pausa, y eso se confirma antes.
        $activos = Abandono::activos(Auth::id());
        if ($activos) {
            if ($this->post('confirmo') !== '1') {
                redirigir('posgrado/iniciar/cambio');
            }
            $this->dejarActivos($activos, 'posgrado/iniciar/cambio', 'para iniciar un trámite nuevo');
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
        flash('exito', ($activos ? $this->resumenDejados() . ' ' : '') . 'Listo, tu trámite está abierto. Empieza por capturar tus datos.');
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
            // ¿Se puede borrar o solo dejar en pausa? (solo para el dueño de un trámite activo)
            'dejar' => PosgradoAccess::esDueno($t) && $t['estado'] === 'abierto' ? (Abandono::huboIntervencion($t) === [] ? 'borrar' : 'abandonar') : null,
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
            // El correo del director es obligatorio: a él le llega el enlace para firmar el voto y la solicitud.
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
        $bloqueos = Firmas::bloqueos(['datos' => $datos] + $t, $hitos);
        if ($bloqueos) {
            flash('error', 'No guardamos los cambios: ya está firmado por todos «' . implode('», «', $bloqueos) . '» y esos datos quedaron fijos. Si hay un error, pídele a Apoyo a Grado que lo anule para corregirlo.');
            redirigir($volver);
        }
        PosgradoRepository::guardarDatos((int) $t['id'], $datos);
        PosgradoRepository::evento((int) $t['id'], Auth::id(), 'captura', 'Datos del interesado guardados.');
        $regenerados = Firmas::sincronizarDatos((int) $t['id'], Auth::id());
        $t = $this->sincronizar((int) $t['id']);
        $faltan = Flujo::faltantesInteresado($t);
        $nota = $regenerados ? ' Como cambiaste datos que van en «' . implode('», «', $regenerados) . '», lo regeneramos y se pidieron de nuevo las firmas.' : '';
        if ($errores) {
            flash('error', 'Guardamos tu avance, pero revisa: ' . implode(' ', array_slice($errores, 0, 3)) . $nota);
        } elseif ($faltan) {
            flash('exito', 'Guardamos tu avance. Aún faltan: ' . implode(', ', array_slice($faltan, 0, 6)) . (count($faltan) > 6 ? '…' : '') . '.' . $nota);
        } else {
            flash('exito', ($nota !== '' ? 'Guardamos tus datos.' : 'Datos completos. Ya puedes pedir el voto aprobatorio a tu director, firmar tu formato de datos y subir tus documentos.') . $nota);
        }
        redirigir($volver);
    }

    // ---------------------------------------------------------- firmas del momento 1

    /** «Pedir el voto aprobatorio a mi director»: genera el voto una sola vez y le envía su enlace. */
    public function pedirVoto(string $id): void
    {
        $t = $this->tramite($id);
        if (!PosgradoAccess::puedeEditar($t)) {
            $this->prohibido('Edición de trámite ajeno o cerrado');
        }
        $volver = 'posgrado/tramite/' . (int) $t['id'] . '#req-voto_aprobatorio';
        [$ev] = $this->evaluar($t);
        if (isset($ev['hitos']['cotejo']) || ($ev['requisitos']['voto_aprobatorio']['estado'] ?? '') !== 'por_pedir') {
            flash('error', 'El voto aprobatorio ya se pidió o ya está firmado.');
            redirigir($volver);
        }
        if (!filter_var((string) ($t['datos']['interesado']['director_correo'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Escribe el correo de tu director en tus datos: a ese correo le llega su enlace para firmar.');
            redirigir('posgrado/tramite/' . (int) $t['id'] . '#datos');
        }
        try {
            Firmas::emitir($t, 'voto', Auth::id());
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirigir($volver);
        } catch (Throwable $e) {
            error_log('Posgrado: no se pidió el voto: ' . $e->getMessage());
            flash('error', 'No se pudo preparar el voto aprobatorio. Intenta de nuevo.');
            redirigir($volver);
        }
        $this->sincronizar((int) $t['id']);
        flash('exito', 'Listo: tu director recibió por correo su enlace para firmar el voto aprobatorio en línea.');
        redirigir($volver);
    }

    /** El egresado firma con su sesión el formato de datos del interesado. */
    public function firmarDatos(string $id): void
    {
        $t = $this->tramite($id);
        if (!PosgradoAccess::puedeEditar($t)) {
            $this->prohibido('Edición de trámite ajeno o cerrado');
        }
        $volver = 'posgrado/tramite/' . (int) $t['id'] . '#req-datos_interesado';
        [$ev] = $this->evaluar($t);
        if (($ev['requisitos']['datos_interesado']['estado'] ?? '') !== 'por_firmar' || isset($ev['hitos']['cotejo'])) {
            flash('error', $ev['faltanDatos'] ? 'Completa primero tus datos.' : 'Tu formato ya está firmado.');
            redirigir($volver);
        }
        if ($this->post('leido') !== '1') {
            flash('error', 'Marca «Revisé mi formato y mis datos son correctos» para firmarlo.');
            redirigir($volver);
        }
        try {
            $estado = Firmas::estado((int) $t['id']);
            if (!isset($estado['datos_interesado'])) {
                Firmas::emitir($t, 'datos_interesado', Auth::id());
                $estado = Firmas::estado((int) $t['id']);
            }
            $f = Flujo::primeraPendiente($estado, 'datos_interesado', 'egresado');
            if ($f === null) {
                throw new \RuntimeException('No se encontró tu formato para firmar.');
            }
            Firmas::firmar($f, ['usuario_id' => Auth::id()]);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirigir($volver);
        } catch (Throwable $e) {
            error_log('Posgrado: no se firmó el formato de datos: ' . $e->getMessage());
            flash('error', 'No se pudo firmar tu formato. Intenta de nuevo.');
            redirigir($volver);
        }
        $this->sincronizar((int) $t['id']);
        flash('exito', 'Firmaste tu formato de datos del interesado.');
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
            flash('error', 'Primero termina tus requisitos: el sínodo se propone después del cotejo de originales y de que la Coordinación firme el oficio de cotejo.');
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
        $correos = array_filter(array_column($miembros, 'correo'));
        if (count($correos) !== count(array_unique($correos))) {
            $errores[] = 'Hay un correo repetido en tu sínodo: cada integrante necesita el suyo para firmar.';
        }
        $fecha = self::linea($_POST['fecha'] ?? '', 10);
        $hora = self::linea($_POST['hora'] ?? '', 5);
        $modalidad = in_array($_POST['modalidad'] ?? '', ['presencial', 'virtual'], true) ? (string) $_POST['modalidad'] : '';
        $hoy = new \DateTimeImmutable('today');
        $minima = Calendario::agregarHabiles($hoy, Catalogo::DIAS_MINIMOS);
        $fechaAntes = (string) ($t['datos']['sinodo']['fecha'] ?? '');
        if ($fecha !== '' && !self::fechaValida($fecha)) {
            $errores[] = 'La fecha no es válida.';
            $fecha = '';
        } elseif ($fecha !== '' && $fecha !== $fechaAntes && $fecha < $minima->format('Y-m-d')) {
            $sugerida = Calendario::agregarHabiles($hoy, Catalogo::DIAS_SUGERIDOS);
            $errores[] = 'Tu examen debe quedar al menos a ' . Catalogo::DIAS_MINIMOS . ' días hábiles de hoy: elige el ' . Flujo::fechaLarga($minima->format('Y-m-d'))
                . ' o después. Te sugerimos dejar ' . Catalogo::DIAS_SUGERIDOS . ' días hábiles, es decir, del ' . Flujo::fechaLarga($sugerida->format('Y-m-d')) . ' en adelante, para que firmen con calma.';
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
        $datos['sinodo'] = ['miembros' => $miembros, 'fecha' => $fecha, 'hora' => $hora, 'modalidad' => $modalidad];
        $hitos = PosgradoRepository::hitos((int) $t['id']);
        $bloqueos = Firmas::bloqueos(['datos' => $datos] + $t, $hitos);
        if ($bloqueos) {
            flash('error', 'No guardamos los cambios: tu director y tu sínodo ya firmaron «' . implode('», «', $bloqueos) . '». Si necesitas cambiar algo, pídele a Apoyo a Grado que te devuelva la propuesta.');
            redirigir($volver);
        }
        PosgradoRepository::guardarDatos((int) $t['id'], $datos);
        PosgradoRepository::evento((int) $t['id'], Auth::id(), 'captura', 'Propuesta de sínodo y fecha guardada.');
        $nota = '';
        $enviar = $this->post('enviar') === '1';
        try {
            if ($ev['propuestaEnviada']) {
                $regenerados = Firmas::sincronizarDatos((int) $t['id'], Auth::id());
                $nota = $regenerados ? ' Como cambiaste la propuesta, se regeneró «' . implode('» y «', $regenerados) . '» y se pidieron de nuevo las firmas.' : '';
            } elseif ($enviar && !$errores && !Flujo::faltantesSinodo(['datos' => $datos] + $t)) {
                $t2 = PosgradoRepository::obtener((int) $t['id']);
                Firmas::emitir($t2, 'solicitud', Auth::id());
                Firmas::emitir($t2, 'visto_bueno', Auth::id());
                $nota = ' Enviamos la solicitud a tu director y el visto bueno a cada integrante de tu sínodo para que firmen en línea.';
            }
        } catch (Throwable $e) {
            error_log('Posgrado: no se enviaron a firma la solicitud o el visto bueno: ' . $e->getMessage());
            $errores[] = 'No se pudo enviar tu propuesta a firma; intenta de nuevo.';
        }
        $t = $this->sincronizar((int) $t['id']);
        $faltan = Flujo::faltantesSinodo($t);
        if ($errores) {
            flash('error', 'Guardamos tu avance, pero revisa: ' . implode(' ', array_slice($errores, 0, 3)) . $nota);
        } elseif ($faltan) {
            flash('exito', 'Guardamos tu avance. Aún falta: ' . implode(', ', array_slice($faltan, 0, 5)) . '.' . $nota);
        } elseif ($nota === '' && !$ev['propuestaEnviada']) {
            flash('exito', 'Propuesta completa y guardada. Cuando la revises, pulsa «Enviar a firma».');
        } else {
            flash('exito', 'Propuesta guardada.' . $nota);
        }
        redirigir($volver);
    }

    // ------------------------------------------------------------ firmantes

    /** La firma pedida en este trámite (para reenviar o corregir el correo). */
    private function firmaDe(array $t, string $firmaId): array
    {
        $f = ctype_digit($firmaId) && strlen($firmaId) < 19 ? Firmas::una((int) $firmaId) : null;
        if ($f === null || (int) $f['tramite_id'] !== (int) $t['id'] || $f['estado'] === 'cancelada') {
            $this->noEncontrado();
        }
        return $f;
    }

    /** Reenviar el enlace a un firmante (el egresado o el personal que opera): token nuevo, el anterior deja de servir. */
    public function reenviar(string $id, string $firmaId): void
    {
        $t = $this->tramite($id);
        if (!PosgradoAccess::puedeEditar($t) && !PosgradoAccess::esPersonalOperativo()) {
            $this->prohibido('Reenvío de firma en trámite ajeno');
        }
        if ($t['estado'] !== 'abierto') {
            flash('error', $t['estado'] === 'abandonado' ? 'Este trámite está en pausa: no se envían enlaces ni recordatorios hasta que se retome.' : 'El trámite ya no está abierto.');
            redirigir($this->volverA($t));
        }
        $f = $this->firmaDe($t, $firmaId);
        $volver = $this->volverA($t);
        try {
            Firmas::reenviar($f, $t, Auth::id());
            flash('exito', 'Reenviamos el enlace a ' . $f['nombre'] . '. El enlace anterior ya no funciona.');
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirigir($volver);
    }

    /** Corregir el correo de un firmante que aún no firma. */
    public function correo(string $id, string $firmaId): void
    {
        $t = $this->tramite($id);
        if (!PosgradoAccess::puedeEditar($t) && !PosgradoAccess::esPersonalOperativo()) {
            $this->prohibido('Corrección de correo en trámite ajeno');
        }
        if ($t['estado'] !== 'abierto') {
            flash('error', $t['estado'] === 'abandonado' ? 'Este trámite está en pausa: no se envían enlaces ni recordatorios hasta que se retome.' : 'El trámite ya no está abierto.');
            redirigir($this->volverA($t));
        }
        $f = $this->firmaDe($t, $firmaId);
        try {
            Firmas::corregirCorreo($f, $t, $this->post('email'), Auth::id());
            flash('exito', 'Corregimos el correo de ' . $f['nombre'] . ' y le enviamos un enlace nuevo.');
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        $this->sincronizar((int) $t['id']);
        redirigir($this->volverA($t));
    }

    private function volverA(array $t): string
    {
        $ancla = preg_replace('/[^a-z0-9_-]/', '', (string) ($_POST['volver'] ?? ''));
        return (PosgradoAccess::esDueno($t) ? 'posgrado/tramite/' : 'posgrado/gestion/') . (int) $t['id'] . ($ancla !== '' ? '#' . $ancla : '');
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
        if ($req === null || $req['tipo'] !== 'pdf') {
            $this->noEncontrado();
        }
        $volver = 'posgrado/tramite/' . (int) $t['id'] . '#req-' . $clave;
        if ($ev['hecho'][1] || isset($ev['hitos']['cotejo'])) {
            flash('error', 'Tus requisitos ya se cotejaron; ya no se pueden reemplazar.');
            redirigir($volver);
        }
        if ($ev['faltanDatos']) {
            flash('error', 'Antes de subir documentos, completa tus datos.');
            redirigir('posgrado/tramite/' . (int) $t['id'] . '#datos');
        }
        if ($req['estado'] === 'validado') {
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
        PosgradoRepository::evento((int) $t['id'], Auth::id(), 'documento', 'Documento enviado a validación: ' . $req['nombre']);
        Audit::record('posgrado::subir', 'correcto', 'Trámite ' . (int) $t['id'] . ' · ' . $clave);
        $this->sincronizar((int) $t['id']);
        flash('exito', 'Recibimos «' . $req['nombre'] . '». Apoyo a Grado lo revisará.');
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

    /** Vista previa (sin firmas) de los formatos del egresado, generada al vuelo con sus datos; no se firma ni se sube. */
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
        PosgradoRepository::evento((int) $t['id'], Auth::id(), 'formato', 'Vista previa: ' . Catalogo::formatosEgresado()[$clave], false);
        $this->enviarBytes($bytes, 'Vista_previa_' . $nombre, 'application/pdf', true);
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
        // Lo que firma la Coordinación se entrega al egresado hasta que está firmado.
        if (!PosgradoAccess::esPersonal() && (int) \App\Core\Database::valor("SELECT COUNT(*) FROM pos_firmas WHERE generado_id = ? AND rol = 'coordinacion' AND estado = 'pendiente'", [(int) $g['id']]) > 0) {
            $this->noEncontrado();
        }
        Audit::record('posgrado::generado', 'atendido', 'Generado ' . (int) $g['id']);
        $this->enviarArchivo((string) $g['ruta'], $g['clave'] . '_' . $t['folio'] . '.pdf', true);
    }
    // --------------------------------------------- dejar (borrar o pausar) y retomar

    /** Trámite del egresado con sesión; a cualquier otra persona (personal incluido) le responde 404. */
    private function propio(string $id): array
    {
        $t = ctype_digit($id) && strlen($id) < 19 ? PosgradoRepository::obtener((int) $id) : null;
        if ($t === null || !PosgradoAccess::esDueno($t)) {
            $this->noEncontrado();
        }
        return $t;
    }

    /** @var array<string,string> folio => 'borrado'|'abandonado' */
    private array $dejados = [];

    /** Deja los trámites activos tras la confirmación; si algo cambió, vuelve a la confirmación sin tocar nada. */
    private function dejarActivos(array $activos, string $volver, string $motivo): void
    {
        if ($this->post('entiendo') !== '1') {
            flash('error', 'Marca la casilla «Entiendo…» para confirmar.');
            redirigir($volver);
        }
        $esperado = is_array($_POST['plan'] ?? null) ? $_POST['plan'] : [];
        $lista = [];
        foreach ($activos as $a) {
            $a = PosgradoRepository::obtener((int) $a['id']);
            if ($a === null || ($esperado[(string) $a['id']] ?? '') !== Abandono::plan($a)['accion']) {
                flash('error', 'Algo cambió mientras confirmabas. Revisa de nuevo qué pasará con tu trámite actual.');
                redirigir($volver);
            }
            $lista[] = $a;
        }
        if (count($esperado) !== count($lista)) {
            flash('error', 'Algo cambió mientras confirmabas. Revisa de nuevo qué pasará con tu trámite actual.');
            redirigir($volver);
        }
        foreach ($lista as $a) {
            try {
                $this->dejados[(string) $a['folio']] = Abandono::dejar($a, Auth::id(), $motivo);
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
                redirigir($volver);
            }
        }
    }

    private function resumenDejados(): string
    {
        $partes = [];
        foreach ($this->dejados as $folio => $que) {
            $partes[] = 'Tu trámite ' . $folio . ($que === 'borrado' ? ' se borró.' : ' quedó en «Abandonadas».');
        }
        return implode(' ', $partes);
    }

    /** @return array<int,array{t:array,plan:array,nombre:string}> */
    private function planesActivos(): array
    {
        $r = [];
        foreach (Abandono::activos(Auth::id()) as $a) {
            $a = PosgradoRepository::obtener((int) $a['id']);
            if ($a !== null) {
                $r[] = ['t' => $a, 'plan' => Abandono::plan($a), 'nombre' => Abandono::nombreModalidad($a)];
            }
        }
        return $r;
    }

    public function dejarForm(string $id): void
    {
        $this->privado();
        $t = $this->propio($id);
        if (($imp = Abandono::impedimento($t)) !== null) {
            flash('error', $imp);
            redirigir('posgrado/tramite/' . (int) $t['id']);
        }
        $plan = Abandono::plan($t);
        $this->vista('dejar', [
            'titulo' => $plan['accion'] === 'borrar' ? 'Borrar mi trámite' : 'Dejar mi trámite en pausa',
            't' => $t, 'plan' => $plan, 'nombre' => Abandono::nombreModalidad($t),
        ]);
    }

    public function dejar(string $id): void
    {
        $t = $this->propio($id);
        $volver = 'posgrado/tramite/' . (int) $t['id'] . '/dejar';
        if (($imp = Abandono::impedimento($t)) !== null) {
            flash('error', $imp);
            redirigir('posgrado/tramite/' . (int) $t['id']);
        }
        if ($this->post('entiendo') !== '1') {
            flash('error', 'Marca la casilla «Entiendo…» para confirmar.');
            redirigir($volver);
        }
        if ($this->post('accion') !== Abandono::plan($t)['accion']) {
            flash('error', 'Algo cambió mientras confirmabas. Revisa de nuevo qué pasará con tu trámite.');
            redirigir($volver);
        }
        try {
            $r = Abandono::dejar($t, Auth::id());
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirigir($volver);
        }
        flash('exito', $r === 'borrado'
            ? 'Borramos tu trámite ' . $t['folio'] . '. Puedes comenzar uno nuevo cuando quieras.'
            : 'Tu trámite ' . $t['folio'] . ' quedó en pausa. Lo encuentras en «Abandonadas» y puedes retomarlo cuando quieras.');
        redirigir('posgrado');
    }

    /** Confirmación de «un trámite activo a la vez» al comenzar uno nuevo. */
    public function cambio(): void
    {
        $this->privado();
        if (!PosgradoAccess::puedeIniciar()) {
            $this->noEncontrado();
        }
        $actuales = $this->planesActivos();
        if (!$actuales) {
            redirigir('posgrado#comenzar');
        }
        $this->vista('cambio', [
            'titulo' => 'Comenzar otro trámite',
            'modo' => 'iniciar', 'destinoNombre' => 'un trámite nuevo',
            'accion' => 'posgrado/iniciar', 'volver' => 'posgrado/tramite/' . (int) $actuales[0]['t']['id'],
            'actuales' => $actuales, 'ocultos' => ['acepto' => '1'],
        ]);
    }

    public function retomarForm(string $id): void
    {
        $this->privado();
        $t = $this->propio($id);
        if ($t['estado'] !== 'abandonado') {
            redirigir('posgrado/tramite/' . (int) $t['id']);
        }
        $this->vista('cambio', [
            'titulo' => 'Retomar mi trámite',
            'modo' => 'retomar', 'destinoNombre' => 'tu trámite ' . $t['folio'] . ' (' . Abandono::nombreModalidad($t) . ')',
            'accion' => 'posgrado/tramite/' . (int) $t['id'] . '/retomar', 'volver' => 'posgrado',
            'actuales' => $this->planesActivos(), 'ocultos' => [],
        ]);
    }

    public function retomar(string $id): void
    {
        $t = $this->propio($id);
        if ($t['estado'] !== 'abandonado') {
            flash('error', 'Este trámite no está en pausa.');
            redirigir('posgrado/tramite/' . (int) $t['id']);
        }
        $volver = 'posgrado/tramite/' . (int) $t['id'] . '/retomar';
        $activos = Abandono::activos(Auth::id());
        if ($activos) {
            if ($this->post('confirmo') !== '1') {
                redirigir($volver);
            }
            $this->dejarActivos($activos, $volver, 'para retomar ' . $t['folio']);
        }
        try {
            Abandono::retomar($t, Auth::id());
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirigir('posgrado');
        }
        flash('exito', ($activos ? $this->resumenDejados() . ' ' : '') . 'Retomaste tu trámite justo donde se quedó.');
        redirigir('posgrado/tramite/' . (int) $t['id']);
    }
}
