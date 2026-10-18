# Guía de módulos independientes de Traza FI

## Objetivo

Cada función nueva debe poder incorporarse copiando una sola carpeta a
`app/Modules/<NombreModulo>/`. El núcleo descubre el manifiesto, valida su contrato,
registra sus rutas y agrega sus entradas al menú. No se modifica `Router.php`, el
layout ni un catálogo central.

Copiar una carpeta **no equivale a confiar en ella**: un módulo contiene código PHP
y sólo debe instalarse después de revisión, pruebas y respaldo. Un manifiesto JSON
evita ejecutar código durante el descubrimiento, pero no convierte un módulo de
origen desconocido en software seguro.

## Estructura de una carpeta instalable

```text
app/Modules/Ejemplo/
├── module.json                 manifiesto obligatorio para módulos nuevos
├── EjemploController.php       entrada HTTP
├── EjemploRepository.php       acceso a datos del módulo (opcional)
├── Services/                   reglas e integraciones (opcional)
├── Policies/                   autorización por registro (opcional)
└── views/
    └── index.php
```

Las tablas se crean con una migración separada en `database/migrations/`. Copiar una
carpeta nunca ejecuta SQL automáticamente: esa separación impide que un módulo
accidental o malicioso altere datos al ser descubierto.

Hay una plantilla completa en `docs/modulos/plantilla/`. Para usarla:

1. Copiarla como `app/Modules/NombreModulo/`.
2. Renombrar controlador, namespace, clave, rutas y textos.
3. Ejecutar `php tools/validar_modulos.php`.
4. Aplicar su migración en pruebas.
5. Probar acceso con cada rol y después desplegar la carpeta y la migración aprobada.

## Contrato de `module.json`

| Campo | Tipo | Regla |
|---|---|---|
| `clave` | texto | Única, minúsculas, 2–40 caracteres; letras, números, `_` o `-`. |
| `nombre` | texto | Nombre humano del módulo. Recomendado. |
| `version` | texto | Versión semántica, por ejemplo `1.0.0`. Recomendado. |
| `descripcion` | texto | Alcance funcional breve. Recomendado. |
| `roles` | lista | Acceso general a todas las rutas privadas. Vacía significa cualquier cuenta autenticada. |
| `menu` | lista | Entradas que se incorporan al menú principal. Puede estar vacía. |
| `rutas` | objeto | Mapa `MÉTODO /ruta` → `[Controlador, acción]`. Obligatorio. |

### Qué significa cada campo del manifiesto

- `clave`: identificador técnico permanente. Se usa en logs, permisos y para localizar
  la carpeta; no debe cambiarse después de publicar el módulo. Ejemplo: `servicio_social`.
- `nombre`: texto que identifica el módulo para administradores y documentación. No
  crea por sí mismo una opción de menú.
- `version`: versión del código del módulo en formato `mayor.menor.parche`. Se aumenta
  `mayor` cuando hay incompatibilidades, `menor` al agregar funciones compatibles y
  `parche` para correcciones.
- `descripcion`: explica qué problema resuelve y qué información administra.
- `roles`: roles de login que pueden entrar a todas las rutas privadas del módulo. Si
  está vacío, puede entrar cualquier cuenta autenticada; no significa acceso público.
- `menu`: lista de enlaces que el módulo aporta al menú. Puede contener varias entradas
  o quedar vacía si el módulo sólo ofrece una API o una función interna.
- `rutas`: relaciona cada petición HTTP con la clase y el método PHP que la atienden.
  La ruta no concede acceso a registros ajenos; esa comprobación corresponde al
  controlador o a una política del módulo.

Campos de cada entrada de `menu`:

| Campo | Significado |
|---|---|
| `seccion` | Encabezado bajo el cual aparecerá el enlace, por ejemplo `GESTIÓN`. |
| `titulo` | Texto visible que selecciona el usuario. |
| `ruta` | URL interna sin diagonal inicial; debe corresponder con una ruta registrada. |
| `roles` | Quién puede ver la entrada. Normalmente coincide con `roles` del módulo. Ocultar el enlace no sustituye la autorización de la ruta. |
| `orden` | Número para ordenar secciones y enlaces; los números menores aparecen primero. |

