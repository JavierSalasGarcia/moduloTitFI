<?php
declare(strict_types=1);

namespace App\Modules\Consejos\Services;

use App\Core\Database;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/**
 * Oficios de dictamen de los HH. Consejos: propone destinatario, cuerpo y acuerdo a partir del asunto (reglas de
 * `consejos/dictamenes.py`), asigna el número de oficio de forma atómica y arma el .docx sobre la hoja membretada
 * `plantillas/plantilla_dictamen.docx`. Todo lo que el sistema no pudo deducir con seguridad queda marcado «revisar»
 * y sale resaltado en amarillo para que la persona lo confirme antes de emitir.
 */
final class Dictamenes
{
    private const CONSEJOS = 'los H.H. Consejos Académico y de Gobierno';
    private const NEGATIVOS = ['no se aprueba', 'no procede', 'improcedente', 'se rechaza', 'no se autoriza', 'no se avala', 'rechaza', 'no avala'];
    private const CIUDAD = 'Ciudad Universitaria, Toluca, Méx.';
    private const CARGO_M = 'PROFESOR DE LA FACULTAD DE INGENIERÍA UAEM';
    private const CARGO_F = 'PROFESORA DE LA FACULTAD DE INGENIERÍA UAEM';
    private const VERBOS = '(solicita|informa|somete|presenta|propone|notifica|remite|dictamina|pide)';
    private const RE_CARGO = '/\b(coordinador|coordinadora|profesor|profesora|director|directora|subdirector|subdirectora|alumno|alumna|estudiante|jefe|jefa|secretario|secretaria|presidente|presidenta|responsable|encargado|encargada|titular|investigador|investigadora)\b/iu';

    // ------------------------------------------------------------------ generación

