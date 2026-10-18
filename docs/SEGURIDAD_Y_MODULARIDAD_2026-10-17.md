# Revisión de seguridad y modularidad — 17 de octubre de 2026

## Resultado

Traza FI ya puede recibir módulos independientes mediante una carpeta con
`module.json`. El núcleo descubre el módulo, valida su contrato, registra menú y rutas,
detecta colisiones y aísla un módulo inválido sin derribar toda la plataforma. Los 23
módulos heredados continúan funcionando con `module.php` durante la transición.

La especificación para desarrollar e instalar módulos está en `GUIA_MODULOS.md`. Las
fuentes de verdad compartidas son `App\Core\Roles` para roles y la tabla `usuarios`
para identidad. Un módulo funcional no debe copiar contraseñas, secretos MFA, correo,
rol o departamento en tablas propias.

## Controles aplicados

| Área | Cambio |
| --- | --- |
| Secretos | `config.php` ya no contiene credenciales; cada entorno usa variables o un `config.local.php` no versionado y bloqueado por HTTP. |
| Sesión | Cookies `Secure` bajo HTTPS, `HttpOnly`, `SameSite=Lax`, modo estricto, sólo cookies y cierre por inactividad autenticada de 2 horas. |
| Autorización | Rutas privadas por defecto; las excepciones públicas se declaran una por una. Roles centralizados y validados. |
| CSRF | Validación para POST, PUT, PATCH y DELETE. El cierre de sesión ahora es POST con token CSRF. |
| Login | Respuesta uniforme para cuentas inexistentes o contraseña incorrecta, reduciendo enumeración de cuentas. |
| OTP y recuperación | Códigos almacenados como hash, limitación de intentos, respuestas uniformes y autorización de restablecimiento con vigencia de 10 minutos. |
| Enrutamiento | Partes literales escapadas antes de construir regex; se rechazan rutas con byte nulo o barra invertida. |
| Navegador | CSP, HSTS en HTTPS, protección contra MIME sniffing y framing, política de referente y permisos. |
| Archivos | Validación de extensión, MIME, tamaño y origen real de la carga; rutas de descarga contenidas en `storage/`; PHP bloqueado en `public/assets`. |
| Módulos | Manifiesto JSON sin ejecución durante el descubrimiento, validación estructural, métodos permitidos, control de roles y detección de rutas duplicadas. |
| Respaldos | Los respaldos de publicación y los históricos salieron del proyecto/webroot; retención automática de los 30 conjuntos más recientes. |
| Entorno de pruebas | El clon ya no copia el token de correo de producción; sólo envía si recibe `TRAZAFI_STAGE_MAIL_TOKEN` y `TRAZAFI_STAGE_MAIL_SENDER` exclusivos. |

## Pendientes que requieren seguimiento

1. **Rotar inmediatamente** la contraseña de la base de producción y el token del
   servicio de correo. Aunque ya no están en la configuración pública/versionable,
   estuvieron almacenados previamente y deben considerarse comprometidos.
2. Restringir permisos, cifrar o aplicar una retención definida a los ZIP históricos y
   al respaldo de la migración de secretos en `D:\Proyectos\trazafi-backups`.
3. Migrar gradualmente los 23 `module.php` heredados a `module.json`. Un manifiesto PHP
   se ejecuta al descubrirlo y sólo debe aceptarse de una fuente plenamente confiable.
4. Eliminar progresivamente scripts y estilos en línea para retirar `unsafe-inline` de
   la CSP. La política actual mejora el aislamiento, pero conserva esa excepción por
   compatibilidad con la interfaz existente.
5. Auditar, módulo por módulo, reglas sobre objetos concretos (propiedad del registro,
   departamento, programa y estado). El rol de ruta no sustituye esos controles.
6. Unificar las rutas de carga antiguas de Fila y Reuniones con el helper endurecido y
   agregar análisis antimalware si se admiten documentos provenientes de terceros.
7. Añadir y verificar el contador de uso de credenciales WebAuthn para detectar
   autenticadores clonados cuando el proveedor lo soporte.

## Verificación repetible

```powershell
C:\xampp\php\php.exe tools\validar_modulos.php
Get-ChildItem app -Recurse -Filter *.php | ForEach-Object {
    C:\xampp\php\php.exe -l $_.FullName
}
```

Antes de instalar un módulo: revisar su código, ejecutar el validador, aplicar su
migración explícitamente, probar con cada rol, respaldar sólo los archivos afectados y
conservar un mecanismo de reversión. Copiar una carpeta registra el módulo, pero no
convierte código de terceros en código confiable.
