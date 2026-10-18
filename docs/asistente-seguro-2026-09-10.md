# Asistente seguro y Gemini

## Alcance publicado

- Configuración administrativa: `/public/ajustes/ia`. Gemini 2.5 Flash-Lite, API key de servidor (nunca se devuelve al formulario), prueba sintética, selección local/Gemini y eliminación explícita de clave.
- Modo inicial: local. Activar Gemini requiere clave y confirmación del administrador sobre servicios de pago. Cada usuario opta expresamente por enviar su pregunta para orientación; no enviar datos personales/confidenciales en la pregunta.
- Gemini clasifica entre seis funciones oficiales. No recibe SQL, credenciales de BD, esquema completo, archivos, historial, expedientes ni filas de consultas. Solo devuelve un índice validado; la explicación y los enlaces los construye el servidor.
- El portal fuerza búsqueda local, sin usar el servicio anterior de embeddings ni NVIDIA. Se conservan los parámetros antiguos por compatibilidad con otros módulos.
- Herramientas de lectura fijas: solicitudes propias; solicitudes del departamento para titular/operativo; reuniones de creador/organizador o participante con invitación no revocada. Un administrador no hereda ámbito global en el asistente. No hay herramienta SQL ni de escritura.
- Se seleccionan solo ID, estado, actualización y fechas de reunión; no asuntos, descripción, mensajes, correos ni adjuntos. Máximo diez registros recientes y enlaces a las pantallas originales.
- Usuarios pendientes de verificar pueden usar POST `/consulta` como operación de lectura. El control de escritura en BD sigue vigente; solo auditoría y contadores usan la excepción interna existente.

## Clasificación y límites importantes

Pública: catálogo de funciones oficiales. Interna: catálogo de herramientas autenticadas. Los registros personales habilitados se consideran restringidos y requieren pertenencia/propiedad. En Ajustes el administrador puede excluir por ID una solicitud o reunión como confidencial; queda fuera del asistente incluso para su propietario y el administrador. Una clasificación desconocida también se deniega. Revertir a restringida conserva los permisos de pertenencia, no hace público el registro.

Esta clasificación **es del asistente**, no una migración de las autorizaciones de todo el sitio. Las pantallas originales, descargas y exportaciones mantienen sus controles actuales. No se habilitan expedientes académicos/laborales ni acceso general por nivel jerárquico o concesiones arbitrarias. Ampliar a otros módulos requiere revisar sus permisos y habilitar herramientas específicas; no basta con añadir una etiqueta o instrucción al modelo.

Los datos autorizados se presentan directamente desde el servidor, no se sintetizan con Gemini. No hay historial compartido ni caché de respuestas privadas. Respuestas POST tienen `Cache-Control: no-store, private`.

## Consumo

- 100 reservas externas por día global y 20 por usuario por defecto, ajustables con rangos validados; 5 por minuto por usuario.
- Ventanas de cuota UTC. Reserva en base de datos antes de llamar; los intentos fallidos consumen cuota de la aplicación. No hay reintentos automáticos. La prueba administrativa también consume cuota.
- Un modelo fijo, HTTPS a host fijo sin redirecciones; pregunta limitada, prompt máximo 12,000 bytes, salida 256 tokens y thinkingBudget 0.
- Al llegar al límite, faltar clave, fallar el proveedor o recibirse JSON inválido se usa el motor local. Los contadores dependen del subsistema de accesos existente; si no está disponible no se permite llamar a Gemini.
- Auditoría registra usuario/actor efectivo, fecha y hora, operación, resultado, HTTP y tokens informados por Google, pero no prompts, respuestas privadas ni claves.
- Los límites internos no cubren uso de la misma clave fuera de este asistente. Configurar además el límite mensual de proyecto en AI Studio. No es una garantía de factura exacta: Google advierte latencia aproximada de 10 minutos.

## Obtener la clave

1. Crear un proyecto dedicado y una API key en https://aistudio.google.com/apikey.
2. Configurar facturación del proyecto; no utilizar servicios gratuitos para datos personales/confidenciales. No compartir la clave por chat ni incluirla en JavaScript.
3. En AI Studio → Spend → Monthly spend cap → Edit spend cap establecer un importe inicial pequeño, por ejemplo USD 5 si la cuenta lo permite. Si se usa prepago, no habilitar recargas automáticas ilimitadas.
4. En Traza FI → Ajustes → Configurar Gemini, límites y privacidad, introducir la clave, probar con el mensaje sintético, confirmar servicios de pago, seleccionar Gemini y guardar.

Referencias oficiales consultadas: https://ai.google.dev/gemini-api/docs/api-key, https://ai.google.dev/gemini-api/docs/billing#project-spend-caps, https://ai.google.dev/gemini-api/docs/pricing, https://ai.google.dev/gemini-api/terms.

## Verificación

`tools/test_ia_access.php`: permisos entre alumnos, ámbito departamental, administrador sin privilegio global, revocación de invitación, confidencialidad, clasificación desconocida, entradas tipo inyección, fallback y cuotas. Fixtures en transacción revertida, solo BD QA `fi_ux_test_20260909`.

`tools/test_ia_ui.cjs`: administración, validación/CSRF, consulta de solicitudes/reuniones/trámite, cuenta pendiente con lectura permitida y escritura denegada, pantallas 360/390/768/1440. Guía emil-design-eng aplicada a controles nativos, etiquetas claras y respuesta inmediata, sin animaciones nuevas. Pruebas de navegador, no de dispositivos físicos.

Despliegue mediante `tools/ia-deploy-files.json`; respaldo previo de ocho archivos existentes en `database/production-backups/site-update-20260910-173122`. No se migra ni elimina información de negocio, no se instala un endpoint público temporal y no se despliega configuración local ni pruebas.

La conexión real a Gemini queda pendiente de una clave del usuario; no se realizó consumo de Google ni se comprobó facturación de una cuenta real.

Verificación en producción completada: Ajustes IA, Ajustes, grupos, inicio, consulta de solicitudes propias, reuniones y orientación de constancia; HTTP 200 y sin errores de PHP/JavaScript. Una sesión de administrador; ninguna configuración, grupo, mensaje o invitación de prueba creada. Controles de ancho comprobados en cuatro tamaños de pantalla.
