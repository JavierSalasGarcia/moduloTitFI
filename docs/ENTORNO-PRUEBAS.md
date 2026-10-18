# Entorno de pruebas

## Ubicaciones

- Clon local: `C:\trazafi`
- Staging: `https://fingenieria.mx/pruebas/`
- Credenciales de la base de prueba: `D:\sitios\trazafi\cred2.txt`

Producción y staging comparten código, pero no base de datos, configuración privada
ni cookies de sesión. El correo está activo en staging y usa el nombre de remitente
`[PRUEBAS] Traza FI`.

## Publicar nuevamente el clon

Desde la raíz del proyecto:

```powershell
.\tools\deploy_test_clone.ps1
```

El script copia los archivos ejecutables desde `C:\trazafi`, genera en el servidor
un `app/config.local.php` exclusivo de pruebas y no sube respaldos, documentos,
llaves ni credenciales de producción.

## Rutas y promoción

Las rutas relativas se calculan a partir del front controller. Las URL absolutas de
correos dependen únicamente de `app.url_publica`:

- Producción: `https://fingenieria.mx`
- Staging: `https://fingenieria.mx/pruebas`
- Clon local: `http://127.0.0.1:8090`

Para promover código probado se copian únicamente los cambios de código desde el
clon al proyecto principal. Nunca deben copiarse a producción:

- `app/config.local.php`
- el `.htaccess` generado dentro de `/pruebas`
- `storage/`
- credenciales o respaldos de la base de prueba

Producción conserva su `app/config.php`, su `.htaccess` raíz y sus datos. Así no es
necesario buscar ni reemplazar `/pruebas` dentro de controladores o vistas.

El módulo Fila usa otra base de datos por diseño. Mientras no exista una base Fila
de pruebas independiente, el staging muestra un 503 controlado en esa ruta y nunca
se conecta a los datos de Fila de producción.
