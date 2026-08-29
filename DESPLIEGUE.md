# Despliegue — UNE Sports Perú (Fase 1)

Esta es la **Fase 1 (Base y captación)** del MVP, tal como la define el
prompt maestro en su §13: esquema de base de datos completo, ubigeo del
Perú poblado, catálogos, formulario público de captura rápida, backoffice
(login, bandeja de leads, ficha completa, importador CSV) y ficha pública
básica. Las Fases 2 (buscador completo, mapa, SEO/sitemap, sugerir/reclamar)
y 3 (blog, servicios, nosotros, contacto, PHPMailer/SMTP, dashboard de
métricas completo, exportación CSV) no están incluidas todavía.

## 1. Requisitos previos en hPanel (Hostinger)

1. Selecciona **PHP 8.2** (o superior) para el dominio.
2. Crea una base de datos MySQL/MariaDB y un usuario con todos los
   permisos sobre ella. Anota: host, nombre de la base, usuario, contraseña.
3. Activa el **SSL gratuito** del dominio.

## 2. Subir los archivos

Sube **todo el contenido de la carpeta `public_html/`** (no la carpeta
`public_html` en sí, sino lo que está dentro) a la raíz del hosting, vía
FTP o el Administrador de archivos de hPanel.

Verifica especialmente:
- `.htaccess` se subió (algunos clientes FTP ocultan archivos que
  empiezan con punto; activa "mostrar archivos ocultos").
- Las carpetas `uploads/logos/` y `uploads/galeria/` existen y tienen
  permisos de escritura (habitualmente 755; si el servidor lo exige, 775).
- El archivo `uploads/.htaccess` se subió (bloquea la ejecución de PHP
  ahí dentro; es una medida de seguridad, no la borres).

## 3. Importar la base de datos

En phpMyAdmin, sobre la base de datos que creaste, importa los archivos
de `sql/` **en este orden exacto**:

1. `01_esquema.sql`
2. `02_catalogos.sql`
3. `03_ubigeo.sql`
4. `04_semilla.sql`

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

Edita `config/config.php` (directamente en el servidor, por FTP) y
reemplaza:

- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` con los datos reales del paso 1.
- `SITE_URL` con tu dominio real (sin `/` al final).
- `SITE_EMAIL_CONTACTO` y `SITE_EMAIL_ADMIN` con tus correos reales.
- `SMTP_USUARIO` / `SMTP_PASSWORD` con las credenciales de tu correo del
  plan Business Starter (usadas por `enviarCorreoSimple()`; ver nota en
  §7 más abajo sobre el estado de los correos en esta fase).
- `APP_SECRET` y `TAREAS_TOKEN` con cadenas aleatorias propias. Puedes
  generarlas ejecutando en cualquier PHP local:
  ```
  php -r "echo bin2hex(random_bytes(32));"
  ```
- Confirma que `APP_DEBUG` quede en `false`.

## 5. Cambiar la contraseña del administrador de prueba

El usuario sembrado es `admin` / `CambiaEsto123`. **Cámbialo antes de
publicar el sitio.** Como todavía no existe una pantalla de "cambiar mi
contraseña" en esta fase, hazlo por phpMyAdmin:

1. Genera un hash nuevo (en tu máquina, con PHP instalado):
   ```
   php -r "echo password_hash('TU_CONTRASENA_NUEVA', PASSWORD_DEFAULT);"
   ```
2. En phpMyAdmin, edita la fila del usuario `admin` en la tabla
   `une_admins` y pega el hash generado en la columna `password_hash`.

Para dar de alta a los otros 2 miembros del equipo (§7E: hasta 3 personas
usan el panel), repite el mismo procedimiento: genera un hash para su
contraseña e insértalos por phpMyAdmin, por ejemplo:
```sql
INSERT INTO une_admins (usuario, password_hash, nombre, rol)
VALUES ('asistente1', 'PEGA_AQUI_EL_HASH', 'Nombre del asistente', 'asistente');
```
(`rol` debe ser `administrador` o `asistente`, según lo definido en §7E).
La pantalla `admin/usuarios.php` para hacer esto desde el propio panel
es una de las piezas pendientes de una fase posterior.

## 6. Logo real

Reemplaza el archivo vacío `assets/img/logo.svg` por el logo definitivo
("UNE sports" + ícono deportivo, en `--naranja` #FF8300). Mientras el
archivo esté vacío o no exista, el sitio muestra automáticamente un
logo de repuesto en SVG inline (no se rompe nada).

## 7. Qué SÍ funciona en esta Fase 1

- Formulario público `/registrar` (3 campos obligatorios + 3 opcionales,
  deduplicación por teléfono, límite de envíos por IP, autocompletado de
  distrito en un solo gesto).
- Aviso al admin por correo cuando llega un lead nuevo, usando la función
  `mail()` nativa de PHP (`enviarCorreoSimple()` en `includes/functions.php`).
  **Esto es una implementación mínima**: la Fase 3 del prompt (§11)
  reemplaza esto por PHPMailer con SMTP autenticado, porque `mail()`
  suele terminar en spam. Si necesitas entrega confiable de correos
  desde ya, dime y lo adelantamos fuera de la fase.
- Login del panel con bloqueo tras 5 intentos fallidos.
- Bandeja de leads (`/admin/leads.php`): filtros, vista de pendientes y
  vencidos, registrar contacto, acciones en lote, enlaces directos de
  llamada y WhatsApp con mensaje precargado.
- Ficha completa (`/admin/negocio-editar.php`): los ~45 campos en
  secciones plegables, autoguardado, mapa Leaflet con marcador
  arrastrable, geocodificación manual por botón (Nominatim), bloqueo de
  edición concurrente, publicación y verificación con los umbrales del
  §7C validados en servidor, permisos por rol (el asistente no puede
  publicar ni verificar).
- Importador CSV (`/admin/importar.php`): plantilla descargable, vista
  previa con detección de duplicados y errores, reporte de errores
  descargable.
- Ficha pública básica (`/negocio/{slug}`): nunca expone datos del
  representante, distingue fichas verificadas y no verificadas, incluye
  el enlace obligatorio de "Solicitar corrección o retiro de esta ficha".
- Página de privacidad (`/legal-privacidad`), enlazada desde el
  checkbox de consentimiento del formulario público.

## 8. Qué NO está incluido todavía (Fases 2 y 3 del prompt)

- Buscador completo con filtros combinados, vista de mapa con
  agrupamiento de marcadores y URLs compartibles (`/buscar`).
- Páginas de aterrizaje geográficas (`/academias/{departamento}/...`),
  sitemap XML dinámico y el resto del SEO estructurado (JSON-LD, etc.).
- `/sugerir` (sugerencia ciudadana) y `/reclamar/{slug}` (reclamo de
  ficha) — las tablas `une_sugerencias` y `une_reclamos` ya existen en
  el esquema, listas para cuando se construyan esas pantallas. Por eso
  la ficha pública enlaza a `/reclamar/{slug}`, que por ahora da 404.
- Blog, Servicios, Nosotros, Contacto, y el resto de correos
  transaccionales del §11 (confirmación al gestor, ficha publicada,
  enlace de reclamo, aviso de sugerencia).
- `admin/dashboard.php` completo (embudo con tasas de conversión,
  avance por persona del equipo, ranking de cobertura por departamento)
  y `admin/exportar.php`. La versión actual de `dashboard.php` muestra
  solo las cifras básicas del día a día.
- `admin/usuarios.php` (alta de usuarios desde el propio panel; por
  ahora se hace por phpMyAdmin, ver §5 arriba).
- `admin/ubigeo.php` (mantenimiento del catálogo geográfico desde el
  panel; por ahora, cualquier corrección al ubigeo se hace directamente
  por phpMyAdmin sobre `une_departamentos` / `une_provincias` / `une_distritos`).

## 9. Verificación rápida tras subir todo

1. Abre tu dominio: debe verse la página de inicio.
2. Ve a `/registrar`, completa el formulario y confirma que llega el
   correo al admin y que el registro aparece en `/admin/leads.php`.
3. Inicia sesión en `/admin/index.php` con el usuario que configuraste
   en el paso 5.
4. Abre una ficha desde la bandeja de leads, complétala y publícala.
5. Visita `/negocio/{slug}` de esa ficha y confirma que se ve
   correctamente y que ningún dato del representante aparece en la página.
