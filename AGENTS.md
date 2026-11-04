# Contexto para agentes de TRAZAFI

TRAZAFI es la plataforma modular PHP 8 + MySQL de la Facultad de Ingeniería de la UAEMéx. Antes de cambiarla, leer `README.md`, `docs/GUIA_MODULOS.md` y la documentación específica del flujo afectado. No exponer archivos fuera de `public/`, no versionar secretos y conservar la autorización por rol, CSRF, consultas preparadas, auditoría y escape de salida.

Para formularios o encuestas privadas por enlace es obligatorio leer `docs/ENCUESTAS_PRIVADAS.md`. El módulo `Formularios` recauda datos identificables y exporta Excel; el módulo `Encuestas` se reserva para votaciones anónimas con tablero público. No mezclar ambos modelos de privacidad.

Las migraciones nuevas se agregan a `database/migrations/` y a `database/construir_actualizar.php`; después se regenera `database/actualizar.sql`. No editar el acumulado a mano.
