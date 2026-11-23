<?php
declare(strict_types=1);
namespace App\Core;

/** Presentation labels and navigation; permissions still come from module manifests. */
final class InterfaceUi
{
    public const NAMES = [''=>'Inicio','portal'=>'Inicio','consulta'=>'Consulta','tickets'=>'Pedir apoyo a un área',
        'solicitudes'=>'Gestión entre áreas','firmas'=>'Firmas de documentos','reuniones'=>'Reuniones',
        'calendario'=>'Calendario','eventos'=>'Avisos y eventos','comunicados'=>'Avisos oficiales','fila'=>'Fila',
        'foro'=>'Foro','comisiones'=>'Comisiones de trabajo','encuestas'=>'Encuestas','grupos-destinatarios'=>'Grupos de destinatarios',
        'busqueda'=>'Buscar en Traza FI','directorio'=>'Directorio','funciones'=>'Mis funciones',
        'teams'=>'Carpetas de Teams','recursos'=>'Recursos','ajustes'=>'Ajustes','seguridad'=>'Acceso seguro',
        'alumnos'=>'Alumnos','usuarios'=>'Usuarios y coordinadores','accesos'=>'Cuentas y acceso','auditoria'=>'Registro de acciones','cuenta'=>'Mi cuenta','verificar'=>'Verificar correo','plan'=>'Plan de Desarrollo FI','posgrado'=>'Obtención de grado','titulacion'=>'Titulación','constancias'=>'Constancias firmadas','consejos'=>'Consejos'];

    /** Un enlace por grupo en el menú lateral; las páginas de entrada viven en `seccion/<slug>`. */
    public const GRUPOS = [
        'Inicio' => ['slug' => 'inicio', 'icono' => 'panel', 'frase' => 'Tu portada y los avisos que requieren tu atención.'],
        'Trámites y seguimiento' => ['etiqueta' => 'Trámites', 'slug' => 'tramites', 'icono' => 'flujo', 'frase' => 'Solicitudes, firmas y gestiones entre áreas: lo que envías y lo que te toca atender.'],
        'Titulación' => ['slug' => 'titulacion', 'icono' => 'academia', 'frase' => 'Tu trámite de titulación y las gestiones que te corresponden, ordenadas por tarea.'],
        'Constancias' => ['slug' => 'constancias', 'icono' => 'ok', 'frase' => 'Solicita una constancia con firma en línea y consulta las constancias ya firmadas, ordenadas por fecha o departamento.'],
        'Consejos' => ['slug' => 'consejos', 'icono' => 'portafolio', 'frase' => 'Solicitudes a los HH. Consejos, comisiones, reuniones, actas y dictámenes.'],
        'Agenda' => ['slug' => 'agenda', 'icono' => 'reloj', 'frase' => 'Avisos, eventos, calendario y reuniones de la Facultad.'],
        'Fila' => ['slug' => 'fila', 'icono' => 'lista', 'frase' => 'Turnos y plan de desarrollo de la Facultad.'],
        'Comunidad' => ['slug' => 'comunidad', 'icono' => 'usuarios', 'frase' => 'Foro, encuestas y comisiones para participar con la comunidad.'],
        'Concursos' => ['slug' => 'concursos', 'icono' => 'premio', 'frase' => 'Convocatorias vigentes, resultados anteriores y su gestión.'],
        'Recursos' => ['slug' => 'recursos', 'icono' => 'portafolio', 'frase' => 'Documentos, directorios, carpetas y tu cuenta.'],
        'Administración' => ['slug' => 'administracion', 'icono' => 'engrane', 'frase' => 'Ajustes, cuentas, permisos y registros de la plataforma.'],
    ];

    /** Icono de entradas que se pintan sueltas (grupo «Inicio»). */
    private const ICONOS_ENTRADA = ['notificaciones' => 'alerta', 'eventos' => 'reloj', 'perfil' => 'usuario'];

