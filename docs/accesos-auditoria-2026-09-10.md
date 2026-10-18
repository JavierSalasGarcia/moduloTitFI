# Autorregistro, verificación y auditoría

Publicado el 10 de septiembre de 2026 en fingenieria.mx.

## Cambios

- Se publicó primero el cambio urgente del menú a **Foro**, con respaldo independiente `database/production-backups/site-update-20260910-092351`.
- Se activó el umbral de **100 apoyos** para las nuevas peticiones y las existentes todavía abiertas. No se revirtieron seguimientos ya iniciados. Los valores previos se conservaron en el ajuste interno `accesos_v1_respaldo_umbral`.
- El acceso muestra **Crear mi cuenta** y el mensaje «Menos trámites. Todo a la mano y con seguimiento.», acompañado por «Para administrativos, docentes y estudiantes».
- Las cuentas nuevas tienen permisos básicos de comunidad, sin privilegios administrativos. Se conservan los accesos de las cuentas existentes. El correo verificado habilita escritura dentro del rol asignado, no acredita un cargo institucional.
- La verificación utiliza códigos aleatorios de seis dígitos, almacenados únicamente como hash. Vencen en diez minutos, admiten cinco intentos y se invalidan al verificar. Hay límite de registros por IP, cinco solicitudes de código por hora y espera de un minuto entre envíos.
- Las cuentas pendientes no pueden crear, actualizar ni borrar contenido: existe control de rutas y de consultas PDO, incluso para INSERT en peticiones GET. Registro de cuentas, verificación, límites de abuso y auditoría son excepciones internas necesarias; no las controla un parámetro enviado por el navegador.
- La guía de diseño orientó el aviso discreto integrado visualmente al encabezado, sin ventanas emergentes. Los campos tienen etiquetas y permiten autocompletar el código.
- Administración dispone de **Cuentas y acceso** para buscar cualquier usuario e impersonar cuentas activas no administrativas. Se exige la contraseña del administrador y el formulario protegido contra CSRF. El encabezado identifica al usuario y al administrador real, con una acción para volver. La sesión dura como máximo treinta minutos y no permite gestionar credenciales de la cuenta impersonada.
- **Registro de acciones** muestra autor real, cuenta utilizada, fecha y hora de Ciudad de México, acción y resultado. Incluye inicios y cierres de sesión, accesos, bloqueos y operaciones de escritura. No almacena contraseñas, códigos, tokens ni contenido de formularios. El registro comienza con esta actualización; no se reconstruyen accesos históricos.
- Las menciones visibles de UAEMéx se actualizaron a **UAEMEX**, incluidos textos de acceso, privacidad, calendario y documentos generados. No se modificaron documentos históricos ni respaldos.

## Publicación y respaldo

Manifiesto: `tools/access-deploy-files.json`. Se publicaron y verificaron por SHA-256 treinta archivos. Se respaldaron los dieciocho archivos que ya existían en `database/production-backups/site-update-20260910-092851`; los otros doce son nuevos. No se sobrescribieron credenciales ni se reimportó la base de datos.

La activación de las tres tablas adicionales se ejecutó desde una acción administrativa autenticada y protegida con CSRF. No se subió ningún instalador público ni herramientas de prueba.

## Pruebas

En base de pruebas aislada:

- Registro pendiente, bloqueo de escritura mediante HTTP y mediante INSERT directo en PDO.
- Código vencido, bloqueo tras cinco intentos y rechazo de formulario sin CSRF.
- Verificación correcta seguida de publicación habilitada.
- Impersonación, bloqueo de cambios de credenciales, retorno a la cuenta administrativa y atribución de la escritura al administrador real.
- Regresión de veintiocho rutas en cinco anchos, más recorridos de solicitud, reunión y encuesta.

En producción:

- Activación, menú Foro, umbral de cien apoyos y registro de inicio de sesión verificados.
- Veintidós rutas a 360, 390, 768 y 1440 píxeles sin errores PHP visibles, errores JavaScript detectados ni desbordamiento horizontal del documento.
- Botón Crear mi cuenta visible y formulario de registro accesible, con capturas del navegador móvil simulado.
- El proveedor aceptó un código de prueba para `jsalasg@uaemex.mx`. La recepción en el buzón requiere confirmación del usuario. La prueba no cambió su contraseña ni sus permisos.
- No se crearon cuentas ni contenido de prueba en producción; se cerró la sesión administrativa de pruebas.

Las pruebas móviles son simuladas, no pruebas en dispositivos físicos.
