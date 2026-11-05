# Acceso de egresados sin correo institucional (propuesta)

Problema: al egresar, la UAEMéx cancela el correo `@alumno.uaemex.mx`, pero el trámite de titulación sigue. Hoy `/registro` solo admite `uaemex.mx` y subdominios (`app/Modules/Auth/RegistroController.php`, `Auth::esDominioUaemex`), así que esa persona no puede entrar.

## Principio

Un correo personal **no prueba** que la persona sea quien dice. Por eso el correo externo solo sirve para *entrar a una cuenta que antes validó el departamento*. El control no está en el registro, sino en la **validación de identidad** y en lo que la cuenta puede ver.

## Ruta propuesta

1. **Solicitud de acceso** (público, `/registro/egresado`). Aviso al inicio: «Si ya no tienes correo institucional, solicita aquí tu acceso; solo podrás usar el módulo de Titulación». Pide nombre completo, licenciatura, número de cuenta, correo personal y teléfono. No crea cuenta con contraseña: crea una **solicitud**.
2. **Verificación del correo**: código de 6 dígitos o enlace de un solo uso (ya existe el mecanismo de `cuenta/verificar`). Sin esto la solicitud no llega al personal.
3. **Prevalidación automática** contra lo que el departamento ya tiene: cuenta + licenciatura en `tit_historico`, `tit_solicitudes_historicas`, `tit_protocolos_recibidos` y `tit_articulo5` (migración 040), y el **correo que la persona dio al departamento** (esas tablas lo traen). Resultado: *coincide* (cuenta y correo), *coincide a medias* (cuenta sí, correo distinto) o *no aparece*.
4. **Envío de documentos** (solo si no coincide del todo o si se quiere doble control): identificación oficial vigente y comprobante de que fue alumno de la Facultad (historial académico, constancia de terminación de estudios o carta de pasante). Se guardan en `storage/` privado, con hash, y se borran al resolver la solicitud salvo el sello de que se validó.
5. **Validación por el personal de Titulación** (cola «Accesos por validar»): compara nombre, cuenta y documento; aprueba, devuelve o rechaza con motivo. Queda en la bitácora (quién, cuándo, con qué evidencia). Las coincidencias completas pueden aprobarse con un clic.
6. **Activación**: al aprobar, se crea la cuenta con rol `egresado`, se envía enlace para fijar contraseña y se exige segundo factor (el módulo de acceso seguro ya lo soporta). Para no depender de contraseñas débiles, solo se permite el ingreso con el correo validado.
7. **Alcance limitado**: el rol `egresado` solo ve *Titulación → Licenciatura* y sus propios trámites (no directorio, foro, calendario ni el resto de módulos). Vence a los 24 meses sin actividad, al concluir el trámite (o cuando el departamento lo marque) y se desactiva con un clic.

## Qué evita el registro indiscriminado

- Nada se activa sin aprobación humana; la solicitud sin correo verificado ni siquiera llega a la cola.
- Límite por IP y por correo (ya hay limitadores en el login), campo trampa y pausa entre intentos; un máximo de solicitudes abiertas por cuenta.
- La cuenta de egresado no puede iniciar un trámite de otra persona: queda ligada a **una** cuenta de alumno y licenciatura; el trámite nuevo se abre solo con esa identidad.
- Si la persona aún conserva su correo institucional, se le dirige al registro normal (se detecta por el dominio).
- Las cuentas externas se ven distintas en el panel de personal (etiqueta «Egresado · validado por X»).

## Qué hace falta construir

| Pieza | Esfuerzo |
|---|---|
| Rol `egresado` y bandera `identidad_validada` en `usuarios`, con alcance de menú limitado | pequeño |
| Tabla `solicitudes_acceso_egresado` (datos, estado, documentos, evidencia, resolutor) | pequeño |
| Formulario público + verificación de correo + límites | mediano |
| Prevalidación contra las tablas de Titulación (la parte útil de la migración 040) | pequeño |
| Cola de validación para el personal con vista de documentos | mediano |
| Caducidad y desactivación; textos del aviso de privacidad | pequeño |

## Implementación (migración 041)

Se aceptaron las recomendaciones de este documento y quedó construido:

- **Cuenta**: no hay rol nuevo. Es una cuenta de rol `alumno` con `usuarios.egresado_externo = 1`, programa de su licenciatura, correo personal ya verificado y `egresado_vence` (24 meses; cada mes de uso lo renueva y, vencido, la cuenta se desactiva sola).
- **Alcance**: `Router` y `ModuleManager` limitan estas cuentas a los módulos `titulacion`, `auth` y `seguridad` (la raíz las lleva a «Mis pendientes»; el resto responde 403 y no aparece en el menú).
- **Solicitud pública** `/registro/egresado` → verificación con código (10 min, 5 intentos) → prevalidación (`AccesoEgresados::prevalidar`: cuenta, nombre, licenciatura y correo contra `tit_historico`, solicitudes, protocolos y artículo 5).
  - *Coincide* (las cuatro cosas) → pasa directo a «Por validar» y se aprueba con un clic.
  - *A medias* o *no aparece* → pide identificación oficial y comprobante de estudios (PDF/JPG/PNG, 5 MB) en `storage/egresados/` (fuera de git y de `public/`).
- **Cola del personal** «Constancias → Accesos de egresados» (`TitulacionAccess::esPersonal`): aprobar, devolver con motivo, rechazar, y desactivar cuentas. Al resolver se borran los archivos y queda el sello (tipo, huella SHA-256, fechas) en `egresado_documentos`.
- **Activación**: al aprobar se crea la cuenta y se envía un enlace de un solo uso (7 días) para fijar la contraseña. Iniciar sesión pide siempre el segundo paso (código al correo, huella o app autenticadora).
- **Antiabuso**: 5 solicitudes por IP y 3 por correo cada hora, campo trampa, pausa mínima de 4 s, una sola solicitud abierta por cuenta o correo, y todo token se guarda como hash. Un correo institucional se redirige al registro normal.
- Tablas: `egresado_solicitudes`, `egresado_documentos` y seis columnas `egresado_*` en `usuarios`.

Pendiente a propósito: confirmar con Control Escolar si pueden dar fe de «fue alumno» por número de cuenta (sustituiría la copia del comprobante) y, si se desea, avisar al personal por correo cuando entre una solicitud nueva.

## Decisiones tomadas

1. Validan el personal operativo y la titular de Titulación (cualquiera con acceso al módulo; queda en la bitácora quién).
2. Documentos solo cuando la prevalidación no coincide del todo.
3. Se acepta cualquier correo personal: el control es la validación humana.
4. Los documentos se borran al resolver; solo queda el sello.
5. Control Escolar: por confirmar.
