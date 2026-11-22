# SelectorPersonas: el cuadro para elegir personas

Nombre de la función: **`SelectorPersonas`** (`public/assets/js/selector-personas.js`, cargado en todas las páginas con sesión; estilos `.sp-*`
en `public/assets/css/ux.css`; vista `app/Views/selector_personas.php`).

**Regla:** todo cuadro de la plataforma donde se deba capturar a una persona (por nombre o por correo) usa `SelectorPersonas`. No se hacen
cajas de texto libres para nombres ni buscadores nuevos.

## Cómo se comporta (como al agregar personas en Microsoft Teams)

1. Se teclean letras del nombre **o** del correo en el mismo cuadro. Desde 2 caracteres salen las coincidencias (cada palabra escrita debe
   aparecer en el nombre o en el correo, en cualquier orden; no importan mayúsculas ni acentos).
2. Con el ratón, o con ↑ ↓ y Enter, se elige una. Queda como un elemento de la lista con una **×** para quitarla.
3. El cuadro sigue disponible: se teclea y se elige la siguiente persona. Retroceso en un cuadro vacío quita la última.
4. Con `libre` se puede agregar un nombre que no está en la lista (se ofrece «Agregar «texto» tal cual»; Enter lo confirma).

## Uso en una vista

```php
<?php
$campo = 'profesores[]';              // nombre del campo; con [] si admite varias personas
$url = url('consejos/personas');      // buscador JSON: {"personas":[{"id","nombre","email"}]} para ?q=
$seleccion = [['id' => 12, 'nombre' => 'Dra. Ana Pérez', 'email' => 'ana@uaemex.mx']];   // lo ya elegido (o textos)
$id = 'personas'; $max = 0; $libre = false; $valor = 'id';   // $valor: qué viaja al servidor: 'id', 'nombre' o 'email'
$placeholder = 'Escribe el nombre o correo…'; $etiqueta = 'Profesores mencionados';
include dirname(__DIR__, 3) . '/Views/selector_personas.php';
```

- `$max = 1` para una sola persona (el campo viaja como escalar, p. ej. `presidente`).
- El servidor recibe los valores como un arreglo (`$_POST['profesores']`) y debe **validarlos de nuevo** (que existan, que estén activos,
  que tenga permiso de verlos): el cuadro solo ayuda a capturar.
- El buscador del servidor decide a quién se puede encontrar; en Consejos es `GET /consejos/personas` (solo gestores).

## API de JavaScript

`SelectorPersonas.agregar(contenedor, {id, nombre, email})`, `.quitar(clave)`, `.valores(contenedor)` y el evento `sp:agregar` / `sp:quitar`
sobre el contenedor. `SelectorPersonas.mencionar(lienzo, contenedor)` permite escribir `@nombre` dentro de un texto editable: el nombre queda
en el texto y la persona se suma al selector (quitarla del selector no la borra del texto). Se usa en el cuerpo de los casos de Consejos.

## Dos modos

1. **Con elementos («chips»)**: la vista `app/Views/selector_personas.php` (arriba). Para campos donde se eligen una o varias personas.
2. **Sugerencias en un cuadro que ya existe** (`SelectorPersonas.sugerir(caja, {url|lista, formato, alElegir})`): cuando el formulario ya tiene
   su campo de nombre o correo y el servidor espera ese valor (asesores, firmantes, «se extiende a»…). Salen las mismas coincidencias y,
   al elegir, se rellena la caja y los campos relacionados. Se activa con atributos: `data-sp-url="<buscador>"` (con `data-sp-llena="<selector css>"`
   para rellenar otro campo), o `data-sp-lista="<id de un datalist>"` si el `<datalist>` ya trae a todas las personas («Nombre — correo»).
   `public/assets/js/titulacion-profesores.js` lo usa para las filas con `data-prof`, `data-prof-correo`, `data-prof-grado` y `data-prof-genero`.

## Buscador del servidor

`App\Core\BuscadorPersonas::responder($roles, $alumnos)` entrega `{"personas":[{id,nombre,email}]}`; cada módulo expone su propio endpoint y **autoriza
ahí** quién puede buscar y a quién (p. ej. `GET /comisiones/personas`, `/concesiones/personas`, `/constancias/personas`, `/eventos/estadisticas/buscar`,
`/consejos/personas`). El componente también entiende las respuestas `{resultados:[…]}` o una lista simple.

## Dónde se usa

Consejos (mesa, consejeros, profesores mencionados, avisos), Grupos de destinatarios (integrantes y audiencia), Teams (compartir documento),
Comisiones (invitar), Accesos especiales (persona y persona cuyo grafo se ve), Estadísticas de avisos (dar acceso), Estructura académica
(buscar profesor, responsables, NAB/CAP), Constancias de Titulación (buscar profesor) y de Constancias (se extiende a), Firmas (firmantes y
pasos, grupos), Titulación (asesores, jurado, agenda) y Espacios (responsables y administradores).

## Excepciones conocidas

Los selectores propios de **Reuniones** (invitados internos y externos con disponibilidad en el calendario) y de **Tickets** (personas o
departamentos) ya funcionan como el de Teams —buscan al teclear y dejan elementos con «×»— pero llevan lógica propia; al rehacerlos, usar
`SelectorPersonas`.
