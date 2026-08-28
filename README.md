# UNE Sports

Directorio web de academias, escuelas, centros de rehabilitación, psicología deportiva y demás negocios de **deporte formativo en el Perú**. Sitio en **HTML + CSS + JS** con backend en **PHP + MySQL**, pensado para desplegarse directamente en **Hostinger** (hosting compartido: Apache + PHP + MySQL/phpMyAdmin, sin Node ni build).

## Estructura del proyecto

```
/
├── index.html                 Página principal
├── buscar.html                 Búsqueda con filtros, lista y mapa
├── negocio.html                 Perfil de negocio (usa ?slug=xxx)
├── registrar.html               Formulario para registrar un negocio
├── blog.html                    Listado del blog
├── blog-articulo.html           Artículo individual (usa ?slug=xxx)
├── contacto.html                Formulario de contacto + FAQ
├── 404.html                     Página de error personalizada
├── css/
│   └── style.css                Sistema de diseño completo (colores, tipografía, componentes)
├── js/
│   ├── main.js                   Menú móvil, toasts, lightbox, helpers compartidos
│   ├── buscar.js                  Filtros, orden, paginación y mapa de resultados
│   ├── negocio.js                 Render del perfil, reseñas, mapa, JSON-LD
│   ├── registrar.js               Validación + envío del formulario a la API
│   ├── blog.js                     Listado y artículo individual del blog
│   └── contacto.js                Validación + envío del formulario a la API
├── api/                          Backend PHP (API JSON que consume el JS de arriba)
│   ├── config.sample.php          Plantilla de credenciales de la BD (copiar a config.php)
│   ├── db.php                     Conexión PDO + helpers compartidos
│   ├── negocios.php     [GET]     Lista de negocios publicados
│   ├── blog.php         [GET]     Lista de artículos del blog
│   ├── ubicaciones.php  [GET]     Catálogo completo región/provincia/distrito del Perú
│   ├── resena.php       [POST]    Crea una reseña y recalcula el promedio
│   ├── registrar.php    [POST]    Crea un negocio en estado "pendiente"
│   ├── contacto.php     [POST]    Guarda un mensaje de contacto
│   └── .htaccess                  Bloquea el acceso directo a config.php
├── database/
│   ├── schema.sql                 Solo estructura (tablas, relaciones, índices)
│   ├── seed.sql                   Solo datos iniciales de negocios/blog (generado desde /data)
│   ├── ubicaciones_peru.sql       Catálogo completo de 25 regiones / 196 provincias / 1892 distritos
│   ├── une_sports.sql             schema.sql + seed.sql + ubicaciones_peru.sql → el archivo a importar en phpMyAdmin
│   ├── generate_seed.py           Regenera seed.sql si cambian data/negocios.json o data/blog.json
│   └── build_ubicaciones.py       Script que generó ubicaciones_peru.sql (no hace falta volver a correrlo)
├── data/
│   ├── negocios.json              Datos de ejemplo originales (fuente de la semilla SQL)
│   └── blog.json                   Artículos de ejemplo originales (fuente de la semilla SQL)
├── sitemap.xml
├── robots.txt
└── .htaccess                     Config básica de Apache/Hostinger (gzip, caché, HTTPS, 404)
```

`data/*.json` ya no las consume el sitio en vivo (eso ahora lo hace `api/negocios.php` y `api/blog.php` desde MySQL); se conservan como la fuente legible con la que se generó `database/seed.sql`.

## Identidad visual

| Uso | Color |
|---|---|
| Primario (CTAs, acentos) | `#FF8300` naranja |
| Secundario (headers, textos fuertes, botones outline) | `#1A365D` azul |
| Texto / menú | `#2D3748` gris carbón |
| Fondos | `#FFFFFF` / `#F7F8FA` |

Tipografías: **Montserrat** (títulos) + **Inter** (texto), cargadas desde Google Fonts. El naranja se reserva para elementos interactivos (botones, enlaces activos, badges); los párrafos largos siempre usan gris carbón sobre blanco para mantener buena legibilidad.

## Base de datos (MySQL)

Tablas: `categorias`, `distritos_peru` (catálogo de región/provincia/distrito), `usuarios`, `negocios`, `servicios` + `negocio_servicios` (N:N), `negocio_imagenes`, `negocio_horarios`, `valoraciones`, `blog_categorias`, `blog_articulos`, `mensajes_contacto`.

### Catálogo de regiones, provincias y distritos

