-- =======================================================================
-- 08_eventos_deportivos.sql — cartelera de eventos nacionales e
-- internacionales (flyer + nota de prensa + enlace del organizador).
-- UNE Sports no organiza estos eventos, solo los difunde.
-- Seguro de ejecutar una sola vez sobre una instalación que ya tiene
-- Fase 1 + Fase 2 (sql/01 a sql/07) cargados.
-- =======================================================================

CREATE TABLE une_eventos_deportivos (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ambito             ENUM('nacional','internacional') NOT NULL,
  titulo             VARCHAR(200) NOT NULL,
  flyer              VARCHAR(255) NULL,
  nota               TEXT NULL,
  enlace_organizador VARCHAR(255) NULL,
  departamento_id    INT UNSIGNED NULL,
  fecha_evento       DATE NULL,
  publicado          TINYINT(1) NOT NULL DEFAULT 0,
  creado_en          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_publicado (publicado, ambito, fecha_evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
