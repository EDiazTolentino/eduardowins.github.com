-- =========================================================================
-- UNE Sports — Esquema de base de datos (MySQL / MariaDB, compatible Hostinger)
-- Importar este archivo completo desde phpMyAdmin (una sola vez) sobre una
-- base de datos vacía. Incluye estructura + datos iniciales de ejemplo.
-- =========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------------
-- Categorías (tipos de negocio: academia de fútbol, natación, etc.)
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS categorias;
CREATE TABLE categorias (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  slug VARCHAR(50) NOT NULL UNIQUE,
  nombre VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Catálogo de referencia de regiones / provincias / distritos (barrios)
-- No está enlazado por FK a "negocios": es solo un catálogo de apoyo para
-- formularios y autocompletado, se puede ampliar libremente.
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS distritos_peru;
CREATE TABLE distritos_peru (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  region VARCHAR(100) NOT NULL,
  provincia VARCHAR(100) NOT NULL,
  distrito VARCHAR(100) NOT NULL,
  UNIQUE KEY uq_distrito (region, provincia, distrito)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Usuarios (dueños de negocio / autores de reseñas registrados / admins)
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS usuarios;
CREATE TABLE usuarios (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) DEFAULT NULL,
  rol ENUM('cliente','propietario','admin') NOT NULL DEFAULT 'cliente',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Negocios (academias, escuelas, centros de rehabilitación/psicología, etc.)
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS negocios;
CREATE TABLE negocios (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  slug VARCHAR(160) NOT NULL UNIQUE,
  nombre VARCHAR(180) NOT NULL,
  categoria_id INT UNSIGNED NOT NULL,
  region VARCHAR(100) NOT NULL,
  provincia VARCHAR(100) NOT NULL,
  distrito VARCHAR(100) NOT NULL,
  direccion VARCHAR(255) NOT NULL,
  telefono VARCHAR(30) DEFAULT NULL,
  whatsapp VARCHAR(30) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  precio ENUM('$','$$','$$$') NOT NULL DEFAULT '$$',
  descripcion TEXT,
  imagen_principal VARCHAR(255) DEFAULT NULL,
  contacto_nombre VARCHAR(150) DEFAULT NULL,
  contacto_cargo VARCHAR(150) DEFAULT NULL,
  contacto_foto VARCHAR(255) DEFAULT NULL,
  destacado TINYINT(1) NOT NULL DEFAULT 0,
  verificado TINYINT(1) NOT NULL DEFAULT 0,
  estado ENUM('pendiente','publicado','rechazado') NOT NULL DEFAULT 'publicado',
  valoracion_promedio DECIMAL(2,1) NOT NULL DEFAULT 0.0,
  total_resenas INT UNSIGNED NOT NULL DEFAULT 0,
  lat DECIMAL(9,6) DEFAULT NULL,
  lng DECIMAL(9,6) DEFAULT NULL,
  usuario_id INT UNSIGNED DEFAULT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_region (region),
  INDEX idx_categoria (categoria_id),
  INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Servicios / especialidades (catálogo) y su relación N:N con negocios
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS negocio_servicios;
DROP TABLE IF EXISTS servicios;
CREATE TABLE servicios (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE negocio_servicios (
  negocio_id INT UNSIGNED NOT NULL,
  servicio_id INT UNSIGNED NOT NULL,
  orden INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (negocio_id, servicio_id),
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Etapas del deporte formativo atendidas (catálogo fijo de 5 rangos de edad)
-- y su relación N:N con negocios
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS negocio_etapas;
DROP TABLE IF EXISTS etapas;
CREATE TABLE etapas (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(30) NOT NULL UNIQUE,
  orden INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE negocio_etapas (
  negocio_id INT UNSIGNED NOT NULL,
  etapa_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (negocio_id, etapa_id),
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (etapa_id) REFERENCES etapas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Galería de imágenes por negocio
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS negocio_imagenes;
CREATE TABLE negocio_imagenes (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  negocio_id INT UNSIGNED NOT NULL,
  url VARCHAR(255) NOT NULL,
  orden INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Horario de atención por negocio (filas ordenadas: "Lunes a Viernes", etc.)
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS negocio_horarios;
CREATE TABLE negocio_horarios (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  negocio_id INT UNSIGNED NOT NULL,
  dia VARCHAR(60) NOT NULL,
  hora VARCHAR(60) NOT NULL,
  orden INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Valoraciones / reseñas
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS valoraciones;
CREATE TABLE valoraciones (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  negocio_id INT UNSIGNED NOT NULL,
  usuario_nombre VARCHAR(150) NOT NULL,
  usuario_avatar VARCHAR(255) DEFAULT NULL,
  puntuacion TINYINT UNSIGNED NOT NULL,
  comentario TEXT NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  CONSTRAINT chk_puntuacion CHECK (puntuacion BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Blog
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS blog_articulos;
DROP TABLE IF EXISTS blog_categorias;
CREATE TABLE blog_categorias (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_articulos (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  slug VARCHAR(180) NOT NULL UNIQUE,
  titulo VARCHAR(220) NOT NULL,
  categoria_id INT UNSIGNED NOT NULL,
  resumen VARCHAR(400) DEFAULT NULL,
  imagen VARCHAR(255) DEFAULT NULL,
  autor_nombre VARCHAR(150) DEFAULT NULL,
  autor_foto VARCHAR(255) DEFAULT NULL,
  fecha_publicacion DATE DEFAULT NULL,
  tiempo_lectura VARCHAR(20) DEFAULT NULL,
  -- "contenido" guarda un array JSON de bloques: [{tipo:"parrafo"|"titulo"|"cita"|"lista", texto|items}, ...]
  contenido LONGTEXT NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (categoria_id) REFERENCES blog_categorias(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Mensajes del formulario de contacto
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS mensajes_contacto;
CREATE TABLE mensajes_contacto (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  asunto VARCHAR(150) DEFAULT NULL,
  mensaje TEXT NOT NULL,
  atendido TINYINT(1) NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
