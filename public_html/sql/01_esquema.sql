-- =====================================================================
-- 01_esquema.sql — Esquema principal de UNE Sports Perú
-- Motor InnoDB, utf8mb4_unicode_ci. Ejecutar en phpMyAdmin ANTES que
-- 02_catalogos.sql, 03_ubigeo.sql y 04_semilla.sql (en ese orden).
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = "+00:00";

-- ---------------------------------------------------------------------
-- une_admins — usuarios del backoffice (equipo UNE Sports)
-- ---------------------------------------------------------------------
CREATE TABLE une_admins (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario             VARCHAR(60) UNIQUE NOT NULL,
  password_hash       VARCHAR(255) NOT NULL,
  nombre              VARCHAR(120) NOT NULL,
  rol                 ENUM('administrador','asistente') NOT NULL DEFAULT 'asistente',
  activo              TINYINT(1) NOT NULL DEFAULT 1,
  intentos_fallidos   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta     DATETIME NULL,
  ultimo_acceso       DATETIME NULL,
  creado_en           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- une_negocios — tabla principal (leads y fichas)
-- ---------------------------------------------------------------------
CREATE TABLE une_negocios (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug                VARCHAR(180) UNIQUE NOT NULL,
  tipo_registro       ENUM('formativo','servicio') NOT NULL DEFAULT 'formativo',
  nombre_comercial    VARCHAR(180) NOT NULL,
  razon_social        VARCHAR(200) NULL,
  ruc                 CHAR(11) NULL,
  anio_fundacion      SMALLINT NULL,
  descripcion         TEXT NULL,

  -- Ubicación
  departamento_id     SMALLINT UNSIGNED NOT NULL,
  provincia_id        MEDIUMINT UNSIGNED NULL,
  distrito_id         MEDIUMINT UNSIGNED NULL,
  direccion           VARCHAR(255) NULL,
  referencia          VARCHAR(255) NULL,
  latitud             DECIMAL(10,7) NULL,
  longitud            DECIMAL(10,7) NULL,

  -- Contacto público
  telefono_publico    VARCHAR(20) NULL,
  telefono_publico_2  VARCHAR(20) NULL,
  email_publico       VARCHAR(120) NULL,
  web                 VARCHAR(200) NULL,
  facebook            VARCHAR(200) NULL,
  instagram           VARCHAR(200) NULL,
  tiktok              VARCHAR(200) NULL,
  youtube             VARCHAR(200) NULL,

  -- Contacto PRIVADO (solo administrador, nunca se renderiza en público)
  contacto_nombre     VARCHAR(120) NULL,
  contacto_cargo      VARCHAR(80)  NULL,
  contacto_telefono   VARCHAR(20)  NULL,
  contacto_email      VARCHAR(120) NULL,

  -- Operación
  modalidad           ENUM('presencial','virtual','mixta') DEFAULT 'presencial',
  rango_precio        TINYINT NULL COMMENT '1..4 - se muestra como iconos, nunca cifra',
  precio_mensual_ref  DECIMAL(8,2) NULL COMMENT 'PRIVADO: solo para calcular el rango',
  tiene_matricula     TINYINT(1) DEFAULT 0,
  ofrece_beca         TINYINT(1) DEFAULT 0,
  clase_prueba_gratis TINYINT(1) DEFAULT 0,
  capacidad_alumnos   SMALLINT NULL,
  alumnos_actuales    SMALLINT NULL,
  num_entrenadores    SMALLINT NULL,
  atiende_genero      ENUM('mixto','femenino','masculino') DEFAULT 'mixto',

  -- Confianza y salvaguarda infantil
  local_propio          TINYINT(1) DEFAULT 0,
  seguro_accidentes     TINYINT(1) DEFAULT 0,
  protocolo_salvaguarda TINYINT(1) DEFAULT 0,
  personal_certificado  TINYINT(1) DEFAULT 0,
  requiere_examen_medico TINYINT(1) DEFAULT 0,
  afiliacion_federacion VARCHAR(200) NULL,

  -- Medios
  logo                VARCHAR(255) NULL,

  -- Pipeline de captación y gestión comercial
  estado              ENUM('lead','en_gestion','en_revision','publicado','rechazado','duplicado','no_contactable') DEFAULT 'lead',
  origen              ENUM('captura_rapida','sugerencia','importacion','alta_admin','reclamo') NOT NULL,
  verificado          TINYINT(1) DEFAULT 0,
  fecha_verificacion  DATETIME NULL,
  completitud         TINYINT UNSIGNED DEFAULT 0 COMMENT '0-100, recalculado al guardar',
  admin_asignado_id   INT UNSIGNED NULL,
  resultado_contacto  ENUM('sin_contactar','no_contesta','numero_errado','interesado','en_espera','rechazo') DEFAULT 'sin_contactar',
  intentos_contacto   TINYINT UNSIGNED DEFAULT 0,
  ultimo_contacto     DATETIME NULL,
  proximo_seguimiento DATE NULL,
  notas_internas      TEXT NULL,
  token_edicion       CHAR(48) NULL,
  utm_source          VARCHAR(60) NULL,
  utm_campaign        VARCHAR(60) NULL,
  ip_registro         VARBINARY(16) NULL,
  vistas              INT UNSIGNED DEFAULT 0,
  clics_contacto      INT UNSIGNED DEFAULT 0,

  -- Bloqueo de edición concurrente (§7E)
  editando_por        INT UNSIGNED NULL,
  editando_desde      DATETIME NULL,

  creado_en           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado_en      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_ubic (departamento_id, provincia_id, distrito_id),
  INDEX idx_estado (estado, verificado),
  INDEX idx_pipeline (estado, proximo_seguimiento, admin_asignado_id),
  INDEX idx_telefono (telefono_publico),
  INDEX idx_tipo (tipo_registro),
  INDEX idx_geo (latitud, longitud),
  INDEX idx_slug (slug),
  INDEX idx_token_edicion (token_edicion),
  FULLTEXT KEY ft_busqueda (nombre_comercial, descripcion, direccion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- une_lead_historial — bitácora de gestión comercial de cada lead/ficha
-- ---------------------------------------------------------------------
CREATE TABLE une_lead_historial (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT UNSIGNED NOT NULL,
  admin_id    INT UNSIGNED NULL,
  accion      ENUM('creado','llamada','whatsapp','correo','visita','cambio_estado','ficha_actualizada') NOT NULL,
  resultado   VARCHAR(60) NULL,
  nota        TEXT NULL,
  creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_negocio (negocio_id, creado_en),
  INDEX idx_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- une_horarios
-- ---------------------------------------------------------------------
CREATE TABLE une_horarios (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT UNSIGNED NOT NULL,
  dia_semana  TINYINT UNSIGNED NOT NULL COMMENT '1=lunes .. 7=domingo',
  turno       ENUM('mañana','tarde','noche') NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin    TIME NOT NULL,
  INDEX idx_negocio (negocio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- une_imagenes — galería (máx. 8 por negocio, validado en PHP)
-- ---------------------------------------------------------------------
CREATE TABLE une_imagenes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT UNSIGNED NOT NULL,
  archivo     VARCHAR(255) NOT NULL,
  alt         VARCHAR(180) NULL,
  orden       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_negocio (negocio_id, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- une_sugerencias — vía 3 de captación: sugerencia ciudadana
-- ---------------------------------------------------------------------
CREATE TABLE une_sugerencias (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre_lugar   VARCHAR(180) NOT NULL,
  distrito_id    MEDIUMINT UNSIGNED NULL,
  deporte_id     SMALLINT UNSIGNED NULL,
  contacto_dato  VARCHAR(180) NULL COMMENT 'teléfono o red social, si lo conoce',
  comentario     TEXT NULL,
  procesada      TINYINT(1) NOT NULL DEFAULT 0,
  negocio_id     INT UNSIGNED NULL COMMENT 'lead creado a partir de esta sugerencia',
  ip_registro    VARBINARY(16) NULL,
  creado_en      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_procesada (procesada, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- une_articulos — blog (fase 3, tabla creada desde ya)
-- ---------------------------------------------------------------------
CREATE TABLE une_articulos (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo           VARCHAR(200) NOT NULL,
  slug             VARCHAR(220) UNIQUE NOT NULL,
  resumen          VARCHAR(300) NULL,
  contenido        LONGTEXT NOT NULL,
  imagen           VARCHAR(255) NULL,
  categoria        VARCHAR(80) NULL,
  meta_titulo      VARCHAR(160) NULL,
  meta_descripcion VARCHAR(200) NULL,
  publicado        TINYINT(1) NOT NULL DEFAULT 0,
  fecha            DATE NULL,
  creado_en        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_publicado (publicado, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- une_equipo — sección Nosotros (fase 3)
-- ---------------------------------------------------------------------
CREATE TABLE une_equipo (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre    VARCHAR(120) NOT NULL,
  cargo     VARCHAR(120) NULL,
  bio       TEXT NULL,
  foto      VARCHAR(255) NULL,
  linkedin  VARCHAR(200) NULL,
  orden     TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- une_eventos — analítica interna
-- ---------------------------------------------------------------------
CREATE TABLE une_eventos (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo       ENUM('vista_ficha','clic_telefono','clic_whatsapp','clic_compartir','inicio_registro','paso_completado','registro_enviado') NOT NULL,
  negocio_id INT UNSIGNED NULL,
  metadata   JSON NULL,
  creado_en  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tipo (tipo, creado_en),
  INDEX idx_negocio (negocio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- une_solicitudes_contacto — formulario de /contacto y de solicitud de
-- información de /servicios (fase 3, tabla creada desde ya)
-- ---------------------------------------------------------------------
CREATE TABLE une_solicitudes_contacto (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre     VARCHAR(120) NOT NULL,
  correo     VARCHAR(120) NOT NULL,
  asunto     VARCHAR(180) NULL,
  mensaje    TEXT NOT NULL,
  origen     VARCHAR(40) NOT NULL DEFAULT 'contacto',
  atendida   TINYINT(1) NOT NULL DEFAULT 0,
  ip_registro VARBINARY(16) NULL,
  creado_en  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- une_solicitudes_retiro — "Solicitar corrección o retiro de esta ficha"
-- (§10, salvaguarda legal para fichas no verificadas)
-- ---------------------------------------------------------------------
CREATE TABLE une_solicitudes_retiro (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT UNSIGNED NOT NULL,
  nombre      VARCHAR(120) NOT NULL,
  correo      VARCHAR(120) NOT NULL,
  motivo      ENUM('corregir_datos','retirar_ficha','no_soy_responsable','otro') NOT NULL,
  mensaje     TEXT NULL,
  atendida    TINYINT(1) NOT NULL DEFAULT 0,
  creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_negocio (negocio_id),
  INDEX idx_atendida (atendida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- une_reclamos — vía 5 de captación: reclamo de ficha
-- ---------------------------------------------------------------------
CREATE TABLE une_reclamos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT UNSIGNED NOT NULL,
  nombre      VARCHAR(120) NOT NULL,
  telefono    VARCHAR(20) NOT NULL,
  correo      VARCHAR(120) NULL,
  estado      ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_negocio (negocio_id),
  INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
