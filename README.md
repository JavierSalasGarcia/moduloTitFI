# Plataforma FI · Facultad de Ingeniería UAEMéx

Plataforma web modular (PHP 8 + MySQL) para la gestión de la Facultad de Ingeniería:
funciones del Manual de Organización, solicitudes interdepartamentales, **tickets
canalizados por departamento, comunicados granulares para alumnos, encuestas anónimas
verificables, foro con peticiones y comisiones de seguimiento auditables**.

Identidad visual conforme al **Manual de Identidad Institucional UAEMéx 2021-2025**
(verde `#2C5234`, oro `#9C8412`, diseño plano) con la estructura y navegación de la
plantilla de seguridad ElectroKumo: barra superior de 48 px, menú lateral blanco con
secciones colapsables filtradas por rol, y `storage/` fuera del webroot.

## Estructura

```
plataformaFI/
├── public/                  ← ÚNICA carpeta expuesta (document root)
│   ├── index.php            ← front controller
│   ├── .htaccess            ← reescritura de URLs (Apache)
│   └── assets/              ← css / js
├── app/
│   ├── config.php           ← configuración base sin secretos
│   ├── config.local.php     ← secretos/overrides del entorno (no versionar)
│   ├── bootstrap.php        ← autoloader PSR-4 + sesión
│   ├── Core/                ← Router, Database (PDO), Auth, View, ModuleManager, helpers
│   ├── Views/               ← layouts (app, auth) y errores (403/404)
│   └── Modules/             ← ★ un módulo = una carpeta autocontenida
│       ├── Auth/            ← login / logout
│       ├── Portal/          ← tablero por rol (staff / alumnos-profesores)
│       ├── Funciones/       ← funciones oficiales (MO) del departamento
│       ├── Solicitudes/     ← trámites entre departamentos (canales autorizados)
│       ├── Tickets/         ← tickets canalizados al depto. que atiende el asunto
│       ├── Alumnos/         ← padrón granular por programa (siglas alfabéticas)
│       ├── Comunicados/     ← avisos granulares (todos / nivel / programa)
│       ├── Encuestas/       ← votaciones anónimas con tablero público de tokens
│       ├── Foro/            ← publicaciones y peticiones con likes
│       ├── Comisiones/      ← trazabilidad: comisiones, bitácora y evidencias
│       ├── Busqueda/        ← búsqueda semántica: "¿a dónde dirijo mi asunto?"
│       ├── Recursos/        ← menú RECURSOS configurable por el admin (archivos/enlaces)
│       ├── Eventos/         ← avisos suscribibles + buscador + biblioteca de concluidos
│       ├── Calendario/      ← calendario personal con los eventos suscritos
│       ├── Titulacion/      ← trámite de titulación en línea (licenciatura; posgrado próximamente)
│       ├── Espacios/        ← Auditorios y salas: horarios, personas autorizadas y ocupación (Titulación aparta aquí los exámenes)
│       ├── Notificaciones/  ← bandeja única de avisos de cada persona (el correo solo alerta y lleva aquí)
│       ├── Directorio/      ← organigrama navegable
│       └── Ajustes/         ← parámetros configurables (solo admin)
├── database/                ← schema.sql, schema_fase2.sql y seeds
├── storage/                 ← archivos subidos (FUERA del webroot)
├── docs/                    ← manuales + propuesta de interoperabilidad
└── legacy/                  ← demo estática original (referencia)
```

## Roles

| Rol | Acceso |
| --- | --- |
| `alumno` | Portal, comunicados de su programa, tickets, encuestas (crear/votar), foro, directorio, ver comisiones. |
| `profesor` | Lo anterior + funciones del MO + participar en comisiones (invitación confirmable o voluntario). |
| `operativo` | Módulos de su departamento: solicitudes, tickets recibidos, alumnos, comunicados (si su depto. emite). |
| `coordinador` | Gestión académica y operativa de su coordinación conforme a las rutas autorizadas. |
| `titular` | Lo anterior + atender/resolver + formar comisiones cuando el asunto es de su competencia. |
| `admin` | Todo + ajustes de la plataforma. |

## Instalación

### Hosting (producción)

1. Crear la base del entorno y declarar sus credenciales en `app/config.local.php` o
   mediante las variables `TRAZAFI_DB_HOST`, `TRAZAFI_DB_NAME`, `TRAZAFI_DB_USER` y
   `TRAZAFI_DB_PASSWORD`.
2. Importar en orden por phpMyAdmin: `schema.sql` (**sin** las líneas `DROP/CREATE DATABASE/USE`),
   `seed_departamentos.sql`, `seed_demo.sql`, `schema_fase2.sql`, `seed_fase2.sql`.
3. La URL canónica es `https://fingenieria.mx/`, sin `/public`. Lo ideal es configurar
   `public/` como document root. Cuando el hosting obliga a usar `public_html`, deben
   conservarse el `index.php` y `.htaccess` de la raíz: solo permiten servir
   `public/assets/` y envían el resto al front controller. Los `.htaccess` internos
   bloquean además el acceso HTTP a `app/`, `storage/`, `database/`, `docs/`, `lib/`
   y `tools/`.
4. `app/config.local.php` puede existir en el servidor, pero debe quedar fuera del
   repositorio y bloqueado por el servidor web. Nunca debe copiarse a otro entorno.
5. El diseño es compatible con blindaje de permisos (archivos 444 / carpetas 555 en el
   webroot): la única escritura ocurre en `storage/`.

### Actualizar la base de datos

Para llevar una base ya instalada a la versión actual, importar **`database/actualizar.sql`** (phpMyAdmin →
Importar, o `mysql -u usuario base < database/actualizar.sql`). Es acumulativo y repetible: reúne en orden los
esquemas y todas las migraciones, solo crea o cambia lo que falta y no borra datos, así que puede importarse
cuantas veces haga falta sin importar desde qué versión se parta. No se edita a mano: después de cambiar una
migración se regenera con `php database/construir_actualizar.php`.

