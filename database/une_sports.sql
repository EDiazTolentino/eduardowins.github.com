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
-- =========================================================================
-- UNE Sports — Datos iniciales (generado automáticamente desde data/*.json)
-- No editar a mano: volver a correr database/generate_seed.py si cambian los
-- JSON de ejemplo en /data.
-- =========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Categorías de negocio
INSERT INTO categorias (slug, nombre) VALUES
  ('academia-futbol', 'Academia de Fútbol'),
  ('escuela-natacion', 'Escuela de Natación'),
  ('academia-voley', 'Academia de Vóley'),
  ('artes-marciales', 'Centro de Artes Marciales'),
  ('academia-basquet', 'Academia de Básquet'),
  ('rehabilitacion', 'Centro de Rehabilitación Deportiva'),
  ('psicologia', 'Psicología Deportiva'),
  ('academia-tenis', 'Academia de Tenis'),
  ('atletismo', 'Escuela de Atletismo'),
  ('preparacion-fisica', 'Centro de Preparación Física'),
  ('otro', 'Otro');

-- Catálogo de referencia región / provincia / distrito
INSERT INTO distritos_peru (region, provincia, distrito) VALUES
  ('Lima', 'Lima', 'Miraflores'),
  ('Lima', 'Lima', 'San Borja'),
  ('Lima', 'Lima', 'La Molina'),
  ('Arequipa', 'Arequipa', 'Cercado'),
  ('La Libertad', 'Trujillo', 'Trujillo'),
  ('Lima', 'Lima', 'San Isidro'),
  ('Cusco', 'Cusco', 'Wanchaq'),
  ('Tacna', 'Tacna', 'Tacna'),
  ('Lambayeque', 'Chiclayo', 'Chiclayo'),
  ('Piura', 'Piura', 'Piura'),
  ('Junín', 'Huancayo', 'El Tambo'),
  ('Ica', 'Ica', 'Ica'),
  ('Loreto', 'Maynas', 'Iquitos'),
  ('Áncash', 'Huaraz', 'Huaraz');

-- Catálogo de servicios/especialidades
INSERT INTO servicios (nombre) VALUES
  ('Menores de 4 a 17 años'),
  ('Cancha de grass sintético'),
  ('Torneos internos'),
  ('Preparación física'),
  ('Escuela de porteros'),
  ('Uniforme incluido'),
  ('Piscina climatizada'),
  ('Natación para bebés'),
  ('Nivel competitivo'),
  ('Clases particulares'),
  ('Aqua-terapia'),
  ('Casilleros y duchas'),
  ('Categorías mini, sub-14 y sub-17'),
  ('Entrenadoras ex seleccionadas'),
  ('Ligas escolares'),
  ('Vóley playa (temporada)'),
  ('Karate infantil'),
  ('Taekwondo competitivo'),
  ('Defensa personal'),
  ('Exámenes de grado'),
  ('Clases para adultos'),
  ('Categorías 6 a 16 años'),
  ('Losa techada propia'),
  ('Torneos interescolares'),
  ('Campamentos de verano'),
  ('Fisioterapia deportiva'),
  ('Evaluación biomecánica'),
  ('Recuperación post-lesión'),
  ('Terapia de fortalecimiento'),
  ('Medicina deportiva'),
  ('Sesiones individuales'),
  ('Talleres para equipos'),
  ('Manejo de ansiedad competitiva'),
  ('Escuela para padres'),
  ('Evaluación psicodeportiva'),
  ('Canchas de polvo de ladrillo'),
  ('Entrenador certificado ITF'),
  ('Alquiler de equipos'),
  ('Pista oficial de 400m'),
  ('Velocidad y fondo'),
  ('Saltos y lanzamientos'),
  ('Preparación para juegos escolares'),
  ('Evaluación física inicial'),
  ('Programas de fuerza y velocidad'),
  ('Prevención de lesiones'),
  ('Trabajo funcional'),
  ('Categorías 5 a 15 años'),
  ('Técnica individual'),
  ('Ligas locales'),
  ('Uniforme y balón incluidos'),
  ('Piscina temperada'),
  ('Natación desde los 3 años'),
  ('Los cuatro estilos'),
  ('Clases grupales y particulares'),
  ('Kickboxing juvenil'),
  ('Muay thai'),
  ('Acondicionamiento físico'),
  ('Sparring controlado'),
  ('Talleres para entrenadores'),
  ('Manejo del estrés competitivo'),
  ('Rehabilitación de rodilla y tobillo'),
  ('Terapia de altura'),
  ('Vendaje neuromuscular');

-- Negocios
INSERT INTO negocios (id, slug, nombre, categoria_id, region, provincia, distrito, direccion, telefono, whatsapp, email, precio, descripcion, imagen_principal, contacto_nombre, contacto_cargo, contacto_foto, destacado, verificado, estado, valoracion_promedio, total_resenas, lat, lng) VALUES
  (1, 'academia-gol-de-oro', 'Academia Gol de Oro', 1, 'Lima', 'Lima', 'Miraflores', 'Av. Angamos Oeste 456, Miraflores', '+51 1 445 2201', '51987654321', 'contacto@goldeoro.pe', '$$', 'Formamos futbolistas desde los 4 hasta los 17 años con metodología certificada por la Federación Peruana de Fútbol. Contamos con cancha de grass sintético FIFA Quality, preparadores físicos y psicólogo deportivo permanente.', 'https://placehold.co/900x650/1A365D/FFFFFF?text=Academia+Gol+de+Oro', 'Renzo Aguilar', 'Director Deportivo', 'https://i.pravatar.cc/100?img=12', 1, 1, 'publicado', 4.8, 132, -12.1211, -77.0296),
  (2, 'delfines-natacion-club', 'Delfines Natación Club', 2, 'Lima', 'Lima', 'San Borja', 'Jr. Los Cedros 220, San Borja', '+51 1 224 8890', '51987001122', 'info@delfinesnatacion.pe', '$$$', 'Piscina climatizada semiolímpica con instructores certificados por la Federación Deportiva Peruana de Natación. Programas desde bebés (natación infantil) hasta preparación competitiva federada.', 'https://placehold.co/900x650/2C5282/FFFFFF?text=Delfines+Natacion', 'Claudia Injoque', 'Coordinadora Académica', 'https://i.pravatar.cc/100?img=47', 1, 1, 'publicado', 4.9, 98, -12.1017, -76.998),
  (3, 'voley-peru-academy', 'Vóley Perú Academy', 3, 'Lima', 'Lima', 'La Molina', 'Av. La Molina 1180, La Molina', '+51 1 348 7712', '51988112233', 'hola@voleyperu.pe', '$$', 'Escuela formativa de vóley con ex jugadoras de la selección nacional como entrenadoras. Trabajamos técnica, táctica y valores dentro y fuera de la cancha.', 'https://placehold.co/900x650/FF8300/FFFFFF?text=Voley+Peru+Academy', 'Katherine Salas', 'Entrenadora Principal', 'https://i.pravatar.cc/100?img=29', 0, 1, 'publicado', 4.6, 74, -12.0868, -76.9451),
  (4, 'dojo-samurai-peru', 'Dojo Samurai Perú', 4, 'Arequipa', 'Arequipa', 'Cercado', 'Calle Mercaderes 315, Cercado', '+51 54 223 4477', '51954112200', 'info@dojosamurai.pe', '$', 'Más de 20 años formando cinturones negros en karate, taekwondo y defensa personal. Trabajamos disciplina, respeto y confianza en niños, jóvenes y adultos.', 'https://placehold.co/900x650/2D3748/FFFFFF?text=Dojo+Samurai+Peru', 'Sensei Hugo Talavera', 'Instructor Principal (5to Dan)', 'https://i.pravatar.cc/100?img=51', 1, 1, 'publicado', 4.7, 156, -16.3989, -71.535),
  (5, 'canasta-basket-academy', 'Canasta Basket Academy', 5, 'La Libertad', 'Trujillo', 'Trujillo', 'Av. Húsares de Junín 540, Trujillo', '+51 44 291 3345', '51944556677', 'contacto@canastabasket.pe', '$$', 'Academia formativa de básquet con losa techada propia. Fundamentos técnicos, trabajo en equipo y participación en torneos interescolares de La Libertad.', 'https://placehold.co/900x650/1A365D/FFFFFF?text=Canasta+Basket+Academy', 'Piero Zavaleta', 'Director Técnico', 'https://i.pravatar.cc/100?img=33', 0, 0, 'publicado', 4.5, 61, -8.1116, -79.0288),
  (6, 'crd-revital', 'CRD ReVital - Centro de Rehabilitación Deportiva', 6, 'Lima', 'Lima', 'San Isidro', 'Av. Camino Real 815, San Isidro', '+51 1 421 6650', '51999887766', 'citas@crdrevital.pe', '$$$', 'Equipo multidisciplinario de fisioterapeutas y médicos deportólogos especializados en la recuperación de lesiones deportivas en niños, jóvenes y atletas de alto rendimiento.', 'https://placehold.co/900x650/2F855A/FFFFFF?text=CRD+ReVital', 'Dra. Valeria Ponce', 'Médico Deportóloga', 'https://i.pravatar.cc/100?img=44', 1, 1, 'publicado', 4.9, 87, -12.097, -77.0365),
  (7, 'mente-ganadora-psicologia-deportiva', 'Mente Ganadora - Psicología Deportiva', 7, 'Lima', 'Lima', 'Miraflores', 'Calle Schell 275, Miraflores', '+51 1 447 9021', '51977123456', 'hola@menteganadora.pe', '$$', 'Consultorio especializado en psicología del deporte para niños y jóvenes atletas: manejo de ansiedad competitiva, motivación, concentración y trabajo con padres y entrenadores.', 'https://placehold.co/900x650/FF8300/FFFFFF?text=Mente+Ganadora', 'Ps. Daniela Farfán', 'Psicóloga Deportiva Colegiada', 'https://i.pravatar.cc/100?img=41', 1, 1, 'publicado', 4.8, 53, -12.1235, -77.028),
  (8, 'ace-tenis-club-cusco', 'Ace Tenis Club Cusco', 8, 'Cusco', 'Cusco', 'Wanchaq', 'Av. La Cultura 1450, Wanchaq', '+51 84 235 9910', '51984223311', 'info@acetenis.pe', '$$$', 'Canchas de polvo de ladrillo y entrenadores certificados ITF. Programas de iniciación, competitivo y clases particulares para toda la familia.', 'https://placehold.co/900x650/1A365D/FFFFFF?text=Ace+Tenis+Club', 'Christian Béjar', 'Head Coach', 'https://i.pravatar.cc/100?img=52', 0, 1, 'publicado', 4.6, 39, -13.5319, -71.9675),
  (9, 'atletas-del-sur-tacna', 'Atletas del Sur', 9, 'Tacna', 'Tacna', 'Tacna', 'Av. Bolognesi 780, Tacna', '+51 52 241 7788', '51968334455', 'contacto@atletasdelsur.pe', '$', 'Escuela formativa de atletismo en pista oficial: velocidad, fondo, saltos y lanzamientos, con entrenadores egresados del IPD.', 'https://placehold.co/900x650/2D3748/FFFFFF?text=Atletas+del+Sur', 'Prof. Édgar Mamani', 'Entrenador Principal', 'https://i.pravatar.cc/100?img=54', 0, 0, 'publicado', 4.4, 28, -18.0146, -70.2536),
  (10, 'fuerza-total-chiclayo', 'Fuerza Total - Preparación Física', 10, 'Lambayeque', 'Chiclayo', 'Chiclayo', 'Av. Balta 990, Chiclayo', '+51 74 220 4456', '51978445566', 'info@fuerzatotal.pe', '$$', 'Centro especializado en preparación física para deportistas juveniles: fuerza, velocidad, agilidad y prevención de lesiones con seguimiento personalizado.', 'https://placehold.co/900x650/FF8300/FFFFFF?text=Fuerza+Total', 'Marco Delgado', 'Preparador Físico', 'https://i.pravatar.cc/100?img=59', 0, 1, 'publicado', 4.5, 44, -6.7714, -79.8409),
  (11, 'academia-futbol-base-piura', 'Academia Fútbol Base Piura', 1, 'Piura', 'Piura', 'Piura', 'Av. Grau 1120, Piura', '+51 73 309 2214', '51969112233', 'contacto@futbolbasepiura.pe', '$', 'Academia formativa de fútbol base para niños de 5 a 15 años, con enfoque en técnica individual y valores deportivos.', 'https://placehold.co/900x650/1A365D/FFFFFF?text=Futbol+Base+Piura', 'Willy Chunga', 'Director de Academia', 'https://i.pravatar.cc/100?img=61', 0, 0, 'publicado', 4.3, 35, -5.1945, -80.6328),
  (12, 'sirenas-natacion-huancayo', 'Sirenas Natación Huancayo', 2, 'Junín', 'Huancayo', 'El Tambo', 'Jr. Amazonas 640, El Tambo', '+51 64 231 8890', '51966889900', 'info@sirenashuancayo.pe', '$$', 'Piscina temperada en altura con instructores especializados en técnica de los cuatro estilos, para niños desde los 3 años.', 'https://placehold.co/900x650/2C5282/FFFFFF?text=Sirenas+Natacion', 'Yesenia Camayo', 'Coordinadora', 'https://i.pravatar.cc/100?img=63', 0, 1, 'publicado', 4.6, 41, -12.0651, -75.2049),
  (13, 'kickboxing-center-ica', 'Kickboxing Center Ica', 4, 'Ica', 'Ica', 'Ica', 'Calle Lima 233, Ica', '+51 56 223 5567', '51955667788', 'info@kickboxingica.pe', '$', 'Escuela de kickboxing y muay thai para jóvenes, con enfoque en disciplina, acondicionamiento físico y defensa personal.', 'https://placehold.co/900x650/2D3748/FFFFFF?text=Kickboxing+Center+Ica', 'Coach Bryan Ochoa', 'Instructor Principal', 'https://i.pravatar.cc/100?img=65', 0, 0, 'publicado', 4.4, 22, -14.0678, -75.7286),
  (14, 'psicodeporte-iquitos', 'Psicodeporte Iquitos', 7, 'Loreto', 'Maynas', 'Iquitos', 'Jr. Próspero 412, Iquitos', '+51 65 223 1145', '51945223311', 'contacto@psicodeporteiquitos.pe', '$$', 'Atención psicológica especializada para deportistas escolares y federados de la región Loreto, con enfoque en motivación y manejo emocional.', 'https://placehold.co/900x650/FF8300/FFFFFF?text=Psicodeporte+Iquitos', 'Ps. Rosmery Tuesta', 'Psicóloga Deportiva', 'https://i.pravatar.cc/100?img=42', 0, 1, 'publicado', 4.7, 19, -3.7491, -73.2538),
  (15, 'rehab-sport-huaraz', 'Rehab Sport Huaraz', 6, 'Áncash', 'Huaraz', 'Huaraz', 'Av. Confraternidad Internacional 350, Huaraz', '+51 43 242 6690', '51934556677', 'citas@rehabsporthuaraz.pe', '$$', 'Centro de fisioterapia y rehabilitación deportiva para atletas de montaña, ciclistas y deportistas escolares de la región Áncash.', 'https://placehold.co/900x650/2F855A/FFFFFF?text=Rehab+Sport+Huaraz', 'Lic. Óscar Ponte', 'Fisioterapeuta Deportivo', 'https://i.pravatar.cc/100?img=67', 0, 0, 'publicado', 4.5, 17, -9.5277, -77.5279);

-- Relación negocio <-> servicios
INSERT INTO negocio_servicios (negocio_id, servicio_id, orden) VALUES
  (1, 1, 0),
  (1, 2, 1),
  (1, 3, 2),
  (1, 4, 3),
  (1, 5, 4),
  (1, 6, 5),
  (2, 7, 0),
  (2, 8, 1),
  (2, 9, 2),
  (2, 10, 3),
  (2, 11, 4),
  (2, 12, 5),
  (3, 13, 0),
  (3, 14, 1),
  (3, 15, 2),
  (3, 16, 3),
  (3, 4, 4),
  (4, 17, 0),
  (4, 18, 1),
  (4, 19, 2),
  (4, 20, 3),
  (4, 21, 4),
  (5, 22, 0),
  (5, 23, 1),
  (5, 24, 2),
  (5, 25, 3),
  (6, 26, 0),
  (6, 27, 1),
  (6, 28, 2),
  (6, 29, 3),
  (6, 30, 4),
  (7, 31, 0),
  (7, 32, 1),
  (7, 33, 2),
  (7, 34, 3),
  (7, 35, 4),
  (8, 36, 0),
  (8, 37, 1),
  (8, 10, 2),
  (8, 38, 3),
  (8, 3, 4),
  (9, 39, 0),
  (9, 40, 1),
  (9, 41, 2),
  (9, 42, 3),
  (10, 43, 0),
  (10, 44, 1),
  (10, 45, 2),
  (10, 46, 3),
  (11, 47, 0),
  (11, 48, 1),
  (11, 49, 2),
  (11, 50, 3),
  (12, 51, 0),
  (12, 52, 1),
  (12, 53, 2),
  (12, 54, 3),
  (13, 55, 0),
  (13, 56, 1),
  (13, 57, 2),
  (13, 58, 3),
  (14, 31, 0),
  (14, 35, 1),
  (14, 59, 2),
  (14, 60, 3),
  (15, 26, 0),
  (15, 61, 1),
  (15, 62, 2),
  (15, 63, 3);

-- Galería de imágenes
INSERT INTO negocio_imagenes (negocio_id, url, orden) VALUES
  (1, 'https://placehold.co/900x650/1A365D/FFFFFF?text=Academia+Gol+de+Oro', 0),
  (1, 'https://placehold.co/700x500/FF8300/FFFFFF?text=Entrenamiento', 1),
  (1, 'https://placehold.co/700x500/2D3748/FFFFFF?text=Cancha+Sintetica', 2),
  (1, 'https://placehold.co/700x500/1A365D/FFFFFF?text=Torneo+Interno', 3),
  (1, 'https://placehold.co/700x500/FF8300/FFFFFF?text=Equipo+Tecnico', 4),
  (2, 'https://placehold.co/900x650/2C5282/FFFFFF?text=Delfines+Natacion', 0),
  (2, 'https://placehold.co/700x500/FF8300/FFFFFF?text=Piscina+Climatizada', 1),
  (2, 'https://placehold.co/700x500/1A365D/FFFFFF?text=Clase+Infantil', 2),
  (2, 'https://placehold.co/700x500/2D3748/FFFFFF?text=Entrenamiento+Competitivo', 3),
  (3, 'https://placehold.co/900x650/FF8300/FFFFFF?text=Voley+Peru+Academy', 0),
  (3, 'https://placehold.co/700x500/1A365D/FFFFFF?text=Entrenamiento+Tecnico', 1),
  (3, 'https://placehold.co/700x500/2D3748/FFFFFF?text=Liga+Escolar', 2),
  (4, 'https://placehold.co/900x650/2D3748/FFFFFF?text=Dojo+Samurai+Peru', 0),
  (4, 'https://placehold.co/700x500/FF8300/FFFFFF?text=Examen+de+Grado', 1),
  (4, 'https://placehold.co/700x500/1A365D/FFFFFF?text=Clase+Infantil', 2),
  (4, 'https://placehold.co/700x500/2D3748/FFFFFF?text=Torneo+Regional', 3),
  (5, 'https://placehold.co/900x650/1A365D/FFFFFF?text=Canasta+Basket+Academy', 0),
  (5, 'https://placehold.co/700x500/FF8300/FFFFFF?text=Losa+Techada', 1),
  (5, 'https://placehold.co/700x500/2D3748/FFFFFF?text=Torneo+Interescolar', 2),
  (6, 'https://placehold.co/900x650/2F855A/FFFFFF?text=CRD+ReVital', 0),
  (6, 'https://placehold.co/700x500/1A365D/FFFFFF?text=Sala+de+Terapia', 1),
  (6, 'https://placehold.co/700x500/FF8300/FFFFFF?text=Evaluacion+Biomecanica', 2),
  (6, 'https://placehold.co/700x500/2D3748/FFFFFF?text=Equipo+Medico', 3),
  (7, 'https://placehold.co/900x650/FF8300/FFFFFF?text=Mente+Ganadora', 0),
  (7, 'https://placehold.co/700x500/1A365D/FFFFFF?text=Sesion+Individual', 1),
  (7, 'https://placehold.co/700x500/2D3748/FFFFFF?text=Taller+de+Equipo', 2),
  (8, 'https://placehold.co/900x650/1A365D/FFFFFF?text=Ace+Tenis+Club', 0),
  (8, 'https://placehold.co/700x500/FF8300/FFFFFF?text=Cancha+de+Polvo', 1),
  (8, 'https://placehold.co/700x500/2D3748/FFFFFF?text=Clase+Particular', 2),
  (9, 'https://placehold.co/900x650/2D3748/FFFFFF?text=Atletas+del+Sur', 0),
  (9, 'https://placehold.co/700x500/FF8300/FFFFFF?text=Pista+Oficial', 1),
  (9, 'https://placehold.co/700x500/1A365D/FFFFFF?text=Entrenamiento+de+Fondo', 2),
  (10, 'https://placehold.co/900x650/FF8300/FFFFFF?text=Fuerza+Total', 0),
  (10, 'https://placehold.co/700x500/1A365D/FFFFFF?text=Entrenamiento+Funcional', 1),
  (10, 'https://placehold.co/700x500/2D3748/FFFFFF?text=Evaluacion+Fisica', 2),
  (11, 'https://placehold.co/900x650/1A365D/FFFFFF?text=Futbol+Base+Piura', 0),
  (11, 'https://placehold.co/700x500/FF8300/FFFFFF?text=Entrenamiento+Tecnico', 1),
  (11, 'https://placehold.co/700x500/2D3748/FFFFFF?text=Liga+Local', 2),
  (12, 'https://placehold.co/900x650/2C5282/FFFFFF?text=Sirenas+Natacion', 0),
  (12, 'https://placehold.co/700x500/FF8300/FFFFFF?text=Piscina+Temperada', 1),
  (12, 'https://placehold.co/700x500/1A365D/FFFFFF?text=Clase+Grupal', 2),
  (13, 'https://placehold.co/900x650/2D3748/FFFFFF?text=Kickboxing+Center+Ica', 0),
  (13, 'https://placehold.co/700x500/FF8300/FFFFFF?text=Entrenamiento+de+Sparring', 1),
  (13, 'https://placehold.co/700x500/1A365D/FFFFFF?text=Clase+Juvenil', 2),
  (14, 'https://placehold.co/900x650/FF8300/FFFFFF?text=Psicodeporte+Iquitos', 0),
  (14, 'https://placehold.co/700x500/1A365D/FFFFFF?text=Sesion+Individual', 1),
  (15, 'https://placehold.co/900x650/2F855A/FFFFFF?text=Rehab+Sport+Huaraz', 0),
  (15, 'https://placehold.co/700x500/1A365D/FFFFFF?text=Sala+de+Terapia', 1),
  (15, 'https://placehold.co/700x500/FF8300/FFFFFF?text=Vendaje+Neuromuscular', 2);

-- Horarios de atención
INSERT INTO negocio_horarios (negocio_id, dia, hora, orden) VALUES
  (1, 'Lunes a Viernes', '15:00 - 20:00', 0),
  (1, 'Sábados', '08:00 - 13:00', 1),
  (1, 'Domingos', 'Cerrado', 2),
  (2, 'Lunes a Viernes', '06:00 - 21:00', 0),
  (2, 'Sábados', '07:00 - 15:00', 1),
  (2, 'Domingos', '08:00 - 12:00', 2),
  (3, 'Martes y Jueves', '16:00 - 19:00', 0),
  (3, 'Sábados', '09:00 - 12:00', 1),
  (4, 'Lunes a Viernes', '16:00 - 21:00', 0),
  (4, 'Sábados', '09:00 - 13:00', 1),
  (5, 'Lunes, Miércoles y Viernes', '17:00 - 19:30', 0),
  (5, 'Sábados', '10:00 - 13:00', 1),
  (6, 'Lunes a Viernes', '07:00 - 20:00', 0),
  (6, 'Sábados', '08:00 - 14:00', 1),
  (7, 'Lunes a Viernes', '09:00 - 19:00', 0),
  (7, 'Sábados', '09:00 - 12:00', 1),
  (8, 'Lunes a Sábado', '07:00 - 20:00', 0),
  (8, 'Domingos', '08:00 - 13:00', 1),
  (9, 'Martes, Jueves y Sábado', '07:00 - 09:30', 0),
  (10, 'Lunes a Viernes', '15:00 - 20:00', 0),
  (10, 'Sábados', '09:00 - 12:00', 1),
  (11, 'Lunes, Miércoles y Viernes', '16:00 - 18:30', 0),
  (11, 'Sábados', '08:00 - 11:00', 1),
  (12, 'Lunes a Viernes', '08:00 - 19:00', 0),
  (12, 'Sábados', '08:00 - 13:00', 1),
  (13, 'Lunes a Viernes', '17:00 - 21:00', 0),
  (14, 'Lunes a Viernes', '09:00 - 18:00', 0),
  (15, 'Lunes a Sábado', '08:00 - 19:00', 0);

-- Reseñas de ejemplo (el promedio/total mostrado en el negocio ya incluye
-- reseñas históricas adicionales no listadas individualmente aquí)
INSERT INTO valoraciones (negocio_id, usuario_nombre, usuario_avatar, puntuacion, comentario, creado_en) VALUES
  (1, 'Marisol Chávez', 'https://i.pravatar.cc/100?img=32', 5, 'Mi hijo entró tímido y en un año ya es capitán de su categoría. Los profesores son muy pacientes con los más chicos.', '2026-06-14 12:00:00'),
  (1, 'Jorge Ríos', 'https://i.pravatar.cc/100?img=15', 5, 'Excelente infraestructura y puntualidad. Se nota la organización de cada práctica.', '2026-05-02 12:00:00'),
  (1, 'Diana Paredes', 'https://i.pravatar.cc/100?img=45', 4, 'Muy buena academia, solo sugiero más horarios los fines de semana.', '2026-03-20 12:00:00'),
  (2, 'Fabiola Ugarte', 'https://i.pravatar.cc/100?img=21', 5, 'El programa para bebés es espectacular, mi hija perdió el miedo al agua en tres semanas.', '2026-07-01 12:00:00'),
  (2, 'Luis Bendezú', 'https://i.pravatar.cc/100?img=8', 5, 'Instructores muy profesionales y la piscina siempre impecable.', '2026-04-18 12:00:00'),
  (3, 'Pamela Ortiz', 'https://i.pravatar.cc/100?img=36', 5, 'El nivel de las entrenadoras es altísimo, mi hija mejoró muchísimo su saque.', '2026-06-02 12:00:00'),
  (3, 'Gonzalo Prado', 'https://i.pravatar.cc/100?img=14', 4, 'Buena academia, el único punto es que se llena rápido los sábados.', '2026-02-11 12:00:00'),
  (4, 'Andrea Vilca', 'https://i.pravatar.cc/100?img=24', 5, 'El sensei tiene una paciencia enorme con los niños. Mi hijo ya es cinturón amarillo y encantado.', '2026-05-29 12:00:00'),
  (4, 'Manuel Cárdenas', 'https://i.pravatar.cc/100?img=6', 5, 'Un dojo con historia y disciplina real, se los recomiendo a cualquier padre.', '2026-01-15 12:00:00'),
  (5, 'Ximena Rodríguez', 'https://i.pravatar.cc/100?img=39', 4, 'Buen ambiente y profesores comprometidos, la losa techada ayuda mucho en época de lluvias.', '2026-04-09 12:00:00'),
  (6, 'Sebastián Loayza', 'https://i.pravatar.cc/100?img=17', 5, 'Me recuperé de una lesión de rodilla en tiempo récord gracias al plan personalizado que me hicieron.', '2026-06-25 12:00:00'),
  (6, 'Carla Núñez', 'https://i.pravatar.cc/100?img=48', 5, 'Trato humano excelente y resultados visibles desde las primeras sesiones.', '2026-03-30 12:00:00'),
  (7, 'Rosa Elguera', 'https://i.pravatar.cc/100?img=23', 5, 'Ayudó muchísimo a mi hijo a controlar los nervios antes de las competencias de natación.', '2026-07-10 12:00:00'),
  (7, 'Iván Chumpitaz', 'https://i.pravatar.cc/100?img=3', 4, 'Profesional muy preparada, se nota la experiencia trabajando con deportistas jóvenes.', '2026-05-05 12:00:00'),
  (8, 'Fiorella Zúñiga', 'https://i.pravatar.cc/100?img=27', 5, 'Las canchas están en muy buen estado y el coach explica muy bien la técnica.', '2026-04-22 12:00:00'),
  (9, 'Katherine Flores', 'https://i.pravatar.cc/100?img=19', 4, 'Muy buena disciplina y horarios tempranos que se respetan siempre.', '2026-02-27 12:00:00'),
  (10, 'Anthony Vega', 'https://i.pravatar.cc/100?img=9', 5, 'Noté una mejora enorme en mi rendimiento en solo dos meses de entrenamiento.', '2026-05-16 12:00:00'),
  (11, 'Milagros Neyra', 'https://i.pravatar.cc/100?img=25', 4, 'Precios accesibles y buen trato a los niños, recomendable para empezar en el fútbol.', '2026-03-11 12:00:00'),
  (12, 'Herbert Quispe', 'https://i.pravatar.cc/100?img=11', 5, 'El agua siempre está a buena temperatura, ideal para el clima de Huancayo.', '2026-06-08 12:00:00'),
  (13, 'Josué Palomino', 'https://i.pravatar.cc/100?img=7', 4, 'Buen ambiente para empezar en las artes marciales, coach muy atento con los novatos.', '2026-01-30 12:00:00'),
  (14, 'Elvis Ríos', 'https://i.pravatar.cc/100?img=5', 5, 'Ayudó mucho a mi sobrino a manejar la presión antes de los juegos nacionales escolares.', '2026-05-19 12:00:00'),
  (15, 'Yolanda Camones', 'https://i.pravatar.cc/100?img=31', 4, 'Buen trato y resultados, aunque hay que sacar cita con unos días de anticipación.', '2026-04-02 12:00:00');

-- Categorías del blog
INSERT INTO blog_categorias (nombre) VALUES
  ('Deporte Formativo'),
  ('Fútbol Formativo'),
  ('Psicología Deportiva'),
  ('Natación'),
  ('Nutrición Deportiva'),
  ('Rehabilitación');

-- Artículos del blog
INSERT INTO blog_articulos (id, slug, titulo, categoria_id, resumen, imagen, autor_nombre, autor_foto, fecha_publicacion, tiempo_lectura, contenido) VALUES
  (1, 'beneficios-deporte-formativo-en-ninos', '7 beneficios del deporte formativo en niños y adolescentes', 1, 'Más allá de lo físico: cómo la práctica deportiva organizada impacta la disciplina, la autoestima y el rendimiento escolar de los más pequeños.', 'https://placehold.co/1000x600/1A365D/FFFFFF?text=Deporte+Formativo', 'Equipo UNE Sports', 'https://i.pravatar.cc/100?img=68', '2026-08-10', '5 min', '[{"tipo": "parrafo", "texto": "El deporte formativo —aquel que se practica en academias y escuelas deportivas orientadas al desarrollo integral, y no solo a la competencia— es una de las herramientas más poderosas para el crecimiento de niños y adolescentes. En el Perú, cada vez más familias buscan alternativas cercanas y confiables donde sus hijos puedan iniciarse en una disciplina deportiva."}, {"tipo": "titulo", "texto": "1. Disciplina y manejo del tiempo"}, {"tipo": "parrafo", "texto": "Asistir a entrenamientos regulares enseña a los niños a organizar su tiempo entre el colegio, las tareas y el deporte, una habilidad que los acompañará toda la vida."}, {"tipo": "titulo", "texto": "2. Autoestima y confianza"}, {"tipo": "parrafo", "texto": "Superar retos técnicos, como aprender una nueva jugada o mejorar un tiempo en la piscina, refuerza la confianza en las propias capacidades."}, {"tipo": "cita", "texto": "Un niño que se siente capaz en la cancha, suele trasladar esa confianza a otras áreas de su vida, incluida el aula."}, {"tipo": "titulo", "texto": "3. Trabajo en equipo"}, {"tipo": "parrafo", "texto": "Deportes como el fútbol o el vóley enseñan a colaborar, comunicarse y resolver conflictos con pares, habilidades sociales clave para la vida adulta."}, {"tipo": "titulo", "texto": "4. Salud física y prevención"}, {"tipo": "parrafo", "texto": "La actividad física regular reduce el riesgo de sobrepeso infantil y fortalece el sistema cardiovascular desde edades tempranas."}, {"tipo": "lista", "items": ["Mejora la coordinación motora", "Fortalece huesos y articulaciones en desarrollo", "Reduce el sedentarismo asociado a las pantallas", "Favorece hábitos de alimentación saludable"]}, {"tipo": "titulo", "texto": "5. Manejo emocional"}, {"tipo": "parrafo", "texto": "Ganar, perder y competir con respeto son lecciones emocionales que un buen entrenador sabe transmitir a través del deporte formativo."}, {"tipo": "titulo", "texto": "¿Cómo elegir la academia correcta?"}, {"tipo": "parrafo", "texto": "Verifica que los instructores estén certificados, que existan protocolos de seguridad y que el enfoque esté centrado en el desarrollo del niño y no solo en el resultado deportivo. En UNE Sports puedes filtrar academias por región, tipo de disciplina y valoraciones de otras familias para tomar la mejor decisión."}]'),
  (2, 'como-elegir-academia-de-futbol-para-tu-hijo', 'Guía práctica: cómo elegir la academia de fútbol ideal para tu hijo', 2, 'Infraestructura, ratio de alumnos por entrenador, metodología y seguridad: los puntos clave antes de matricular a tu hijo en una academia de fútbol.', 'https://placehold.co/1000x600/FF8300/FFFFFF?text=Futbol+Formativo', 'Renzo Aguilar', 'https://i.pravatar.cc/100?img=12', '2026-07-22', '6 min', '[{"tipo": "parrafo", "texto": "El fútbol sigue siendo el deporte más popular entre los niños peruanos, y con esa popularidad ha crecido también la oferta de academias formativas en todo el país. Elegir bien marca la diferencia entre una experiencia que enamora del deporte y una que lo aleja de él."}, {"tipo": "titulo", "texto": "Revisa la infraestructura"}, {"tipo": "parrafo", "texto": "Una cancha en buen estado, con superficie adecuada y medidas de seguridad (mallas, iluminación, primeros auxilios) reduce el riesgo de lesiones y mejora la calidad del entrenamiento."}, {"tipo": "titulo", "texto": "Ratio entrenador-alumno"}, {"tipo": "parrafo", "texto": "Lo ideal es no superar los 12 a 15 niños por entrenador en categorías menores, para garantizar atención personalizada en la técnica individual."}, {"tipo": "titulo", "texto": "Metodología por edades"}, {"tipo": "parrafo", "texto": "Las academias serias trabajan con una progresión pedagógica clara: en las categorías más pequeñas priorizan el juego y la coordinación, y recién en la adolescencia introducen exigencia táctica y física."}, {"tipo": "lista", "items": ["Solicita ver una clase de prueba antes de matricular", "Pregunta por el protocolo ante lesiones", "Revisa las valoraciones y comentarios de otros padres", "Confirma si el precio incluye uniforme y torneos"]}, {"tipo": "parrafo", "texto": "En UNE Sports puedes comparar varias academias de fútbol de tu distrito, revisar sus valoraciones reales y contactarlas directamente por WhatsApp antes de decidir."}]'),
  (3, 'psicologia-deportiva-infantil-ansiedad-competitiva', 'Psicología deportiva infantil: cómo manejar la ansiedad antes de competir', 3, 'Los nervios antes de una competencia son normales, pero cuando se vuelven un obstáculo, la psicología deportiva puede ayudar. Conversamos con especialistas peruanas sobre el tema.', 'https://placehold.co/1000x600/2C5282/FFFFFF?text=Psicologia+Deportiva', 'Ps. Daniela Farfán', 'https://i.pravatar.cc/100?img=41', '2026-07-05', '4 min', '[{"tipo": "parrafo", "texto": "Es común que niños y adolescentes sientan nervios antes de una competencia. El problema aparece cuando esa ansiedad les impide disfrutar o rendir en lo que tanto han entrenado."}, {"tipo": "titulo", "texto": "Señales para prestar atención"}, {"tipo": "lista", "items": ["Dolores de estómago o de cabeza antes de competir", "Dificultad para dormir la noche previa", "Pensamientos catastróficos sobre el resultado", "Evitar la competencia o inventar excusas para no asistir"]}, {"tipo": "titulo", "texto": "Estrategias que funcionan"}, {"tipo": "parrafo", "texto": "La respiración consciente, las rutinas previas a la competencia y el enfoque en el proceso (y no solo en el resultado) son herramientas simples que un psicólogo deportivo puede enseñar tanto al niño como a sus padres."}, {"tipo": "cita", "texto": "El objetivo no es eliminar los nervios, sino aprender a competir con ellos."}, {"tipo": "titulo", "texto": "El rol de los padres y entrenadores"}, {"tipo": "parrafo", "texto": "Evitar comparaciones, celebrar el esfuerzo más que el resultado y mantener un lenguaje positivo antes y después de competir son claves para que el niño construya una relación sana con la competencia."}]'),
  (4, 'natacion-infantil-edad-ideal-para-empezar', 'Natación infantil: ¿cuál es la edad ideal para empezar?', 4, 'Desde la adaptación acuática en bebés hasta el nivel competitivo: te contamos las etapas del aprendizaje de la natación en niños.', 'https://placehold.co/1000x600/1A365D/FFFFFF?text=Natacion+Infantil', 'Claudia Injoque', 'https://i.pravatar.cc/100?img=47', '2026-06-18', '5 min', '[{"tipo": "parrafo", "texto": "La natación es uno de los deportes más completos para el desarrollo físico infantil, y también uno de los que genera más dudas sobre cuándo empezar."}, {"tipo": "titulo", "texto": "De 6 meses a 3 años: adaptación acuática"}, {"tipo": "parrafo", "texto": "En esta etapa el objetivo no es ''nadar'', sino perder el miedo al agua y desarrollar reflejos básicos de flotación, siempre acompañados de un adulto dentro de la piscina."}, {"tipo": "titulo", "texto": "De 4 a 6 años: aprendizaje técnico inicial"}, {"tipo": "parrafo", "texto": "Es la edad ideal para empezar clases estructuradas sin acompañante en el agua, donde se introducen los primeros movimientos de los estilos crol y espalda."}, {"tipo": "titulo", "texto": "De 7 años en adelante: perfeccionamiento y nivel competitivo"}, {"tipo": "parrafo", "texto": "A partir de esta edad los niños con buena base técnica pueden optar por un camino más competitivo, incorporando los cuatro estilos y entrenamiento de resistencia."}, {"tipo": "parrafo", "texto": "Sea cual sea la edad de inicio, lo más importante es elegir una escuela con instructores certificados y piscinas con buen mantenimiento del agua."}]'),
  (5, 'nutricion-para-jovenes-deportistas', 'Nutrición básica para jóvenes deportistas: lo que todo padre debería saber', 5, 'Qué comer antes y después de entrenar, la importancia de la hidratación y errores comunes en la alimentación de niños que practican deporte de forma regular.', 'https://placehold.co/1000x600/FF8300/FFFFFF?text=Nutricion+Deportiva', 'Equipo UNE Sports', 'https://i.pravatar.cc/100?img=68', '2026-05-30', '4 min', '[{"tipo": "parrafo", "texto": "Un niño que entrena varias veces por semana tiene necesidades nutricionales distintas a uno sedentario, aunque esto no significa que necesite dietas especiales ni suplementos."}, {"tipo": "titulo", "texto": "Antes de entrenar"}, {"tipo": "parrafo", "texto": "Una comida ligera rica en carbohidratos, como fruta con avena o pan integral, una o dos horas antes del entrenamiento aporta la energía necesaria sin generar pesadez."}, {"tipo": "titulo", "texto": "Después de entrenar"}, {"tipo": "parrafo", "texto": "La combinación de proteína y carbohidratos ayuda a la recuperación muscular: por ejemplo, un sándwich de pollo o un yogur con frutas."}, {"tipo": "titulo", "texto": "Hidratación, la gran olvidada"}, {"tipo": "lista", "items": ["Ofrecer agua antes, durante y después del entrenamiento", "Evitar bebidas azucaradas como reemplazo del agua", "En climas cálidos, aumentar la frecuencia de hidratación", "Las bebidas isotónicas rara vez son necesarias en niños"]}, {"tipo": "parrafo", "texto": "Consulta siempre con un nutricionista deportivo antes de hacer cambios significativos en la alimentación de un menor que entrena de forma intensiva."}]'),
  (6, 'prevencion-lesiones-deportivas-en-ninos', 'Prevención de lesiones deportivas en niños: la guía de un fisioterapeuta', 6, 'El calentamiento, la progresión de cargas y el descanso son claves para que la práctica deportiva formativa no termine en lesión. Un especialista nos explica cómo prevenir.', 'https://placehold.co/1000x600/2F855A/FFFFFF?text=Prevencion+de+Lesiones', 'Dra. Valeria Ponce', 'https://i.pravatar.cc/100?img=44', '2026-05-12', '5 min', '[{"tipo": "parrafo", "texto": "El cuerpo de un niño o adolescente sigue en desarrollo, lo que lo hace especialmente sensible a las lesiones por sobreuso cuando el entrenamiento no está bien planificado."}, {"tipo": "titulo", "texto": "Calentamiento y estiramiento"}, {"tipo": "parrafo", "texto": "Dedicar al menos 10 minutos a un calentamiento dinámico antes de cada entrenamiento reduce significativamente el riesgo de lesiones musculares."}, {"tipo": "titulo", "texto": "Progresión gradual de la carga"}, {"tipo": "parrafo", "texto": "Aumentar la intensidad o el volumen de entrenamiento de forma abrupta es una de las principales causas de lesión en deportistas jóvenes."}, {"tipo": "titulo", "texto": "Señales de alerta que no se deben ignorar"}, {"tipo": "lista", "items": ["Dolor que persiste más de dos días", "Cojera o cambios en la forma de caminar", "Hinchazón visible en una articulación", "Dolor que aparece siempre en el mismo punto"]}, {"tipo": "cita", "texto": "Ante cualquiera de estas señales, lo recomendable es pausar la actividad y acudir a evaluación con un especialista en rehabilitación deportiva."}, {"tipo": "parrafo", "texto": "El descanso también es parte del entrenamiento: dormir bien y respetar al menos un día de descanso a la semana es fundamental para la recuperación muscular en niños y adolescentes."}]');

-- Ajustar los AUTO_INCREMENT para que sigan después de los IDs sembrados
ALTER TABLE negocios AUTO_INCREMENT = 16;
ALTER TABLE blog_articulos AUTO_INCREMENT = 7;

SET FOREIGN_KEY_CHECKS = 1;