    private static ?array $navegacion = null;

    public static function menu(array $sections): array
    {
        $groups = array_fill_keys(['Inicio','Trámites y seguimiento','Titulación','Constancias','Consejos','Agenda','Fila','Comunidad','Concursos','Recursos','Administración'], ['items'=>[]]);
        foreach ($sections as $section) foreach ($section['items'] as $item) {
            $root = explode('/', $item['ruta'])[0];
            $group = match ($root) {
                '', 'notificaciones' => 'Inicio', 'seguridad', 'perfil' => 'Recursos', 'tickets','solicitudes','firmas','funciones','grupos-destinatarios' => 'Trámites y seguimiento',
                // «Avisos y eventos» va suelto en el menú principal, con su globito de no leídos.
                'calendario','reuniones','eventos' => str_contains($item['ruta'],'biblioteca') ? 'Recursos' : ($item['ruta'] === 'eventos' ? 'Inicio' : 'Agenda'),
                'fila' => 'Fila', 'titulacion' => 'Titulación',
                'posgrado' => 'Titulación', 'constancias' => 'Constancias', 'consejos' => 'Consejos',
                'foro','encuestas','comisiones','concursos' => 'Comunidad', 'ajustes','formularios' => 'Administración',
                'recursos' => empty($item['dinamico']) ? 'Administración' : 'Recursos', default => 'Recursos'
            };
            // «Mi perfil» va suelto en el menú principal.
            if ($item['ruta'] === 'perfil') $group = 'Inicio';
            if (in_array($root, ['seguridad', 'perfil'], true) && empty($item['grupo'])) $item['grupo'] = 'Mi cuenta';
            if ($root === 'concursos' && empty($item['grupo'])) $item['grupo'] = 'Concursos';
            if (!str_contains($item['ruta'], '/') && empty($item['dinamico'])) $item['titulo'] = self::NAMES[$root] ?? $item['titulo'];
            $groups[$group]['items'][] = $item;
        }
        // Agenda: primero Calendario, luego Reuniones y después lo demás (el orden relativo del resto no cambia).
        if (!empty($groups['Agenda']['items'])) {
            $peso = static fn(array $i): int => str_starts_with((string) $i['ruta'], 'calendario') ? 0 : (str_starts_with((string) $i['ruta'], 'reuniones') ? 1 : 2);
            usort($groups['Agenda']['items'], static fn(array $a, array $b): int => $peso($a) <=> $peso($b));
        }
        return array_filter($groups, fn($group)=>count($group['items'])>0);
    }

