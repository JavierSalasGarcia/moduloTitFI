# Búsqueda del encabezado: qué puede y qué no puede mostrar

La búsqueda (`/busqueda?q=…` y las sugerencias `/busqueda/sugerencias?q=…`) existe para **encontrar a dónde ir**, no para consultar
datos de otras personas. Reglas obligatorias para quien la modifique o agregue fuentes:

1. **Solo lo que la sesión ya puede ver.** Cada fuente aplica el alcance de la persona en el servidor:
   - Opciones del sitio: catálogo `consulta` de los manifiestos, ya filtrado por rol, concesiones y condiciones.
   - Avisos y eventos: vigentes y dirigidos a su público (`Eventos\BuscarAvisos`); en las **sugerencias** nunca salen borradores.
   - Registros propios: `Portal\ConsultasSeguras` (solicitudes y reuniones propias, solo estado; documentos indizados con `Ingesta::alcance()`).
   - Áreas responsables: el Manual de Organización (información pública de funciones, sin personas).
2. **Nunca** buscar por nombre de personas, correos, números de cuenta, expedientes, dictámenes ni **casos de los Consejos**. Eso solo
   existe en «Buscar en las actas», reservado al Subdirector Académico, su asistente y quien tenga la concesión `consejos.gestor`.
3. Las sugerencias devuelven solo **título, tipo y enlace** (no el texto de los avisos), mínimo 3 letras y hasta 5 resultados.
4. Requiere sesión y correo verificado (las cuentas pendientes solo ven opciones del sitio). Tiene límite de uso por persona
   (`AccessControl::limited`: 40 búsquedas y 90 sugerencias por minuto) para impedir barridos automáticos.
5. En la bitácora solo se guarda la **longitud** del texto y cuántos resultados hubo, nunca el texto buscado.
6. Las respuestas llevan `Cache-Control: no-store, private`; el texto buscado se escapa siempre al mostrarse.
7. Sin IA externa: ningún texto buscado sale del servidor.

Una fuente nueva debe documentarse aquí y cumplir los puntos 1 a 5.
