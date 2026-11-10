<?php
declare(strict_types=1);

namespace App\Modules\Ingesta;

use App\Core\Acceso;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DocumentArchive;

/**
 * Ingesta documental (docs/INGESTA_DOCUMENTAL.md): PDFs sueltos, carpetas de PDFs y enlaces a archivos de Teams/SharePoint.
 * Toda lectura pasa por alcance(): el acceso se resuelve en SQL con la sesión, igual para la pantalla y para el asistente.
 */
final class Ingesta
{
    public const ALCANCES = [
        'propio' => 'Solo yo',
        'departamento' => 'Mi dependencia',
        'personal' => 'Personal de la Facultad (sin alumnos)',
        'comunidad' => 'Toda la comunidad, incluidos alumnos',
    ];
    public const ROLES_INGESTA = ['admin', 'titular', 'operativo', 'coordinador'];
    public const MAX_BYTES = 20 * 1048576;
    private const MAX_TEXTO = 200000;
    /** Únicos destinos admitidos para un enlace: Teams, SharePoint y OneDrive de Microsoft 365. */
    private const HOSTS = '/(^|\.)sharepoint\.com$|^teams\.microsoft\.com$|(^|\.)office\.com$|^1drv\.ms$|^onedrive\.live\.com$/i';

    public static function lista(): bool
    {
        try {
            Database::valor('SELECT 1 FROM ing_documentos LIMIT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function puedeIngresar(): bool
    {
        return Acceso::puede('ingesta.subir', self::ROLES_INGESTA, null, null, 'usar', false);
    }

    /** Dependencias de la persona con sesión (la principal y las de sus roles adicionales). @return int[] */
    public static function dependencias(): array
    {
        $u = Auth::usuario() ?? [];
        $ids = [(int) ($u['departamento_id'] ?? 0)];
        foreach ($u['roles_extra'] ?? [] as $extra) {
            $ids[] = (int) ($extra['departamento_id'] ?? 0);
        }
        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Predicado de acceso y sus parámetros para una tabla con alcance, departamento_id, creado_por (y carpeta_id si $carpeta).
     * Quien ingresó siempre ve lo suyo; lo «propio» de otra persona no lo ve nadie más, tampoco la administración.
     * Una concesión por persona («ingesta.carpeta» sobre esa carpeta, o sobre toda la función) también da lectura.
     */
    public static function alcance(string $alias, string $columnaCarpeta = 'carpeta_id'): array
    {
        $uid = Auth::id();
        $sql = ["$alias.creado_por = ?", "$alias.alcance = 'comunidad'"];
        $par = [$uid];
        if (Auth::tieneRol('admin', 'titular', 'operativo', 'coordinador', 'profesor')) {
            $sql[] = "$alias.alcance = 'personal'";
        }
        if (Auth::tieneRol('admin')) {
            $sql[] = "$alias.alcance = 'departamento'";
        } elseif ($deptos = self::dependencias()) {
            $sql[] = "($alias.alcance = 'departamento' AND $alias.departamento_id IN (" . implode(',', array_fill(0, count($deptos), '?')) . '))';
            $par = [...$par, ...$deptos];
        }
        $sql[] = "($alias.alcance <> 'propio' AND EXISTS (SELECT 1 FROM acceso_concesiones ac WHERE ac.usuario_id = ? AND ac.funcion = 'ingesta.carpeta' AND ac.permiso = 'usar'
                   AND (ac.recurso_id IS NULL OR ac.recurso_id = $alias.$columnaCarpeta) AND ac.revocado_en IS NULL AND ac.vigente_desde <= CURDATE() AND ac.vigente_hasta >= CURDATE()))";
        $par[] = $uid;
        return ['(' . implode(' OR ', $sql) . ')', $par];
    }

    // ------------------------------------------------------------------ consulta

    /** Documentos visibles para la sesión que coinciden con $q (o los más recientes si $q está vacío). */
    public static function buscar(string $q, ?int $carpetaId = null, int $limite = 30, int $desde = 0): array
    {
        [$donde, $par] = self::alcance('d');
        $q = trim(mb_substr($q, 0, 160));
        $orden = 'd.creado_en DESC, d.id DESC';
        $columnas = 'd.id, d.carpeta_id, d.origen, d.titulo, d.tipo_documento, d.autor, d.fecha_documento, d.palabras_clave, d.resumen, d.ruta_relativa, d.enlace,
                     d.nombre_original, d.bytes, d.paginas, d.alcance, d.creado_por, d.creado_en, c.nombre AS carpeta, u.nombre AS ingresado_por, dp.nombre AS departamento';
        if ($carpetaId !== null) {
            $donde .= ' AND d.carpeta_id = ?';
            $par[] = $carpetaId;
            $orden = 'd.ruta_relativa, d.titulo';
        }
        if ($q !== '') {
            $palabras = array_slice(array_filter(preg_split('/[^\p{L}\p{N}]+/u', $q) ?: [], static fn(string $p): bool => mb_strlen($p) >= 3), 0, 8);
            $cond = [];
            if ($palabras) {
                // FULLTEXT en modo booleano con prefijo; LIKE cubre términos cortos, folios y lo que el índice aún no tenga.
                $cond[] = 'MATCH (d.titulo, d.tipo_documento, d.autor, d.palabras_clave, d.resumen, d.nombre_original, d.ruta_relativa, d.texto) AGAINST (? IN BOOLEAN MODE)';
                $par[] = implode(' ', array_map(static fn(string $p): string => $p . '*', $palabras));
            }
            $cond[] = '(d.titulo LIKE ? OR d.palabras_clave LIKE ? OR d.nombre_original LIKE ? OR d.ruta_relativa LIKE ? OR c.nombre LIKE ?)';
            $como = '%' . addcslashes($q, '%_\\') . '%';
            $par = [...$par, $como, $como, $como, $como, $como];
            $donde .= ' AND (' . implode(' OR ', $cond) . ')';
        }
        return Database::todas(
            "SELECT $columnas FROM ing_documentos d LEFT JOIN ing_carpetas c ON c.id = d.carpeta_id
               JOIN usuarios u ON u.id = d.creado_por LEFT JOIN departamentos dp ON dp.id = d.departamento_id
              WHERE $donde ORDER BY $orden LIMIT " . max(1, min(200, $limite)) . ' OFFSET ' . max(0, $desde),
            $par
        );
    }

    public static function documento(int $id): ?array
    {
        [$donde, $par] = self::alcance('d');
        return Database::una(
            "SELECT d.*, c.nombre AS carpeta, u.nombre AS ingresado_por, dp.nombre AS departamento FROM ing_documentos d
               LEFT JOIN ing_carpetas c ON c.id = d.carpeta_id JOIN usuarios u ON u.id = d.creado_por LEFT JOIN departamentos dp ON dp.id = d.departamento_id
              WHERE d.id = ? AND $donde",
            [$id, ...$par]
        );
    }

    public static function carpetas(): array
    {
        [$donde, $par] = self::alcance('c', 'id');
        return Database::todas(
            "SELECT c.*, u.nombre AS ingresado_por, dp.nombre AS departamento, (SELECT COUNT(*) FROM ing_documentos d WHERE d.carpeta_id = c.id) AS documentos
               FROM ing_carpetas c JOIN usuarios u ON u.id = c.creado_por LEFT JOIN departamentos dp ON dp.id = c.departamento_id
              WHERE $donde ORDER BY c.actualizado_en DESC LIMIT 200",
            $par
        );
    }

    public static function carpeta(int $id): ?array
    {
        [$donde, $par] = self::alcance('c', 'id');
        return Database::una(
            "SELECT c.*, u.nombre AS ingresado_por, dp.nombre AS departamento FROM ing_carpetas c JOIN usuarios u ON u.id = c.creado_por
               LEFT JOIN departamentos dp ON dp.id = c.departamento_id WHERE c.id = ? AND $donde",
            [$id, ...$par]
        );
    }

    /** Solo quien ingresó (o la administración, salvo lo «propio» ajeno) puede editar o eliminar. */
    public static function esDueno(array $fila): bool
    {
        return (int) $fila['creado_por'] === Auth::id() || (Auth::tieneRol('admin') && $fila['alcance'] !== 'propio');
    }

    // ------------------------------------------------------------------ ingesta

    /** Alcance y dependencia validados para lo que se va a ingresar. */
    public static function destino(string $alcance, int $departamentoId): array
    {
        if (!isset(self::ALCANCES[$alcance])) {
            throw new \InvalidArgumentException('Elige quién puede consultar los documentos.');
        }
        $deptos = self::dependencias();
        if ($alcance === 'departamento') {
            if (!Auth::tieneRol('admin') && !in_array($departamentoId, $deptos, true)) {
                $departamentoId = $deptos[0] ?? 0;
            }
            if ($departamentoId <= 0 || !Database::valor('SELECT 1 FROM departamentos WHERE id = ?', [$departamentoId])) {
                throw new \InvalidArgumentException('Elige la dependencia a la que pertenecen los documentos.');
            }
        } else {
            $departamentoId = $deptos[0] ?? 0;
        }
        return [$alcance, $departamentoId ?: null];
    }

    public static function crearCarpeta(string $nombre, string $descripcion, string $origen, ?string $enlace, string $alcance, ?int $departamentoId): int
    {
        $nombre = trim(mb_substr($nombre, 0, 200));
        if ($nombre === '') {
            throw new \InvalidArgumentException('Escribe el nombre de la carpeta.');
        }
        $ahora = date('Y-m-d H:i:s');
        Database::ejecutar(
            'INSERT INTO ing_carpetas (nombre, descripcion, origen, enlace, alcance, departamento_id, creado_por, creado_en, actualizado_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$nombre, mb_substr(trim($descripcion), 0, 4000), $origen === 'teams' ? 'teams' : 'pdf', $enlace, $alcance, $departamentoId, Auth::id(), $ahora, $ahora]
        );
        return (int) Database::valor('SELECT LAST_INSERT_ID()');
    }

    /**
     * Guarda un PDF subido (ya validado por is_uploaded_file en el controlador) y lo indiza.
     * @param array $archivo  ['tmp' => ruta temporal, 'nombre' => nombre original]
     * @param array $meta     titulo, tipo_documento, autor, fecha_documento, palabras_clave, resumen, ruta_relativa (opcionales)
     * @return array{id:int,duplicado:bool}
     */
    public static function ingresarPdf(array $archivo, array $meta, ?array $carpeta, string $alcance, ?int $departamentoId): array
    {
        $tmp = (string) $archivo['tmp'];
        $bytes = (int) @filesize($tmp);
        if ($bytes < 100 || $bytes > self::MAX_BYTES) {
            throw new \InvalidArgumentException('El PDF debe pesar menos de ' . (int) (self::MAX_BYTES / 1048576) . ' MB.');
        }
        $nombre = mb_substr(basename(str_replace('\\', '/', (string) $archivo['nombre'])), 0, 250);
        $cabecera = (string) file_get_contents($tmp, false, null, 0, 1024);
        if ((new \finfo(FILEINFO_MIME_TYPE))->file($tmp) !== 'application/pdf' || !str_contains($cabecera, '%PDF-') || strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) !== 'pdf') {
            throw new \InvalidArgumentException('Solo se admiten archivos PDF reales: ' . $nombre);
        }
        $sha = hash_file('sha256', $tmp);
        $carpetaId = $carpeta ? (int) $carpeta['id'] : null;
        // El mismo archivo no se repite dentro de la misma carpeta (ni entre los documentos particulares de la persona).
        $previo = $carpetaId
            ? Database::valor('SELECT id FROM ing_documentos WHERE sha256 = ? AND carpeta_id = ?', [$sha, $carpetaId])
            : Database::valor('SELECT id FROM ing_documentos WHERE sha256 = ? AND carpeta_id IS NULL AND creado_por = ?', [$sha, Auth::id()]);
        if ($previo) {
            return ['id' => (int) $previo, 'duplicado' => true];
        }
        $pdf = Pdf::leer($tmp);
        $clave = bin2hex(random_bytes(16)) . '.bin';
        $destino = DocumentArchive::path($clave);
        if (!move_uploaded_file($tmp, $destino)) {
            throw new \RuntimeException('No se pudo guardar el archivo en el almacenamiento privado.');
        }
        @chmod($destino, 0600);
        try {
            $titulo = trim((string) ($meta['titulo'] ?? '')) ?: ($pdf['titulo'] ?: self::tituloDeNombre($nombre));
            $ahora = date('Y-m-d H:i:s');
            Database::ejecutar(
                'INSERT INTO ing_documentos (carpeta_id, origen, titulo, tipo_documento, autor, fecha_documento, palabras_clave, resumen, ruta_relativa, clave, nombre_original, bytes, paginas, sha256, texto, alcance, departamento_id, creado_por, creado_en, actualizado_en)
                 VALUES (?, \'pdf\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$carpetaId, mb_substr($titulo, 0, 250), mb_substr(trim((string) ($meta['tipo_documento'] ?? '')), 0, 100), mb_substr(trim((string) ($meta['autor'] ?? '')) ?: $pdf['autor'], 0, 200),
                    self::fecha((string) ($meta['fecha_documento'] ?? '')) ?? $pdf['fecha'], mb_substr(trim((string) ($meta['palabras_clave'] ?? '')), 0, 2000), mb_substr(trim((string) ($meta['resumen'] ?? '')), 0, 4000),
                    self::rutaRelativa((string) ($meta['ruta_relativa'] ?? '')), $clave, $nombre, $bytes, $pdf['paginas'], $sha, mb_substr($pdf['texto'], 0, self::MAX_TEXTO) ?: null,
                    $alcance, $departamentoId, Auth::id(), $ahora, $ahora]
            );
        } catch (\Throwable $e) {
            @unlink($destino);
            throw $e;
        }
        $id = (int) Database::valor('SELECT LAST_INSERT_ID()');
        if ($carpetaId) {
            Database::ejecutar('UPDATE ing_carpetas SET actualizado_en = ? WHERE id = ?', [date('Y-m-d H:i:s'), $carpetaId]);
        }
        return ['id' => $id, 'duplicado' => false];
    }

    /**
     * Registra enlaces de Teams/SharePoint. Cada línea: «URL» o «Título | URL».
     * @return array{nuevos:int,rechazados:string[]}
     */
    public static function ingresarEnlaces(string $lineas, ?array $carpeta, string $alcance, ?int $departamentoId, array $meta = []): array
    {
        $nuevos = 0;
        $rechazados = [];
        $carpetaId = $carpeta ? (int) $carpeta['id'] : null;
        foreach (array_slice(preg_split('/\R/u', $lineas) ?: [], 0, 300) as $linea) {
            $linea = trim($linea);
            if ($linea === '') {
                continue;
            }
            $titulo = '';
            if (preg_match('/^(.*?)\s*\|\s*(https:\/\/\S+)$/u', $linea, $m)) {
                [$titulo, $linea] = [trim($m[1]), $m[2]];
            }
            $url = self::enlaceValido($linea);
            if ($url === null) {
                $rechazados[] = mb_substr($linea, 0, 80);
                continue;
            }
            $sha = hash('sha256', $url);
            $previo = $carpetaId
                ? Database::valor('SELECT id FROM ing_documentos WHERE sha256 = ? AND carpeta_id = ?', [$sha, $carpetaId])
                : Database::valor('SELECT id FROM ing_documentos WHERE sha256 = ? AND carpeta_id IS NULL AND creado_por = ?', [$sha, Auth::id()]);
            if ($previo) {
                continue;
            }
            $nombre = self::nombreDeEnlace($url);
            $ahora = date('Y-m-d H:i:s');
            Database::ejecutar(
                'INSERT INTO ing_documentos (carpeta_id, origen, titulo, tipo_documento, autor, fecha_documento, palabras_clave, resumen, ruta_relativa, enlace, nombre_original, sha256, alcance, departamento_id, creado_por, creado_en, actualizado_en)
                 VALUES (?, \'teams\', ?, ?, ?, ?, ?, ?, \'\', ?, ?, ?, ?, ?, ?, ?, ?)',
                [$carpetaId, mb_substr($titulo !== '' ? $titulo : self::tituloDeNombre($nombre), 0, 250), mb_substr(trim((string) ($meta['tipo_documento'] ?? '')), 0, 100), mb_substr(trim((string) ($meta['autor'] ?? '')), 0, 200),
                    self::fecha((string) ($meta['fecha_documento'] ?? '')), mb_substr(trim((string) ($meta['palabras_clave'] ?? '')), 0, 2000), mb_substr(trim((string) ($meta['resumen'] ?? '')), 0, 4000),
                    $url, mb_substr($nombre, 0, 250), $sha, $alcance, $departamentoId, Auth::id(), $ahora, $ahora]
            );
            $nuevos++;
        }
        if ($carpetaId && $nuevos) {
            Database::ejecutar('UPDATE ing_carpetas SET actualizado_en = ? WHERE id = ?', [date('Y-m-d H:i:s'), $carpetaId]);
        }
        return ['nuevos' => $nuevos, 'rechazados' => $rechazados];
    }

    /** Devuelve la URL normalizada si es https y apunta a Teams, SharePoint u OneDrive; null en cualquier otro caso. */
    public static function enlaceValido(string $url): ?string
    {
        $url = trim($url);
        if (strlen($url) > 1000 || !preg_match('#^https://#i', $url) || preg_match('/[\s<>"\'\x00-\x1f]/', $url)) {
            return null;
        }
        $p = parse_url($url);
        $host = strtolower((string) ($p['host'] ?? ''));
        if ($host === '' || isset($p['user']) || isset($p['pass']) || (isset($p['port']) && (int) $p['port'] !== 443) || !preg_match(self::HOSTS, $host)) {
            return null;
        }
        return $url;
    }

    private static function nombreDeEnlace(string $url): string
    {
        $p = parse_url($url);
        parse_str((string) ($p['query'] ?? ''), $q);
        foreach (['file', 'id', 'sourcedoc'] as $k) {
            if (!empty($q[$k]) && is_string($q[$k]) && str_contains($q[$k], '.')) {
                return basename(str_replace('\\', '/', $q[$k]));
            }
        }
        $ultimo = basename(rawurldecode((string) ($p['path'] ?? '')));
        return $ultimo !== '' && !preg_match('/^(AllItems|Forms|_layouts|guestaccess)\b/i', $ultimo) ? $ultimo : 'Archivo de Teams';
    }

    private static function tituloDeNombre(string $nombre): string
    {
        $t = preg_replace('/\.[A-Za-z0-9]{2,5}$/', '', $nombre) ?? $nombre;
        return trim(preg_replace('/[_\-.]+/', ' ', $t) ?? $t) ?: 'Documento';
    }

    private static function rutaRelativa(string $ruta): string
    {
        $ruta = trim(str_replace('\\', '/', $ruta), '/');
        $partes = array_filter(explode('/', $ruta), static fn(string $p): bool => $p !== '' && $p !== '.' && $p !== '..');
        return mb_substr(preg_replace('/[\x00-\x1f]/', '', implode('/', $partes)) ?? '', 0, 500);
    }

    public static function fecha(string $f): ?string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($f));
        return $d && $d->format('Y-m-d') === trim($f) ? trim($f) : null;
    }

