-- Desvincula de cuentas las entradas históricas de auditoría del flujo de voto.
-- Conserva qué operación ocurrió y su resultado, pero elimina actor y usuario.
-- Las tablas funcionales mantienen únicamente:
--   encuesta_participantes: que una cuenta ya participó en una encuesta;
--   encuesta_votos: recibo y opción, sin usuario ni FK hacia usuarios.

UPDATE auditoria_acciones
SET actor_id = NULL,
    actor_email = '',
    usuario_id = NULL,
    usuario_email = ''
WHERE accion = 'encuestas::votar'
   OR accion = 'BD: INSERT INTO encuesta_participantes'
   OR accion = 'BD: INSERT INTO encuesta_votos';

