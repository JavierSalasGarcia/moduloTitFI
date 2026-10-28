<?php
declare(strict_types=1);
namespace App\Core;

/** Presentation labels and navigation; permissions still come from module manifests. */
final class InterfaceUi
{
    public const NAMES = [''=>'Inicio','portal'=>'Inicio','consulta'=>'Consulta','tickets'=>'Mis solicitudes',
        'solicitudes'=>'Gestión entre áreas','firmas'=>'Firmas de documentos','reuniones'=>'Reuniones',
        'calendario'=>'Calendario','eventos'=>'Avisos y eventos','comunicados'=>'Avisos oficiales','fila'=>'Fila',
        'foro'=>'Foro','comisiones'=>'Comisiones','encuestas'=>'Encuestas','grupos-destinatarios'=>'Grupos de destinatarios',
        'busqueda'=>'Encontrar un trámite','directorio'=>'Directorio','funciones'=>'Mis funciones',
        'teams'=>'Carpetas de Teams','recursos'=>'Recursos','ajustes'=>'Ajustes','seguridad'=>'Acceso seguro',
        'alumnos'=>'Alumnos','usuarios'=>'Usuarios y coordinadores','accesos'=>'Cuentas y acceso','auditoria'=>'Registro de acciones','cuenta'=>'Mi cuenta','verificar'=>'Verificar correo','plan'=>'Plan de Desarrollo FI'];

    public static function menu(array $sections): array
    {
        $groups = array_fill_keys(['Inicio','Trámites y seguimiento','Titulación','Agenda','Fila','Comunidad','Concursos','Recursos','Administración'], ['items'=>[]]);
        foreach ($sections as $section) foreach ($section['items'] as $item) {
            $root = explode('/', $item['ruta'])[0];
            $group = match ($root) {
                '', 'seguridad', 'notificaciones' => 'Inicio', 'tickets','solicitudes','firmas','funciones','grupos-destinatarios' => 'Trámites y seguimiento',
                'calendario','reuniones','eventos' => str_contains($item['ruta'],'biblioteca') ? 'Recursos' : 'Agenda',
                'fila' => 'Fila', 'titulacion' => 'Titulación',
                'foro','encuestas','comisiones' => 'Comunidad', 'concursos' => 'Concursos', 'ajustes','formularios' => 'Administración',
                'recursos' => empty($item['dinamico']) ? 'Administración' : 'Recursos', default => 'Recursos'
            };
            if (!str_contains($item['ruta'], '/') && empty($item['dinamico'])) $item['titulo'] = self::NAMES[$root] ?? $item['titulo'];
            $groups[$group]['items'][] = $item;
        }
        return array_filter($groups, fn($group)=>count($group['items'])>0);
    }

    public static function status(string $value): string
    {
        return ['abierto'=>'Recibida','en_atencion'=>'En atención','resuelto'=>'Resuelta','cerrado'=>'Finalizada',
            'rechazado'=>'Requiere revisión','fecha_confirmada'=>'Fecha confirmada','en_respuesta'=>'Esperando disponibilidad',
            'convocada'=>'Convocada','borrador'=>'Borrador','finalizada'=>'Finalizada','formacion'=>'En formación',
            'en_seguimiento'=>'En seguimiento','concluida'=>'Concluida'][$value] ?? ucfirst(str_replace('_',' ',$value));
    }
}