Cada valor de `rutas` contiene:

| Posición | Significado |
|---|---|
| `0` | Nombre completo de la clase controladora, incluido su namespace. |
| `1` | Método público del controlador que atenderá la petición. |
| `2` | Opcional. El único valor permitido es `publico`; permite entrar sin sesión y debe usarse excepcionalmente. |

Los métodos HTTP tienen intención concreta: `GET` consulta sin modificar; `POST` crea
o ejecuta una acción; `PUT`/`PATCH` actualizan y `DELETE` elimina. Todos los métodos de
escritura requieren CSRF.

Entrada de menú:

```json
{
  "seccion": "MI ESPACIO",
  "titulo": "Ejemplo",
  "ruta": "ejemplo",
  "roles": ["admin", "titular", "operativo"],
  "orden": 50
}
```

Ruta privada y ruta pública:

```json
{
  "GET /ejemplo": ["App\\Modules\\Ejemplo\\EjemploController", "index"],
  "GET /ejemplo/publico/{token}": ["App\\Modules\\Ejemplo\\EjemploController", "publico", "publico"]
}
```

El tercer valor `publico` debe usarse sólo cuando la ruta realmente funcione sin
sesión. Los enlaces públicos sensibles deben usar tokens aleatorios de al menos 32
bytes, tener vencimiento y comprobar el permiso en el controlador. Las escrituras
usan `POST`, `PUT`, `PATCH` o `DELETE`; el Router exige CSRF para todas ellas.

El cargador rechaza claves, roles, métodos o rutas inválidos; detecta rutas duplicadas;
comprueba que exista el archivo del controlador y aísla el módulo defectuoso. Los
errores quedan en el log del servidor y el resto de Traza FI continúa disponible.

## Niveles de acceso compartidos

El catálogo canónico está en `App\Core\Roles`:

| Valor en `usuarios.rol` | Alcance esperado |
|---|---|
| `admin` | Configuración técnica, usuarios y auditoría. No implica acceso automático a expedientes confidenciales. |
| `titular` | Responsable de una dependencia o coordinación. |
| `operativo` | Personal que atiende procesos de su dependencia. |
| `coordinador` | Coordinación académica/posgrado asignada explícitamente. |
| `profesor` | Personal académico. |
| `alumno` | Estudiante. |

El manifiesto controla el acceso general al módulo. La autorización sobre un registro
concreto debe estar en una clase `Policies` o en el controlador; por ejemplo, ser
titular no autoriza a leer expedientes de otra dependencia.

## Campos comunes de identidad

Los módulos no crean otra tabla de cuentas. Referencian `usuarios.id` mediante
`usuario_id`, `creado_por`, `responsable_id` o un nombre equivalente que termine en
`_id` y tenga llave foránea.

| Campo de `usuarios` | Uso compartido |
|---|---|
| `id` | Identificador interno; se obtiene con `Auth::id()`. |
| `nombre` | Nombre visible. |
| `email` | Cuenta institucional normalizada. |
| `rol` | Uno de los seis valores de `Roles::TODOS`. |
| `activo` | Una cuenta inactiva no puede iniciar ni conservar sesión. |
| `departamento_id` | Dependencia laboral; usar `Auth::departamentoId()`. |
| `programa_id` | Programa académico cuando aplique. |
| `tipo_contratacion` | Clasificación laboral opcional. |
| `password_hash` | Sólo autenticación; ningún módulo debe leerlo ni copiarlo. |
| `totp_secret` | Sólo seguridad MFA; ningún módulo funcional debe usarlo. |

`Auth::usuario()` añade datos de consulta como `departamento`, `depto_clave`, `area`,
`programa`, `programa_siglas` y `programa_nivel`. Son datos de sesión para presentar o
autorizar; no deben duplicarse en tablas del módulo.

