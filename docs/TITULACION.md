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
