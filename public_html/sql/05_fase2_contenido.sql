-- =====================================================================
-- 05_fase2_contenido.sql — Contenido adicional de la Fase 2 (blog).
-- Ejecutar UNA sola vez, después de 01-04, sobre una base de datos que
-- ya tiene el esquema de la Fase 1 (no crea tablas nuevas: une_articulos
-- y une_equipo ya existen desde 01_esquema.sql).
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO `une_articulos` (`titulo`, `slug`, `resumen`, `contenido`, `categoria`, `meta_titulo`, `meta_descripcion`, `publicado`, `fecha`) VALUES
(
  'Cómo elegir la academia deportiva ideal según la edad de tu hijo',
  'como-elegir-la-academia-deportiva-ideal-segun-la-edad-de-tu-hijo',
  'No todas las academias son iguales para todas las edades. Aquí te contamos qué priorizar en cada etapa de formación.',
  '<p>Elegir una academia deportiva no es solo cuestión de cercanía o precio: la etapa de desarrollo de tu hijo o hija influye directamente en qué tipo de formación conviene más.</p>
   <h2>Iniciación (4 a 6 años)</h2>
   <p>En esta etapa el objetivo no es competir, sino desarrollar coordinación, equilibrio y el gusto por moverse. Prioriza academias con grupos reducidos, sesiones cortas y entrenadores con experiencia específica en esta edad, más que resultados deportivos tempranos.</p>
   <h2>Formación básica y específica (7 a 12 años)</h2>
   <p>Aquí ya se pueden introducir fundamentos técnicos de una disciplina concreta. Busca academias que combinen técnica con juego, y que sigan cuidando la variedad de movimiento en lugar de una especialización total.</p>
   <h2>Especialización y rendimiento (13 a 18 años)</h2>
   <p>En la adolescencia, si tu hijo o hija muestra interés real por competir, conviene evaluar academias con planes de entrenamiento más estructurados, participación en torneos y, de ser el caso, vínculo con federaciones o ligas escolares.</p>
   <h2>Preguntas que vale la pena hacer</h2>
   <ul>
     <li>¿Cuántos alumnos hay por entrenador?</li>
     <li>¿El local es propio o alquilado ocasionalmente?</li>
     <li>¿Cuentan con seguro contra accidentes?</li>
     <li>¿Tienen un protocolo claro de salvaguarda infantil?</li>
   </ul>
   <p>En UNE Sports Perú puedes filtrar academias por etapa de edad y revisar estos distintivos de confianza directamente en cada ficha.</p>',
  'Guía para padres',
  NULL, NULL, 1, CURDATE()
),
(
  'Prevención de lesiones deportivas en niños y adolescentes',
  'prevencion-de-lesiones-deportivas-en-ninos-y-adolescentes',
  'El cuerpo de un niño no es el de un adulto en miniatura. Estas son las precauciones básicas que toda academia debería seguir.',
  '<p>El sistema óseo y muscular de niños y adolescentes sigue en desarrollo, lo que los hace más vulnerables a ciertos tipos de lesiones si el entrenamiento no está bien planificado.</p>
   <h2>Calentamiento y progresión</h2>
   <p>Un calentamiento adecuado y una carga de entrenamiento que aumenta gradualmente son la primera línea de prevención. Desconfía de academias que buscan resultados rápidos a costa de entrenamientos intensos sin progresión.</p>
   <h2>Especialización temprana</h2>
   <p>Concentrar a un niño en una sola disciplina de forma muy temprana, sin variedad de movimiento, se asocia a mayor riesgo de lesiones por sobreuso. La recomendación general de pediatras deportivos es priorizar el desarrollo motor amplio antes de especializar.</p>
   <h2>Señales de alerta</h2>
   <ul>
     <li>Dolor que persiste después del entrenamiento.</li>
     <li>Entrenadores que minimizan quejas de dolor.</li>
     <li>Ausencia de pausas o hidratación durante la sesión.</li>
   </ul>
   <h2>El rol del examen médico</h2>
   <p>Muchas academias serias exigen un examen médico antes de matricular a un menor, especialmente en disciplinas de contacto o alto impacto. En UNE Sports Perú puedes ver qué academias lo requieren directamente en su ficha.</p>
   <p>Ante cualquier duda, la recomendación siempre es consultar con el pediatra del menor antes de iniciar una disciplina exigente.</p>',
  'Salud deportiva',
  NULL, NULL, 1, CURDATE()
),
(
  'El rol de los padres en la formación deportiva de sus hijos',
  'el-rol-de-los-padres-en-la-formacion-deportiva-de-sus-hijos',
  'Acompañar sin presionar: cómo apoyar a un hijo o hija que practica deporte formativo sin quitarle el disfrute.',
  '<p>El deporte formativo no busca crear campeones a los ocho años; busca formar hábitos, disciplina y disfrute por el movimiento. El rol de los padres en ese proceso es más de acompañamiento que de exigencia.</p>
   <h2>Celebrar el esfuerzo, no solo el resultado</h2>
   <p>Los especialistas en psicología deportiva infantil coinciden en algo simple: los comentarios después de un partido o una práctica deberían enfocarse en el esfuerzo y la actitud, no únicamente en si se ganó o se perdió.</p>
   <h2>Evitar la presión de resultados</h2>
   <p>La presión constante por ganar es una de las principales causas de abandono deportivo en la adolescencia. Dale espacio a tu hijo o hija para que el deporte siga siendo algo que eligen, no una obligación.</p>
   <h2>Ser un espectador respetuoso</h2>
   <p>Gritar instrucciones desde la tribuna o cuestionar en público las decisiones del entrenador no ayuda al aprendizaje del menor. Si tienes una observación, el canal adecuado es una conversación directa y privada con la academia.</p>
   <h2>Involúcrate, pero desde afuera de la cancha</h2>
   <p>Preguntar cómo le fue, asistir a las presentaciones, conocer al entrenador y entender el plan de la academia son formas de involucrarse que refuerzan la motivación del menor sin invadir su espacio de aprendizaje.</p>',
  'Guía para padres',
  NULL, NULL, 1, CURDATE()
),
(
  'Calendario de ligas escolares en el Perú: qué debes saber',
  'calendario-de-ligas-escolares-en-el-peru-que-debes-saber',
  'Una introducción a cómo funcionan las competencias escolares y ligas distritales en el Perú, y cómo saber si tu academia participa en ellas.',
  '<p>Muchas familias descubren el deporte formativo de sus hijos a través de campeonatos escolares o ligas organizadas por municipios y federaciones distritales. Entender cómo funcionan ayuda a elegir mejor dónde entrenar.</p>
   <h2>Juegos Florales Escolares Nacionales</h2>
   <p>Es la competencia deportiva y cultural escolar más conocida a nivel nacional, organizada dentro del sistema educativo peruano, con etapas que van desde el colegio hasta instancias regionales y nacionales.</p>
   <h2>Ligas distritales y municipales</h2>
   <p>Muchas municipalidades organizan campeonatos vecinales o distritales en disciplinas como fútbol, vóley y básquet, muchas veces en alianza con academias locales que preparan a sus alumnos para participar.</p>
   <h2>Ligas de federaciones</h2>
   <p>Para quienes buscan un camino más competitivo, las federaciones de cada disciplina organizan campeonatos categorizados por edad, generalmente accesibles a través de clubes o academias afiliadas.</p>
   <h2>Cómo saber si una academia participa</h2>
   <p>En UNE Sports Perú, cada ficha indica si la academia tiene afiliación a una federación. Para calendarios específicos de tu distrito, la fuente más confiable sigue siendo la municipalidad o la federación de la disciplina que te interesa.</p>',
  'Comunidad',
  NULL, NULL, 1, CURDATE()
),
(
  'Salvaguarda infantil en el deporte: qué debe tener una academia segura',
  'salvaguarda-infantil-en-el-deporte-que-debe-tener-una-academia-segura',
  'La salvaguarda infantil no es un detalle opcional. Estas son las señales de que una academia se toma en serio la protección de sus alumnos.',
  '<p>La salvaguarda infantil en el deporte se refiere al conjunto de políticas y prácticas que protegen a niños y adolescentes de cualquier forma de daño, abuso o negligencia dentro del entorno deportivo.</p>
   <h2>Qué es un protocolo de salvaguarda</h2>
   <p>Es un documento y una práctica interna que define, entre otras cosas, cómo se seleccionan los entrenadores, qué comportamientos no están permitidos, cómo se supervisan los espacios (vestuarios, traslados, entrenamientos) y a quién acudir ante una preocupación.</p>
   <h2>Señales de una academia comprometida</h2>
   <ul>
     <li>Verificación de antecedentes de su personal.</li>
     <li>Políticas claras sobre contacto físico y comunicación con menores.</li>
     <li>Espacios de entrenamiento visibles, nunca completamente aislados.</li>
     <li>Un canal definido para que un padre o un menor reporte una incomodidad.</li>
   </ul>
   <h2>Personal certificado</h2>
   <p>Contar con entrenadores certificados no solo mejora la calidad técnica de la enseñanza: también suele ir de la mano de una formación básica en el trato adecuado con menores.</p>
   <h2>Tu rol como padre o madre</h2>
   <p>Pregunta directamente si la academia tiene un protocolo de salvaguarda, quién es el responsable de aplicarlo, y presta atención a cómo reacciona el personal ante esa pregunta. En UNE Sports Perú, las fichas que cuentan con este distintivo lo muestran explícitamente.</p>',
  'Salvaguarda infantil',
  NULL, NULL, 1, CURDATE()
);
