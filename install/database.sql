-- ============================================
-- TrazaFI - Database Schema
-- Facultad de Ingeniería UAEMEX
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================
-- TABLA: usuarios
-- ============================================
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `apellidos` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `email_verificado` TINYINT(1) NOT NULL DEFAULT 0,
  `codigo_verificacion` VARCHAR(6) DEFAULT NULL,
  `codigo_expiracion` DATETIME DEFAULT NULL,
  `imagen_perfil` VARCHAR(255) DEFAULT NULL,
  `biografia` TEXT DEFAULT NULL,
  `fecha_registro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_acceso` DATETIME DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `es_admin` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `email_verificado` (`email_verificado`),
  KEY `activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: grupos
-- ============================================
CREATE TABLE IF NOT EXISTS `grupos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(200) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `tipo_grupo` ENUM('departamento', 'licenciatura', 'posgrado', 'capitulo') NOT NULL,
  `imagen_portada` VARCHAR(255) DEFAULT NULL,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `tipo_grupo` (`tipo_grupo`),
  KEY `activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: permisos
-- ============================================
CREATE TABLE IF NOT EXISTS `permisos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `clave` VARCHAR(100) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `categoria` VARCHAR(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: roles
-- ============================================
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `clave` VARCHAR(100) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `es_sistema` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: rol_permisos (relación roles-permisos)
-- ============================================
CREATE TABLE IF NOT EXISTS `rol_permisos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rol_id` INT UNSIGNED NOT NULL,
  `permiso_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rol_permiso_unico` (`rol_id`, `permiso_id`),
  KEY `rol_id` (`rol_id`),
  KEY `permiso_id` (`permiso_id`),
  CONSTRAINT `fk_rol_permisos_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rol_permisos_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: grupo_miembros (membresías)
-- ============================================
CREATE TABLE IF NOT EXISTS `grupo_miembros` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grupo_id` INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `rol_id` INT UNSIGNED DEFAULT NULL,
  `es_coordinador` TINYINT(1) NOT NULL DEFAULT 0,
  `estado` ENUM('pendiente', 'aprobado', 'rechazado') NOT NULL DEFAULT 'pendiente',
  `fecha_solicitud` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_aprobacion` DATETIME DEFAULT NULL,
  `aprobado_por` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grupo_usuario_unico` (`grupo_id`, `usuario_id`),
  KEY `grupo_id` (`grupo_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `rol_id` (`rol_id`),
  KEY `estado` (`estado`),
  CONSTRAINT `fk_grupo_miembros_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grupo_miembros_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grupo_miembros_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_grupo_miembros_aprobador` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: avisos
-- ============================================
CREATE TABLE IF NOT EXISTS `avisos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(255) NOT NULL,
  `contenido` TEXT NOT NULL,
  `grupo_id` INT UNSIGNED DEFAULT NULL,
  `autor_id` INT UNSIGNED NOT NULL,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_inicio_publicacion` DATETIME DEFAULT NULL,
  `fecha_fin_publicacion` DATETIME DEFAULT NULL,
  `publicado` TINYINT(1) NOT NULL DEFAULT 0,
  `destacado` TINYINT(1) NOT NULL DEFAULT 0,
  `categoria` VARCHAR(50) DEFAULT NULL,
  `etiquetas` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `grupo_id` (`grupo_id`),
  KEY `autor_id` (`autor_id`),
  KEY `publicado` (`publicado`),
  KEY `fecha_inicio_publicacion` (`fecha_inicio_publicacion`),
  KEY `fecha_fin_publicacion` (`fecha_fin_publicacion`),
  CONSTRAINT `fk_avisos_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_avisos_autor` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: aviso_archivos
-- ============================================
CREATE TABLE IF NOT EXISTS `aviso_archivos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `aviso_id` INT UNSIGNED NOT NULL,
  `nombre_archivo` VARCHAR(255) NOT NULL,
  `ruta_archivo` VARCHAR(500) NOT NULL,
  `tipo_archivo` VARCHAR(100) DEFAULT NULL,
  `tamano_bytes` INT UNSIGNED DEFAULT NULL,
  `fecha_subida` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `aviso_id` (`aviso_id`),
  CONSTRAINT `fk_aviso_archivos_aviso` FOREIGN KEY (`aviso_id`) REFERENCES `avisos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: historicos
-- ============================================
CREATE TABLE IF NOT EXISTS `historicos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `aviso_original_id` INT UNSIGNED DEFAULT NULL,
  `titulo` VARCHAR(255) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `grupo_id` INT UNSIGNED DEFAULT NULL,
  `creado_por` INT UNSIGNED NOT NULL,
  `fecha_evento` DATE DEFAULT NULL,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `metadatos` JSON DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `aviso_original_id` (`aviso_original_id`),
  KEY `grupo_id` (`grupo_id`),
  KEY `creado_por` (`creado_por`),
  KEY `fecha_evento` (`fecha_evento`),
  CONSTRAINT `fk_historicos_aviso` FOREIGN KEY (`aviso_original_id`) REFERENCES `avisos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_historicos_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_historicos_usuario` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: historico_evidencias
-- ============================================
CREATE TABLE IF NOT EXISTS `historico_evidencias` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `historico_id` INT UNSIGNED NOT NULL,
  `titulo` VARCHAR(255) DEFAULT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `tipo` ENUM('imagen', 'video', 'documento', 'audio', 'otro') NOT NULL,
  `ruta_archivo` VARCHAR(500) NOT NULL,
  `subido_por` INT UNSIGNED NOT NULL,
  `fecha_subida` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `historico_id` (`historico_id`),
  KEY `subido_por` (`subido_por`),
  CONSTRAINT `fk_historico_evidencias_historico` FOREIGN KEY (`historico_id`) REFERENCES `historicos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_historico_evidencias_usuario` FOREIGN KEY (`subido_por`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: categorias_propuesta
-- ============================================
CREATE TABLE IF NOT EXISTS `categorias_propuesta` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `color` VARCHAR(7) DEFAULT '#0099ff',
  `icono` VARCHAR(50) DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `orden` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: propuestas
-- ============================================
CREATE TABLE IF NOT EXISTS `propuestas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(255) NOT NULL,
  `descripcion` TEXT NOT NULL,
  `categoria_id` INT UNSIGNED NOT NULL,
  `autor_id` INT UNSIGNED NOT NULL,
  `estado` ENUM('votacion', 'revision', 'en_progreso', 'completada', 'rechazada', 'archivada') NOT NULL DEFAULT 'votacion',
  `umbral_firmas` INT UNSIGNED NOT NULL DEFAULT 200,
  `contador_firmas` INT UNSIGNED NOT NULL DEFAULT 0,
  `grupo_asignado` INT UNSIGNED DEFAULT NULL,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_umbral_alcanzado` DATETIME DEFAULT NULL,
  `fecha_completada` DATETIME DEFAULT NULL,
  `visibilidad` ENUM('publica', 'grupo') NOT NULL DEFAULT 'publica',
  PRIMARY KEY (`id`),
  KEY `categoria_id` (`categoria_id`),
  KEY `autor_id` (`autor_id`),
  KEY `estado` (`estado`),
  KEY `grupo_asignado` (`grupo_asignado`),
  CONSTRAINT `fk_propuestas_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_propuesta` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_propuestas_autor` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_propuestas_grupo` FOREIGN KEY (`grupo_asignado`) REFERENCES `grupos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: propuesta_firmas
-- ============================================
CREATE TABLE IF NOT EXISTS `propuesta_firmas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `propuesta_id` INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `fecha_firma` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `propuesta_usuario_unico` (`propuesta_id`, `usuario_id`),
  KEY `propuesta_id` (`propuesta_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `fk_propuesta_firmas_propuesta` FOREIGN KEY (`propuesta_id`) REFERENCES `propuestas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_propuesta_firmas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: comisiones
-- ============================================
CREATE TABLE IF NOT EXISTS `comisiones` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `propuesta_id` INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `asignado_por` INT UNSIGNED NOT NULL,
  `estado` ENUM('pendiente', 'aceptada', 'rechazada') NOT NULL DEFAULT 'pendiente',
  `fecha_asignacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_respuesta` DATETIME DEFAULT NULL,
  `fecha_expiracion` DATETIME DEFAULT NULL,
  `notas` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `propuesta_usuario_unico` (`propuesta_id`, `usuario_id`),
  KEY `propuesta_id` (`propuesta_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `asignado_por` (`asignado_por`),
  KEY `estado` (`estado`),
  CONSTRAINT `fk_comisiones_propuesta` FOREIGN KEY (`propuesta_id`) REFERENCES `propuestas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comisiones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comisiones_asignador` FOREIGN KEY (`asignado_por`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: propuesta_seguimientos
-- ============================================
CREATE TABLE IF NOT EXISTS `propuesta_seguimientos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `propuesta_id` INT UNSIGNED NOT NULL,
  `titulo` VARCHAR(255) NOT NULL,
  `descripcion` TEXT NOT NULL,
  `autor_id` INT UNSIGNED NOT NULL,
  `es_publico` TINYINT(1) NOT NULL DEFAULT 1,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `propuesta_id` (`propuesta_id`),
  KEY `autor_id` (`autor_id`),
  CONSTRAINT `fk_propuesta_seguimientos_propuesta` FOREIGN KEY (`propuesta_id`) REFERENCES `propuestas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_propuesta_seguimientos_autor` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: propuesta_seguimiento_archivos
-- ============================================
CREATE TABLE IF NOT EXISTS `propuesta_seguimiento_archivos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seguimiento_id` INT UNSIGNED NOT NULL,
  `nombre_archivo` VARCHAR(255) NOT NULL,
  `ruta_archivo` VARCHAR(500) NOT NULL,
  `es_publico` TINYINT(1) NOT NULL DEFAULT 1,
  `fecha_subida` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `seguimiento_id` (`seguimiento_id`),
  CONSTRAINT `fk_propuesta_seguimiento_archivos` FOREIGN KEY (`seguimiento_id`) REFERENCES `propuesta_seguimientos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: comentarios
-- ============================================
CREATE TABLE IF NOT EXISTS `comentarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contenido` TEXT NOT NULL,
  `autor_id` INT UNSIGNED NOT NULL,
  `es_anonimo` TINYINT(1) NOT NULL DEFAULT 0,
  `tipo_contenido` ENUM('aviso', 'propuesta', 'historico') NOT NULL,
  `contenido_id` INT UNSIGNED NOT NULL,
  `comentario_padre_id` INT UNSIGNED DEFAULT NULL,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_edicion` DATETIME DEFAULT NULL,
  `editado` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `autor_id` (`autor_id`),
  KEY `tipo_contenido` (`tipo_contenido`, `contenido_id`),
  KEY `comentario_padre_id` (`comentario_padre_id`),
  CONSTRAINT `fk_comentarios_autor` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comentarios_padre` FOREIGN KEY (`comentario_padre_id`) REFERENCES `comentarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: likes
-- ============================================
CREATE TABLE IF NOT EXISTS `likes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED NOT NULL,
  `tipo_contenido` ENUM('aviso', 'propuesta', 'comentario', 'historico') NOT NULL,
  `contenido_id` INT UNSIGNED NOT NULL,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_contenido_unico` (`usuario_id`, `tipo_contenido`, `contenido_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `tipo_contenido` (`tipo_contenido`, `contenido_id`),
  CONSTRAINT `fk_likes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: tickets
-- ============================================
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(255) NOT NULL,
  `descripcion` TEXT NOT NULL,
  `grupo_id` INT UNSIGNED NOT NULL,
  `solicitante_id` INT UNSIGNED NOT NULL,
  `estado` ENUM('pendiente', 'en_desarrollo', 'completado', 'rechazado') NOT NULL DEFAULT 'pendiente',
  `prioridad` ENUM('baja', 'media', 'alta', 'urgente') NOT NULL DEFAULT 'media',
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_completado` DATETIME DEFAULT NULL,
  `notas_admin` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `grupo_id` (`grupo_id`),
  KEY `solicitante_id` (`solicitante_id`),
  KEY `estado` (`estado`),
  CONSTRAINT `fk_tickets_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tickets_solicitante` FOREIGN KEY (`solicitante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: modulos_personalizados
-- ============================================
CREATE TABLE IF NOT EXISTS `modulos_personalizados` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `carpeta` VARCHAR(100) NOT NULL,
  `icono` VARCHAR(50) DEFAULT NULL,
  `color` VARCHAR(7) DEFAULT '#0099ff',
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: grupo_modulos (activación de módulos por grupo)
-- ============================================
CREATE TABLE IF NOT EXISTS `grupo_modulos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grupo_id` INT UNSIGNED NOT NULL,
  `modulo_id` INT UNSIGNED NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `configuracion` JSON DEFAULT NULL,
  `fecha_activacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grupo_modulo_unico` (`grupo_id`, `modulo_id`),
  KEY `grupo_id` (`grupo_id`),
  KEY `modulo_id` (`modulo_id`),
  CONSTRAINT `fk_grupo_modulos_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grupo_modulos_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `modulos_personalizados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: encuestas
-- ============================================
CREATE TABLE IF NOT EXISTS `encuestas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(255) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `grupo_id` INT UNSIGNED DEFAULT NULL,
  `creador_id` INT UNSIGNED NOT NULL,
  `fecha_inicio` DATETIME NOT NULL,
  `fecha_fin` DATETIME NOT NULL,
  `activa` TINYINT(1) NOT NULL DEFAULT 1,
  `resultados_publicos` TINYINT(1) NOT NULL DEFAULT 1,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `grupo_id` (`grupo_id`),
  KEY `creador_id` (`creador_id`),
  KEY `activa` (`activa`),
  CONSTRAINT `fk_encuestas_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_encuestas_creador` FOREIGN KEY (`creador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: encuesta_preguntas
-- ============================================
CREATE TABLE IF NOT EXISTS `encuesta_preguntas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `encuesta_id` INT UNSIGNED NOT NULL,
  `pregunta` TEXT NOT NULL,
  `tipo` ENUM('opcion_simple', 'opcion_multiple', 'abierta') NOT NULL,
  `opciones` JSON DEFAULT NULL,
  `requerida` TINYINT(1) NOT NULL DEFAULT 1,
  `orden` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `encuesta_id` (`encuesta_id`),
  CONSTRAINT `fk_encuesta_preguntas_encuesta` FOREIGN KEY (`encuesta_id`) REFERENCES `encuestas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: encuesta_respuestas
-- ============================================
CREATE TABLE IF NOT EXISTS `encuesta_respuestas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pregunta_id` INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `respuesta` TEXT NOT NULL,
  `fecha_respuesta` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pregunta_id` (`pregunta_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `fk_encuesta_respuestas_pregunta` FOREIGN KEY (`pregunta_id`) REFERENCES `encuesta_preguntas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_encuesta_respuestas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: notificaciones
-- ============================================
CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED NOT NULL,
  `tipo` VARCHAR(50) NOT NULL,
  `titulo` VARCHAR(255) NOT NULL,
  `mensaje` TEXT NOT NULL,
  `url` VARCHAR(500) DEFAULT NULL,
  `leida` TINYINT(1) NOT NULL DEFAULT 0,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_leida` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `leida` (`leida`),
  KEY `fecha_creacion` (`fecha_creacion`),
  CONSTRAINT `fk_notificaciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: configuracion_sistema
-- ============================================
CREATE TABLE IF NOT EXISTS `configuracion_sistema` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clave` VARCHAR(100) NOT NULL,
  `valor` TEXT DEFAULT NULL,
  `tipo` ENUM('texto', 'numero', 'boolean', 'json') NOT NULL DEFAULT 'texto',
  `descripcion` TEXT DEFAULT NULL,
  `fecha_modificacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DATOS INICIALES: Permisos del Sistema
-- ============================================
INSERT INTO `permisos` (`nombre`, `clave`, `descripcion`, `categoria`) VALUES
('Crear avisos', 'puede_crear_avisos', 'Permite crear nuevos avisos en grupos', 'avisos'),
('Editar avisos propios', 'puede_editar_avisos_propios', 'Permite editar avisos creados por el usuario', 'avisos'),
('Eliminar avisos propios', 'puede_eliminar_avisos_propios', 'Permite eliminar avisos creados por el usuario', 'avisos'),
('Moderar comentarios', 'puede_moderar_comentarios', 'Permite moderar comentarios en avisos del grupo', 'comentarios'),
('Aprobar usuarios', 'puede_aprobar_usuarios', 'Permite aprobar solicitudes de ingreso a grupos', 'usuarios'),
('Gestionar roles', 'puede_gestionar_roles', 'Permite asignar y modificar roles de usuarios', 'usuarios'),
('Ver comentarios anonimos', 'puede_ver_autor_comentarios_anonimos', 'Permite ver el autor real de comentarios anónimos', 'comentarios'),
('Crear propuestas', 'puede_crear_propuestas', 'Permite crear propuestas comunitarias', 'propuestas'),
('Gestionar propuestas', 'puede_gestionar_propuestas', 'Permite asignar comisiones y cambiar estado de propuestas', 'propuestas'),
('Crear encuestas', 'puede_crear_encuestas', 'Permite crear encuestas para el grupo', 'encuestas'),
('Crear tickets', 'puede_crear_tickets', 'Permite crear tickets de funcionalidades', 'tickets'),
('Gestionar modulos', 'puede_gestionar_modulos', 'Permite activar/desactivar módulos personalizados', 'modulos'),
('Acceder historicos', 'puede_acceder_historicos', 'Permite acceder a la sección de históricos', 'historicos'),
('Subir evidencias', 'puede_subir_evidencias', 'Permite subir evidencias a históricos', 'historicos');

-- ============================================
-- DATOS INICIALES: Roles del Sistema
-- ============================================
INSERT INTO `roles` (`nombre`, `clave`, `descripcion`, `es_sistema`) VALUES
('Miembro', 'miembro', 'Rol básico de miembro de grupo', 1),
('Coordinador', 'coordinador', 'Coordinador de grupo con permisos administrativos', 1),
('Moderador', 'moderador', 'Moderador con permisos de gestión de contenido', 1);

-- ============================================
-- DATOS INICIALES: Asignación de permisos a roles
-- ============================================
INSERT INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
-- Permisos de Miembro (rol_id = 1)
(1, 1),  -- puede_crear_avisos
(1, 2),  -- puede_editar_avisos_propios
(1, 3),  -- puede_eliminar_avisos_propios
(1, 8),  -- puede_crear_propuestas
(1, 13), -- puede_acceder_historicos

-- Permisos de Coordinador (rol_id = 2) - Todos los permisos
(2, 1), (2, 2), (2, 3), (2, 4), (2, 5), (2, 6), (2, 7), (2, 8), (2, 9), (2, 10), (2, 11), (2, 12), (2, 13), (2, 14),

-- Permisos de Moderador (rol_id = 3)
(3, 1),  -- puede_crear_avisos
(3, 2),  -- puede_editar_avisos_propios
(3, 3),  -- puede_eliminar_avisos_propios
(3, 4),  -- puede_moderar_comentarios
(3, 7),  -- puede_ver_autor_comentarios_anonimos
(3, 8),  -- puede_crear_propuestas
(3, 13), -- puede_acceder_historicos
(3, 14); -- puede_subir_evidencias

-- ============================================
-- DATOS INICIALES: Categorías de Propuestas
-- ============================================
INSERT INTO `categorias_propuesta` (`nombre`, `slug`, `descripcion`, `color`, `icono`, `orden`) VALUES
('Académica', 'academica', 'Propuestas relacionadas con aspectos académicos y educativos', '#0099ff', 'fa-graduation-cap', 1),
('Infraestructura', 'infraestructura', 'Propuestas sobre mejoras en infraestructura física', '#00ffaa', 'fa-building', 2),
('Servicios Estudiantiles', 'servicios-estudiantiles', 'Propuestas sobre servicios para estudiantes', '#ffaa00', 'fa-users', 3),
('Vinculación', 'vinculacion', 'Propuestas de vinculación con empresas y sociedad', '#ff4444', 'fa-handshake', 4),
('Bienestar', 'bienestar', 'Propuestas relacionadas con bienestar estudiantil', '#00ff88', 'fa-heart', 5),
('Tecnología', 'tecnologia', 'Propuestas sobre tecnología e innovación', '#0077cc', 'fa-laptop-code', 6),
('Administrativa', 'administrativa', 'Propuestas sobre procesos administrativos', '#666666', 'fa-clipboard-list', 7),
('Extensión', 'extension', 'Propuestas de extensión universitaria y difusión', '#9966ff', 'fa-bullhorn', 8);

-- ============================================
-- DATOS INICIALES: Grupos (Departamentos, Licenciaturas, Posgrados, Capítulos)
-- ============================================

-- Subdirección Académica y Departamentos
INSERT INTO `grupos` (`nombre`, `slug`, `tipo_grupo`, `descripcion`) VALUES
('Subdirección Académica', 'subdireccion-academica', 'departamento', 'Subdirección Académica de la Facultad'),
('Departamento de Propedéuticas', 'depto-propedeuticas', 'departamento', 'Departamento de Propedéuticas'),
('Departamento de Control Escolar', 'depto-control-escolar', 'departamento', 'Departamento de Control Escolar'),
('Departamento de Evaluación Profesional y de Grado', 'depto-evaluacion', 'departamento', 'Departamento de Evaluación Profesional y de Grado'),
('Departamento de Tutoría Académica', 'depto-tutoria', 'departamento', 'Departamento de Tutoría Académica'),

-- Subdirección Administrativa
('Subdirección Administrativa', 'subdireccion-administrativa', 'departamento', 'Subdirección Administrativa de la Facultad'),
('Departamento de Tecnologías de Información y Comunicación', 'depto-tic', 'departamento', 'Departamento de TIC'),
('Departamento de Mantenimiento de Instalaciones', 'depto-mantenimiento', 'departamento', 'Departamento de Mantenimiento'),

-- Dirección y Coordinaciones
('Dirección', 'direccion', 'departamento', 'Dirección de la Facultad de Ingeniería'),
('Coordinación de Planeación', 'coord-planeacion', 'departamento', 'Coordinación de Planeación'),
('Coordinación de Investigación', 'coord-investigacion', 'departamento', 'Coordinación de Investigación'),
('Coordinación de Estudios Avanzados', 'coord-estudios-avanzados', 'departamento', 'Coordinación de Estudios Avanzados'),
('Coordinación de Difusión Cultural', 'coord-difusion-cultural', 'departamento', 'Coordinación de Difusión Cultural'),
('Coordinación de Extensión y Vinculación', 'coord-extension-vinculacion', 'departamento', 'Coordinación de Extensión y Vinculación'),
('Departamento de Educación Continua y a Distancia', 'depto-educacion-continua', 'departamento', 'Departamento de Educación Continua y a Distancia'),
('Coordinación de Inglés', 'coord-ingles', 'departamento', 'Coordinación de Inglés'),

-- Licenciaturas
('Ingeniería Civil', 'ing-civil', 'licenciatura', 'Licenciatura en Ingeniería Civil'),
('Ingeniería Mecánica', 'ing-mecanica', 'licenciatura', 'Licenciatura en Ingeniería Mecánica'),
('Ingeniería en Computación', 'ing-computacion', 'licenciatura', 'Licenciatura en Ingeniería en Computación'),
('Ingeniería en Electrónica', 'ing-electronica', 'licenciatura', 'Licenciatura en Ingeniería en Electrónica'),
('Ingeniería en Sistemas Energéticos Sustentables', 'ing-energia', 'licenciatura', 'Licenciatura en Ingeniería en Sistemas Energéticos Sustentables'),
('Ingeniería en Inteligencia Artificial', 'ing-ia', 'licenciatura', 'Licenciatura en Ingeniería en Inteligencia Artificial'),

-- Posgrados
('Maestría en Ciencias de la Ingeniería', 'mci', 'posgrado', 'Maestría en Ciencias de la Ingeniería'),
('Maestría en Inteligencia Artificial Aplicada', 'mia', 'posgrado', 'Maestría en Inteligencia Artificial Aplicada'),
('Maestría en Movilidad y Transporte', 'mmt', 'posgrado', 'Maestría en Movilidad y Transporte'),
('Doctorado en Ciencias de la Ingeniería', 'dci', 'posgrado', 'Doctorado en Ciencias de la Ingeniería'),
('Doctorado en Ciencia, Tecnología Biomédica y Control', 'dctbc', 'posgrado', 'Doctorado en Ciencia, Tecnología Biomédica y Control'),

-- Capítulos Estudiantiles
('Minibaja SAE', 'baja-sae', 'capitulo', 'Capítulo estudiantil Baja SAE'),
('Canoa de Concreto', 'canoa-c', 'capitulo', 'Capítulo estudiantil Canoa de Concreto'),
('Hyadi Solar Racing Team', 'hyadi', 'capitulo', 'Capítulo estudiantil HYADI Solar Racing'),
('American Concrete Institute', 'aci-uaemex', 'capitulo', 'Capítulo estudiantil ACI UAEMEX'),
('Rama IEEE UAEMEX', 'ieee-uaemex', 'capitulo', 'Rama IEEE UAEMEX'),
('Robotics and Automation Society', 'ras', 'capitulo', 'Capítulo RAS'),
('Women in Engineering', 'wie', 'capitulo', 'Capítulo Women in Engineering'),
('Sociedad Aeroespacial', 'safi', 'capitulo', 'Sociedad Aeroespacial SAFI'),
('Puente de Acero', 'asce', 'capitulo', 'Capítulo ASCE - Puente de Acero'),
('POTROSPORTS', 'potrosports', 'capitulo', 'Capítulo POTROSPORTS Formula SAE');

-- ============================================
-- DATOS INICIALES: Configuración del Sistema
-- ============================================
INSERT INTO `configuracion_sistema` (`clave`, `valor`, `tipo`, `descripcion`) VALUES
('umbral_firmas_default', '200', 'numero', 'Umbral de firmas predeterminado para propuestas'),
('dias_aceptar_comision', '4', 'numero', 'Días que tienen los usuarios para aceptar asignación a comisiones'),
('smtp_host', '', 'texto', 'Host del servidor SMTP'),
('smtp_port', '587', 'numero', 'Puerto del servidor SMTP'),
('smtp_usuario', 'contacto@fingenieria.mx', 'texto', 'Usuario SMTP para envío de emails'),
('smtp_password', '', 'texto', 'Contraseña SMTP'),
('smtp_from_name', 'TrazaFI - Facultad de Ingeniería UAEMEX', 'texto', 'Nombre del remitente de emails'),
('nombre_sitio', 'TrazaFI', 'texto', 'Nombre de la plataforma'),
('url_sitio', 'https://fingenieria.mx', 'texto', 'URL base del sitio'),
('tamano_maximo_archivo', '5242880', 'numero', 'Tamaño máximo de archivo en bytes (5MB)');

COMMIT;
