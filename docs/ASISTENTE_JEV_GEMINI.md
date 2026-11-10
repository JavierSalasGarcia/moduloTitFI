# Asistente de la página de inicio: Jev (árbitro) + Gemini

Sustituye, para la consulta de inicio, a `docs/asistente-seguro-2026-09-10.md` (que sigue describiendo la extracción
documental y la clasificación de registros confidenciales).

## Idea central

**Ningún modelo decide qué puede ver una persona.** El servidor filtra primero, con los permisos de la sesión, lo que
existe para ella; los modelos solo eligen entre esas opciones y su salida se valida contra la misma lista. Por eso un
modelo engañado no puede revelar nada: no conoce la base de datos, ni los roles, ni secciones fuera del acceso de quien
pregunta.

Los permisos **no se envían a la IA como parámetros**. Se aplican en el servidor antes (catálogo filtrado) y después
(consultas fijas con el `id` de la sesión). Es la única forma de que el nivel de acceso no dependa de que el modelo
obedezca.

## Flujo de una consulta (`POST /consulta`)

```text
pregunta
  │ 1. Saneado (Arbitro::sanear): UTF-8 válido, sin caracteres de control ni invisibles, máx. 1,200 caracteres
  │ 2. Filtro local (Prompts::PATRONES_BLOQUEO): manipulación o sondeo evidente → se rechaza sin llamar a nadie
  │ 3. Catálogo de la persona (ModuleManager::consulta): secciones que SU cuenta puede abrir
  │ 4. Cuota (GeminiClient::quota): una reserva por consulta; sin cuota todo sigue con motores locales
  ▼
ÁRBITRO (Arbitro::evaluar)  ── Jev si está activo; si no, o si falla, motor local de palabras
  │   a) ¿es manipulación?            sí → «No puedo atender esa petición»            (fin)
  │   b) ¿corresponde al sitio?       no → «Esta consulta no corresponde a Traza FI»  (fin)
  │   c) ¿a qué secciones?            → enlaces (máx. 5) de su catálogo
  ▼
RESPUESTA (AsistenteConsulta::resolver)
  │   · Registros propios (ConsultasSeguras): consultas SQL fijas, acotadas por la sesión, sin modelo
  │   · Orientación de trámite: Buscador local elige 6 funciones oficiales; Gemini (si hay cuota) elige una por índice
  ▼
Pantalla: respuesta + «Dónde consultarlo» (sección › dirección, título enlazado, descripción breve)
```

| Pieza | Archivo |
|---|---|
| Flujo | `app/Modules/Portal/AsistenteConsulta.php` |
| Árbitro (Jev + local) | `app/Modules/Portal/Arbitro.php` |
| Cliente de Jev | `app/Modules/Portal/JevClient.php` |
| Cliente de Gemini y cuotas | `app/Modules/Portal/GeminiClient.php` |
| Consultas de registros propios | `app/Modules/Portal/ConsultasSeguras.php` |
| **Prompts (todos)** | `app/Modules/Portal/Prompts.php` |
| **Claves de los servicios** | `app/Core/SecretosIa.php` |
| Catálogo de consulta | `ModuleManager::consulta()` + clave `consulta` de cada manifiesto |
| Pantalla de inicio | `app/Modules/Portal/views/index.php` |
| Configuración del administrador | `/ajustes/ia` (`app/Modules/Ajustes/InteligenciaController.php`) |
| Pruebas | `php tools/prueba_asistente.php` |

## Jev en este sitio

Jev (TypeSafe AI) no conversa ni genera texto: recibe un **estado** y **preguntas tipadas**, y devuelve valores con
probabilidad. Aquí se le hace una sola llamada por consulta (`POST https://api.typesafe.ai/v1/systemone`) con tres preguntas:

| Pregunta | Tipo | Uso |
|---|---|---|
| `ambito` | sí/no (`noul`) | Probabilidad de que la pregunta corresponda al sitio. Se acepta si ≥ umbral (Ajustes, 0.5 por defecto). |
| `manipulacion` | sí/no (`noul`) | Si ≥ 0.6 se rechaza, aunque parezca en ámbito. |
| `destino` | elección (`choice`) | Las opciones son las secciones del catálogo de la persona, con claves opacas (`s0`, `s1`, …). De sus probabilidades salen los enlaces. |

- La pregunta de la persona viaja en `state` (`{"pregunta": "…"}`), nunca dentro de las instrucciones.
- A Jev no se le envían rutas, identificadores, roles ni nada que la persona no pueda abrir.
- De la respuesta solo se aceptan claves que el servidor envió; cualquier otra se ignora.
- Si Jev no responde, responde algo malformado o no hay cuota, decide el árbitro local. El sitio nunca depende de Jev.

## Dónde van las credenciales

Se leen en un solo lugar, `App\Core\SecretosIa`, en este orden (gana el primero que exista):

| # | Dónde | Jev | Gemini | Cuándo usarlo |
|---|---|---|---|---|
| 1 | Variable de entorno del servidor | `TRAZAFI_JEV_API_KEY` | `TRAZAFI_GEMINI_API_KEY` | Producción, si el hosting lo permite. La clave no toca la base. |
| 2 | `app/config.local.php` (no se versiona ni se sube) | `'ia' => ['jev_api_key' => '…']` | `'ia' => ['gemini_api_key' => '…']` | Desarrollo local. |
| 3 | **Ajustes → Asistente y privacidad** (`/ajustes/ia`) | campo «API key de Jev» | campo «API key de Gemini» | Lo habitual en producción. Se guarda **cifrada** en `ajustes` (`ia_jev_api_key`, `ia_gemini_api_key`). |

