-- =====================================================================
-- 02_catalogos.sql — Categorías de negocio, deportes y etapas (§12)
-- Ejecutar después de 01_esquema.sql.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- une_categorias — tipos de negocio (formativo / servicio)
-- ---------------------------------------------------------------------
CREATE TABLE une_categorias (
  id             SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre         VARCHAR(120) NOT NULL,
  slug           VARCHAR(140) UNIQUE NOT NULL,
  tipo_registro  ENUM('formativo','servicio') NOT NULL,
  orden          SMALLINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `une_categorias` (`id`, `nombre`, `slug`, `tipo_registro`, `orden`) VALUES
(1, 'Academia deportiva', 'academia-deportiva', 'formativo', 1),
(2, 'Escuela deportiva', 'escuela-deportiva', 'formativo', 2),
(3, 'Club deportivo', 'club-deportivo', 'formativo', 3),
(4, 'Centro de alto rendimiento formativo', 'centro-alto-rendimiento-formativo', 'formativo', 4),
(5, 'Rehabilitación y Fisioterapia Deportiva', 'rehabilitacion-fisioterapia-deportiva', 'servicio', 1),
(6, 'Medicina Deportiva Pediátrica', 'medicina-deportiva-pediatrica', 'servicio', 2),
(7, 'Nutrición y Dietética Deportiva', 'nutricion-dietetica-deportiva', 'servicio', 3),
(8, 'Laboratorio de Biomecánica Deportiva', 'laboratorio-biomecanica-deportiva', 'servicio', 4),
(9, 'Psicología Deportiva', 'psicologia-deportiva', 'servicio', 5),
(10, 'Coaching Deportivo y Liderazgo', 'coaching-deportivo-liderazgo', 'servicio', 6),
(11, 'Organización de Intervención Familiar', 'organizacion-intervencion-familiar', 'servicio', 7),
(12, 'Tutoría y Nivelación Académica', 'tutoria-nivelacion-academica', 'servicio', 8),
(13, 'Agencia de Protección Infantil', 'agencia-proteccion-infantil', 'servicio', 9),
(14, 'Derecho Deportivo', 'derecho-deportivo', 'servicio', 10);

-- ---------------------------------------------------------------------
-- une_deportes — disciplinas deportivas, agrupadas por familia (§12.2)
-- `orden` bajo (0-9) = disciplinas más frecuentes en el Perú; se usan
-- también en el selector corto de triaje del formulario público (§7A).
-- ---------------------------------------------------------------------
CREATE TABLE une_deportes (
  id      SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre  VARCHAR(80) NOT NULL,
  slug    VARCHAR(100) UNIQUE NOT NULL,
  grupo   VARCHAR(60) NOT NULL,
  icono   VARCHAR(10) NOT NULL DEFAULT '🏅',
  orden   SMALLINT UNSIGNED NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `une_deportes` (`id`, `nombre`, `slug`, `grupo`, `icono`, `orden`) VALUES
(1, 'Artes marciales (a especificar)', 'artes-marciales-a-especificar', 'Combate', '🥋', 101),
(2, 'Fútbol', 'futbol', 'Equipo con balón', '⚽', 0),
(3, 'Fútbol sala', 'futbol-sala', 'Equipo con balón', '⚽', 103),
(4, 'Fútbol playa', 'futbol-playa', 'Equipo con balón', '⚽', 104),
(5, 'Fútbol americano', 'futbol-americano', 'Equipo con balón', '🏈', 105),
(6, 'Flag football', 'flag-football', 'Equipo con balón', '🏈', 106),
(7, 'Baloncesto', 'baloncesto', 'Equipo con balón', '🏀', 3),
(8, 'Balonmano', 'balonmano', 'Equipo con balón', '🤾', 108),
(9, 'Voleibol', 'voleibol', 'Equipo con balón', '🏐', 1),
(10, 'Rugby', 'rugby', 'Equipo con balón', '🏉', 110),
(11, 'Rugby 7', 'rugby-7', 'Equipo con balón', '🏉', 111),
(12, 'Hockey sobre césped', 'hockey-sobre-cesped', 'Equipo con balón', '🏑', 112),
(13, 'Lacrosse', 'lacrosse', 'Equipo con balón', '🥍', 113),
(14, 'Netball', 'netball', 'Equipo con balón', '🏐', 114),
(15, 'Korfbal', 'korfbal', 'Equipo con balón', '🏐', 115),
(16, 'Polo', 'polo', 'Equipo con balón', '🐎', 116),
(17, 'Tenis', 'tenis', 'Raqueta y pared', '🎾', 5),
(18, 'Tenis de mesa', 'tenis-de-mesa', 'Raqueta y pared', '🏓', 118),
(19, 'Bádminton', 'badminton', 'Raqueta y pared', '🏸', 119),
(20, 'Squash', 'squash', 'Raqueta y pared', '🎾', 120),
(21, 'Pádel', 'padel', 'Raqueta y pared', '🎾', 121),
(22, 'Pickleball', 'pickleball', 'Raqueta y pared', '🏓', 122),
(23, 'Racquetball', 'racquetball', 'Raqueta y pared', '🎾', 123),
(24, 'Frontón', 'fronton', 'Raqueta y pared', '🎾', 124),
(25, 'Béisbol', 'beisbol', 'Bate y campo', '⚾', 125),
(26, 'Sóftbol', 'softbol', 'Bate y campo', '⚾', 126),
(27, 'Críquet', 'criquet', 'Bate y campo', '🏏', 127),
(28, 'Judo', 'judo', 'Combate', '🥋', 128),
(29, 'Taekwondo', 'taekwondo', 'Combate', '🥋', 129),
(30, 'Boxeo', 'boxeo', 'Combate', '🥊', 130),
(31, 'Lucha libre', 'lucha-libre', 'Combate', '🤼', 131),
(32, 'Lucha grecorromana', 'lucha-grecorromana', 'Combate', '🤼', 132),
(33, 'Esgrima', 'esgrima', 'Combate', '🤺', 133),
(34, 'MMA', 'mma', 'Combate', '🥊', 134),
(35, 'Jiu-jitsu brasileño', 'jiu-jitsu-brasileno', 'Combate', '🥋', 135),
(36, 'Kickboxing', 'kickboxing', 'Combate', '🥊', 136),
(37, 'Muay thai', 'muay-thai', 'Combate', '🥊', 137),
(38, 'Karate', 'karate', 'Combate', '🥋', 138),
(39, 'Sumo', 'sumo', 'Combate', '🤼', 139),
(40, 'Kung fu', 'kung-fu', 'Combate', '🥋', 140),
(41, 'Capoeira', 'capoeira', 'Combate', '🥋', 141),
(42, 'Atletismo', 'atletismo', 'Atletismo y fuerza', '🏃', 4),
(43, 'Ciclismo de ruta', 'ciclismo-de-ruta', 'Atletismo y fuerza', '🚴', 143),
(44, 'Ciclismo de pista', 'ciclismo-de-pista', 'Atletismo y fuerza', '🚴', 144),
(45, 'MTB', 'mtb', 'Atletismo y fuerza', '🚵', 145),
(46, 'BMX', 'bmx', 'Atletismo y fuerza', '🚲', 146),
(47, 'Halterofilia', 'halterofilia', 'Atletismo y fuerza', '🏋️', 147),
(48, 'Powerlifting', 'powerlifting', 'Atletismo y fuerza', '🏋️', 148),
(49, 'CrossFit', 'crossfit', 'Atletismo y fuerza', '🏋️', 149),
(50, 'Strongman', 'strongman', 'Atletismo y fuerza', '🏋️', 150),
(51, 'Fisicoculturismo', 'fisicoculturismo', 'Atletismo y fuerza', '🏋️', 151),
(52, 'Natación en piscina', 'natacion-en-piscina', 'Acuáticos', '🏊', 2),
(53, 'Natación en aguas abiertas', 'natacion-en-aguas-abiertas', 'Acuáticos', '🏊', 153),
(54, 'Clavados', 'clavados', 'Acuáticos', '🤽', 154),
(55, 'Natación artística', 'natacion-artistica', 'Acuáticos', '🏊', 155),
(56, 'Waterpolo', 'waterpolo', 'Acuáticos', '🤽', 156),
(57, 'Surf', 'surf', 'Acuáticos', '🏄', 157),
(58, 'Vela', 'vela', 'Acuáticos', '⛵', 158),
(59, 'Remo', 'remo', 'Acuáticos', '🚣', 159),
(60, 'Piragüismo / Canotaje', 'piraguismo-canotaje', 'Acuáticos', '🛶', 160),
(61, 'Tiro con arco', 'tiro-con-arco', 'Precisión y gimnásticos', '🏹', 161),
(62, 'Tiro deportivo', 'tiro-deportivo', 'Precisión y gimnásticos', '🎯', 162),
(63, 'Golf', 'golf', 'Precisión y gimnásticos', '⛳', 163),
(64, 'Gimnasia artística', 'gimnasia-artistica', 'Precisión y gimnásticos', '🤸', 164),
(65, 'Gimnasia rítmica', 'gimnasia-ritmica', 'Precisión y gimnásticos', '🤸', 165),
(66, 'Trampolín', 'trampolin', 'Precisión y gimnásticos', '🤸', 166),
(67, 'Motocross', 'motocross', 'Motor', '🏍️', 167),
(68, 'Superbike', 'superbike', 'Motor', '🏍️', 168),
(69, 'Billar (pool, snooker, carambola)', 'billar-pool-snooker-carambola', 'Mesa y mente', '🎱', 169),
(70, 'Dardos', 'dardos', 'Mesa y mente', '🎯', 170),
(71, 'Bolos', 'bolos', 'Mesa y mente', '🎳', 171),
(72, 'Bochas', 'bochas', 'Mesa y mente', '🎳', 172),
(73, 'Ajedrez', 'ajedrez', 'Mesa y mente', '♟️', 173),
(74, 'Damas', 'damas', 'Mesa y mente', '⚫', 174),
(75, 'Go', 'go', 'Mesa y mente', '⚫', 175),
(76, 'Bridge', 'bridge', 'Mesa y mente', '🃏', 176),
(77, 'eSports', 'esports', 'Mesa y mente', '🎮', 177),
(78, 'Escalada deportiva', 'escalada-deportiva', 'Aventura y altura', '🧗', 178),
(79, 'Montañismo / Alpinismo', 'montanismo-alpinismo', 'Aventura y altura', '🏔️', 179),
(80, 'Paracaidismo', 'paracaidismo', 'Aventura y altura', '🪂', 180),
(81, 'Parapente', 'parapente', 'Aventura y altura', '🪂', 181),
(82, 'Vuelo sin motor', 'vuelo-sin-motor', 'Aventura y altura', '🛩️', 182),
(83, 'Salto BASE', 'salto-base', 'Aventura y altura', '🪂', 183),
(84, 'Gimnasia (a especificar)', 'gimnasia-a-especificar', 'Precisión y gimnásticos', '🤸', 184);

-- ---------------------------------------------------------------------
-- une_etapas — rangos etarios atendidos (§12.3)
-- ---------------------------------------------------------------------
CREATE TABLE une_etapas (
  id      TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre  VARCHAR(60) NOT NULL,
  rango   VARCHAR(20) NOT NULL,
  orden   TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `une_etapas` (`id`, `nombre`, `rango`, `orden`) VALUES
(1, 'Iniciación', '4-6 años', 1),
(2, 'Formación básica', '7-9 años', 2),
(3, 'Formación específica', '10-12 años', 3),
(4, 'Especialización', '13-15 años', 4),
(5, 'Rendimiento', '16-18 años', 5);

-- ---------------------------------------------------------------------
-- Tablas pivote
-- ---------------------------------------------------------------------
CREATE TABLE une_negocio_categorias (
  negocio_id   INT UNSIGNED NOT NULL,
  categoria_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (negocio_id, categoria_id),
  INDEX idx_categoria (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE une_negocio_deportes (
  negocio_id INT UNSIGNED NOT NULL,
  deporte_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (negocio_id, deporte_id),
  INDEX idx_deporte (deporte_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE une_negocio_etapas (
  negocio_id INT UNSIGNED NOT NULL,
  etapa_id   TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (negocio_id, etapa_id),
  INDEX idx_etapa (etapa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
