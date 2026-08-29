-- =====================================================================
-- 04_semilla.sql — Datos de ejemplo
-- Ejecutar al final, después de 01, 02 y 03.
--
-- IMPORTANTE: estos NO son negocios reales. Son fichas de demostración,
-- claramente marcadas como "(Ejemplo)" en el nombre, para poder probar de
-- inmediato la ficha pública, el buscador y el panel admin con datos
-- completos antes de que el equipo cargue academias reales por las cinco
-- vías de captación descritas en el prompt (§6). Bórralas cuando tengas
-- las primeras fichas reales publicadas:
--   DELETE FROM une_negocios WHERE utm_source = 'semilla_demo';
-- (los registros dependientes se limpian solos por los borrados en cascada
-- de tu propio flujo de administración; si prefieres, borra también a mano
-- de une_negocio_categorias, une_negocio_deportes, une_negocio_etapas,
-- une_horarios e une_imagenes donde negocio_id coincida).
-- =====================================================================

SET NAMES utf8mb4;

-- Usuario administrador inicial. Usuario: admin — Contraseña: CambiaEsto123
-- Genera tu propio hash antes de producción con:
--   php -r "echo password_hash('TU_CONTRASENA', PASSWORD_DEFAULT);"
INSERT INTO `une_admins` (`id`, `usuario`, `password_hash`, `nombre`, `rol`, `activo`) VALUES
(1, 'admin', '$2y$12$.xAjgR9wOUp1wofanbFn4.g7TZLz6QjaIaBNpnTxmfxBSfNREAzX2', 'Administrador UNE Sports', 'administrador', 1);

-- ---------------------------------------------------------------------
-- Negocio 1: ficha formativa completa y VERIFICADA (Miraflores, Lima)
-- ---------------------------------------------------------------------
INSERT INTO `une_negocios` (
  `id`, `slug`, `tipo_registro`, `nombre_comercial`, `descripcion`,
  `departamento_id`, `provincia_id`, `distrito_id`, `direccion`, `referencia`,
  `latitud`, `longitud`, `telefono_publico`, `email_publico`, `instagram`,
  `contacto_nombre`, `contacto_telefono`,
  `modalidad`, `rango_precio`, `tiene_matricula`, `clase_prueba_gratis`,
  `capacidad_alumnos`, `num_entrenadores`, `atiende_genero`,
  `local_propio`, `seguro_accidentes`, `protocolo_salvaguarda`, `personal_certificado`,
  `estado`, `origen`, `verificado`, `fecha_verificacion`, `completitud`,
  `resultado_contacto`, `intentos_contacto`, `token_edicion`, `utm_source`, `creado_en`
) VALUES (
  1, 'academia-ejemplo-futbol-miraflores', 'formativo', 'Academia Ejemplo Fútbol Miraflores',
  'Escuela de fútbol formativo para niños y adolescentes, con metodología por edades y torneos internos cada trimestre. Ficha de demostración del directorio.',
  14, 127, 1267, 'Av. Ejemplo 123, Miraflores', 'A dos cuadras del parque Kennedy',
  -12.1211000, -77.0295000, '987654321', 'contacto@academiaejemplo.pe', 'academiaejemplofutbol',
  'Juan Pérez (representante, dato privado)', '987654321',
  'presencial', 2, 1, 1,
  120, 8, 'mixto',
  1, 1, 1, 1,
  'publicado', 'alta_admin', 1, NOW(), 90,
  'interesado', 1, LOWER(HEX(RANDOM_BYTES(24))), 'semilla_demo', NOW()
);

