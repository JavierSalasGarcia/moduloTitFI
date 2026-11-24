# API de Oficios (subir desde VS Code)

`POST /api/oficios` archiva un oficio en «Oficios enviados» (`/oficios`) de la coordinación o departamento de la persona dueña del token. Código: `app/Modules/Oficios/Services/ApiOficios.php` (se atiende en `App::run`, antes del router: sin sesión ni CSRF, solo token).

## Token

- Formato `ofi_` + 48 hex. En la base solo está su SHA-256 (`oficios_api_tokens`, migración 072); se ve una sola vez al crearlo. Vigencia 365 días por defecto; se revoca con `revocarToken(id)`.
- Solo sirve para cuentas activas con rol `admin`, `titular`, `coordinador` u `operativo` y un departamento asignado.
- Crear (producción): `python tools/subir/subir.py php -e "return App\Modules\Oficios\Services\ApiOficios::crearToken(<usuario_id>, 'VS Code secretaria');"`
- El token es secreto: va en una variable de entorno (`OFICIOS_TOKEN`), nunca en el repositorio.

## Petición

`multipart/form-data` con `Authorization: Bearer <token>`:

| Campo | Obligatorio | Nota |
|---|---|---|
| `archivo` | sí | PDF ≤ 10 MB |
| `folio` | sí | ≤ 60 caracteres; único por coordinación |
| `fecha` | sí | `AAAA-MM-DD` |
| `asunto`, `destinatarios` | sí | ≤ 250 y ≤ 500 |
| `tipo` | no | `oficio` (defecto), `memorandum`, `circular`, `otro` |
| `firmante`, `notas` | no | |

```powershell
curl.exe -X POST https://fingenieria.mx/api/oficios `
  -H "Authorization: Bearer $env:OFICIOS_TOKEN" `
  -F tipo=oficio -F folio="FI/CA/123/2026" -F fecha=2026-11-23 `
  -F asunto="Asunto del oficio" -F destinatarios="Dr. Fulano" `
  -F "archivo=@oficio.pdf;type=application/pdf"
```

Respuestas JSON: `201 {ok,id,folio,sha256,url}`, `401` token inválido, `403` sin acceso, `409` folio repetido, `422` datos inválidos. Cada alta queda en la auditoría como `oficios_api_archivar`.