El campo `usuarios.rol` indica **nivel de acceso a Traza FI**, no el puesto completo de
la persona. Por ejemplo, una profesora puede ser miembro del NAB, integrante del CAP y
coordinadora de un posgrado al mismo tiempo, aunque conserve un solo rol de login. Los
cargos y cuerpos académicos se representan con las relaciones siguientes.

## Estructura organizacional compartida

### Catálogos base

| Tabla/campo | Significado |
|---|---|
| `departamentos.id` | Identificador de una dependencia, departamento, centro o coordinación administrativa. |
| `departamentos.clave` | Clave técnica única y estable de la dependencia. |
| `departamentos.nombre` | Denominación oficial visible. |
| `departamentos.area_id` | Área superior a la que pertenece dentro de la estructura. |
| `programas.id` | Identificador del programa educativo. |
| `programas.siglas` | Siglas oficiales, por ejemplo `MCI`. |
| `programas.nombre` | Nombre completo del programa. |
| `programas.nivel` | `licenciatura` o `posgrado`. |
| `programas.depto_id` | Dependencia que administra el programa; no identifica por sí solo a su coordinador. |

### Titulares, jefaturas y coordinaciones

`departamento_responsables` conserva quién ocupa u ocupó un cargo en cada dependencia:

| Campo | Significado |
|---|---|
| `departamento_id` | Dependencia a la que corresponde el nombramiento. |
| `usuario_id` | Persona registrada en `usuarios`. Su nombre se obtiene de `usuarios.nombre`. |
| `cargo` | `titular`, `jefe`, `coordinador`, `encargado` o `secretario`. |
| `fecha_inicio`, `fecha_fin` | Vigencia histórica del nombramiento; `fecha_fin` vacía significa que no se ha cerrado. |
| `activo` | Indica si la asignación debe considerarse vigente. |
| `observaciones` | Aclaración administrativa opcional, nunca permisos ni secretos. |

`programa_responsables` registra responsables de un programa educativo con los mismos
campos de vigencia. Su `cargo` puede ser `coordinador`, `responsable` o
`secretario_academico`. Así se puede consultar el nombre de la coordinación sin
convertir el cargo en un rol global del login.

### NAB y CAP de posgrado

`programa_organos` define los cuerpos de cada programa:

| Campo | Significado |
|---|---|
| `programa_id` | Posgrado al que pertenece el cuerpo. |
| `tipo` | `NAB` (Núcleo Académico Básico) o `CAP` (Comité Académico de Posgrado). |
| `nombre` | Denominación visible; normalmente se genera como “NAB de MCI” o equivalente. |
| `activo` | Permite retirar el órgano sin perder su historia. |

`programa_organo_miembros` relaciona profesores con el NAB o CAP:

| Campo | Significado |
|---|---|
| `organo_id` | NAB o CAP definido en `programa_organos`. |
| `usuario_id` | Profesor o persona integrante. El nombre y correo permanecen en `usuarios`. |
| `cargo` | `presidente`, `coordinador`, `secretario`, `vocal` o `miembro`. |
| `fecha_inicio`, `fecha_fin` | Periodo de pertenencia. |
| `activo` | Membresía vigente. |
| `observaciones` | Nota administrativa opcional. |

Un mismo profesor puede aparecer en varios programas y órganos sin duplicar su cuenta.
Los módulos deben consultar estas tablas en vez de crear listas privadas de nombres.
Las tablas antiguas `reuniones_posgrados` y `reuniones_coordinadores` se mantienen sólo
por compatibilidad mientras Reuniones se migra al catálogo común `programas`.

Consultas de referencia para módulos futuros:

