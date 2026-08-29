# Despliegue — UNE Sports Perú (Fase 1 + Fase 2 + menú principal)

Este paquete incluye la **Fase 1 (Base y captación)** completa, más el
**buscador y descubrimiento (Fase 2)** y las páginas de contenido del
menú principal (Blog, Servicios, Nosotros, Contacto, Términos) que
normalmente corresponden a la Fase 3 del prompt maestro (§13), adelantadas
para que el sitio se vea completo y confiable desde ya. Quedan pendientes:
PHPMailer/SMTP real (se usa `mail()` nativo como interino), el dashboard
de métricas completo con embudo y cobertura por departamento,
`admin/usuarios.php`, `admin/ubigeo.php` y `admin/exportar.php`.

## 1. Requisitos previos en hPanel (Hostinger)

1. Selecciona **PHP 8.2** (o superior) para el dominio.
2. Crea una base de datos MySQL/MariaDB y un usuario con todos los
   permisos sobre ella. Anota: host (normalmente `localhost` en hosting
   compartido), nombre de la base, usuario, contraseña.
3. Activa el **SSL gratuito** del dominio.

## 2. Subir los archivos

Sube **todo el contenido de la carpeta `public_html/`** (no la carpeta
`public_html` en sí, sino lo que está dentro) a la raíz del hosting, vía
FTP o el Administrador de archivos de hPanel. Si ya tenías la Fase 1
subida, sobrescribe todos los archivos con esta versión.

Verifica especialmente:
- `.htaccess` se subió (algunos clientes FTP ocultan archivos que
  empiezan con punto; activa "mostrar archivos ocultos").
- Las carpetas `uploads/logos/` y `uploads/galeria/` existen y tienen
  permisos de escritura (habitualmente 755; si el servidor lo exige, 775).
- El archivo `uploads/.htaccess` se subió (bloquea la ejecución de PHP
  ahí dentro; es una medida de seguridad, no la borres).

## 3. Importar la base de datos

**Si es una instalación nueva**, en phpMyAdmin importa los archivos de
`sql/` en este orden exacto:

1. `01_esquema.sql`
2. `02_catalogos.sql`
3. `03_ubigeo.sql`
4. `04_semilla.sql`
5. `05_fase2_contenido.sql`

**Si ya tenías la Fase 1 funcionando**, solo te falta importar el
archivo nuevo:

5. `05_fase2_contenido.sql` — agrega los 5 artículos de ejemplo del blog.
   No crea tablas nuevas (`une_articulos` ya existía) ni te pide volver
   a correr los archivos anteriores.

`03_ubigeo.sql` carga 25 departamentos, 194 provincias y 1834 distritos
(ver el comentario al inicio del archivo: es un catálogo vivo, no una
cifra oficial cerrada — corrige o agrega registros puntuales según los
detecte el equipo en campo).

`04_semilla.sql` crea un usuario administrador de prueba y 3 fichas de
ejemplo **claramente marcadas como demo** (nombres con "Ejemplo"), para
que puedas probar el sitio de inmediato. Bórralas cuando tengas las
primeras fichas reales:
```sql
DELETE FROM une_lead_historial WHERE negocio_id IN (1,2,3);
DELETE FROM une_negocio_categorias WHERE negocio_id IN (1,2,3);
DELETE FROM une_negocio_deportes WHERE negocio_id IN (1,2,3);
DELETE FROM une_negocio_etapas WHERE negocio_id IN (1,2,3);
DELETE FROM une_horarios WHERE negocio_id IN (1,2,3);
DELETE FROM une_negocios WHERE id IN (1,2,3);
```

## 4. Configurar `config/config.php`

Si ya lo configuraste en la Fase 1, no necesitas tocarlo de nuevo. Si es
la primera vez, edita `config/config.php` (directamente en el servidor,
por FTP) y reemplaza:

- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` con los datos reales de tu
  base de datos (revisa el nombre exacto del usuario en hPanel: en
  algunos planes el usuario MySQL tiene el mismo nombre que la base,
  no uno separado).
- `SITE_URL` con tu dominio real (sin `/` al final, sin `http://` ni
  `ftp://` en ningún otro campo).
- `SITE_EMAIL_CONTACTO` y `SITE_EMAIL_ADMIN` con tus correos reales.
- `SMTP_USUARIO` / `SMTP_PASSWORD` con las credenciales de tu correo del
  plan Business Starter (usadas por `enviarCorreoSimple()`; ver la nota
  sobre correos más abajo).
- `APP_SECRET` y `TAREAS_TOKEN` con cadenas aleatorias propias. Puedes
  generarlas ejecutando en cualquier PHP local:
  ```
  php -r "echo bin2hex(random_bytes(32));"
  ```
- Confirma que `APP_DEBUG` quede en `false`.

**Seguridad:** usa contraseñas distintas para la base de datos y el
correo SMTP. Si alguna contraseña quedó expuesta en un chat, captura de
pantalla o repositorio compartido, cámbiala en hPanel antes de lanzar el sitio.

## 5. Cambiar la contraseña del administrador de prueba

El usuario sembrado es `admin` / `CambiaEsto123`. **Cámbialo antes de
publicar el sitio.** Como todavía no existe una pantalla de "cambiar mi
contraseña", hazlo por phpMyAdmin:

1. Genera un hash nuevo (en tu máquina, con PHP instalado):
   ```
   php -r "echo password_hash('TU_CONTRASENA_NUEVA', PASSWORD_DEFAULT);"
   ```
