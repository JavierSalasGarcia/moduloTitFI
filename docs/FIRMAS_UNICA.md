# Regla: un solo lugar para firmar (bandeja única «Por firmar»)

**Todo lo que espera la firma de una persona —documentos, constancias, oficios, acuses, actas, trámites de cualquier módulo— se
concentra en un solo lugar: `/firmas`, pestaña «Por firmar» (módulo Firmas).** Quien firma no debe buscar en cada módulo ni
depender del departamento por el que llegó el documento.

## Quién y qué

- Aplica a todas las personas que firman: **coordinaciones, Subdirector Académico, Director**, jefaturas, titulares, profesores y
  alumnos (acuses). El menú «Firmas de documentos» está disponible para todos los roles, incluido `coordinador`.
- Lo que le llega a una persona se decide **por la persona designada como firmante** (`firmante_id`, correo del firmante o la
  autoridad vigente), nunca por el departamento, la dependencia o el módulo de origen. Una constancia dirigida a la
  Coordinación de Docencia de Mecánica y otra al Subdirector aparecen en la misma bandeja de quien debe firmarlas.
- La bandeja muestra lo pendiente de firmar. Las constancias ya firmadas se consultan en «Constancias firmadas» y lo que cada
  quien firmó **no** se mezcla con lo que recibió (ver `docs/CONSEJOS.md` y el módulo Constancias).

## Cómo lo hace un módulo (obligatorio para todo flujo de firma nuevo o modificado)

1. **No crees una bandeja ni una entrada de menú propia de «por firmar».** Reutiliza `/firmas`.
2. Declara en el `module.json` del módulo una función proveedora:

   ```json
   "por_firmar": "App\Modules\MiModulo\MiController::porFirmar"
   ```

3. Esa función `public static` devuelve, solo para la persona con sesión (`Auth::id()`), una lista de renglones:

   ```php
   ['tipo' => 'Constancia', 'texto' => 'Constancia CEA/CONST/004 · Ana Pérez (solicita …)',
    'enlace' => 'constancias/firmar/12', 'fecha' => '2026-11-20 10:00:00']
   ```

   `tipo` es la etiqueta corta (Constancia, Firma, Oficio…); `texto` dice qué se firma y quién lo pide; `enlace` es la ruta
   relativa donde se firma (la pantalla de firma sigue viviendo en el módulo); `fecha` es la de envío a firma (puede ser `null`).
4. `App\Core\PorFirmar::lista()` reúne los proveedores de todos los módulos y `FirmasController::index` los pinta en la pestaña
   «Por firmar». Un proveedor que lance excepción se registra en el log y no afecta a los demás.
5. Conserva además el proveedor `pendientes` del módulo (bandeja de Notificaciones y su insignia); lo habitual es que
   `por_firmar` sea el subconjunto de `pendientes` con `tipo` «Firma».
6. No dupliques la entrada en el menú lateral: la pantalla de firma se abre desde la bandeja única o desde el enlace del correo
   de aviso. Titulación y Posgrado ya no tienen entrada propia de firmas en el menú (sus pantallas de firma se abren desde la bandeja única).
7. Toda ruta de firma sigue comprobando que la persona sea el firmante designado (404 en otro caso) y deja registro en la
   auditoría.

## Proveedores registrados hoy

| Módulo | Función | Qué aporta |
|---|---|---|
| Constancias | `FirmaController::porFirmar` | Constancias en firma cuyo firmante designado es la persona, de cualquier departamento. |
| Titulación | `PanelController::porFirmar` | Firmas externas, y firma electrónica de la Subdirección. |
| Posgrado | `FirmaController::porFirmar` | Firmas de evaluación de grado y de la Coordinación. |
| Firmas | (la propia tabla de la pestaña) | Solicitudes de firma de documentos con ruta secuencial o paralela. |

## Para quien lea esto como IA

Antes de agregar «una bandeja de firmas», «un botón de mis firmas» o un menú «Por firmar» en cualquier módulo, **reutiliza esta
regla**: registra `por_firmar` y enlaza a tu pantalla de firma. Si el flujo es nuevo, agrégalo a la tabla anterior.
