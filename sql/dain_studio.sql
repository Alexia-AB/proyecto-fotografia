-- ==========================================================================
-- DAIN Studio - Sprint 3 (incluye lo del Sprint 2)
-- Base de datos: dain_studio
-- Importar desde phpMyAdmin (pestaña "Importar") o pegar en la pestaña "SQL".
-- OJO: este script BORRA y recrea las 3 tablas (los datos actuales se pierden).
-- ==========================================================================

CREATE DATABASE IF NOT EXISTS dain_studio
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE dain_studio;

-- Se borran primero las tablas hijas (por las llaves foráneas)
DROP TABLE IF EXISTS tb_solicitudes;
DROP TABLE IF EXISTS tb_servicios;
DROP TABLE IF EXISTS tb_clientes;
DROP TABLE IF EXISTS tb_detalle_usuarios;
DROP TABLE IF EXISTS tb_usuarios;

-- --------------------------------------------------------------------------
-- TB_USUARIOS: credenciales y rol
-- Roles: 1 ADMIN | 2 PROGRAMADOR | 3 USUARIA_TEST | 4 USU_GRAL (cliente)
-- --------------------------------------------------------------------------
CREATE TABLE tb_usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    usuario    VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,            -- hash (password_hash)
    rol        ENUM('ADMIN','PROGRAMADOR','USUARIA_TEST','USU_GRAL')
               NOT NULL DEFAULT 'USU_GRAL',
    activo     TINYINT(1) NOT NULL DEFAULT 1             -- 1 = activo, 0 = desactivado
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- TB_DETALLE_USUARIOS: datos generales de TODOS los usuarios
-- --------------------------------------------------------------------------
CREATE TABLE tb_detalle_usuarios (
    id_detalle      INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario      INT NOT NULL,
    nombre_completo VARCHAR(100) NOT NULL,
    email           VARCHAR(100) NOT NULL UNIQUE,
    telefono        VARCHAR(20),
    fecha_registro  DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_detalle_usuario
        FOREIGN KEY (id_usuario) REFERENCES tb_usuarios(id_usuario)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- TB_CLIENTES: datos específicos de los clientes (rol USU_GRAL)
-- --------------------------------------------------------------------------
CREATE TABLE tb_clientes (
    id_cliente      INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario      INT NOT NULL,
    nombre_completo VARCHAR(100) NOT NULL,
    email           VARCHAR(100) NOT NULL,
    telefono        VARCHAR(20),
    direccion       VARCHAR(255),
    fecha_registro  DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cliente_usuario
        FOREIGN KEY (id_usuario) REFERENCES tb_usuarios(id_usuario)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- TB_SERVICIOS: catálogo de servicios que administra el ADMIN (Sprint 3)
-- --------------------------------------------------------------------------
CREATE TABLE tb_servicios (
    id_servicio    INT AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(100) NOT NULL,
    descripcion    VARCHAR(255),
    cantidad       INT NOT NULL DEFAULT 1,
    precio         DECIMAL(10,2) NOT NULL,
    visible        TINYINT(1) NOT NULL DEFAULT 1,        -- 1 = publicado, 0 = oculto
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- TB_SOLICITUDES: servicios que piden los clientes (se usa desde el Sprint 4)
-- ON DELETE RESTRICT: un servicio ya solicitado no se puede borrar, solo ocultar
-- --------------------------------------------------------------------------
CREATE TABLE tb_solicitudes (
    id_solicitud    INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario      INT NOT NULL,
    id_servicio     INT NOT NULL,
    cantidad        INT NOT NULL DEFAULT 1,
    fecha_solicitud DATETIME DEFAULT CURRENT_TIMESTAMP,
    estatus         ENUM('SOLICITADO','EN_PROCESO','FINALIZADO') NOT NULL DEFAULT 'SOLICITADO',
    CONSTRAINT fk_solicitud_usuario
        FOREIGN KEY (id_usuario)  REFERENCES tb_usuarios(id_usuario)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_solicitud_servicio
        FOREIGN KEY (id_servicio) REFERENCES tb_servicios(id_servicio)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- DATOS DE PRUEBA (las contraseñas ya están en hash bcrypt)
--   admin   / dain2026     -> rol ADMIN
--   cliente / cliente2026  -> rol USU_GRAL
-- --------------------------------------------------------------------------
INSERT INTO tb_usuarios (usuario, password, rol) VALUES
('admin',   '$2b$10$ztzDNduFp1qgIZVqS0MDpOr7dcrKTwe7jOkSNTKzptRouxRfdXa3K', 'ADMIN'),
('cliente', '$2b$10$yxzr.AcMzRKTnv.Jw2SD5.p8iQhY4pL3mVKEsZfIEn6S0Lvn8lobm', 'USU_GRAL');

INSERT INTO tb_detalle_usuarios (id_usuario, nombre_completo, email, telefono) VALUES
(1, 'Administrador DAIN',  'admin@dainstudio.mx',   '2223334455'),
(2, 'Cliente de Prueba',   'cliente@ejemplo.com',   '2221112233');

INSERT INTO tb_clientes (id_usuario, nombre_completo, email, telefono, direccion) VALUES
(2, 'Cliente de Prueba', 'cliente@ejemplo.com', '2221112233', 'Puebla, Pue.');

-- Servicios de prueba (cantidad = número que se muestra en la tarjeta, p. ej. fotografías incluidas)
INSERT INTO tb_servicios (nombre, descripcion, cantidad, precio, visible) VALUES
('Fotografía de producto',   'Fotos para ecommerce, catálogo y redes sociales.',          10, 3500.00, 1),
('Gastronomía y Food Styling','Imágenes para menús digitales y campañas de alimentos.',   15, 5200.00, 1),
('Retrato profesional',      'Foto para LinkedIn, perfiles y equipos corporativos.',       5, 1800.00, 1),
('Arquitectura e Interiorismo','Reportaje fotográfico de espacios comerciales.',           20, 7500.00, 0);