2. En phpMyAdmin, edita la fila del usuario `admin` en la tabla
   `une_admins` y pega el hash generado en la columna `password_hash`.

Para dar de alta a los otros 2 miembros del equipo (§7E: hasta 3 personas
usan el panel), repite el mismo procedimiento e insértalos por phpMyAdmin:
```sql
INSERT INTO une_admins (usuario, password_hash, nombre, rol)
VALUES ('asistente1', 'PEGA_AQUI_EL_HASH', 'Nombre del asistente', 'asistente');
```
(`rol` debe ser `administrador` o `asistente`). La pantalla
`admin/usuarios.php` para hacer esto desde el propio panel sigue pendiente.

## 6. Logo real

Reemplaza el archivo vacío `assets/img/logo.svg` por el logo definitivo
("UNE sports" + ícono deportivo, en `--naranja` #FF8300). Mientras el
archivo esté vacío o no exista, el sitio muestra automáticamente un
logo de repuesto en SVG inline (no se rompe nada).

## 7. Qué SÍ funciona ahora

**Captación (Fase 1):**
- `/registrar`: 3 campos obligatorios + 3 opcionales, deduplicación por
  teléfono, límite de envíos por IP, autocompletado de distrito.
- Login del panel con bloqueo tras 5 intentos fallidos.
- `/admin/leads.php`: bandeja de leads con filtros, registrar contacto,
  acciones en lote, enlaces de llamada y WhatsApp con mensaje precargado.
- `/admin/negocio-editar.php`: ficha completa (~45 campos), autoguardado,
  mapa Leaflet, geocodificación manual, bloqueo de edición concurrente,
  publicación y verificación con umbrales validados en servidor,
  permisos por rol.
- `/admin/importar.php`: importador CSV con vista previa y duplicados.

**Descubrimiento (Fase 2):**
- `/buscar`: filtros combinados (ubicación, tipo, categoría, disciplina,
  etapa, turno, precio, verificado, local propio, prueba gratis),
  reflejados en la URL, vista de lista y de mapa con agrupamiento de
  marcadores.
- `/academias/{departamento}` y `/academias/{departamento}/{distrito}`:
  páginas de aterrizaje geográficas con contenido propio.
- `/sitemap.php`: sitemap XML dinámico (fichas publicadas, artículos,
  páginas geográficas con al menos una ficha).
- Datos estructurados JSON-LD: `Organization` en inicio, `SportsActivityLocation`
  y `BreadcrumbList` en cada ficha, `Article` en cada entrada del blog.
- `/sugerir`: sugerencia ciudadana de 4 campos.
- `/reclamar/{slug}`: reclamo de ficha por su responsable.
- `/editar/{token}`: edición sin cuenta ni contraseña para quien reclamó
  su ficha; todo cambio queda en revisión hasta que un administrador lo apruebe.
- `/admin/sugerencias.php`: bandeja para convertir sugerencias en leads y
  aprobar o rechazar reclamos (al aprobar, genera y envía el enlace de edición).

**Contenido y confianza (adelantado de Fase 3):**
- `/blog` y `/blog/{slug}`, con 5 artículos reales de ejemplo ya cargados
  (elección de academia por edad, prevención de lesiones, rol de los
  padres, ligas escolares, salvaguarda infantil).
- `/admin/articulos.php`: alta, edición y borrado de artículos.
- `/servicios`, `/nosotros` (el grid de equipo se omite hasta que cargues
  gente en `une_equipo` por phpMyAdmin — no se inventaron nombres),
  `/contacto`, `/legal-terminos`.

**Correos:** siguen usando `mail()` nativo de PHP (`enviarCorreoSimple()`),
no PHPMailer con SMTP. Es una implementación mínima que puede terminar en
spam; si necesitas entrega confiable ahora, dilo y lo priorizamos.

## 8. Qué NO está incluido todavía

- PHPMailer con SMTP autenticado (§11 completo: confirmación al gestor,
  aviso de ficha publicada, plantillas HTML de marca).
- `admin/dashboard.php` completo: embudo con tasas de conversión, avance
  por persona del equipo, ranking de departamentos con menos de 5 fichas.
  La versión actual muestra solo cifras básicas del día a día.
- `admin/exportar.php` (exportación CSV de toda la base).
- `admin/usuarios.php` y `admin/ubigeo.php` (se administran por phpMyAdmin
  mientras tanto, ver §5 y el comentario en `sql/03_ubigeo.sql`).

## 9. Verificación rápida tras subir todo

1. Abre tu dominio: debe verse la página de inicio con el buscador y el
   menú completo (Inicio, Buscar, Registrar, Blog, Servicios, Nosotros, Contacto).
2. Ve a `/buscar`, prueba un par de filtros y alterna a la vista de mapa.
3. Ve a `/registrar`, completa el formulario y confirma que aparece en `/admin/leads.php`.
4. Inicia sesión en `/admin/index.php`, completa y publica una ficha desde la bandeja de leads.
5. Visita `/negocio/{slug}` de esa ficha: no debe mostrar ningún dato del representante.
6. Abre `/blog` y confirma que ves los 5 artículos de ejemplo.
7. Desde una ficha publicada, prueba "Reclamar ficha" con tu propio teléfono,
   apruébalo en `/admin/sugerencias.php`, y confirma que el enlace `/editar/{token}` funciona.
