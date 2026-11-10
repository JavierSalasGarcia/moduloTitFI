# Concesiones de acceso por persona

**Principio.** Cada módulo se abre **por rol** (el Router ya lo aplica). Los casos especiales —alguien que, por su
función, debe usar algo que su rol no incluye— se asignan **a una persona concreta**, con **vigencia obligatoria**: la
concesión caduca sola al terminar la función. La identidad no cambia: la persona sigue siendo `usuarios.id`.

Un alumno no ve archivos de profesores ni de departamentos; un profesor no ve funciones ni archivos de departamento,
salvo que alguien con facultad se lo conceda por el tiempo que dure su función.

## Piezas

- Migración `043_acceso_concesiones.sql`: tabla `acceso_concesiones` (persona, función, recurso opcional, dependencia dueña,
  permiso, motivo, otorgante, `vigente_desde`, `vigente_hasta` obligatoria, revocación).
- `App\Core\Acceso`: punto único de decisión.

## Orden de decisión (se niega por defecto)

1. Propietario del recurso.
2. Rol que el módulo declara para la acción.
3. Concesión por persona vigente.

```php
Acceso::exigir('documentos', ['admin', 'coordinador'], $docId, $doc['subido_por'], 'leer');   // 404 si no puede
if (Acceso::puede('titulacion.panel', ['operativo'])) { ... }
```

Una concesión sin `recurso_id` cubre toda la función; con `recurso_id`, solo ese recurso. El permiso debe coincidir.

## Quién concede

- **Administrador**: en cualquier dependencia y sobre toda la plataforma.
- **Titular** (rol principal o adicional): solo sobre recursos de su propia dependencia.
- Nadie se concede acceso a sí mismo. La vigencia debe ser hoy o futura. Se puede revocar antes del vencimiento.
- Concesión, revocación y denegación quedan en la auditoría.

## Dónde se aplica

Cada política conserva sus reglas actuales y suma la concesión como vía adicional (no cambia quién entra hoy):

| Función concedible | Política que la reconoce |
|---|---|
| `titulacion.personal` | `TitulacionAccess::esPersonal` |
| `asuntos.gestor` | `AsuntosAccess::esGestor` |
| `reuniones.precargar` | `ReunionPolicy::puedePrecargar` |
| `documentos.subir` | `DocumentArchive::uploader` |
| `modulo.<clave>` | Router y menú: abre el módulo completo a esa persona aunque su rol no figure en el manifiesto |
| `ingesta.subir` | `Ingesta::puedeIngresar`: ingresar PDFs, carpetas y enlaces de Teams |
| `ingesta.carpeta` | `Ingesta::alcance`: leer una carpeta de documentos indizados (se concede sobre la carpeta) |
| `grafo.personas`, `grafo.programa`, `grafo.organo`, `grafo.persona` | `GrafoPersona::idsVisibles`: ver el grafo de todas las personas, de los profesores de un programa, de los miembros de un órgano o de una persona |

`ajustes`, `seguridad`, `auth` y `concesiones` no se conceden completos. `archivo_accesos` (permiso por documento) sigue
igual; es el antecedente de esta tabla.

## Pantalla «Accesos especiales» (`/concesiones`)

Módulo `Concesiones`, para administración y titulares. Se concede por correo de una cuenta existente, con fecha de término
obligatoria. El administrador elige cualquier función (incluidos módulos completos) y dependencia; el titular solo las
funciones marcadas para titulares y sobre su propia dependencia. Lista vigentes, vencidas y revocadas de los últimos 90
días, y permite revocar.

**Avisos**: la persona recibe en Notificaciones «Tu acceso … vence el …» desde 15 días antes; quien concede ve cuántos
accesos de su dependencia vencen pronto.

## Pendiente

- Agregar funciones al catálogo (`Acceso::CATALOGO`) al migrar más políticas (`FilaAccess`, comisiones, etc.).
- La pantalla concede por recurso solo en las funciones del grafo (programa, órgano o persona). Falta para `ingesta.carpeta` y otros recursos.

Prueba: `php tools/prueba_acceso.php` (revierte todo lo que escribe).
