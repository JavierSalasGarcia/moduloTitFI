# Puesta en operación de la firma institucional

Este directorio reúne las tareas que requieren intervención humana, custodia física o autorización institucional. La solución utiliza **PDF Advanced Electronic Signatures (PAdES)**, estándar que incorpora firmas digitales verificables al archivo en formato de documento portátil (PDF), y una **infraestructura de llave pública (PKI)** interna, conjunto de autoridades y certificados que vincula identidades con llaves criptográficas.

El código se entrega deshabilitado de manera intencional. No cambie `firma_pki.enabled` a `true` hasta completar y documentar todos los pasos de `TAREAS_MANUALES.md`.

Archivos de este directorio:

- `TAREAS_MANUALES.md`: ceremonia, instalación y activación.
- `RESPALDOS_Y_RECUPERACION.md`: qué debe respaldarse, dónde y cómo probar la restauración.
- `INCIDENTES_Y_REVOCACION.md`: respuesta ante pérdida, baja o sospecha de compromiso.
- `VERIFICACION_ADOBE_FOXIT.md`: distribución segura del certificado público raíz.

El sistema nunca debe publicar ni permitir la descarga de una llave privada. El único archivo descargable desde la aplicación es el certificado **público** de la autoridad raíz.

