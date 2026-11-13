-- Datos del profesor que él mismo puede completar o corregir en «Mi perfil»: categoría de contratación.
-- (grado, género y número de empleado ya existen.) Es repetible: puede ejecutarse completa las veces que haga falta.
SET NAMES utf8mb4;

SET @s = (SELECT IF(COUNT(*) = 0, "ALTER TABLE profesores ADD COLUMN categoria ENUM('ptc','medio_tiempo','asignatura','tecnico_academico') NULL DEFAULT NULL AFTER grado", 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'profesores' AND COLUMN_NAME = 'categoria');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
