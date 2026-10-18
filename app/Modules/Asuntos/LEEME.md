# Expedientes de asuntos por oficina

El módulo atiende la URL histórica impresa en los QR:

`/public/fila/<oficina>/reg.php?<folio>`

Aunque la ruta termina en `.php`, se resuelve mediante el front controller de
Traza FI para reutilizar su sesión, CSRF, auditoría y control de acceso.

## Instalación en producción

1. Respaldar la base de datos y `storage/`.
2. Ejecutar `database/migrations/007_asuntos.sql` en la base de datos de
   Traza FI.
   Si la versión anterior con tablas `sa_*` ya se instaló, ejecutar después
   `database/migrations/008_generalizar_asuntos.sql`.
3. Publicar `app/Modules/Asuntos`, los cambios de `app/Core/Auth.php`,
   `app/Modules/Auth` y `app/config.php`.
4. Confirmar que el usuario de PHP puede crear `storage/sa` y escribir en él.
5. Probar con un folio de un lote no usado, primero como gestor de
   la oficina configurada y luego con una cuenta expresamente autorizada.

## Modelo de seguridad

- Los folios tienen aproximadamente 129 bits de aleatoriedad y no son
  secuenciales.
- Conocer el folio no concede acceso: el correo de la sesión debe figurar en
  `asunto_expediente_accesos`.
- Ni siquiera un administrador ve un expediente existente si no está en esa
  lista. Un administrador o gestor configurado para la oficina sí puede abrir
  una entrada todavía inexistente; queda agregado como primer acceso.
- Un expediente inexistente y uno no autorizado producen la misma respuesta
  404 para evitar revelar su existencia.
- Las consultas tienen límite por IP y usuario, no se almacenan en caché y se
  auditan. Los archivos viven fuera de `public/` y solo se descargan después de
  volver a comprobar el acceso.
- Los correos autorizados deben pertenecer a los dominios configurados en
  `asuntos.dominios_correo`.

## Agregar otra oficina

Agregar una entrada en `asuntos.oficinas` de `app/config.php` con un prefijo
único, segmento de URL, nombre, departamentos y correos gestores. El mismo
controlador, tablas y flujo sirven para todas las oficinas.
