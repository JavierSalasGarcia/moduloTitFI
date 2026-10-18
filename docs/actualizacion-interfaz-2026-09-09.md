# Actualización de interfaz — 9 de septiembre de 2026

## Cambios realizados

- Navegación agrupada por tareas, logo enlazado a Inicio, ubicación activa única, ruta de navegación y menú móvil con cierre exterior, Escape y control del foco.
- Portada conversacional minimalista, ejemplos opcionales, notificaciones explicadas y selección alternativa del área destinataria.
- Estilos compartidos, botones táctiles, tablas convertidas en tarjetas móviles, búsqueda, filtros y paginación. Los estilos y scripts usan versión por fecha de modificación para actualizar la caché.
- Formularios de reuniones y firmas por pasos, revisión antes de enviar, recuperación de borradores en la pestaña y selección de destinatarios accesible.
- Archivos con lista, retirada individual, arrastre, límite de tamaño y progreso de envío en los formularios compatibles. Los archivos y las contraseñas no se guardan en borradores.
- Reuniones pendientes de disponibilidad con datos reales; corrección de validaciones CSRF faltantes en convocatoria, finalización y respuesta de horarios.
- Calendario con agenda móvil y vista mensual, diálogos manejables con teclado y acceso directo a eventos.
- Solicitudes con estados comprensibles y siguiente paso; matriz de prioridades opcional.
- Comisiones con próximo compromiso y fecha, registrados en bitácora por el gestor autorizado.
- Avisos con vigencia opcional y archivo de vencidos. Los avisos existentes sin fecha no reciben una caducidad inventada.
- Encuestas con editor de opciones, filtros por participación y copia de comprobante; propuestas, grupos y altas con formularios plegables.
- Directorio y funciones con búsqueda por necesidad/persona, contactos y acceso para enviar solicitud. Programas educativos con nombres completos.
- Recursos con favoritos y recientes en la pestaña; Teams con navegación mediante un toque y detalles separados.
- Ajustes agrupados, campos secretos sin mostrar su valor y prueba explícita de NVIDIA. Sin clave se conserva el motor local; no se garantiza una cuota gratuita del proveedor.
- Recuperación de acceso en tres etapas y explicaciones más claras de los métodos de seguridad.

Las guías de diseño consultadas orientaron la consistencia visual, el foco visible, los objetivos táctiles y el respeto a la preferencia de movimiento reducido.

## Verificación local

Se utilizó una base aislada `fi_ux_test_20260909`, sin envío de correo. No se importó nuevamente el respaldo en producción ni se modificaron sus credenciales.

- Sintaxis PHP correcta en los archivos del despliegue y comprobación de JavaScript.
- 28 rutas a 360, 390, 768, 1024 y 1440 píxeles: carga correcta, sin desbordamiento horizontal del documento ni errores JavaScript detectados.
- Consulta en lenguaje natural → envío de solicitud → seguimiento.
- Borrador de reunión recuperado con horarios e invitados → convocatoria → respuesta del profesor.
- Carga de PDF con progreso → solicitud de firma → registro de firma.
- Creación de encuesta, archivo de aviso vencido y actualización de compromiso de comisión.
- Recorridos con alumno, profesor, personal y administrador; acceso de profesor a prueba NVIDIA rechazado.
- Recuperación de contraseña en tres fases con respuestas simuladas, sin cambiar una contraseña ni enviar correo.
- NVIDIA sin clave muestra la instrucción correspondiente; no se probó una clave real.

Las dimensiones móviles fueron simuladas en navegador. Queda pendiente la validación de comodidad en dispositivos físicos Android/iPhone y con usuarios finales; esta revisión no es una certificación de accesibilidad.

## Despliegue

Manifiesto: `tools/ux-deploy-files.json` (49 archivos de aplicación y recursos; excluye configuración, credenciales y herramientas de prueba).

Respaldo previo de 46 archivos existentes: `database/production-backups/site-update-20260909-163216`. Los tres recursos nuevos no tenían versión anterior. Cada archivo publicado se descarga y compara mediante SHA-256.

Publicación completada: 49 archivos subidos y verificados por SHA-256. La prueba autenticada de producción pasó en las 28 rutas y cinco anchos, sin errores JavaScript detectados, errores PHP visibles ni desbordamiento horizontal del documento. Se comprobó el menú con teclado y se cerró la sesión de prueba. No se crearon solicitudes, reuniones, documentos, votos ni publicaciones en producción.

La raíz `https://fingenieria.mx/` dirige al visitante sin sesión a `/public/login` mediante el acceso `/public/`. Se verificó que los estilos de aplicación, los nuevos estilos y el nuevo script contienen su parámetro de versión en el HTML publicado.