```sql
-- Jefaturas y titulares vigentes por dependencia
SELECT d.nombre AS dependencia, u.nombre, dr.cargo
FROM departamento_responsables dr
JOIN departamentos d ON d.id = dr.departamento_id
JOIN usuarios u ON u.id = dr.usuario_id
WHERE dr.activo = 1 AND dr.fecha_inicio <= CURRENT_DATE
  AND (dr.fecha_fin IS NULL OR dr.fecha_fin >= CURRENT_DATE);

-- Coordinación vigente de cada programa
SELECT p.siglas, p.nombre AS programa, u.nombre AS coordinador
FROM programa_responsables pr
JOIN programas p ON p.id = pr.programa_id
JOIN usuarios u ON u.id = pr.usuario_id
WHERE pr.cargo = 'coordinador' AND pr.activo = 1
  AND pr.fecha_inicio <= CURRENT_DATE
  AND (pr.fecha_fin IS NULL OR pr.fecha_fin >= CURRENT_DATE);

-- Integrantes vigentes del NAB y CAP de cada posgrado
SELECT p.siglas, po.tipo, u.nombre, pom.cargo
FROM programa_organo_miembros pom
JOIN programa_organos po ON po.id = pom.organo_id
JOIN programas p ON p.id = po.programa_id
JOIN usuarios u ON u.id = pom.usuario_id
WHERE p.nivel = 'posgrado' AND po.activo = 1 AND pom.activo = 1
  AND pom.fecha_inicio <= CURRENT_DATE
  AND (pom.fecha_fin IS NULL OR pom.fecha_fin >= CURRENT_DATE)
ORDER BY p.siglas, po.tipo, pom.cargo, u.nombre;
```

Estas consultas devuelven nombres sólo cuando las asignaciones ya fueron capturadas.
La plataforma no debe deducir automáticamente una jefatura a partir de
`usuarios.departamento_id` ni un NAB/CAP a partir del rol `profesor`.

La captura se realiza en **Administración → Estructura académica**. El panel permite
entrar por posgrado o buscar una persona por fragmentos de su nombre/correo. Las
coordinaciones y membresías guardadas aparecen automáticamente en **Mi perfil** de la
persona, junto con su historial.

## Convenciones de datos compartidas

- Llave primaria: `id` entero sin signo.
- Llave foránea: `<entidad>_id`.
- Autor: `creado_por` o `usuario_id`, nunca nombre/correo como única relación.
- Sellos: `creado_en`, `actualizado_en`, `eliminado_en` en hora de México.
- Estado: `estado` con catálogo cerrado y transición validada en servidor.
- Alcance departamental: `departamento_id`; académico: `programa_id`.
- Archivos: guardar sólo la ruta relativa mediante `guardar_archivo()`; nunca dentro de `public/`.
- Secretos: variables de entorno o `app/config.local.php`; nunca manifiestos, repositorio o tabla de ajustes visible.
- SQL: consultas preparadas a través de `Database`; no concatenar entrada del usuario.
- Salida HTML: imprimir datos variables con `e()`.
- CSV: anteponer `'` a celdas que comiencen con `=`, `+`, `-` o `@`.

## Seguridad mínima exigida a cada módulo

1. Privado por defecto. Marcar como pública cada ruta excepcional, no todo el módulo.
2. Toda escritura usa un método no GET y token `Auth::csrfToken()`.
3. Validar longitud, tipo, catálogo y pertenencia de cada dato en servidor.
4. Comprobar autorización tanto para ver como para descargar, editar o eliminar.
5. Aplicar límites a endpoints públicos, correos, búsquedas costosas y códigos.
6. No revelar si existe una cuenta, token o registro cuando esa información facilite ataques.
7. No registrar contraseñas, tokens, códigos, contenido confidencial ni llaves en auditoría.
8. Probar al menos: anónimo, rol permitido, rol denegado, ID ajeno, CSRF inválido y entrada sobredimensionada.

## Alta, baja y actualización

- **Alta:** copiar la carpeta validada. El menú aparece en la siguiente petición.
- **Actualización:** reemplazar sólo esa carpeta y aplicar su migración versionada.
- **Desactivación:** retirar la carpeta después de comprobar que ninguna ruta o tarea la usa. Conservar sus datos hasta una baja formal.
- **Rollback:** restaurar el respaldo de esa versión y revertir la migración sólo si existe un procedimiento probado y no destructivo.

No se deben conservar copias de cada despliegue dentro del árbol web. Los respaldos se
guardan fuera del proyecto, se agrupan por despliegue y se aplica una retención limitada.
Base de datos, archivos de usuario y código tienen políticas de respaldo distintas.
