-- =======================================================================
-- 07_autor_url_articulos.sql — agrega el enlace de perfil del autor
-- Requiere haber corrido antes sql/06_autor_articulos.sql (columna
-- autor). Seguro de ejecutar una sola vez; no borra datos.
-- =======================================================================

ALTER TABLE une_articulos
  ADD COLUMN autor_url VARCHAR(255) NULL AFTER autor;
