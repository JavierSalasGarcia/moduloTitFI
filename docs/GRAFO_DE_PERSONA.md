# Grafo de relaciones de una persona

Muestra, en un solo dibujo y una lista, todo el perfil funcional y documental que la plataforma tiene de una persona:
adscripción y cargos, programas en los que imparte clases, cuerpos y órganos académicos, estudiantes que asesora
(licenciatura, maestría, doctorado), protocolos revisados, jurados, constancias, documentos ingresados y accesos
especiales. Pantalla: **Mi espacio → Mi grafo de relaciones** (`/ingesta/grafo`).

## Cómo se construye

| Pieza | Archivo |
|---|---|
| Núcleo: reúne a los proveedores, valida su salida, decide quién ve a quién y dibuja el SVG | `app/Core/GrafoPersona.php` |
| Pantalla y buscador de personas | `app/Modules/Ingesta/IngestaController.php` (`grafo`), `views/grafo.php` |
| Proveedores actuales | `Organizacion\GrafoOrganizacion::de`, `Titulacion\Services\GrafoTitulacion::de`, `Ingesta\Ingesta::grafo` |
| Prueba | `php tools/prueba_ingesta.php` |

1. `GrafoPersona::de($usuarioId)` obtiene los correos verificados de la persona y su ficha de profesor.
2. Agrega los grupos del núcleo (adscripción, roles, cargos, accesos especiales vigentes).
3. Llama a cada función que los módulos declaran en su manifiesto con la clave **`grafo`** y junta sus grupos.
4. Normaliza: solo texto, máximo 25 elementos por grupo, y enlaces que sean rutas internas relativas.
5. `GrafoPersona::svg()` dibuja un árbol radial en el servidor (sin bibliotecas ni scripts): la persona al centro, un
   nodo por grupo y sus elementos en el anillo exterior. La lista de abajo es el equivalente accesible, con enlaces.

Todo se busca por la **cuenta**, la **ficha de profesor** o los **correos verificados**; nunca por nombre.

## Agregar un módulo al grafo

Basta con el manifiesto del módulo nuevo; no se toca el núcleo ni la pantalla.

1. Declara la clave en `module.json` o `module.php`:

   ```json
   "grafo": "App\\Modules\\Ejemplo\\GrafoEjemplo::de"
   ```

2. Escribe la función. Recibe la persona y devuelve una lista de grupos:

   ```php
   public static function de(int $usuarioId, array $correos, ?int $profesorId): array
   {
       $filas = Database::todas('SELECT id, nombre, estado FROM ej_registros WHERE usuario_id = ? ORDER BY id DESC LIMIT 25', [$usuarioId]);
       return $filas ? [[
           'titulo' => 'Registros de Ejemplo',                       // nombre del grupo (nodo)
           'total'  => (int) Database::valor('SELECT COUNT(*) FROM ej_registros WHERE usuario_id = ?', [$usuarioId]),
           'items'  => array_map(fn(array $f) => [
               'etiqueta' => $f['nombre'],                            // texto del nodo
               'detalle'  => $f['estado'],                            // segunda línea en la lista
               'enlace'   => 'ejemplo/' . $f['id'],                   // ruta interna relativa, o null
           ], $filas),
       ]] : [];
   }
   ```

3. `php tools/validar_modulos.php` y `php tools/prueba_ingesta.php`.

Reglas para un proveedor:

- **Solo lo de esa persona.** Filtra por `$usuarioId`, `$profesorId` o `$correos`. No busques por nombre.
- **Sin datos de terceros innecesarios.** El grafo lo puede ver quien coordina a la persona: incluye lo funcional
  (qué asesora, qué revisó), no contenido confidencial. Lo marcado como privado no se muestra cuando lo ve otra persona
  (ejemplo: `Ingesta::grafo` omite el alcance «propio» si `$usuarioId !== Auth::id()`).
- **Enlaces relativos** a una sección que aplique sus propios permisos (`'titulacion/constancias'`). Un enlace absoluto
  o externo se descarta.
- **Acotado y tolerante.** Máximo 25 elementos (indica `total` si hay más). Si la tabla aún no existe, devuelve `[]`;
  una excepción se registra y el resto del grafo sigue.

## Quién puede ver el grafo de quién

Regla en `GrafoPersona::idsVisibles()`; la aplica `puedeVer()` en cada petición.

| Quién | Ve el grafo de |
|---|---|
| Cualquier persona (alumno, profesor, personal) | El suyo, y nada más |
| Administración | Todas las cuentas, con buscador por nombre o correo |
| Líder de un cuerpo u órgano académico (presidente o coordinador en `programa_organo_miembros`) | Los miembros de ese órgano |
| Coordinador o responsable de un programa, de carrera o de posgrado (`programa_responsables`, coordinaciones de posgrado) | Los profesores del programa y los miembros de sus órganos |
| Titular o responsable de una dependencia | El personal adscrito a ella (nunca los alumnos) |
| Quien tenga una **concesión temporal** | Lo que diga la concesión, hasta su fecha de término |

Quien no es administrador y tiene personas a su cargo ve un buscador limitado a esas personas. Un intento de abrir un
grafo ajeno responde 404 y queda en la bitácora (`grafo_persona`, denegado); cada consulta del grafo de otra persona
también se registra.

### Concesiones temporales (por ejemplo, para una comisión)

En **Administración → Accesos especiales** (`/concesiones`) el administrador concede a una persona, con fecha de
término obligatoria y motivo:

| Función | Alcance que se elige |
|---|---|
| `grafo.programa` | Un programa: verá a sus profesores |
| `grafo.organo` | Un cuerpo u órgano académico: verá a sus miembros |
| `grafo.persona` | Una persona concreta |
| `grafo.personas` | Todas las personas |

Al vencer o revocarse, el acceso termina solo. Ver `docs/CONCESIONES_DE_ACCESO.md`.

## Pendiente

- El «cuerpo académico» se toma de los órganos por programa (NAB y CAP). Si los cuerpos académicos PRODEP se registran
  en otra tabla, hay que agregar su proveedor y su regla de liderazgo.
- Los elementos del dibujo no son enlaces (los enlaces están en la lista). El dibujo no es interactivo.
- Faltan proveedores para comisiones, reuniones, eventos, justificantes y archivo documental.
