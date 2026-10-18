# Incidentes y revocación

## Revocar inmediatamente cuando

- una persona cause baja o pierda la atribución para firmar;
- exista sospecha de acceso a su segundo factor;
- se copie, exponga o pierda un archivo con llave privada;
- cambie información relevante del certificado;
- se detecte una firma no reconocida por el titular.

## Procedimiento

1. Bloquee temporalmente la firma del usuario.
2. Cambie `firma_certificados.estado` a `revocado`, registre fecha y motivo, y preserve el archivo cifrado como evidencia con acceso restringido.
3. Incorpore la serie a la lista de certificados revocados (CRL), archivo firmado por la autoridad intermedia.
4. Publique la nueva CRL en la ubicación aprobada y compruebe su acceso desde los validadores.
5. Revise la bitácora, las huellas de entrada y salida y los accesos del servidor.
6. Notifique al titular y al responsable institucional; documente alcance, decisiones y recuperación.
7. Emita un certificado nuevo solo después de volver a verificar la identidad.

Ante sospecha sobre la autoridad intermedia, deshabilite `firma_pki.enabled`, preserve evidencias y use la autoridad raíz fuera de línea para revocar y reemplazar la intermedia. Ante sospecha sobre la raíz, deje de emitir certificados y active el procedimiento institucional de sustitución completa de confianza.