- La opción 3 exige la llave de cifrado del servidor (`docs/CIFRADO_DE_DATOS.md`); sin ella la pantalla se niega a
  guardar la clave en lugar de dejarla en claro. Una clave antigua en claro se cifra sola al abrir `/ajustes/ia`.
- Las claves nunca regresan al formulario, a JavaScript ni a la bitácora.
- Dónde se obtienen: Jev en <https://console.typesafe.ai/keys>; Gemini en <https://aistudio.google.com/apikey>.
- No las pongas en `cred.txt`, en el repositorio ni en un chat.

Otros ajustes en `/ajustes/ia`: activar Jev, modelo de Jev (`jev-latest` o una versión fija), umbral de pertinencia,
proveedor (local/Gemini), modelo de Gemini, y los límites.

## Límite por usuario

- «Consultas asistidas diarias por usuario» (`ia_limite_usuario`, 20 por defecto) y total diario de la plataforma
  (`ia_limite_diario`), ambos en `/ajustes/ia`. Además, 5 por minuto por persona.
- Una consulta consume **una** reserva aunque use Jev y Gemini. Los intentos fallidos también cuentan. El día de cuota es UTC.
- La pantalla de inicio muestra cuántas le quedan a la persona. Al agotarse, la consulta sigue funcionando con el
  árbitro y el buscador locales.
- La persona decide en cada consulta si su pregunta sale a los servicios externos (casilla «Usar asistencia de IA»).

## Prompts y defensa contra inyección

Todos los textos de instrucción están en **`app/Modules/Portal/Prompts.php`**. Reglas que el código hace cumplir:

1. **Instrucciones y datos separados.** Las instrucciones son constantes del servidor; la pregunta va aparte (`state`
   en Jev, parte `user` en Gemini con `systemInstruction` fija). Nunca se concatenan.
2. **Salida cerrada.** Jev devuelve probabilidades sobre claves que el servidor definió; Gemini devuelve un índice con
   `responseSchema`. Ningún texto libre de un modelo llega a la pantalla.
3. **Validación en el servidor.** Se descartan claves o índices que no estén en la lista enviada.
4. **Mínimo conocimiento.** Los prompts no describen tablas, columnas, roles ni rutas. No hay herramienta SQL: los
   registros se leen con consultas fijas en `ConsultasSeguras`, acotadas por el `id` de la sesión.
5. **Catálogo ya filtrado.** A los modelos solo se les muestran secciones que la persona puede abrir.
6. **Filtro local previo** (`Prompts::PATRONES_BLOQUEO`) y pregunta `manipulacion` de Jev: dos barreras antes de responder.
7. **Sin memoria.** No hay historial de conversación; cada consulta es independiente.
8. **Bitácora.** Se registra el veredicto (`ia_arbitro`), el consumo (`ia_jev`, `ia_gemini`) y la cuota, sin guardar
   preguntas ni respuestas.

Al editar un prompt: no incluyas nombres de tablas, columnas, roles ni rutas internas, y no insertes en él texto de la
persona. Después ejecuta `php tools/prueba_asistente.php`.

## Integrar un módulo nuevo en la consulta

Basta con el manifiesto del módulo; no se toca el Portal.

1. **Enlaces.** Cada entrada de `menu` entra sola al catálogo (con sus `roles` y `condicion`). Para que el árbitro la
   encuentre mejor, descríbela con la clave `consulta`:

   ```php
   'consulta' => [
       ['ruta' => 'ejemplo', 'descripcion' => 'Qué resuelve esta sección, en una frase.', 'palabras' => 'sinónimos que usaría la gente'],
       // Una entrada que no está en el menú necesita además 'titulo' (y 'roles' si difieren de los del módulo):
       ['ruta' => 'ejemplo/ayuda', 'titulo' => 'Ayuda de Ejemplo', 'descripcion' => '…', 'roles' => ['alumno']],
   ],
   ```

   Si `ruta` coincide con una entrada de menú hereda su título, roles y condición. La descripción es lo que se muestra
   bajo el enlace y lo que lee Jev: escríbela para una persona, sin detalles internos.
2. **Datos propios** (opcional). Si la persona debe poder preguntar por sus registros de ese módulo («mis …»), agrega
   una consulta fija en `ConsultasSeguras`: SQL con el `id` de la sesión en el `WHERE`, columnas mínimas y enlace a la
   pantalla original. No se agrega una herramienta genérica ni se deja que un modelo arme la consulta.
3. `php tools/validar_modulos.php` y `php tools/prueba_asistente.php`.

## Pendiente y límites conocidos

- La conexión real con Jev y con Gemini no se ha probado: faltan las claves. Usa «Probar Jev» y «Probar conexión» en
  `/ajustes/ia` después de capturarlas.
- El modelo de Gemini por defecto es `gemini-2.5-flash-lite`; confírmalo o cámbialo en Ajustes si Google lo retiró.
- Los umbrales (0.5 pertinencia, 0.6 manipulación, 0.06 para listar un enlace) son iniciales; conviene ajustarlos con
  preguntas reales y fijar una versión de Jev al hacerlo.
- `ConsultasSeguras` solo cubre solicitudes, reuniones y archivo documental.