    /**
     * Navegación del menú lateral y de las páginas de entrada, calculada una sola vez por petición:
     * grupos con sus entradas (ya filtradas por roles, concesiones y condiciones), contadores y enlaces del menú.
     * @return array{grupos:array<string,array>,enlaces:array<int,array>}
     */
    public static function navegacion(): array
    {
        if (self::$navegacion !== null) return self::$navegacion;
        $usuario = Auth::usuario();
        if (!$usuario) return self::$navegacion = ['grupos' => [], 'enlaces' => []];
        $roles = array_merge([$usuario['rol']], array_column($usuario['roles_extra'] ?? [], 'rol'));
        $egresado = !empty($usuario['egresado_externo']);
        $conteo = [];
        $grupos = [];
        $enlaces = [];
        foreach (self::menu(ModuleManager::menu($roles)) as $nombre => $grupo) {
            $meta = self::GRUPOS[$nombre];
            $total = 0;
            foreach ($grupo['items'] as &$item) {
                $item['contador'] = 0;
                if ($item['ruta'] === $item['clave']) {
                    $conteo[$item['clave']] ??= self::contador((string) $item['clave'], $usuario);
                    $item['contador'] = $conteo[$item['clave']];
                }
                $total += $item['contador'];
            }
            unset($item);
            // «Inicio» y las cuentas de egresado (cuyo alcance es solo Titulación, sin página de entrada) se pintan entrada por entrada.
            $suelto = $nombre === 'Inicio' || $egresado;
            $grupos[$nombre] = $grupo + $meta + ['nombre' => $nombre, 'contador' => $total, 'pagina' => !$suelto && count($grupo['items']) > 1];
            if ($suelto) {
                foreach ($grupo['items'] as $item) {
                    $enlaces[] = ['etiqueta' => $item['titulo'], 'ruta' => $item['ruta'], 'icono' => self::ICONOS_ENTRADA[$item['ruta']] ?? $meta['icono'],
                        'contador' => $item['contador'], 'grupo' => $nombre, 'slug' => $meta['slug'], 'items' => [$item], 'pagina' => false];
                }
            } else {
                $unico = count($grupo['items']) === 1;
                $enlaces[] = ['etiqueta' => $unico ? $grupo['items'][0]['titulo'] : ($meta['etiqueta'] ?? $nombre), 'ruta' => $unico ? $grupo['items'][0]['ruta'] : 'seccion/' . $meta['slug'],
                    'icono' => $meta['icono'], 'contador' => $total, 'grupo' => $nombre, 'slug' => $meta['slug'], 'items' => $grupo['items'], 'pagina' => !$unico];
            }
        }
        return self::$navegacion = ['grupos' => $grupos, 'enlaces' => $enlaces];
    }

    /** Pendientes que se muestran como contador (reuniones por responder, notificaciones, solicitudes abiertas). */
    private static function contador(string $clave, array $usuario): int
    {
        if ($clave === 'reuniones') {
            return (int) Database::valor(
                'SELECT COUNT(*) FROM reuniones r
                   JOIN reuniones_participantes rp ON rp.reunion_id = r.id AND rp.usuario_id = ?
                  WHERE rp.estado_invitacion <> \'revocada\'
                    AND (rp.respondio_en IS NULL OR r.actualizado_en > rp.respondio_en)
                    AND r.estado IN (\'convocada\', \'en_respuesta\', \'fecha_confirmada\')
                    AND (CASE WHEN r.fecha_confirmada IS NOT NULL THEN r.fecha_confirmada >= CURDATE()
                              ELSE r.rango_fin IS NULL OR r.rango_fin >= CURDATE() END)',
                [$usuario['id']]
            );
        }
        if ($clave === 'notificaciones') {
            return \App\Core\Pendientes::total() + \App\Modules\Notificaciones\Notificaciones::noLeidas(array_merge(Identidad::correosDeUsuario((int) $usuario['id']), [(string) $usuario['email']]));
        }
        if ($clave === 'eventos') {
            return \App\Modules\Eventos\Lecturas::noLeidos();
        }
        if ($clave === 'tickets') {
            return in_array($usuario['rol'], ['alumno', 'profesor'], true)
                ? (int) Database::valor("SELECT COUNT(*) FROM tickets WHERE usuario_id = ? AND estado IN ('abierto','en_atencion')", [$usuario['id']])
                : (int) Database::valor("SELECT COUNT(*) FROM tickets WHERE depto_destino_id = ? AND estado IN ('abierto','en_atencion')", [Auth::departamentoId()]);
        }
        return 0;
    }

    public static function status(string $value): string
    {
        return ['abierto'=>'Recibida','en_atencion'=>'En atención','resuelto'=>'Resuelta','cerrado'=>'Finalizada',
            'rechazado'=>'Requiere revisión','fecha_confirmada'=>'Fecha confirmada','en_respuesta'=>'Esperando disponibilidad',
            'convocada'=>'Convocada','borrador'=>'Borrador','finalizada'=>'Finalizada','formacion'=>'En formación',
            'en_seguimiento'=>'En seguimiento','concluida'=>'Concluida'][$value] ?? ucfirst(str_replace('_',' ',$value));
    }
}
