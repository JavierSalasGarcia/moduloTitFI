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

## Pendiente de migrar

Los módulos con política propia (`ReunionPolicy`, `AsuntosAccess`, `TitulacionAccess`, `DocumentArchive::scope`) siguen
como están. Se migran uno por uno a `Acceso::puede` sin cambiar su comportamiento; `archivo_accesos` es el antecedente
de esta tabla. Falta también la pantalla para conceder, revocar y avisar de vencimientos próximos.

Prueba: `php tools/prueba_acceso.php` (revierte todo lo que escribe).
