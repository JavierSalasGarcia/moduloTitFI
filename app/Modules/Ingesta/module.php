<?php

use App\Modules\Ingesta\IngestaController;

$todos = ['admin', 'titular', 'operativo', 'coordinador', 'profesor', 'alumno'];

return [
    'clave' => 'ingesta',
    'nombre' => 'Documentos indizados',
    'version' => '1.0.0',
    'descripcion' => 'Ingesta de PDFs, carpetas de PDFs y enlaces de Teams; consulta de sus metadatos y grafo de relaciones de la persona.',
    'roles' => $todos,
    // Catálogo de consulta del asistente (docs/ASISTENTE_JEV_GEMINI.md).
    'consulta' => [
        ['ruta' => 'ingesta', 'descripcion' => 'Busca documentos y carpetas indizados (PDF y Teams) y consulta sus metadatos y su enlace, dentro de tu acceso.', 'palabras' => 'documento pdf carpeta teams archivo buscar metadatos enlace acta reglamento manual'],
        ['ruta' => 'ingesta/grafo', 'descripcion' => 'Tu grafo de relaciones: programas, órganos académicos, estudiantes, protocolos, constancias y documentos ligados a tu cuenta.', 'palabras' => 'grafo relaciones perfil programas estudiantes constancias protocolos'],
        ['ruta' => 'ingesta/nueva', 'descripcion' => 'Ingresa un PDF, una carpeta de PDFs o enlaces de Teams para indizarlos.', 'palabras' => 'subir ingresar indizar pdf carpeta teams enlace'],
    ],
    // Aporta al grafo de persona lo que cada quien ha ingresado (docs/GRAFO_DE_PERSONA.md).
    'grafo' => 'App\Modules\Ingesta\Ingesta::grafo',
    'menu' => [
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Documentos indizados', 'ruta' => 'ingesta', 'roles' => $todos, 'orden' => 36],
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Mi grafo de relaciones', 'ruta' => 'ingesta/grafo', 'roles' => $todos, 'orden' => 37],
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Ingresar documentos', 'ruta' => 'ingesta/nueva', 'roles' => $todos, 'orden' => 38,
         'condicion' => 'App\Modules\Ingesta\Ingesta::puedeIngresar'],
    ],
    'rutas' => [
        'GET /ingesta' => [IngestaController::class, 'index'],
        'GET /ingesta/nueva' => [IngestaController::class, 'nueva'],
        'POST /ingesta/documento' => [IngestaController::class, 'crearDocumento'],
        'POST /ingesta/carpeta' => [IngestaController::class, 'crearCarpeta'],
        'GET /ingesta/carpeta/{id}' => [IngestaController::class, 'carpeta'],
        'POST /ingesta/carpeta/{id}/archivo' => [IngestaController::class, 'agregarArchivo'],
        'POST /ingesta/carpeta/{id}/enlaces' => [IngestaController::class, 'agregarEnlaces'],
        'POST /ingesta/carpeta/{id}/eliminar' => [IngestaController::class, 'eliminarCarpeta'],
        'GET /ingesta/documento/{id}' => [IngestaController::class, 'documento'],
        'POST /ingesta/documento/{id}' => [IngestaController::class, 'guardarDocumento'],
        'POST /ingesta/documento/{id}/eliminar' => [IngestaController::class, 'eliminarDocumento'],
        'GET /ingesta/documento/{id}/archivo' => [IngestaController::class, 'archivo', 'token'],
        'GET /ingesta/grafo' => [IngestaController::class, 'grafo'],
        'GET /ingesta/grafo/{id}' => [IngestaController::class, 'grafo'],
    ],
];
