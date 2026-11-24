# API de Oficios de la Subdirección (redactar desde VS Code)

Permite que la asistente de la Subdirección Académica cree oficios desde una sesión de VS Code (o `curl`) con un token. Código: `app/Modules/Oficios/Services/ApiOficios.php`, atendido en `App::run` antes del router (sin sesión ni CSRF, solo token). **La API nunca firma**: el oficio llega al titular, que lo firma con su e.firma en `/firmas` → «Por firmar».

## Flujo

1. `POST /api/oficios/subdireccion` crea el **borrador** (sin número).
2. Con `enviar=1` además lo manda a firma: se reserva el siguiente número de la serie única `ACAD/FI/{n}/{AAAA}` y aparece en «Por firmar» del titular. Sin `enviar`, se revisa y se envía desde `/oficios/subdireccion`.
3. El titular firma, o lo devuelve con observaciones (vuelve a borrador).

## Token y permisos

- Formato `ofi_` + 48 hex; en la base solo su SHA-256 (`oficios_api_tokens`, migración 072). Se muestra una vez; vigencia 365 días; `ApiOficios::revocarToken(id)` lo revoca.
- Crear: `python tools/subir/subir.py php -e "return App\Modules\Oficios\Services\ApiOficios::crearToken(<usuario_id>, 'VS Code');"`.
- Solo sirve si la cuenta está activa y puede redactar: es el titular, está en `tit_asistentes` (activo) o tiene la concesión `consejos.gestor`. Alta de asistente: `INSERT INTO tit_asistentes (email, nombre, activo) VALUES (...)`.
- El token va en una variable de entorno (`OFICIOS_TOKEN`), nunca en el repositorio.

## Crear: `POST /api/oficios/subdireccion` (multipart/form-data)

| Campo | Obligatorio | Nota |
|---|---|---|
| `asunto` | sí | ≤ 250 caracteres |
| `destinatarios` | sí | JSON: lista de objetos `{"nombre","cargo","dependencia"}` (se crean o reutilizan en el catálogo) o de ids de `GET /oficios/subdireccion/destinatarios` |
| `cuerpo_html` o `cuerpo` | sí | HTML limpio (párrafos, negritas, cursivas, tablas) o texto plano (párrafos separados por línea en blanco) |
| `enviar` | no | `1` = enviar a firma y reservar número |
| `adjuntos[]` | no | hasta 10 archivos por petición: pdf, jpg, png, docx, xlsx, pptx, zip (≤ 10 MB c/u) |

```powershell
curl.exe -X POST https://fingenieria.mx/api/oficios/subdireccion `
  -H "Authorization: Bearer $env:OFICIOS_TOKEN" `
  --form-string asunto="Asunto del oficio" `
  --form-string 'destinatarios=[{"nombre":"Dra. Ana López","cargo":"Directora","dependencia":"Facultad de Ingeniería"}]' `
  --form-string "cuerpo=Primer párrafo.`n`nSegundo párrafo." `
  -F "adjuntos[]=@anexo.pdf" --form-string enviar=1
```

Usa `--form-string` (no `-F`) para los textos: `-F` interpreta comillas, comas y `;` y desfigura el JSON de `destinatarios`.

## Anexos a un oficio existente

`POST /api/oficios/subdireccion/{id}/adjuntos` con `adjuntos[]` (borrador o en firma; máximo 30 anexos por oficio).

Los anexos acompañan al oficio y se ven y descargan en su ficha (`/oficios/subdireccion/{id}`); **no se unen al PDF firmado**. Se guardan en `storage/oficios_sub/adjuntos/` (tabla `ofs_adjuntos`, migración 073).

## Respuestas (JSON)

`201 {ok, id, estado, numero, adjuntos[], url}`, `401` token inválido o vencido, `403` cuenta sin permiso de redactar, `404` oficio inexistente, `422` datos inválidos. Cada operación se audita como `oficios_api_subdireccion`.
