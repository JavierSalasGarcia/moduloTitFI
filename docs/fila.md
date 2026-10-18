# Fila en Traza FI

- Módulo con sesión de Traza FI: `/public/fila`.
- Dos pestañas: “Para que yo atienda” muestra las personas y asuntos pendientes únicamente de las oficinas asignadas al responsable; “Otros departamentos” muestra sus filas públicas y los turnos personales del usuario. Los responsables entran por defecto a atención; los demás, a otros departamentos. Los paneles no consultan ni exponen datos privados de otras oficinas.
- En “Para que yo atienda”, el control “No disponible” pausa la atención hasta el cierre del intervalo actual. El kiosco muestra únicamente ese estado neutral y el mismo control permite volver a “Disponible”.
- Kiosco público de pasillo: `/public/fila/publico/{clave}` (también `/pantalla`). QR grande, turno actual, espera y horas estimadas; refresca cada 5 segundos y rota cada 10 segundos si hay más de seis personas. Nunca publica nombres: solo folios.
- Formulario de entrada por QR: `/public/fila/publico/{clave}/turno`. No requiere cuenta o login. Para tomar turno pide únicamente el correo institucional, muestra el folio de inmediato y envía la copia por email. Las reuniones conservan el formulario completo y la confirmación mediante código. Los QR nuevos deben apuntar al formulario, no al kiosco.
- Para pantalla completa: pulsa “Pantalla completa” o F11; el navegador requiere una acción del usuario. No se pierde la pantalla completa al actualizar datos.
- API para laptop/reloj: `/public/fila/publico/api/{accion}`. Conserva `?d={clave}` y `Authorization: Bearer …`.
- Panel: `/public/fila/publico/admin`, exclusivo del administrador de Traza FI; no existe otro login.
- Configuración semanal: `/public/fila/horarios/sa`, accesible al administrador o a titulares asignados a `sa` en la tabla `fila_responsables` de la base exclusiva. El módulo muestra “Configurar mis horarios”. No otorga acceso a otras oficinas ni al panel global.
- Los turnos tomados con un correo aparecen en el módulo de la cuenta cuyo correo coincide, incluso si se solicitaron sin sesión. Las cuentas pendientes de verificar no pueden consultar turnos por correo.
- La confirmación por código de las reuniones no crea ninguna cuenta de Traza FI.
- `fila/credenciales.txt` es privado: no agregarlo al repositorio ni servirlo por HTTP. La conexión PDO de Fila es independiente de `App\Core\Database`.
- Archivos de Fila: `storage/fila/subidas`, fuera del webroot. Kiosco, reloj y aplicación Python siguen ejecutándose en sus dispositivos: no son aplicaciones PHP alojables en el sitio.

## Publicación

`./tools/deploy_fila.ps1 -DryRun` lista los archivos. Sin ese parámetro respalda los archivos anteriores, publica y verifica su contenido, inicializa únicamente el esquema de la BD exclusiva y activa el módulo al final. Retira el instalador temporal en `finally`. No crea oficinas o titulares automáticamente; se configuran en el panel con sus horarios y QR.

El correo de confirmación y los avisos usan el proveedor configurado en Traza FI. Las minutas adjuntas conservan el envío MIME por `mail()` del hosting con el remitente de Traza FI; su entrega debe validarse con un destinatario autorizado antes de operar esa función.

Pruebas: `C:/xampp/php/php.exe fila/publico/pruebas/correr.php`. En Windows, dos expectativas originales sobre transliteración con iconv pueden variar respecto a Linux.
