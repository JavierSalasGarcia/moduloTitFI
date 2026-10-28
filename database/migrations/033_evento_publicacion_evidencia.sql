-- Eventos: enlace de la publicación en Facebook que muestra la evidencia (se agrega después del evento) y alta del
-- sorteo público del Rizoma para la Igualdad. Repetible: solo crea lo que falta y no borra datos.

SET NAMES utf8mb4;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN evidencia_post_url VARCHAR(500) NULL AFTER grabacion_url', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'evidencia_post_url');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

INSERT INTO eventos (titulo, descripcion, categoria, tema, publico, depto_id, autor_id, inicio, lugar, modalidad, organizador, post_url,
                     requiere_registro, formacion_integral, horas, puntos)
SELECT 'Sorteo público para integrar el Rizoma para la Igualdad',
       'Te invitamos a presenciar el sorteo público para seleccionar a las personas que integrarán el Rizoma para la Igualdad de la Facultad de Ingeniería. Este grupo contribuirá a promover una comunidad universitaria libre de violencia, mediante la orientación y el acompañamiento a quienes lo necesiten. Tu presencia contribuye a la transparencia del proceso.',
       'otro', 'comunidad', 'alumno,egresado,profesor', d.id, u.id, '2026-10-30 12:00:00', 'Auditorio "Ing. José Yurrieta Valdés"', 'presencial',
       'Administración F.I. 2026-2030', 'https://www.facebook.com/share/p/19r1KdHMYi/', 0, 0, NULL, 0
  FROM departamentos d
  JOIN (SELECT MIN(id) AS id FROM usuarios WHERE rol = 'admin' AND activo = 1) u ON u.id IS NOT NULL
 WHERE d.clave = 'coordinacion_de_difusion_cultural'
   AND NOT EXISTS (SELECT 1 FROM eventos e WHERE e.titulo = 'Sorteo público para integrar el Rizoma para la Igualdad');