`distritos_peru` trae las **25 regiones, 196 provincias y 1892 distritos** oficiales del Perú (fuente: [RitchieRD/ubigeos-peru-data](https://github.com/RitchieRD/ubigeos-peru-data), Callao ya separado de Lima). Lo consume `api/ubicaciones.php` para poblar en cascada: los filtros de `buscar.html` (región → provincia → distrito) y los selects de `registrar.html`, sin depender de qué negocios existan ya en la base.

Nota: los nombres de región llevan tildes corregidas a mano; algunos nombres de provincia/distrito pueden faltarles la tilde porque la fuente original no las traía (ej. "Ancon" en vez de "Ancón"). Si ves alguno mal, dime cuál y te paso el `UPDATE` puntual para corregirlo.

**Si ya tienes el sitio en producción** y solo quieres sumar este catálogo (sin perder tus negocios/reseñas/mensajes reales), importa **únicamente** `database/ubicaciones_peru.sql` desde phpMyAdmin — es seguro, solo reemplaza el contenido de `distritos_peru`. Reservar `database/une_sports.sql` completo solo para una instalación nueva desde cero (ese sí borra y reinicia todas las tablas).

Un negocio registrado desde `registrar.html` entra con `estado = "pendiente"` y **no aparece** en el directorio público hasta que se cambie manualmente a `"publicado"` desde phpMyAdmin (aún no hay panel de administración, ver "Próximos pasos").

## Despliegue en Hostinger (con base de datos)

1. **Crear la base de datos**: en hPanel → *Bases de datos* → *MySQL*, crea una base y un usuario, y anota host / nombre / usuario / contraseña.
2. **Importar el esquema**: en hPanel → *phpMyAdmin*, entra a esa base, pestaña *Importar*, y sube `database/une_sports.sql` (trae estructura + los 15 negocios y 6 artículos de ejemplo ya cargados).
3. **Configurar la API**: dentro de `api/`, duplica `config.sample.php` como `config.php` y coloca ahí los datos del paso 1.
4. **Subir los archivos**: sube todo el contenido de esta carpeta a `public_html/` (o la subcarpeta de tu dominio) vía Administrador de Archivos o FTP. Verifica que `.htaccess` (raíz y `api/`) hayan subido — algunos clientes ocultan por defecto los archivos que empiezan con punto.
5. Abre tu dominio: la página principal, la búsqueda, los perfiles y el blog ya deberían leer datos reales desde MySQL, y los formularios de reseña/registro/contacto quedan guardados en la base.
6. Actualiza el dominio real en las etiquetas `canonical`/Open Graph y en `sitemap.xml`/`robots.txt` (usan `https://www.unesports.pe/` como referencia) y sube el sitemap a Google Search Console.

`config.php` nunca se sube al repositorio de Git (está en `.gitignore`); al desplegar por FTP/zip sí debes subirlo tú mismo con las credenciales reales.

## Cómo probar el sitio localmente

Necesitas PHP y MySQL/MariaDB corriendo en tu máquina (por ejemplo con [XAMPP](https://www.apachefriends.org/) o [Laragon](https://laragon.org/) en Windows, o `php` + `mariadb` instalados en Linux/Mac).

```bash
# 1. Crea la base e importa el esquema + datos
mysql -u root -e "CREATE DATABASE une_sports CHARACTER SET utf8mb4"
mysql -u root une_sports < database/une_sports.sql

# 2. Copia y completa las credenciales
cp api/config.sample.php api/config.php   # edítalo con tus datos locales

# 3. Levanta el servidor embebido de PHP desde la raíz del proyecto
php -S localhost:8080
```

Luego abre `http://localhost:8080`. Si prefieres no instalar nada, XAMPP/Laragon te dan Apache + PHP + MySQL + phpMyAdmin listos con un instalador.

## Estado actual

**Diseño (Fase 1)** — completo: todas las páginas maquetadas mobile-first con la identidad de marca, componentes reutilizables y estados de carga/vacío.

**Backend (Fase 2)** — completo para este alcance:

- **Búsqueda y filtros** (región → provincia → distrito en cascada usando el catálogo oficial completo, tipo, precio, valoración, servicios), orden y mapa con [Leaflet](https://leafletjs.com/) + OpenStreetMap (sin API key), leyendo `api/negocios.php` + `api/ubicaciones.php`.
- **Perfil de negocio** con galería + lightbox, mapa, WhatsApp/llamada/compartir y **reseñas reales**: se guardan en MySQL vía `api/resena.php` y recalculan el promedio del negocio al instante.
- **Registrar negocio** y **Contacto**: validan en el cliente y **guardan en la base de datos** vía `api/registrar.php` / `api/contacto.php` (ya no son solo una pantalla de confirmación falsa). Región/provincia/distrito se eligen de selects en cascada, no se escriben a mano.
- **Blog** leyendo `api/blog.php`, con filtro por categoría.
- SEO básico: meta tags dinámicas por página, `sitemap.xml`, `robots.txt`, datos estructurados (JSON-LD), `loading="lazy"` en imágenes.

## Próximos pasos sugeridos (Fase 3)

- Panel de administración para aprobar/rechazar negocios `pendientes` y moderar reseñas (hoy se hace a mano desde phpMyAdmin).
- Autenticación real de usuarios/dueños de negocio (la tabla `usuarios` ya existe, falta login y permisos).
- Subida real de imágenes a servidor (hoy se usan imágenes de referencia de `placehold.co` y avatares de `i.pravatar.cc`; el formulario de registro previsualiza fotos pero no las sube).
- Envío de notificaciones por correo al recibir un registro o mensaje de contacto (hoy solo quedan guardados en la base).
- Paginación/índices a nivel de base de datos si el catálogo crece mucho (hoy `api/negocios.php` trae todo el listado publicado en una sola respuesta, igual que hacía el JSON estático).
