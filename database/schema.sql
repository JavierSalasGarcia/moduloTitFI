-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: plataforma_fi
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `ajustes`
--

DROP TABLE IF EXISTS `ajustes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ajustes` (
  `clave` varchar(60) NOT NULL,
  `valor` varchar(255) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `areas`
--

DROP TABLE IF EXISTS `areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `areas` (
  `id` tinyint(3) unsigned NOT NULL,
  `clave` varchar(30) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `orden` tinyint(3) unsigned NOT NULL DEFAULT 99,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comision_bitacora`
--

DROP TABLE IF EXISTS `comision_bitacora`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comision_bitacora` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `comision_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `contenido` text NOT NULL,
  `archivo` varchar(255) DEFAULT NULL COMMENT 'Ruta relativa dentro de storage/',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `comision_id` (`comision_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `comision_bitacora_ibfk_1` FOREIGN KEY (`comision_id`) REFERENCES `comisiones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comision_bitacora_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comision_miembros`
--

DROP TABLE IF EXISTS `comision_miembros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comision_miembros` (
  `comision_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `estado` enum('invitado','aceptado','rechazado') NOT NULL DEFAULT 'invitado',
  `es_voluntario` tinyint(1) NOT NULL DEFAULT 0,
  `respondido_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`comision_id`,`usuario_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `comision_miembros_ibfk_1` FOREIGN KEY (`comision_id`) REFERENCES `comisiones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comision_miembros_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comisiones`
--

DROP TABLE IF EXISTS `comisiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comisiones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `publicacion_id` int(10) unsigned DEFAULT NULL COMMENT 'Petici├│n del foro que la origin├│',
  `origen` enum('peticion','reunion') NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `descripcion` text NOT NULL,
  `creador_id` int(10) unsigned NOT NULL,
  `depto_responsable_id` smallint(5) unsigned NOT NULL,
  `estado` enum('formacion','en_seguimiento','concluida') NOT NULL DEFAULT 'formacion',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `publicacion_id` (`publicacion_id`),
  KEY `creador_id` (`creador_id`),
  KEY `depto_responsable_id` (`depto_responsable_id`),
  CONSTRAINT `comisiones_ibfk_1` FOREIGN KEY (`publicacion_id`) REFERENCES `foro_publicaciones` (`id`),
  CONSTRAINT `comisiones_ibfk_2` FOREIGN KEY (`creador_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `comisiones_ibfk_3` FOREIGN KEY (`depto_responsable_id`) REFERENCES `departamentos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comunicados`
--

DROP TABLE IF EXISTS `comunicados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comunicados` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `autor_id` int(10) unsigned NOT NULL,
  `depto_id` smallint(5) unsigned NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `contenido` text NOT NULL,
  `alcance` enum('todos','licenciatura','posgrado','programa') NOT NULL DEFAULT 'todos',
  `programa_id` smallint(5) unsigned DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `categoria` varchar(50) NOT NULL DEFAULT 'General',
  PRIMARY KEY (`id`),
  KEY `idx_alcance` (`alcance`,`programa_id`),
  KEY `autor_id` (`autor_id`),
  KEY `depto_id` (`depto_id`),
  KEY `programa_id` (`programa_id`),
  CONSTRAINT `comunicados_ibfk_1` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `comunicados_ibfk_2` FOREIGN KEY (`depto_id`) REFERENCES `departamentos` (`id`),
  CONSTRAINT `comunicados_ibfk_3` FOREIGN KEY (`programa_id`) REFERENCES `programas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `departamentos`
--

DROP TABLE IF EXISTS `departamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departamentos` (
  `id` smallint(5) unsigned NOT NULL,
  `clave` varchar(120) NOT NULL,
  `nombre` varchar(180) NOT NULL,
  `area_id` tinyint(3) unsigned NOT NULL,
  `origen_mo` varchar(120) DEFAULT NULL COMMENT 'Referencia de p├íginas del Manual de Organizaci├│n',
  `email` varchar(120) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `ubicacion` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave` (`clave`),
  KEY `area_id` (`area_id`),
  CONSTRAINT `departamentos_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `encuesta_opciones`
--

DROP TABLE IF EXISTS `encuesta_opciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `encuesta_opciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `encuesta_id` int(10) unsigned NOT NULL,
  `texto` varchar(180) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `encuesta_id` (`encuesta_id`),
  CONSTRAINT `encuesta_opciones_ibfk_1` FOREIGN KEY (`encuesta_id`) REFERENCES `encuestas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `encuesta_participantes`
--

DROP TABLE IF EXISTS `encuesta_participantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `encuesta_participantes` (
  `encuesta_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`encuesta_id`,`usuario_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `encuesta_participantes_ibfk_1` FOREIGN KEY (`encuesta_id`) REFERENCES `encuestas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `encuesta_participantes_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `encuesta_votos`
--

DROP TABLE IF EXISTS `encuesta_votos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `encuesta_votos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `encuesta_id` int(10) unsigned NOT NULL,
  `opcion_id` int(10) unsigned NOT NULL,
  `token` char(10) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `encuesta_id` (`encuesta_id`),
  KEY `opcion_id` (`opcion_id`),
  CONSTRAINT `encuesta_votos_ibfk_1` FOREIGN KEY (`encuesta_id`) REFERENCES `encuestas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `encuesta_votos_ibfk_2` FOREIGN KEY (`opcion_id`) REFERENCES `encuesta_opciones` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1204 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `encuestas`
--

DROP TABLE IF EXISTS `encuestas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `encuestas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(180) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `creador_id` int(10) unsigned NOT NULL,
  `estado` enum('abierta','cerrada') NOT NULL DEFAULT 'abierta',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `creador_id` (`creador_id`),
  CONSTRAINT `encuestas_ibfk_1` FOREIGN KEY (`creador_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evento_destinatarios`
--

DROP TABLE IF EXISTS `evento_destinatarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evento_destinatarios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `evento_id` int(10) unsigned NOT NULL,
  `tipo` enum('para','cc') NOT NULL DEFAULT 'para',
  `destinatario_type` enum('usuario','departamento') NOT NULL DEFAULT 'usuario',
  `destinatario_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `evento_id` (`evento_id`),
  CONSTRAINT `evento_destinatarios_ibfk_1` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evento_suscripciones`
--

DROP TABLE IF EXISTS `evento_suscripciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evento_suscripciones` (
  `evento_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`evento_id`,`usuario_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `evento_suscripciones_ibfk_1` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evento_suscripciones_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eventos`
--

DROP TABLE IF EXISTS `eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eventos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(180) NOT NULL,
  `descripcion` text NOT NULL,
  `categoria` enum('reunion','conferencia','taller','curso','academico','cultural','deportivo','otro') NOT NULL DEFAULT 'otro',
  `depto_id` smallint(5) unsigned NOT NULL,
  `autor_id` int(10) unsigned NOT NULL,
  `inicio` datetime NOT NULL,
  `fin` datetime DEFAULT NULL,
  `lugar` varchar(160) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_inicio` (`inicio`),
  KEY `idx_categoria` (`categoria`),
  KEY `depto_id` (`depto_id`),
  KEY `autor_id` (`autor_id`),
  CONSTRAINT `eventos_ibfk_1` FOREIGN KEY (`depto_id`) REFERENCES `departamentos` (`id`),
  CONSTRAINT `eventos_ibfk_2` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `firma_documentos`
--

DROP TABLE IF EXISTS `firma_documentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `firma_documentos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `solicitud_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL COMMENT 'Quien lo subi├│ (creador o intermediario)',
  `tipo` enum('original','anexo','hoja_firmas') NOT NULL DEFAULT 'original',
  `nombre` varchar(160) NOT NULL COMMENT 'Nombre visible (archivo original)',
  `archivo` varchar(255) NOT NULL COMMENT 'PDF tal como se subi├│ (storage/firmas)',
  `archivo_firmado` varchar(255) DEFAULT NULL COMMENT 'Versi├│n con sellos estampados (FPDI)',
  `estampable` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = FPDI no pudo parsearlo: firmas solo en hoja de firmas',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_solicitud` (`solicitud_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `firma_documentos_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `firma_solicitudes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `firma_documentos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `firma_estampas`
--

DROP TABLE IF EXISTS `firma_estampas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `firma_estampas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `firmante_id` int(10) unsigned NOT NULL,
  `documento_id` int(10) unsigned NOT NULL,
  `pagina` enum('primera','ultima') NOT NULL DEFAULT 'ultima',
  `posicion` enum('abajo_izquierda','abajo_centro','abajo_derecha') NOT NULL DEFAULT 'abajo_derecha',
  `estampada` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = sello impreso en el PDF; 0 = solo BD/hoja de firmas',
  `sha256` char(64) NOT NULL COMMENT 'Hash del PDF original al momento de firmar',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_firma_doc` (`firmante_id`,`documento_id`),
  KEY `documento_id` (`documento_id`),
  CONSTRAINT `firma_estampas_ibfk_1` FOREIGN KEY (`firmante_id`) REFERENCES `firma_firmantes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `firma_estampas_ibfk_2` FOREIGN KEY (`documento_id`) REFERENCES `firma_documentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Certificados individuales y operaciones PAdES
--

DROP TABLE IF EXISTS `firma_operaciones`;
DROP TABLE IF EXISTS `firma_segundo_factor_usos`;
DROP TABLE IF EXISTS `firma_certificados`;
CREATE TABLE `firma_certificados` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `serie` varchar(64) NOT NULL,
  `sujeto` varchar(500) NOT NULL,
  `huella_sha256` char(64) NOT NULL,
  `pfx_path` varchar(500) NOT NULL,
  `secreto_envuelto` text NOT NULL,
  `valido_desde` datetime NOT NULL,
  `valido_hasta` datetime NOT NULL,
  `estado` enum('activo','revocado','expirado') NOT NULL DEFAULT 'activo',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `revocado_en` timestamp NULL DEFAULT NULL,
  `motivo_revocacion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_firma_cert_serie` (`serie`),
  KEY `idx_firma_cert_usuario_estado` (`usuario_id`,`estado`),
  CONSTRAINT `firma_certificados_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE `firma_operaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `solicitud_id` int(10) unsigned NOT NULL,
  `firmante_id` int(10) unsigned NOT NULL,
  `documento_id` int(10) unsigned NOT NULL,
  `certificado_id` int(10) unsigned NOT NULL,
  `campo_pdf` varchar(100) NOT NULL,
  `segundo_factor` enum('totp','webauthn') NOT NULL,
  `desafio_sha256` char(64) NOT NULL,
  `entrada_sha256` char(64) NOT NULL,
  `salida_sha256` char(64) NOT NULL,
  `resultado` enum('firmado','fallido') NOT NULL DEFAULT 'firmado',
  `detalle` varchar(500) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_firma_operacion_doc_firmante` (`documento_id`,`firmante_id`),
  KEY `idx_firma_operacion_solicitud` (`solicitud_id`),
  KEY `idx_firma_operacion_certificado` (`certificado_id`),
  CONSTRAINT `firma_operaciones_solicitud_fk` FOREIGN KEY (`solicitud_id`) REFERENCES `firma_solicitudes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `firma_operaciones_firmante_fk` FOREIGN KEY (`firmante_id`) REFERENCES `firma_firmantes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `firma_operaciones_documento_fk` FOREIGN KEY (`documento_id`) REFERENCES `firma_documentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `firma_operaciones_certificado_fk` FOREIGN KEY (`certificado_id`) REFERENCES `firma_certificados` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE `firma_segundo_factor_usos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `codigo_hash` char(64) NOT NULL,
  `usado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_firma_2fa_usuario_codigo` (`usuario_id`,`codigo_hash`),
  KEY `idx_firma_2fa_fecha` (`usado_en`),
  CONSTRAINT `firma_2fa_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Table structure for table `firma_eventos`
--

DROP TABLE IF EXISTS `firma_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `firma_eventos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `solicitud_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `tipo` enum('creacion','firma','rechazo','anexo','avance_paso','cancelacion','comentario') NOT NULL,
  `contenido` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `solicitud_id` (`solicitud_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `firma_eventos_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `firma_solicitudes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `firma_eventos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `firma_firmantes`
--

DROP TABLE IF EXISTS `firma_firmantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `firma_firmantes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `solicitud_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `orden` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `estado` enum('pendiente','firmado','rechazado') NOT NULL DEFAULT 'pendiente',
  `motivo_rechazo` varchar(500) DEFAULT NULL,
  `respondido_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_solicitud_usuario` (`solicitud_id`,`usuario_id`),
  KEY `idx_usuario_estado` (`usuario_id`,`estado`),
  CONSTRAINT `firma_firmantes_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `firma_solicitudes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `firma_firmantes_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `firma_grupo_miembros`
--

DROP TABLE IF EXISTS `firma_grupo_miembros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `firma_grupo_miembros` (
  `grupo_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`grupo_id`,`usuario_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `firma_grupo_miembros_ibfk_1` FOREIGN KEY (`grupo_id`) REFERENCES `firma_grupos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `firma_grupo_miembros_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `firma_grupos`
--

DROP TABLE IF EXISTS `firma_grupos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `firma_grupos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(160) NOT NULL,
  `tipo` enum('personal','nab','cap','cc','cuerpo_academico') NOT NULL DEFAULT 'personal',
  `programa_id` smallint(5) unsigned DEFAULT NULL COMMENT 'nab/cap/cc: programa al que pertenece',
  `creador_id` int(10) unsigned DEFAULT NULL COMMENT 'personal: due├▒o; institucional: NULL',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_institucional` (`tipo`,`programa_id`),
  KEY `programa_id` (`programa_id`),
  KEY `creador_id` (`creador_id`),
  CONSTRAINT `firma_grupos_ibfk_1` FOREIGN KEY (`programa_id`) REFERENCES `programas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `firma_grupos_ibfk_2` FOREIGN KEY (`creador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `firma_solicitudes`
--

DROP TABLE IF EXISTS `firma_solicitudes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `firma_solicitudes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `folio` varchar(20) NOT NULL,
  `creador_id` int(10) unsigned NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `mensaje` text DEFAULT NULL,
  `modo` enum('secuencial','paralela') NOT NULL,
  `estado` enum('en_ruta','completada','rechazada','cancelada') NOT NULL DEFAULT 'en_ruta',
  `paso_actual` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'Solo aplica en modo secuencial',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `folio` (`folio`),
  KEY `idx_creador` (`creador_id`,`estado`),
  CONSTRAINT `firma_solicitudes_ibfk_1` FOREIGN KEY (`creador_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `foro_comentarios`
--

DROP TABLE IF EXISTS `foro_comentarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `foro_comentarios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `publicacion_id` int(10) unsigned NOT NULL,
  `autor_id` int(10) unsigned NOT NULL,
  `contenido` text NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `publicacion_id` (`publicacion_id`),
  KEY `autor_id` (`autor_id`),
  CONSTRAINT `foro_comentarios_ibfk_1` FOREIGN KEY (`publicacion_id`) REFERENCES `foro_publicaciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `foro_comentarios_ibfk_2` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `foro_likes`
--

DROP TABLE IF EXISTS `foro_likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `foro_likes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `publicacion_id` int(10) unsigned DEFAULT NULL,
  `comentario_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_like_pub` (`usuario_id`,`publicacion_id`),
  UNIQUE KEY `uq_like_com` (`usuario_id`,`comentario_id`),
  KEY `publicacion_id` (`publicacion_id`),
  KEY `comentario_id` (`comentario_id`),
  CONSTRAINT `foro_likes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `foro_likes_ibfk_2` FOREIGN KEY (`publicacion_id`) REFERENCES `foro_publicaciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `foro_likes_ibfk_3` FOREIGN KEY (`comentario_id`) REFERENCES `foro_comentarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `foro_publicaciones`
--

DROP TABLE IF EXISTS `foro_publicaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `foro_publicaciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `autor_id` int(10) unsigned NOT NULL,
  `tipo` enum('publicacion','peticion') NOT NULL DEFAULT 'publicacion',
  `titulo` varchar(180) NOT NULL,
  `contenido` text NOT NULL,
  `estado` enum('abierta','en_trazabilidad','atendida','cerrada') NOT NULL DEFAULT 'abierta',
  `umbral_likes` int(10) unsigned NOT NULL DEFAULT 200,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `autor_id` (`autor_id`),
  CONSTRAINT `foro_publicaciones_ibfk_1` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `funcion_embeddings`
--

DROP TABLE IF EXISTS `funcion_embeddings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `funcion_embeddings` (
  `funcion_id` int(10) unsigned NOT NULL,
  `modelo` varchar(80) NOT NULL,
  `vector` mediumtext NOT NULL COMMENT 'JSON array de floats',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`funcion_id`),
  CONSTRAINT `funcion_embeddings_ibfk_1` FOREIGN KEY (`funcion_id`) REFERENCES `funciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `funciones`
--

DROP TABLE IF EXISTS `funciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `funciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `departamento_id` smallint(5) unsigned NOT NULL,
  `numero` smallint(5) unsigned NOT NULL,
  `descripcion` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_depto_num` (`departamento_id`,`numero`),
  CONSTRAINT `funciones_ibfk_1` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=565 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `programas`
--

DROP TABLE IF EXISTS `programas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `programas` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `siglas` varchar(10) NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `nivel` enum('licenciatura','posgrado') NOT NULL,
  `depto_id` smallint(5) unsigned NOT NULL COMMENT 'Coordinaci├│n que atiende el programa',
  PRIMARY KEY (`id`),
  UNIQUE KEY `siglas` (`siglas`),
  KEY `depto_id` (`depto_id`),
  CONSTRAINT `programas_ibfk_1` FOREIGN KEY (`depto_id`) REFERENCES `departamentos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recurso_notas`
--

DROP TABLE IF EXISTS `recurso_notas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recurso_notas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `recurso_id` smallint(5) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `contenido` varchar(500) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_recurso_usuario` (`recurso_id`,`usuario_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `recurso_notas_ibfk_1` FOREIGN KEY (`recurso_id`) REFERENCES `recursos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `recurso_notas_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recursos`
--

DROP TABLE IF EXISTS `recursos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recursos` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo` enum('archivo','enlace') NOT NULL,
  `archivo` varchar(255) DEFAULT NULL COMMENT 'Ruta relativa dentro de storage/',
  `url` varchar(255) DEFAULT NULL,
  `notas_admin` text DEFAULT NULL COMMENT 'Notas visibles del administrador en la ficha',
  `orden` tinyint(3) unsigned NOT NULL DEFAULT 50,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `relaciones`
--

DROP TABLE IF EXISTS `relaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `relaciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `depto_origen_id` smallint(5) unsigned NOT NULL,
  `depto_destino_id` smallint(5) unsigned NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_canal` (`depto_origen_id`,`depto_destino_id`),
  KEY `depto_destino_id` (`depto_destino_id`),
  CONSTRAINT `relaciones_ibfk_1` FOREIGN KEY (`depto_origen_id`) REFERENCES `departamentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `relaciones_ibfk_2` FOREIGN KEY (`depto_destino_id`) REFERENCES `departamentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `solicitud_eventos`
--

DROP TABLE IF EXISTS `solicitud_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `solicitud_eventos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `solicitud_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `tipo` enum('comentario','cambio_estado') NOT NULL,
  `contenido` text NOT NULL,
  `estado_nuevo` varchar(20) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `solicitud_id` (`solicitud_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `solicitud_eventos_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `solicitud_eventos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `solicitudes`
--

DROP TABLE IF EXISTS `solicitudes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `solicitudes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `folio` varchar(20) NOT NULL,
  `depto_origen_id` smallint(5) unsigned NOT NULL,
  `depto_destino_id` smallint(5) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL COMMENT 'Quien levanta la solicitud',
  `asunto` varchar(180) NOT NULL,
  `descripcion` text NOT NULL,
  `prioridad` enum('baja','media','alta') NOT NULL DEFAULT 'media',
  `estado` enum('pendiente','en_proceso','resuelta','rechazada') NOT NULL DEFAULT 'pendiente',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `folio` (`folio`),
  KEY `idx_destino_estado` (`depto_destino_id`,`estado`),
  KEY `idx_origen` (`depto_origen_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `solicitudes_ibfk_1` FOREIGN KEY (`depto_origen_id`) REFERENCES `departamentos` (`id`),
  CONSTRAINT `solicitudes_ibfk_2` FOREIGN KEY (`depto_destino_id`) REFERENCES `departamentos` (`id`),
  CONSTRAINT `solicitudes_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `teams_carpetas`
--

DROP TABLE IF EXISTS `teams_carpetas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams_carpetas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sitio_id` int(10) unsigned NOT NULL,
  `ruta_relativa` varchar(500) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sitio_ruta` (`sitio_id`,`ruta_relativa`(255)),
  CONSTRAINT `teams_carpetas_ibfk_1` FOREIGN KEY (`sitio_id`) REFERENCES `teams_sitios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `teams_sitios`
--

DROP TABLE IF EXISTS `teams_sitios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams_sitios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `url_base` varchar(500) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `microsoft_email` varchar(180) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `teams_sitios_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `teams_ticket_destinatarios`
--

DROP TABLE IF EXISTS `teams_ticket_destinatarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams_ticket_destinatarios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `completado` tinyint(1) NOT NULL DEFAULT 0,
  `archivo_subido` varchar(500) DEFAULT NULL,
  `fecha_completado` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_usuario` (`ticket_id`,`usuario_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `teams_ticket_destinatarios_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `teams_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teams_ticket_destinatarios_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `teams_tickets`
--

DROP TABLE IF EXISTS `teams_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams_tickets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `carpeta_id` int(10) unsigned NOT NULL,
  `creador_id` int(10) unsigned NOT NULL,
  `notas` text NOT NULL,
  `semaforo` enum('verde','rojo') NOT NULL DEFAULT 'verde',
  `fecha_limite` date DEFAULT NULL,
  `archivo_adjunto` varchar(500) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `carpeta_id` (`carpeta_id`),
  KEY `creador_id` (`creador_id`),
  CONSTRAINT `teams_tickets_ibfk_1` FOREIGN KEY (`carpeta_id`) REFERENCES `teams_carpetas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teams_tickets_ibfk_2` FOREIGN KEY (`creador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_archivos`
--

DROP TABLE IF EXISTS `ticket_archivos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_archivos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `ruta` varchar(500) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `ticket_archivos_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_archivos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_categorias`
--

DROP TABLE IF EXISTS `ticket_categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_categorias` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `depto_destino_id` smallint(5) unsigned DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `depto_destino_id` (`depto_destino_id`),
  CONSTRAINT `ticket_categorias_ibfk_1` FOREIGN KEY (`depto_destino_id`) REFERENCES `departamentos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_clasificaciones`
--

DROP TABLE IF EXISTS `ticket_clasificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_clasificaciones` (
  `ticket_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `cuadrante` enum('hacer','planificar','delegar','eliminar') NOT NULL COMMENT 'hacer=urgente+importante, planificar=importante, delegar=urgente, eliminar=ninguno',
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ticket_id`,`usuario_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `ticket_clasificaciones_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_clasificaciones_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_destinatarios`
--

DROP TABLE IF EXISTS `ticket_destinatarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_destinatarios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` int(10) unsigned NOT NULL,
  `tipo` enum('para','cc') NOT NULL DEFAULT 'para',
  `destinatario_type` enum('usuario','departamento') NOT NULL DEFAULT 'usuario',
  `destinatario_id` int(10) unsigned NOT NULL,
  `completado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_completado` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`),
  CONSTRAINT `ticket_destinatarios_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_eventos`
--

DROP TABLE IF EXISTS `ticket_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_eventos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `tipo` enum('comentario','cambio_estado','rechazo','redireccion') NOT NULL,
  `contenido` text NOT NULL,
  `estado_nuevo` varchar(20) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `ticket_eventos_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_eventos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tickets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `folio` varchar(20) NOT NULL,
  `categoria_id` smallint(5) unsigned DEFAULT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `depto_destino_id` smallint(5) unsigned NOT NULL COMMENT 'Resuelto desde la categor├¡a al crear',
  `asunto` varchar(180) NOT NULL,
  `descripcion` text NOT NULL,
  `prioridad` enum('baja','media','alta') NOT NULL DEFAULT 'media',
  `estado` enum('abierto','en_atencion','resuelto','cerrado','rechazado') NOT NULL DEFAULT 'abierto',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `teams_carpeta_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `folio` (`folio`),
  KEY `idx_destino_estado` (`depto_destino_id`,`estado`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `categoria_id` (`categoria_id`),
  KEY `teams_carpeta_id` (`teams_carpeta_id`),
  CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `ticket_categorias` (`id`),
  CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`depto_destino_id`) REFERENCES `departamentos` (`id`),
  CONSTRAINT `tickets_ibfk_4` FOREIGN KEY (`teams_carpeta_id`) REFERENCES `teams_carpetas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuario_eventos_outlook`
--

DROP TABLE IF EXISTS `usuario_eventos_outlook`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario_eventos_outlook` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `uid` varchar(255) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `inicio` datetime NOT NULL,
  `fin` datetime NOT NULL,
  `descripcion` text DEFAULT NULL,
  `lugar` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_usuario_uid` (`usuario_id`,`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=383 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuario_firmas`
--

DROP TABLE IF EXISTS `usuario_firmas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario_firmas` (
  `usuario_id` int(10) unsigned NOT NULL,
  `imagen` varchar(255) NOT NULL COMMENT 'Ruta relativa dentro de storage/',
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`usuario_id`),
  CONSTRAINT `usuario_firmas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuario_microsoft_cuentas`
--

DROP TABLE IF EXISTS `usuario_microsoft_cuentas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario_microsoft_cuentas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `microsoft_email` varchar(180) NOT NULL,
  `es_activa` tinyint(1) NOT NULL DEFAULT 0,
  `token_expira` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_email` (`usuario_id`,`microsoft_email`),
  CONSTRAINT `usuario_microsoft_cuentas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `departamento_id` smallint(5) unsigned DEFAULT NULL,
  `programa_id` smallint(5) unsigned DEFAULT NULL,
  `rol` enum('admin','titular','operativo','profesor','alumno','coordinador') NOT NULL DEFAULT 'operativo',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `tipo_contratacion` varchar(50) DEFAULT NULL,
  `totp_secret` varchar(32) DEFAULT NULL,
  `permite_contrasena` tinyint(1) DEFAULT 1,
  `logins_contrasena_restantes` int(11) DEFAULT 20,
  `outlook_ics_url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `departamento_id` (`departamento_id`),
  KEY `fk_usuario_programa` (`programa_id`),
  CONSTRAINT `fk_usuario_programa` FOREIGN KEY (`programa_id`) REFERENCES `programas` (`id`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- Estructura organizacional compartida. Los cargos no son roles de acceso.
CREATE TABLE `departamento_responsables` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `departamento_id` smallint unsigned NOT NULL,
  `usuario_id` int unsigned NOT NULL,
  `cargo` enum('titular','jefe','coordinador','encargado','secretario') NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `observaciones` varchar(255) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_departamento_responsable_periodo` (`departamento_id`,`usuario_id`,`cargo`,`fecha_inicio`),
  KEY `idx_departamento_responsable_vigente` (`departamento_id`,`cargo`,`activo`,`fecha_fin`),
  KEY `idx_departamento_responsable_usuario` (`usuario_id`,`activo`),
  CONSTRAINT `fk_departamento_responsable_departamento` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`),
  CONSTRAINT `fk_departamento_responsable_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE `programa_responsables` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `programa_id` smallint unsigned NOT NULL,
  `usuario_id` int unsigned NOT NULL,
  `cargo` enum('coordinador','responsable','secretario_academico') NOT NULL DEFAULT 'coordinador',
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `observaciones` varchar(255) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_programa_responsable_periodo` (`programa_id`,`usuario_id`,`cargo`,`fecha_inicio`),
  KEY `idx_programa_responsable_vigente` (`programa_id`,`cargo`,`activo`,`fecha_fin`),
  KEY `idx_programa_responsable_usuario` (`usuario_id`,`activo`),
  CONSTRAINT `fk_programa_responsable_programa` FOREIGN KEY (`programa_id`) REFERENCES `programas` (`id`),
  CONSTRAINT `fk_programa_responsable_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE `programa_profesores` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `programa_id` smallint unsigned NOT NULL,
  `usuario_id` int unsigned NOT NULL,
  `tipo_vinculo` enum('docente','responsable_academico','colaborador') NOT NULL DEFAULT 'docente',
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `observaciones` varchar(255) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_programa_profesor_periodo` (`programa_id`,`usuario_id`,`fecha_inicio`),
  KEY `idx_programa_profesor_vigente` (`programa_id`,`activo`,`fecha_fin`),
  KEY `idx_programa_profesor_usuario` (`usuario_id`,`activo`),
  CONSTRAINT `fk_programa_profesor_programa` FOREIGN KEY (`programa_id`) REFERENCES `programas` (`id`),
  CONSTRAINT `fk_programa_profesor_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE `programa_organos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `programa_id` smallint unsigned NOT NULL,
  `tipo` enum('NAB','CAP') NOT NULL,
  `nombre` varchar(180) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_programa_organo_tipo` (`programa_id`,`tipo`),
  KEY `idx_programa_organo_activo` (`tipo`,`activo`),
  CONSTRAINT `fk_programa_organo_programa` FOREIGN KEY (`programa_id`) REFERENCES `programas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE `programa_organo_miembros` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organo_id` int unsigned NOT NULL,
  `usuario_id` int unsigned NOT NULL,
  `cargo` enum('presidente','coordinador','secretario','vocal','miembro') NOT NULL DEFAULT 'miembro',
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `observaciones` varchar(255) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_organo_miembro_periodo` (`organo_id`,`usuario_id`,`cargo`,`fecha_inicio`),
  KEY `idx_organo_miembro_vigente` (`organo_id`,`activo`,`fecha_fin`),
  KEY `idx_organo_miembro_usuario` (`usuario_id`,`activo`),
  CONSTRAINT `fk_organo_miembro_organo` FOREIGN KEY (`organo_id`) REFERENCES `programa_organos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_organo_miembro_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Table structure for table `usuarios_biometria`
--

DROP TABLE IF EXISTS `usuarios_biometria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios_biometria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `credential_id` varchar(255) NOT NULL,
  `public_key_pem` text NOT NULL,
  `alias` varchar(100) DEFAULT 'Dispositivo Biom├®trico',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `credential_id` (`credential_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `usuarios_biometria_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuarios_codigos_otp`
--

DROP TABLE IF EXISTS `usuarios_codigos_otp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios_codigos_otp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `codigo` varchar(255) NOT NULL,
  `expira_en` datetime NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `usuarios_codigos_otp_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-17 17:26:05
