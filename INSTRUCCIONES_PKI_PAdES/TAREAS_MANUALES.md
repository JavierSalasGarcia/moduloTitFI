# Tareas manuales antes de activar producción

## 1. Aprobaciones que no puede realizar el software

1. Obtener autorización escrita de los Consejos y dictamen de la Dirección de Tecnologías de la Información y Comunicaciones (DTIC), dependencia universitaria responsable de la infraestructura tecnológica.
2. Designar por escrito al menos dos custodios de la autoridad certificadora raíz y un responsable de revocación.
3. Aprobar una política de certificados: identificación del titular, vigencia, cambio de adscripción, baja, revocación, retención y auditoría.
4. Definir si las firmas tendrán efectos internos y qué documentos permanecen también en papel durante el piloto.

## 2. Preparar dos ambientes separados

- **Equipo fuera de línea:** sin red y dedicado a la autoridad raíz. No puede ser el servidor web ni una computadora de uso diario.
- **Servidor de firma:** cuenta del sistema operativo exclusiva, sin acceso interactivo ordinario y con el directorio `storage/pki` fuera de `public`.

Instale Python 3.12 o posterior en el servidor y cree un entorno virtual:

```bash
python3 -m venv /opt/traza-fi-pades
/opt/traza-fi-pades/bin/pip install --requirement services/pades/requirements.txt
```

En Windows para un piloto local, use `python -m venv C:\\TrazaFI\\pades-venv` y configure la ruta de `python.exe` en `app/config.local.php`.

## 3. Ceremonia de la autoridad raíz

En el equipo fuera de línea, con dos custodios presentes:

```bash
python pki_ceremony.py create-root MEDIO_SEGURO/traza-fi-root
```

La contraseña debe tener al menos 16 caracteres, quedar dividida o sellada bajo doble custodia y no almacenarse en el servidor. Registre fecha, personas presentes, equipo, versión del programa y huella del certificado. Copie al servidor únicamente:

`public/traza-fi-root-ca.crt.pem`

La llave `private/traza-fi-root-ca.key.pem` y su contraseña deben permanecer fuera de línea.

## 4. Crear y autorizar la autoridad intermedia

En el servidor de firma:

```bash
python services/pades/pki_ceremony.py create-intermediate-csr storage/pki
python services/pades/pki_ceremony.py create-service-secrets storage/pki/secrets
```

Copie solo la solicitud `traza-fi-intermediate-ca.csr.pem` al equipo fuera de línea. Allí ejecútela con la raíz:

```bash
python pki_ceremony.py sign-intermediate \
  MEDIO_SEGURO/traza-fi-root/private/traza-fi-root-ca.key.pem \
  MEDIO_SEGURO/traza-fi-root/public/traza-fi-root-ca.crt.pem \
  traza-fi-intermediate-ca.csr.pem \
  traza-fi-intermediate-ca.crt.pem
```

Devuelva al servidor únicamente el certificado intermedio y colóquelo en:

`storage/pki/public/traza-fi-intermediate-ca.crt.pem`

## 5. Permisos del servidor

La cuenta del servicio criptográfico debe leer `storage/pki/private`, `storage/pki/secrets` y `storage/pki/users`. La cuenta del servidor web no debe poder leer las llaves directamente; para producción se debe ejecutar `pades_service.py` detrás de un servicio local autenticado con permisos propios. El puente por línea de comandos incluido es adecuado para el piloto, pero comparte los permisos efectivos del proceso web.

Permisos orientativos en Linux:

```bash
chmod 700 storage/pki/private storage/pki/secrets storage/pki/users
chmod 600 storage/pki/private/* storage/pki/secrets/* storage/pki/users/*
chmod 644 storage/pki/public/*
```

## 6. Base de datos y configuración

1. Aplique `database/migrations/009_firma_pades_pki.sql` sobre un respaldo verificable.
2. Copie a `app/config.local.php` la sección `firma_pki`, cambie `python` a la ruta del entorno virtual y mantenga `enabled => false`.
3. Ejecute la comprobación del servicio desde la misma cuenta que usa la aplicación.
4. Realice una firma de prueba, valídela en Adobe Acrobat y Foxit y conserve el acta de prueba.
5. Solo entonces cambie `enabled => true`.

## 7. Segundo factor y emisión individual

Cada usuario debe activar una **contraseña de un solo uso basada en tiempo (TOTP)**, código temporal de seis dígitos. El primer intento de firma emite su certificado individual dentro del servicio; el archivo personal con llave privada permanece cifrado en `storage/pki/users` y nunca se descarga.

Antes del despliegue general, compruebe la identidad y adscripción de cada titular. La existencia de una cuenta de correo no sustituye ese procedimiento.

## 8. Pendientes antes de declarar firma avanzada

- Publicar una lista de certificados revocados (CRL), archivo firmado que enumera certificados no confiables.
- Definir un servicio de sellado de tiempo para evolucionar de PAdES Baseline B a PAdES Baseline con sello de tiempo.
- Someter pruebas de penetración y revisión independiente del código y de los permisos.
- Documentar continuidad, destrucción segura y recuperación ante desastre.