    public static function eliminarDocumento(array $d): void
    {
        Database::ejecutar('DELETE FROM ing_documentos WHERE id = ?', [(int) $d['id']]);
        self::borrarArchivo((string) ($d['clave'] ?? ''));
    }

    public static function eliminarCarpeta(array $c): void
    {
        $claves = array_column(Database::todas('SELECT clave FROM ing_documentos WHERE carpeta_id = ? AND clave IS NOT NULL', [(int) $c['id']]), 'clave');
        Database::ejecutar('DELETE FROM ing_carpetas WHERE id = ?', [(int) $c['id']]);   // los documentos se van en cascada
        array_map([self::class, 'borrarArchivo'], $claves);
    }

    private static function borrarArchivo(string $clave): void
    {
        try {
            if ($clave !== '' && is_file($ruta = DocumentArchive::path($clave))) {
                @unlink($ruta);
            }
        } catch (\Throwable) {
        }
    }

    // ------------------------------------------------------------------ proveedores para otros componentes

    /** Proveedor del grafo de persona (manifiesto): lo que la persona ha ingresado. */
    public static function grafo(int $usuarioId, array $correos, ?int $profesorId): array
    {
        $grupos = [];
        $ver = $usuarioId === Auth::id() ? '' : " AND alcance <> 'propio'";      // lo privado de alguien no se asoma en su grafo
        $carpetas = Database::todas('SELECT c.id, c.nombre, c.origen, (SELECT COUNT(*) FROM ing_documentos d WHERE d.carpeta_id = c.id) n FROM ing_carpetas c WHERE c.creado_por = ?' . $ver . ' ORDER BY c.actualizado_en DESC LIMIT 25', [$usuarioId]);
        if ($carpetas) {
            $grupos[] = ['titulo' => 'Carpetas de documentos ingresadas', 'total' => (int) Database::valor('SELECT COUNT(*) FROM ing_carpetas WHERE creado_por = ?' . $ver, [$usuarioId]),
                'items' => array_map(static fn(array $c): array => ['etiqueta' => $c['nombre'], 'detalle' => $c['n'] . ' documento(s) · ' . ($c['origen'] === 'teams' ? 'Teams' : 'PDF'), 'enlace' => 'ingesta/carpeta/' . $c['id']], $carpetas)];
        }
        $sueltos = Database::todas('SELECT id, titulo, origen, creado_en FROM ing_documentos WHERE creado_por = ? AND carpeta_id IS NULL' . $ver . ' ORDER BY creado_en DESC LIMIT 25', [$usuarioId]);
        if ($sueltos) {
            $grupos[] = ['titulo' => 'Documentos ingresados', 'total' => (int) Database::valor('SELECT COUNT(*) FROM ing_documentos WHERE creado_por = ? AND carpeta_id IS NULL' . $ver, [$usuarioId]),
                'items' => array_map(static fn(array $d): array => ['etiqueta' => $d['titulo'], 'detalle' => ($d['origen'] === 'teams' ? 'Teams' : 'PDF') . ' · ' . substr((string) $d['creado_en'], 0, 10), 'enlace' => 'ingesta/documento/' . $d['id']], $sueltos)];
        }
        return $grupos;
    }
}
