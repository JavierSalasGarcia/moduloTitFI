# Encuestas privadas por enlace en TRAZAFI

Esta guía es el patrón de implementación para solicitudes que pidan recabar datos mediante un enlace, limitar quién puede responder y mantener las respuestas fuera de la vista pública. El módulo que se debe extender es `app/Modules/Formularios`; el módulo `Encuestas` es para votaciones anónimas verificables y no debe usarse para datos identificables.

## Modelo de acceso

Una encuesta privada tiene dos superficies con permisos independientes:

1. **Formulario de captura** (`/f/{slug}`): no aparece en el menú. `frm_formularios.requiere_rol` obliga a iniciar sesión y a tener ese rol, por ejemplo `profesor`. El enlace compartido sólo conduce al formulario; no concede permisos por sí solo.
2. **Registros y Excel** (`/formularios/{id}`): sólo aparecen a `admin`, a las autoridades seleccionadas en `acceso` y a correos explícitos de `responsables`. Las autoridades se resuelven con la titularidad vigente de Organización, no con nombres escritos en el código.

Para información privada no se debe marcar una ruta de resultados como pública, incluir respuestas en tableros abiertos ni usar un token como único control de acceso. Las respuestas identificables conservan `usuario_id`, correo, auditoría y fecha.

## Cómo crear una nueva encuesta

1. Crear una migración numerada en `database/migrations/` e incluirla al final de `$fuentes` en `database/construir_actualizar.php`.
2. Insertar el registro en `frm_formularios` con un `slug` específico, descripción y campos JSON. Usar `INSERT ... ON DUPLICATE KEY UPDATE` para que la migración sea repetible sin borrar respuestas.
3. Definir:
   - `requiere_rol`: rol de quien responde (`profesor`, `alumno`, etc.) o `NULL` si no requiere cuenta.
   - `acceso`: lista de autoridades (`director`, `subdirector`, `coordinadores_docencia`).
   - `responsables`: sólo cuando se necesiten personas adicionales, por correo institucional.
   - `varios_por_correo = 1`: un envío por elemento (por ejemplo, un viaje por registro); `0`: una sola respuesta por persona.
   - `cierra_en`: fecha y hora local de cierre.
4. Para identidad confiable, declarar campos con `"de_cuenta":"nombre"` y `"de_cuenta":"email"`. Cuando `requiere_rol` está activo, el servidor sustituye cualquier valor enviado por los datos de la sesión.
5. Elegir tipos de campo soportados: `texto`, `textarea`, `email`, `numero`, `fecha`, `select`, `confirmar`, `programa`, `evidencia` y `fijo`. `numero` admite `min`/`max_num`; `fecha` admite `min`/`max_fecha`; `no_antes_de` compara fechas o números relacionados.
6. Regenerar `database/actualizar.sql` con `php database/construir_actualizar.php` y ejecutar las validaciones indicadas abajo.

## Privacidad y seguridad obligatorias

- Aplicar permisos tanto en el controlador como en la visibilidad del menú; ocultar un enlace no constituye autorización.
- Mantener CSRF en todos los POST, consultas preparadas y escape HTML en vistas.
- Añadir `X-Robots-Tag: noindex, nofollow` al formulario compartible.
- No aceptar nombre, correo o rol desde campos editables si la encuesta exige cuenta.
- Descargar Excel a través de la ruta protegida existente y con `Cache-Control: no-store, private`.
- No reutilizar el flujo anónimo de `Encuestas`: una planeación administrativa necesita trazabilidad y corrección.
- El administrador puede cerrar la captura, cambiar la fecha y ajustar autoridades desde el panel; una migración posterior no debe revertir esos cambios.

## Encuesta SF/1500/2026

La encuesta `programacion-viajes-internacionales-2027-sf1500-q7m4k9x2` recopila un registro por viaje: competencia, ciudad y país, fechas y duración estimadas, alumnos y profesores participantes, rango de edades y cantidad/tipo de vehículos institucionales. Puede responderse sin cuenta porque también se enviará a profesores no registrados; por ello exige una declaración de pertenencia al profesorado y se protege contra descubrimiento mediante un enlace no listado con sufijo aleatorio. La pantalla recomienda registrarse o iniciar sesión y completa nombre/correo cuando existe una sesión. Sólo consultan `admin`, Dirección y Subdirección Académica. El cierre es el 6 de noviembre de 2026 a las 13:00, hora de México.

El enlace que se comparte es `https://fingenieria.mx/f/programacion-viajes-internacionales-2027-sf1500-q7m4k9x2`. El sufijo no predecible evita la enumeración casual; como no se exige cuenta, la condición de profesor es declarativa y el enlace debe enviarse sólo a las personas destinatarias. Los resultados se descargan como `.xlsx` desde Formularios en el menú de administración.

## Verificación mínima

```text
php -l app/Modules/Formularios/Formularios.php
php -l app/Modules/Formularios/FormulariosController.php
php tools/validar_modulos.php
php tools/test_formulario_privado.php
php database/construir_actualizar.php
```

Para formularios con `requiere_rol`, probar profesor autorizado y usuario sin el rol (403). Para formularios sin cuenta, probar captura anónima, sugerencia de registro, autoridad configurada (registros/Excel) y usuario ordinario (sin menú y 404 al consultar registros).