### Local (XAMPP)

```
C:\xampp\mysql\bin\mysql -u root < database\schema.sql
C:\xampp\mysql\bin\mysql -u root plataforma_fi < database\seed_departamentos.sql
C:\xampp\mysql\bin\mysql -u root plataforma_fi < database\seed_demo.sql
C:\xampp\mysql\bin\mysql -u root plataforma_fi < database\schema_fase2.sql
C:\xampp\mysql\bin\mysql -u root plataforma_fi < database\seed_fase2.sql
C:\xampp\php\php.exe -S localhost:8090 -t public
```

`app/config.local.php` redirige la conexión a la BD local `plataforma_fi`.
En el clon de pruebas de Windows también puede ejecutarse `INICIAR_PRUEBAS.cmd`;
si XAMPP está instalado usa su PHP y publica exclusivamente la carpeta `public/`.

El procedimiento de staging y promoción está documentado en
`docs/ENTORNO-PRUEBAS.md`. La publicación se repite con
`.\tools\deploy_test_clone.ps1`.

**Cuentas demo**: ver `demo.txt` en la raíz del proyecto (no se muestran en el login
ni deben subirse a la carpeta pública). Cuenta rápida de alumno:
`alumno.prueba@uaemex.mx` / `fi2026`.

## Flujos clave

- **Búsqueda semántica** (`/busqueda`): quien no sabe a qué departamento dirigirse
  describe su necesidad; el sistema muestra las funciones del MO más parecidas y con un
  clic el ticket queda precanalizado al departamento correspondiente. Sin configuración
  usa un motor local TF-IDF; con una API key de embeddings (compatible con OpenAI,
  configurable en Ajustes: `ia_api_key`, `ia_url_embeddings`, `ia_modelo_embeddings`)
  usa similitud vectorial — precalcular una vez con
  `php database/generar_embeddings.php`.

- **Tickets**: el usuario elige una categoría y el sistema canaliza el folio TK-AAAA-XXXX
  al departamento que atiende ese asunto (la categoría «Coordinación de mi carrera» se
  resuelve con el programa del alumno). Seguimiento con estados y bitácora.
- **Comunicados**: los emiten las coordinaciones de licenciatura, Estudios Avanzados,
  Servicios Escolares y Difusión Cultural con alcance granular: todos, licenciatura,
  posgrado o un programa específico (ICO, IEL, IIA, IME, ISES / DCI, DCTBC, MCI, MIAA, MMT).
- **Encuestas anónimas**: un voto por usuario (tabla `encuesta_participantes` registra
  *que* votó, nunca *qué* votó); cada voto genera un token público. El tablero
  `/encuestas/{id}/tablero` es accesible **sin login** para verificación ciudadana.
- **Foro → trazabilidad**: una petición que reúne el umbral de likes (ajustable en
  Ajustes) pasa a `en_trazabilidad`; la Dirección/Subdirecciones/titular competente
  forma una comisión (detonadores: petición o reunión). Los profesores confirman su
  asignación o se postulan; la bitácora con evidencias (en `storage/`) queda auditable.

- **Recursos (menú configurable)**: el administrador, desde «Recursos del menú»,
  publica archivos (Calendario Escolar, Plantillas de Salones…) o enlaces externos
  (Control Escolar). Cada entrada aparece en la sección RECURSOS del menú y abre una
  ficha con descripción, notas del administrador y **notas personales editables** de
  cada usuario (p. ej. «Inscribirme el 22 de julio»).
- **Avisos y eventos → Mi Calendario**: los departamentos emiten avisos de reuniones o
  conferencias; cualquier usuario los agrega con un clic a su calendario personal del
  sitio (RECURSOS → Mi Calendario, vista mensual). Hay buscador por texto y categoría;
  los eventos concluidos se archivan en la **Biblioteca de eventos** con carruseles por
  categoría.

## Cómo agregar un módulo

Para módulos nuevos, copiar `docs/modulos/plantilla/` a
`app/Modules/MiModulo/`, adaptar `module.json`, controlador y vistas, y ejecutar
`php tools/validar_modulos.php`. No se toca el núcleo ni el menú principal: el
cargador descubre y valida el manifiesto. La guía completa, incluidos roles, campos
compartidos, migraciones y lista de seguridad, está en `docs/GUIA_MODULOS.md`.

Ejemplo de `module.json`:

```json
{
  "clave": "mimodulo",
  "version": "1.0.0",
  "roles": ["admin", "titular"],
  "menu": [
    {"seccion": "GESTIÓN", "titulo": "Mi módulo", "ruta": "mimodulo", "roles": ["admin", "titular"], "orden": 15}
  ],
  "rutas": {
    "GET /mimodulo": ["App\\Modules\\MiModulo\\MiModuloController", "index"],
    "POST /mimodulo": ["App\\Modules\\MiModulo\\MiModuloController", "guardar"]
  }
}
```

Los manifiestos PHP existentes siguen admitidos temporalmente para no interrumpir los
23 módulos instalados; deben migrarse gradualmente a JSON.

## Seguridad (según plantilla)

- PDO preparado en todas las consultas; `password_hash` bcrypt.
- CSRF automático en POST, PUT, PATCH y DELETE (lo valida el Router); escape con `e()` en vistas.
- Roles aplicados centralmente por el Router (módulo) y en controladores (acciones).
- Subidas validadas (extensión + MIME + tamaño) y guardadas en `storage/` fuera del webroot;
  descargas solo vía controlador con sesión.
- Regenerar seed de departamentos si cambia el MO: `node database/generar_seed.js`.
