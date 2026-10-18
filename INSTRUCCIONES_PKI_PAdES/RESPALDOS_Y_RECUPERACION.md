# Respaldos y recuperación

## Material que debe respaldarse

| Contenido | Ubicación primaria | Respaldo mínimo | Observación |
|---|---|---|---|
| Llave de autoridad raíz | Medio fuera de línea | Dos copias cifradas, en ubicaciones físicas distintas | Nunca en el servidor ni en una nube ordinaria |
| Certificado raíz público | `storage/pki/public` | Repositorio documental y portal institucional | Publicar también su huella creada con el Algoritmo de Hash Seguro de 256 bits (SHA-256) |
| Llave y certificado intermedios | `storage/pki/private` y `storage/pki/public` | Copia cifrada fuera del servidor | Respaldar también la contraseña, por canal y custodia separados |
| Llave maestra y llave de auditoría | `storage/pki/secrets` | Copias cifradas bajo doble custodia | Sin ellas no pueden abrirse certificados individuales ni verificarse controles de auditoría |
| Certificados individuales cifrados | `storage/pki/users` | Respaldo cifrado diario | No exportar a los usuarios |
| Documentos firmados | `storage/firmas/firmados` | Respaldo incremental diario e inmutable periódico | Conservar todas las versiones, no solo la última |
| Base de datos | MariaDB o MySQL | Diario, cifrado y con restauración probada | Incluye series, huellas, operaciones, revocaciones y rutas |
| Lista de revocación | Ubicación pública aprobada | Conservar cada versión histórica | Necesaria para validación posterior |

## Regla de separación

No guarde en el mismo respaldo la llave maestra y los contenedores individuales sin una segunda capa de cifrado. Mantenga al menos una copia desconectada y una copia geográficamente separada. El acceso requiere dos personas autorizadas.

## Prueba trimestral de restauración

1. Restaure en una red aislada una copia de la base de datos y del almacenamiento.
2. Compruebe que los documentos existentes validan sin modificar sus bytes.
3. Compruebe que un certificado individual puede abrirse con la llave maestra restaurada.
4. No genere una firma de producción durante la prueba; use una PKI de ensayo diferente.
5. Registre tiempos, archivos, huellas, participantes y cualquier desviación.

## Pérdida irrecuperable

- Si se pierde una llave individual, revoque el certificado y emita otro; las firmas históricas permanecen asociadas al certificado anterior.
- Si se pierde la llave intermedia, cree una nueva intermedia mediante la autoridad raíz y revoque la anterior.
- Si se pierde la llave raíz o su contraseña, no existe recuperación técnica: debe establecerse una nueva PKI y redistribuir un nuevo certificado raíz.

