# Política de vinculación de cuentas con datos previos

## El problema

La plataforma guarda registros de personas que **aún no tienen cuenta**: constancias de titulación (asesores,
revisores, sinodales), firmas y acuses por enlace, miembros de comités, respuestas de formularios y el directorio de
profesores. Esas personas usan el sitio sin registrarse, y cuando por fin crean su cuenta sus datos deben aparecerle.

## Cómo usa el sitio alguien sin cuenta

| Qué hace | Cómo entra | Qué puede ver |
|---|---|---|
| Firmar un documento de titulación, revisar un protocolo, dar acuse como sinodal | Enlace con token aleatorio que llega a su correo (`/titulacion/firma/t/…`, `/revision/t/…`, `/jurado/t/…`, `/sinodal/t/…`); el token caduca | Solo ese documento y ese trámite |
| Verificar un documento | `/verificar/{codigo}` (público) | Solo la validez del documento |
| Responder un formulario privado | Enlace del formulario | Solo su respuesta |

Un enlace con token prueba que la persona **recibió ese correo**, nada más: no crea cuenta, no da sesión y no permite
ver otros registros. Las constancias y el resto de sus datos solo se consultan con cuenta.

## Reglas

1. **La persona es la cuenta** (`usuarios.id`). El correo es un atributo que puede cambiar (`App\Core\Identidad`).
2. **Solo vincula un correo verificado.** Un registro pasa a una cuenta cuando su correo es uno que la cuenta ya
   verificó (el vigente o uno anterior), o cuando pertenece a la ficha de profesor enlazada a ese correo.
3. **Nunca se vincula por nombre.** Dos personas pueden llamarse igual y un nombre se puede escribir de muchas formas.
   Un registro que solo tiene nombre queda en revisión humana.
4. **Nada se vincula antes de verificar el correo.** Registrarse con un correo no da acceso a lo que ese correo tenga.
5. **Un solo punto**: `Identidad::vincularDatos($usuarioId)`. Es repetible y nunca pisa un vínculo existente.
6. **Queda en la bitácora** (`vinculacion_datos`, con el número de registros por módulo).

## Cuándo se ejecuta

- Al verificar el correo tras el registro (`RegistroController`).
- Al confirmar un cambio de correo en el perfil (`PerfilController`).
- Cuando RR. HH. enlaza una cuenta con una ficha de profesor (`CargaRrhh::enlazar`, que ya pasaba las constancias).
- En bloque, para las cuentas que ya existen: `php tools/vincular_cuentas.php` (informe) y `--aplicar`.

## Qué hacer con cada caso

| Caso | Qué pasa |
|---|---|
| El registro tiene un correo y esa persona se registra con él | Se vincula solo al verificar el correo. |
| El registro tiene un correo distinto al de la cuenta (por ejemplo, el correo anterior al cambio de dominio) | La persona agrega y verifica ese correo en su perfil; al confirmarlo se vincula. |
| El registro apunta a una ficha de profesor sin cuenta | RR. HH. agrega el correo a la ficha o enlaza la cuenta en «Directorio de profesores». |
| El registro solo tiene nombre | Titulación identifica a la persona y corrige la constancia (correo o ficha). Nunca automático. |
| Egresado sin correo institucional | Ruta propia con validación del departamento: `docs/ACCESO_EGRESADOS.md`. |

`php tools/vincular_cuentas.php` cuenta cuántos registros hay en cada caso; con `--detalle` lista los nombres sin correo
ni ficha para que Titulación los resuelva.

## Agregar un módulo

Un módulo que guarde registros de personas sin cuenta debe:

1. Guardar el **correo** (normalizado, en minúsculas) y una columna `usuario_id` que acepte `NULL`.
2. Declarar en su manifiesto `vinculacion: "App\Modules\X\Clase::metodo"`, con firma
   `fn(int $usuarioId, string[] $correos, ?int $profesorId): int` que actualice solo filas con `usuario_id IS NULL` y
   devuelva cuántas vinculó. Ejemplos: `Titulacion\Services\Constancias::vincular`, `Formularios\Formularios::vincular`.
3. Al leer, usar `Identidad::condicion()` para que la persona vea lo suyo por cuenta, por ficha o por cualquiera de sus correos.

## Cambio aplicado junto con esta política

El autorregistro ligaba las respuestas de formularios al crear la cuenta, **antes** de verificar el correo. Se quitó:
ahora ocurre al verificarlo, por el punto único.

## Pendiente

- No hay pantalla para resolver los registros que solo tienen nombre; hoy se corrigen desde las pantallas de constancias.
- `tit_sinodales`, `tit_firmas_externas` y `tit_comision_miembros` se leen por correo y no guardan `usuario_id`; con la
  regla 2 la persona ya los ve al registrarse con ese correo, pero no quedan sellados con su cuenta.
