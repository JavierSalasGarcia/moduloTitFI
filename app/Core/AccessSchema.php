<?php
declare(strict_types=1);
namespace App\Core;

final class AccessSchema
{
    public static function install(): void
    {
        AccessControl::internal(function (): void {
            foreach ([
                "CREATE TABLE IF NOT EXISTS cuenta_verificacion (usuario_id INT UNSIGNED PRIMARY KEY, verificado_en DATETIME NULL, codigo_hash VARCHAR(255) NULL, expira_en DATETIME NULL, intentos INT NOT NULL DEFAULT 0, enviado_en DATETIME NULL, creado_en DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "CREATE TABLE IF NOT EXISTS acceso_limites (clave CHAR(64) PRIMARY KEY, ventana BIGINT NOT NULL, cantidad INT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "CREATE TABLE IF NOT EXISTS auditoria_acciones (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor_id INT UNSIGNED NULL, actor_email VARCHAR(120) NOT NULL, usuario_id INT UNSIGNED NULL, usuario_email VARCHAR(120) NOT NULL, accion VARCHAR(180) NOT NULL, resultado VARCHAR(30) NOT NULL, detalle VARCHAR(500) NOT NULL, creado_en DATETIME NOT NULL, INDEX fecha (creado_en), INDEX actor (actor_id), INDEX usuario (usuario_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            ] as $sql) Database::pdo()->exec($sql);
            if (!Database::valor("SELECT valor FROM ajustes WHERE clave = 'accesos_version_1'")) {
                $backup=['ajuste'=>Database::una("SELECT * FROM ajustes WHERE clave='umbral_likes_trazabilidad'"),'peticiones'=>Database::todas("SELECT id,umbral_likes FROM foro_publicaciones WHERE tipo='peticion' AND estado='abierta'")];
                Database::ejecutar("INSERT IGNORE INTO ajustes (clave,valor,descripcion) VALUES ('accesos_v1_respaldo_umbral',?,'Valores previos al cambio a 100 apoyos')",[json_encode($backup,JSON_UNESCAPED_UNICODE)]);
                Database::ejecutar("INSERT INTO ajustes (clave,valor,descripcion) VALUES ('umbral_likes_trazabilidad','100','Apoyos para dar seguimiento a propuestas') ON DUPLICATE KEY UPDATE valor='100'");
                Database::ejecutar("UPDATE foro_publicaciones SET umbral_likes=100 WHERE tipo='peticion' AND estado='abierta'");
                Database::ejecutar("INSERT INTO ajustes (clave,valor,descripcion) VALUES ('accesos_version_1','1','Autorregistro y auditoría instalados')");
            }
        });
        AccessControl::reset();
    }
}
