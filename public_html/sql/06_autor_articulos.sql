-- =======================================================================
-- 06_autor_articulos.sql — agrega el campo "autor" al blog
-- Seguro de ejecutar una sola vez sobre una instalación que ya tiene
-- Fase 1 + Fase 2 (sql/01 a sql/05) cargados. Solo modifica la
-- estructura de une_articulos, no toca ninguna otra tabla ni borra datos.
-- =======================================================================

ALTER TABLE une_articulos
  ADD COLUMN autor VARCHAR(120) NULL AFTER categoria;