INSERT INTO `une_negocio_categorias` (`negocio_id`, `categoria_id`) VALUES (1, 1);
INSERT INTO `une_negocio_deportes` (`negocio_id`, `deporte_id`) VALUES (1, 2);
INSERT INTO `une_negocio_etapas` (`negocio_id`, `etapa_id`) VALUES (1, 1), (1, 2), (1, 3);
INSERT INTO `une_horarios` (`negocio_id`, `dia_semana`, `turno`, `hora_inicio`, `hora_fin`) VALUES
(1, 2, 'tarde', '16:00:00', '18:00:00'),
(1, 4, 'tarde', '16:00:00', '18:00:00'),
(1, 6, 'mañana', '09:00:00', '12:00:00');

-- ---------------------------------------------------------------------
-- Negocio 2: ficha formativa NO verificada (San Isidro, Lima)
-- ---------------------------------------------------------------------
INSERT INTO `une_negocios` (
  `id`, `slug`, `tipo_registro`, `nombre_comercial`, `descripcion`,
  `departamento_id`, `provincia_id`, `distrito_id`, `direccion`,
  `telefono_publico`, `contacto_nombre`,
  `modalidad`, `rango_precio`, `atiende_genero`,
  `estado`, `origen`, `verificado`, `completitud`,
  `resultado_contacto`, `intentos_contacto`, `token_edicion`, `utm_source`, `creado_en`
) VALUES (
  2, 'escuela-ejemplo-natacion-san-isidro', 'formativo', 'Escuela Ejemplo Natación San Isidro',
  'Clases de natación formativa recopiladas por el equipo de UNE Sports a partir de redes sociales. Ficha de demostración del directorio, pendiente de confirmación por el establecimiento.',
  14, 127, 1276, 'Calle Ejemplo 456, San Isidro',
  '912345678', 'María Torres (representante, dato privado)',
  'presencial', 3, 'mixto',
  'publicado', 'importacion', 0, 55,
  'sin_contactar', 0, LOWER(HEX(RANDOM_BYTES(24))), 'semilla_demo', NOW()
);

INSERT INTO `une_negocio_categorias` (`negocio_id`, `categoria_id`) VALUES (2, 2);
INSERT INTO `une_negocio_deportes` (`negocio_id`, `deporte_id`) VALUES (2, 52);
INSERT INTO `une_negocio_etapas` (`negocio_id`, `etapa_id`) VALUES (2, 2), (2, 3);

-- ---------------------------------------------------------------------
-- Negocio 3: lead recién capturado, aún SIN publicar (Arequipa)
-- Simula lo que deja el formulario público de captura rápida (§7A).
-- ---------------------------------------------------------------------
INSERT INTO `une_negocios` (
  `id`, `slug`, `tipo_registro`, `nombre_comercial`,
  `departamento_id`, `provincia_id`, `distrito_id`,
  `telefono_publico`, `contacto_nombre`,
  `estado`, `origen`, `verificado`, `completitud`,
  `resultado_contacto`, `intentos_contacto`, `notas_internas`,
  `token_edicion`, `utm_source`, `creado_en`
) VALUES (
  3, 'lead-ejemplo-centro-fisioterapia-arequipa', 'servicio', 'Centro Ejemplo Fisioterapia Deportiva Arequipa',
  4, 35, 330,
  '954112233', 'Carlos Ramírez (representante, dato privado)',
  'lead', 'captura_rapida', 0, 25,
  'sin_contactar', 0, 'Lead de ejemplo: registrado desde /registrar, pendiente de primera llamada.',
  LOWER(HEX(RANDOM_BYTES(24))), 'semilla_demo', NOW()
);

INSERT INTO `une_lead_historial` (`negocio_id`, `admin_id`, `accion`, `resultado`, `nota`) VALUES
(1, 1, 'creado', NULL, 'Ficha de ejemplo cargada por semilla.'),
(1, 1, 'llamada', 'interesado', 'Contacto de ejemplo: representante confirmó datos y autorizó publicación.'),
(2, NULL, 'creado', NULL, 'Ficha de ejemplo importada por CSV (simulado).'),
(3, NULL, 'creado', NULL, 'Lead de ejemplo capturado por el formulario público.');
