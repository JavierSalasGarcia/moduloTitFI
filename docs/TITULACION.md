# Módulo Titulación

Trámite de evaluación profesional en línea. Entradas de menú: **Licenciatura**, **Posgrado** (reservada) y **Validar trámites** (personal).

## Flujo

El pasante elige entre *sin trabajo escrito* (Aprovechamiento, EGEL, Créditos en estudios avanzados) y *con trabajo escrito* (Artículo, Ensayo, Memoria, Aplicación, Autoempleo, Residencia, Tesina, Tesis). El asistente recorre etapas definidas en `Catalogo.php`: captura de datos (una sola vez), formatos llenados para firmar, subida de PDF por documento y pasos propios del departamento (autorizaciones, oficios, fechas y resultado). El personal valida cada PDF (validar o devolver con observaciones) y registra los hitos; el sistema avanza solo cuando la etapa queda completa.

## Instalación

1. Aplicar `database/migrations/017_titulacion.sql` (crea `tit_*` y el ajuste `titulacion_subdirector`).
2. Copiar `app/Modules/Titulacion/`. Los archivos subidos se guardan en `storage/titulacion/<id>/`.
3. Personal autorizado: cuentas con rol titular, operativo o coordinador en las dependencias de `titulacion.departamentos` (`app/config.php`), los correos de `titulacion.gestores` y los administradores.
4. El nombre del Subdirector Académico se captura una vez en **Ajustes** (`titulacion_subdirector`) y se imprime en todos los formatos.

## Cuando cambien los formatos oficiales

Reemplazar los archivos en `docs/licenciatura/` y ejecutar `python3 tools/titulacion_preparar.py` (requiere `pdfplumber`, `pypdf` y `pypdfium2`). Regenera `plantillas/` y `formatos/pdf_specs.json`. Los requisitos y documentos por modalidad se editan en `Catalogo.php`.

## Pruebas

`php tools/test_titulacion_formatos.php` genera todos los PDF y Word con datos de ejemplo en un directorio temporal.

## Configuración de formatos y requisitos (solo administración)

Menú **Titulación → Configurar formatos** (`titulacion/configuracion`). Requiere aplicar también `database/migrations/018_titulacion_configuracion.sql`.

- **Formatos**: se puede reemplazar la plantilla de cualquier formato, abrir el **visor de coordenadas** (arrastrar y redimensionar cada campo sobre la plantilla, colocar casillas, ajustar tamaño y alineación, vista previa con datos de ejemplo), retirar un formato para que deje de generarse o pedirse, restablecerlo al original y añadir formatos nuevos a partir de un PDF. La hoja membretada del protocolo también se reemplaza desde ahí.
- **Requisitos documentales**: por documento y por opción de titulación (o para todas) se decide si se pide, si se entrega en físico (se marca con asterisco) y si el original se coteja. Se pueden añadir documentos o entregas presenciales nuevos.
- **Cotejo**: el personal compara el original con el PDF y lo registra con su firma electrónica: *sello de cotejo* (quién, cuándo y huella SHA-256, con sello visible en el PDF) o *firma PAdES* con su certificado y segundo factor (requiere activar la PKI del módulo Firmas). La etapa no se cierra hasta que se cotejan los documentos marcados.
- Los cambios aplican de inmediato a todos los trámites. Quedan en la bitácora de la misma pantalla.
- Las plantillas reemplazadas se guardan en `storage/titulacion_formatos/`. Los PDF deben ser legibles por FPDI (PDF 1.4 sin xref comprimido); el sistema intenta convertirlos con Ghostscript si está disponible y, si no, indica cómo guardarlos.
- Si los PDF oficiales se vuelven a procesar con `tools/titulacion_preparar.py`, las coordenadas editadas en el visor no se pierden: prevalecen sobre las del archivo hasta que se use «Restablecer al original».