    /**
     * Crea los oficios que faltan de la reunión (uno por asunto, o uno por alumno si el asunto trae una tabla de alumnos).
     * @return array{creados:int,primero:?string,ultimo:?string}
     */
    public static function generar(int $reunionId, string $fechaOficio, ?int $inicio): array
    {
        $r = Database::una('SELECT * FROM consejos_reuniones WHERE id = ?', [$reunionId]);
        if (!$r) {
            throw new RuntimeException('La reunión no existe.');
        }
        $asuntos = Database::todas('SELECT a.* FROM consejos_asuntos a WHERE a.reunion_id = ? AND NOT EXISTS (SELECT 1 FROM consejos_oficios o WHERE o.asunto_id = a.id) ORDER BY FIELD(a.grupo, \'profesor\', \'alumno\', \'general\'), a.numero', [$reunionId]);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // Contador único de la Subdirección Académica (el mismo de Titulación y de los oficios de la Subdirección); la reserva
            // lleva candado, así que dos personas generando a la vez no repiten números.
            $anio = (int) date('Y', strtotime($fechaOficio) ?: time());
            if ($inicio !== null && $inicio > 0) {
                \App\Modules\Titulacion\Services\NumeroOficio::fijarUltimo('subdireccion', $anio, $inicio - 1);
            }
            $creados = 0;
            $primero = $ultimoNum = null;
            foreach ($asuntos as $a) {
                foreach (self::proponer($a, $r) as $o) {
                    do {   // salta números ya usados (p. ej. capturados a mano)
                        [, $numero] = \App\Modules\Titulacion\Services\NumeroOficio::reservar('subdireccion', $anio);
                        $ocupado = (bool) Database::valor('SELECT 1 FROM consejos_oficios WHERE numero = ?', [$numero]);
                    } while ($ocupado);
                    Database::ejecutar(
                        'INSERT INTO consejos_oficios (asunto_id, destinatario, cargo, numero, fecha, cuerpo, acuerdo, version_acuerdo, revisar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [(int) $a['id'], $o['destinatario'], $o['cargo'], $numero, $fechaOficio, $o['cuerpo'], $o['acuerdo'], (int) $a['version_acuerdo'], $o['revisar'] ? 1 : 0]
                    );
                    $creados++;
                    $primero ??= $numero;
                    $ultimoNum = $numero;
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return ['creados' => $creados, 'primero' => $primero, 'ultimo' => $ultimoNum];
    }

    /** Vuelve a redactar un oficio desde el asunto (después de cambiar un acuerdo). Conserva el número. */
    public static function regenerar(int $oficioId): bool
    {
        $o = Database::una('SELECT * FROM consejos_oficios WHERE id = ?', [$oficioId]);
        $a = $o ? Database::una('SELECT * FROM consejos_asuntos WHERE id = ?', [(int) $o['asunto_id']]) : null;
        $r = $a ? Database::una('SELECT * FROM consejos_reuniones WHERE id = ?', [(int) $a['reunion_id']]) : null;
        if (!$o || !$a || !$r || $o['estado'] === 'emitido') {
            return false;
        }
        $props = self::proponer($a, $r);
        // En asuntos de varios alumnos se conserva el destinatario del oficio.
        $mismo = null;
        foreach ($props as $p) {
            if ($p['destinatario'] === $o['destinatario']) {
                $mismo = $p;
            }
        }
        $p = $mismo ?? $props[0] ?? null;
        if (!$p) {
            return false;
        }
        Database::ejecutar('UPDATE consejos_oficios SET cuerpo = ?, acuerdo = ?, revisar = ?, version_acuerdo = ?, estado = \'borrador\' WHERE id = ?',
            [$p['cuerpo'], $p['acuerdo'], $p['revisar'] ? 1 : 0, (int) $a['version_acuerdo'], $oficioId]);
        return true;
    }

    // ------------------------------------------------------------------ propuesta de redacción

    /** @return array<int,array{destinatario:string,cargo:string,cuerpo:string,acuerdo:string,revisar:bool}> */
    public static function proponer(array $a, array $r): array
    {
        $tratado = self::fraseTratado($r['academico_fecha'] ?? null, $r['gobierno_fecha'] ?? null, (string) $r['tipo_sesion']);
        $solicitud = Html::texto((string) $a['solicitud_html']);
        $primera = Texto::limpio(strtok($solicitud, "\n") ?: '');
        [$acuerdo, $revAcu] = self::acuerdoFinal($a);
        $alumnos = self::alumnosDeTablas((string) $a['solicitud_html'] . (string) $a['antecedentes_html']);
        $salida = [];
        if ($alumnos) {
            foreach ($alumnos as $al) {
                [$acAl, $revAl] = self::acuerdoAlumno($a, $al, count($alumnos), $acuerdo);
                $salida[] = [
                    'destinatario' => 'C. ' . Texto::mayus($al['nombre']), 'cargo' => '',
                    'cuerpo' => self::cuerpoAlumno($primera, $al, $tratado), 'acuerdo' => $acAl, 'revisar' => $revAl || $revAcu,
                ];
            }
            return $salida;
        }
        $sol = self::analizar($primera);
        $dest = [];
        $cargo = '';
        $revDest = $sol['revisar'];
        $secretario = Texto::norm((string) $r['secretario']);
        if ($sol['tipo'] === 'comision') {
            $dest = ['INTEGRANTES DE LA ' . rtrim(Texto::mayus($sol['sujeto']), ' ,.')];
            $revDest = true;
        } elseif ($sol['nombre'] === '' || ($secretario !== '' && str_contains($secretario, Texto::norm($sol['nombre'])))) {
            $dest = ['[NOMBRE DEL DESTINATARIO]'];
            $cargo = '[CARGO]';
            $revDest = true;
        } else {
            $dest = [Texto::mayus(trim($sol['titulo'] . ' ' . $sol['nombre']))];
            $cargo = self::cargoLinea($sol);
        }
        // Si no se pudo deducir y el asunto menciona a un solo profesor con cuenta, es el destinatario probable.
        if ($revDest) {
            $profes = Database::todas('SELECT u.nombre FROM consejos_asunto_profesores p JOIN usuarios u ON u.id = p.usuario_id WHERE p.asunto_id = ?', [(int) $a['id']]);
            if (count($profes) === 1) {
                $dest = [Texto::mayus((string) $profes[0]['nombre'])];
                $cargo = self::CARGO_M;
                $revDest = true;   // se propone, pero la persona debe confirmarlo (género y título)
            }
        }
        $cuerpo = self::cuerpo($sol, $tratado);
        if (str_starts_with($dest[0], 'INTEGRANTES')) {
            $cuerpo = str_replace('se le informa', 'se les informa', $cuerpo);
        }
        return [[
            'destinatario' => implode("\n", $dest), 'cargo' => $cargo, 'cuerpo' => $cuerpo, 'acuerdo' => $acuerdo,
            'revisar' => $revDest || $sol['revisar'] || $revAcu,
        ]];
    }

    private static function fraseTratado(?string $fa, ?string $fg, string $sesion): string
    {
        $ta = $fa ? strtotime($fa) : false;
        $tg = $fg ? strtotime($fg) : false;
        if ($ta && $tg && date('Y-m-d', $ta) !== date('Y-m-d', $tg)) {
            $dias = date('n', $ta) === date('n', $tg)
                ? 'los días ' . date('d', $ta) . ' y ' . date('d', $tg) . ' de ' . Texto::MESES[(int) date('n', $tg) - 1]
                : 'los días ' . date('d', $ta) . ' de ' . Texto::MESES[(int) date('n', $ta) - 1] . ' y ' . date('d', $tg) . ' de ' . Texto::MESES[(int) date('n', $tg) - 1];
            return "en las sesiones {$sesion}s de {$dias} del año en curso";
        }
        $t = $tg ?: $ta;
        return $t ? "en su sesión {$sesion} del día " . date('d', $t) . ' de ' . Texto::MESES[(int) date('n', $t) - 1] . ' del año en curso' : "en su sesión {$sesion}";
    }

    /** @return array{sujeto:string,titulo:string,nombre:string,genero:string,cargo:string,tipo:string,verbo:string,resto:string,revisar:bool} */
    private static function analizar(string $primero): array
    {
        $o = ['sujeto' => '', 'titulo' => '', 'nombre' => '', 'genero' => 'm', 'cargo' => '', 'tipo' => 'otro', 'verbo' => '', 'resto' => $primero, 'revisar' => false];
        if (!preg_match('/^\s*(El|La|Los|Las)\s+(.+?)\s*,?\s+' . self::VERBOS . '\b\s*(.*)$/isu', $primero, $m)) {
            $o['revisar'] = true;
            return $o;
        }
        [, $art, $pre, $verbo, $resto] = $m;
        $o['genero'] = in_array(mb_strtolower($art), ['la', 'las'], true) ? 'f' : 'm';
        $o['verbo'] = mb_strtolower($verbo);
        $o['resto'] = $resto;
        $pre = trim($pre, ' ,');
        if (str_starts_with(Texto::norm($pre), 'comision')) {
            $o['tipo'] = 'comision';
            $o['sujeto'] = Texto::limpio($pre);
            return $o;
        }
        if (str_contains($pre, ',')) {
            [$suj, $cargo] = explode(',', $pre, 2);
        } elseif (preg_match(self::RE_CARGO, $pre, $mc, PREG_OFFSET_CAPTURE)) {
            $suj = substr($pre, 0, $mc[0][1]);
            $cargo = substr($pre, $mc[0][1]);
        } else {
            [$suj, $cargo] = [$pre, ''];
        }
        $suj = trim($suj, ' ,');
        $cargo = trim($cargo, ' ,');
        $conCuenta = false;
        if (preg_match('/\s+con\s+(n[uú]mero|matr[ií]cula|no\.)/iu', $suj, $mc, PREG_OFFSET_CAPTURE)) {
            $suj = substr($suj, 0, $mc[0][1]);
            $conCuenta = true;
        }
        $toks = preg_split('/\s+/u', $suj) ?: [];
        $tit = [];
        while ($toks && count($toks) > 2 && (str_ends_with($toks[0], '.') || $toks[0] === 'en')) {
            $tit[] = array_shift($toks);
        }
        $o['sujeto'] = $suj;
        $o['titulo'] = implode(' ', $tit);
        $o['nombre'] = implode(' ', $toks);
        $o['cargo'] = $cargo;
        if ($cargo === '' || count($toks) < 2) {
            $o['revisar'] = true;
        }
        $nc = Texto::norm($cargo);
        if ($conCuenta || preg_match('/^(alumn|estudiante|pasante|egresad)/', $nc) || mb_strtoupper($o['titulo']) === 'C.') {
            $o['tipo'] = 'alumno';
        } elseif (preg_match('/^(profesor|asesor|coasesor|investigador)/', $nc)) {
            $o['tipo'] = 'profesor';
        } else {
            $o['tipo'] = 'funcionario';
        }
        return $o;
    }

    private static function cargoLinea(array $sol): string
    {
        if ($sol['tipo'] === 'profesor') {
            return $sol['genero'] === 'f' ? self::CARGO_F : self::CARGO_M;
        }
        if ($sol['tipo'] === 'alumno') {
            return '';
        }
        $c = preg_replace('/\b(de\s+)?(esta|la)\s+(Facultad|facultad|instituci[oó]n)(\s+de\s+Ingenier[ií]a)?\b/u', 'de la Facultad de Ingeniería', $sol['cargo']) ?? $sol['cargo'];
        return Texto::mayus($c);
    }

    private static function quitarConsejos(string $s): string
    {
        $s = preg_replace('/\s*,?\s*(a|de|ante|para)\s+(los\s+)?(H\.?\s*H\.?|HH\.?)\s*Consejos?\s+Acad[eé]mico\s+y\s+de\s+Gobierno(\s+de\s+(la|esta)\s+Facultad(\s+de\s+Ingenier[ií]a)?)?\s*,?/iu', ' ', $s) ?? $s;
        return Texto::limpio($s);
    }

    private static function contraer(string $prefijo, string $resto): string
    {
        $resto = trim($resto);
        if (str_ends_with($prefijo, ' a') && stripos($resto, 'el ') === 0) {
            return substr($prefijo, 0, -2) . ' al ' . substr($resto, 3);
        }
        if (str_ends_with($prefijo, ' de') && stripos($resto, 'el ') === 0) {
            return substr($prefijo, 0, -3) . ' del ' . substr($resto, 3);
        }
        return $prefijo . ' ' . $resto;
    }

    private static function cuerpo(array $sol, string $tratado): string
    {
        $resto = rtrim(self::quitarConsejos($sol['resto']), ' .');
        $cierre = ', se le informa que el asunto fue tratado por ' . self::CONSEJOS . " {$tratado}, llegando al siguiente:";
        if ($sol['tipo'] === 'comision') {
            return rtrim('En relación con el dictamen de la ' . Texto::limpio($sol['sujeto']), ' ,.') . $cierre;
        }
        $v = $sol['verbo'];
        if ($v === 'solicita') {
            if (preg_match('/^(se|que)\b/iu', $resto)) {
                return 'En respuesta a su solicitud para que ' . preg_replace('/^que\s+/iu', '', $resto) . $cierre;
            }
            return self::contraer('En respuesta a su solicitud referente a', $resto) . $cierre;
        }
        if (in_array($v, ['somete', 'presenta', 'propone', 'remite'], true)) {
            return "En atención a su oficio en el que {$v} a consideración de " . self::CONSEJOS . " {$resto}" . $cierre;
        }
        if (in_array($v, ['informa', 'notifica'], true)) {
            return "En atención a su oficio en el que {$v} a " . self::CONSEJOS . " {$resto}" . $cierre;
        }
        return "En respuesta a su oficio relativo a {$resto}" . $cierre;
    }

    // ------------------------------------------------------------------ acuerdos

    private static function vozConjunta(string $t): string
    {
        $t = Texto::limpio($t);
        if ($t === '') {
            return $t;
        }
        $t = preg_replace('/^Se avala(n?) por el H\.\s*Consejo Acad[eé]mico/u', 'Se aprueba$1', $t) ?? $t;
        $t = preg_replace('/^Se avalan\b/u', 'Se aprueban', $t) ?? $t;
        $t = preg_replace('/^Se avala\b/u', 'Se aprueba', $t) ?? $t;
        if (preg_match('/^El H\.\s*Consejo Acad[eé]mico\s+(\w+)\s+(.*)$/us', $t, $m)) {
            $verbo = mb_strtolower($m[1]);
            $resto = $m[2];
            $mapa = ['avala' => 'aprueba', 'aprueba' => 'aprueba', 'autoriza' => 'autoriza', 'otorga' => 'otorga', 'acepta' => 'acepta', 'ratifica' => 'ratifica'];
            if (isset($mapa[$verbo])) {
                $plural = preg_match('/^(los|las)\b/iu', $resto) === 1;
                return 'Se ' . $mapa[$verbo] . ($plural && str_ends_with($mapa[$verbo], 'a') ? 'n' : '') . ' ' . $resto;
            }
            if ($verbo === 'resuelve') {
                return 'Los H.H. Consejos Académico y de Gobierno resuelven ' . $resto;
            }
            if ($verbo === 'solicita') {
                return 'Los H.H. Consejos Académico y de Gobierno solicitan ' . $resto;
            }
            if ($verbo === 'no' || $verbo === 'se') {
                return 'Los H.H. Consejos Académico y de Gobierno ' . $verbo . ' ' . $resto;
            }
            return 'Los H.H. Consejos Académico y de Gobierno ' . $verbo . ' ' . $resto;
        }
        return $t;
    }

    private static function unir(string $base, string $extra): string
    {
        $base = rtrim(Texto::limpio($base), ' .');
        $extra = ltrim(Texto::limpio($extra), ' ,');
        if ($extra === '' || $extra === '.') {
            return $base . '.';
        }
        if ($extra[0] === '.') {
            return $base . '. ' . ltrim($extra, '. ');
        }
        if (preg_match('/^(y|e)\s/iu', $extra)) {
            return rtrim($base . ' ' . $extra, ' .') . '.';
        }
        return rtrim($base . ', ' . $extra, ' .') . '.';
    }

    /** Acuerdo que se comunica: el final del Consejo de Gobierno. @return array{0:string,1:bool} [texto, revisar] */
    public static function acuerdoFinal(array $a): array
    {
        $acadLineas = Texto::lineas($a['acuerdo_academico'] ?? '');
        $acad = implode(' ', array_map(static fn($l) => rtrim(self::vozConjunta($l), ' .') . '.', $acadLineas));
        $gob = Texto::limpio((string) ($a['acuerdo_gobierno'] ?? ''));
        $nota = Texto::limpio((string) ($a['nota_gobierno'] ?? ''));
        if ($gob === '') {
            return [$acad, true];
        }
        if (preg_match('/^(Se\s+aprueba|El\s+H\.\s*Consejo\s+de\s+Gobierno\s+aprueba)\s+el\s+acuerdo\s+del\s+H\.\s*Consejo\s+Acad[eé]mico\s*(.*)$/isu', $gob, $m)) {
            $texto = self::unir($acad, trim($m[2]));
            if ($nota !== '') {
                $texto .= ' ' . $nota . (str_ends_with($nota, '.') ? '' : '.');
            }
            return [$texto, $acad === ''];
        }
        $g = preg_replace_callback('/^Se avala(n?)\b/u', static fn($x) => 'Se aprueba' . $x[1], $gob) ?? $gob;
        if ($nota !== '') {
            $g = rtrim($g, ' ') . ' ' . $nota;
        }
        return [str_ends_with($g, '.') ? $g : $g . '.', false];
    }

    // ------------------------------------------------------------------ alumnos (tablas con Nombre y Cuenta)

    /** @return array<int,array{nombre:string,cuenta:string,ua:string}> */
    private static function alumnosDeTablas(string $html): array
    {
        if (stripos($html, '<table') === false) {
            return [];
        }
        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $res = [];
        foreach ($doc->getElementsByTagName('table') as $tabla) {
            $filas = [];
            foreach ($tabla->getElementsByTagName('tr') as $tr) {
                $f = [];
                foreach ($tr->childNodes as $c) {
                    if ($c instanceof DOMElement && in_array(strtolower($c->tagName), ['td', 'th'], true)) {
                        $f[] = Texto::limpio(Html::texto($c->ownerDocument->saveHTML($c) ?: ''));
                    }
                }
                $filas[] = $f;
            }
            if (!$filas) {
                continue;
            }
            $enc = array_map([Texto::class, 'norm'], $filas[0]);
            $iNom = $iCta = $iUa = null;
            foreach ($enc as $i => $c) {
                if ($iNom === null && str_contains($c, 'nombre')) {
                    $iNom = $i;
                }
                if ($iCta === null && (str_contains($c, 'cuenta') || str_contains($c, 'matricula'))) {
                    $iCta = $i;
                }
                if ($iUa === null && ($c === 'ua' || str_contains($c, 'unidad de aprendizaje'))) {
                    $iUa = $i;
                }
            }
            if ($iNom === null || $iCta === null) {
                continue;
            }
            foreach (array_slice($filas, 1) as $f) {
                if (count($f) <= max($iNom, $iCta) || count(array_unique($f)) === 1) {
                    continue;
                }
                if ($f[$iNom] === '' || !preg_match('/\d/', $f[$iCta])) {
                    continue;
                }
                $res[] = ['nombre' => $f[$iNom], 'cuenta' => $f[$iCta], 'ua' => $iUa !== null ? ($f[$iUa] ?? '') : ''];
            }
        }
        return $res;
    }

    /** @return array{0:string,1:bool} */
    private static function acuerdoAlumno(array $a, array $al, int $total, string $final): array
    {
        $nom = Texto::norm($al['nombre']);
        $propias = [];
        foreach (Texto::lineas(($a['acuerdo_academico'] ?? '') . "\n" . ($a['acuerdo_gobierno'] ?? '')) as $l) {
            foreach (preg_split('/(?<=\.)\s+/u', Texto::limpio($l)) ?: [] as $s) {
                if ($nom !== '' && str_contains(Texto::norm($s), $nom)) {
                    $propias[] = $s;
                }
            }
        }
        if ($propias) {
            return [implode(' ', array_map(static fn($p) => rtrim($p, ' .') . '.', $propias)), true];
        }
        $neg = false;
        foreach (self::NEGATIVOS as $n) {
            if (str_contains(Texto::norm($final), $n)) {
                $neg = true;
            }
        }
        return [$neg ? 'No se aprueba su solicitud.' : 'Se aprueba su solicitud.', $total > 1];
    }

    private static function cuerpoAlumno(string $primera, array $al, string $tratado): string
    {
        $cierre = ', se le informa que el asunto fue tratado por ' . self::CONSEJOS . " {$tratado}, llegando al siguiente:";
        $s = Texto::norm($primera);
        if (str_contains($s, 'examen') && str_contains($s, 'especial') && $al['ua'] !== '') {
            return 'En respuesta a su solicitud para que el Consejo le autorice presentar examen especial de la Unidad de Aprendizaje ' . $al['ua'] . ' de esta Facultad' . $cierre;
        }
        $sol = self::analizar($primera);
        $resto = rtrim(self::quitarConsejos($sol['resto']), ' .');
        if ($al['ua'] !== '') {
            $resto .= ' (Unidad de Aprendizaje ' . $al['ua'] . ')';
        }
        return self::contraer('En respuesta a su solicitud referente a', $resto) . $cierre;
    }

    // ------------------------------------------------------------------ Word sobre la plantilla

    public static function plantilla(): string
    {
        return __DIR__ . '/../plantillas/plantilla_dictamen.docx';
    }

    /** .docx (cadena binaria) con un oficio por página, sobre la hoja membretada. @param array[] $oficios filas de consejos_oficios */
    public static function docx(array $oficios): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException('Este servidor no puede crear archivos de Word (falta la extensión ZipArchive de PHP).');
        }
        $zip = new \ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), 'dic');
        if ($tmp === false || !copy(self::plantilla(), $tmp) || $zip->open($tmp) !== true) {
            throw new RuntimeException('No fue posible abrir la plantilla del dictamen.');
        }
        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            throw new RuntimeException('La plantilla del dictamen no es válida.');
        }
        $zip->addFromString('word/document.xml', self::armar($xml, $oficios));
        $zip->close();
        $bin = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $bin;
    }

    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private static function armar(string $xml, array $oficios): string
    {
        $doc = new DOMDocument();
        $doc->loadXML($xml, LIBXML_NONET);
        $body = $doc->getElementsByTagNameNS(self::W, 'body')->item(0);
        $sect = null;
        $modelo = [];
        foreach (iterator_to_array($body->childNodes) as $n) {
            if ($n instanceof DOMElement && $n->localName === 'sectPr') {
                $sect = $n;
            } else {
                $modelo[] = $n;
            }
        }
        foreach ($modelo as $n) {
            $body->removeChild($n);
        }
        foreach ($oficios as $k => $o) {
            if ($k > 0) {
                $br = $doc->createElementNS(self::W, 'w:p');
                $run = $doc->createElementNS(self::W, 'w:r');
                $b = $doc->createElementNS(self::W, 'w:br');
                $b->setAttributeNS(self::W, 'w:type', 'page');
                $run->appendChild($b);
                $br->appendChild($run);
                $body->insertBefore($br, $sect);
            }
            $fechaTxt = self::CIUDAD . ', ' . Texto::fechaLarga($o['fecha'] ?? null);
            $r = (bool) $o['revisar'];
            foreach ($modelo as $m) {
                $c = $m->cloneNode(true);
                $body->insertBefore($c, $sect);
                if (!$c instanceof DOMElement || $c->localName !== 'p') {
                    continue;
                }
                $t = $c->textContent;
                if (str_contains($t, '{{NUMERO}}')) {
                    self::poner($doc, $c, (string) $o['numero'], false);
                } elseif (str_contains($t, '{{FECHA}}')) {
                    self::poner($doc, $c, $fechaTxt, false);
                } elseif (str_contains($t, '{{DESTINATARIO}}')) {
                    self::ponerLineas($doc, $body, $c, Texto::lineas((string) $o['destinatario']) ?: ['[NOMBRE DEL DESTINATARIO]'], $r);
                } elseif (str_contains($t, '{{CARGO}}')) {
                    $l = Texto::lineas((string) $o['cargo']);
                    if ($l) {
                        self::ponerLineas($doc, $body, $c, $l, $r);
                    } else {
                        $body->removeChild($c);
                    }
                } elseif (str_contains($t, '{{CUERPO}}')) {
                    self::poner($doc, $c, (string) $o['cuerpo'], $r);
                } elseif (str_contains($t, '{{ACUERDO}}')) {
                    self::poner($doc, $c, (string) $o['acuerdo'], $r);
                }
            }
        }
        return (string) $doc->saveXML();
    }

    private static function poner(DOMDocument $doc, DOMElement $p, string $texto, bool $resaltar): void
    {
        $rpr = null;
        foreach ($p->getElementsByTagNameNS(self::W, 'r') as $run) {
            if ($run->getElementsByTagNameNS(self::W, 't')->length) {
                $x = $run->getElementsByTagNameNS(self::W, 'rPr')->item(0);
                $rpr = $x ? $x->cloneNode(true) : null;
                break;
            }
        }
        foreach (iterator_to_array($p->childNodes) as $h) {
            if (!($h instanceof DOMElement && $h->localName === 'pPr')) {
                $p->removeChild($h);
            }
        }
        $run = $doc->createElementNS(self::W, 'w:r');
        $rpr ??= $doc->createElementNS(self::W, 'w:rPr');
        if ($resaltar) {
            $h = $doc->createElementNS(self::W, 'w:highlight');
            $h->setAttributeNS(self::W, 'w:val', 'yellow');
            // El resaltado va antes de subrayado, sombreado, vertAlign, rtl, cs, em y lang (orden del esquema).
            $antes = null;
            foreach ($rpr->childNodes as $hijo) {
                if ($hijo instanceof DOMElement && in_array($hijo->localName, ['u', 'effect', 'bdr', 'shd', 'fitText', 'vertAlign', 'rtl', 'cs', 'em', 'lang', 'eastAsianLayout', 'specVanish', 'oMath'], true)) {
                    $antes = $hijo;
                    break;
                }
            }
            $antes ? $rpr->insertBefore($h, $antes) : $rpr->appendChild($h);
        }
        $run->appendChild($rpr);
        $t = $doc->createElementNS(self::W, 'w:t');
        $t->appendChild($doc->createTextNode($texto));
        $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $run->appendChild($t);
        $p->appendChild($run);
    }

    private static function ponerLineas(DOMDocument $doc, DOMElement $body, DOMElement $p, array $lineas, bool $resaltar): void
    {
        $molde = $p->cloneNode(true);
        self::poner($doc, $p, $lineas[0], $resaltar);
        $ant = $p;
        foreach (array_slice($lineas, 1) as $l) {
            $nuevo = $molde->cloneNode(true);
            self::poner($doc, $nuevo, $l, $resaltar);
            $body->insertBefore($nuevo, $ant->nextSibling);
            $ant = $nuevo;
        }
    }
}
