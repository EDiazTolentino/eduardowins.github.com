# UNE Sports

Directorio web de academias, escuelas, centros de rehabilitación, psicología deportiva y demás negocios de **deporte formativo en el Perú**. Sitio estático (HTML + CSS + JS) pensado para desplegarse directamente en **Hostinger** (hosting compartido), sin necesidad de build ni backend para esta primera fase.

## Estructura del proyecto

```
/
├── index.html              Página principal
├── buscar.html              Búsqueda con filtros, lista y mapa
├── negocio.html              Perfil de negocio (usa ?slug=xxx)
├── registrar.html            Formulario para registrar un negocio
├── blog.html                 Listado del blog
├── blog-articulo.html        Artículo individual (usa ?slug=xxx)
├── contacto.html             Formulario de contacto + FAQ
├── 404.html                  Página de error personalizada
├── css/
│   └── style.css             Sistema de diseño completo (colores, tipografía, componentes)
├── js/
│   ├── main.js                Menú móvil, toasts, lightbox, helpers compartidos
│   ├── buscar.js               Filtros, orden, paginación y mapa de resultados
│   ├── negocio.js              Render del perfil, reseñas (localStorage), mapa, JSON-LD
│   ├── registrar.js            Validación del formulario y preview de fotos
│   ├── blog.js                  Listado y artículo individual del blog
│   └── contacto.js             Validación del formulario de contacto y mapa
├── data/
│   ├── negocios.json           Datos de ejemplo de academias/centros (15 negocios reales de muestra)
│   └── blog.json                Artículos de ejemplo del blog
├── sitemap.xml
├── robots.txt
└── .htaccess                  Config básica de Apache/Hostinger (gzip, caché, HTTPS, 404)
```

## Identidad visual

| Uso | Color |
|---|---|
| Primario (CTAs, acentos) | `#FF8300` naranja |
| Secundario (headers, textos fuertes, botones outline) | `#1A365D` azul |
| Texto / menú | `#2D3748` gris carbón |
| Fondos | `#FFFFFF` / `#F7F8FA` |

Tipografías: **Montserrat** (títulos) + **Inter** (texto), cargadas desde Google Fonts. El naranja se reserva para elementos interactivos (botones, enlaces activos, badges); los párrafos largos siempre usan gris carbón sobre blanco para mantener buena legibilidad.

## Cómo probar el sitio localmente

Los datos (`negocios.json`, `blog.json`) se cargan con `fetch`, por lo que **no funcionan abriendo los archivos con doble clic** (protocolo `file://`). Levanta un servidor local simple desde la carpeta del proyecto:

```bash
python3 -m http.server 8080
# o
npx serve .
```

Luego abre `http://localhost:8080`.

## Despliegue en Hostinger

1. Comprime todo el contenido de esta carpeta (o usa el Administrador de Archivos de hPanel).
2. Sube y descomprime el contenido dentro de `public_html/` (o la subcarpeta de tu dominio).
3. Verifica que `.htaccess` haya subido correctamente (algunos clientes FTP ocultan archivos que empiezan con punto).
4. Actualiza el dominio real en las etiquetas `canonical`, Open Graph y en `sitemap.xml` / `robots.txt` (actualmente usan `https://www.unesports.pe/` como referencia).
5. Sube el sitemap a Google Search Console una vez publicado el dominio definitivo.

No se requiere Node, build ni base de datos para este alcance: todo funciona con archivos estáticos.

## Estado actual (Fase 1: diseño + maquetación)

Siguiendo la prioridad solicitada, primero se completó el diseño visual completo en HTML/CSS (mobile-first, todas las páginas y estados) y luego se añadió una capa de funcionalidad ligera 100% en el navegador:

- **Búsqueda y filtros** (región, tipo, precio, valoración, servicios), orden y mapa con [Leaflet](https://leafletjs.com/) + OpenStreetMap (sin API key).
- **Perfil de negocio** dinámico desde `negocios.json`, con galería + lightbox, mapa, WhatsApp/llamada/compartir y sistema de reseñas (las nuevas reseñas se guardan en `localStorage` del navegador, no en un servidor).
- **Formulario de registro de negocio** y **formulario de contacto** con validación en el cliente. Aún no están conectados a un backend/correo real: al enviar, solo muestran una pantalla de confirmación.
- **Blog** con artículos de ejemplo y filtro por categoría.
- SEO básico: meta tags dinámicas por página, `sitemap.xml`, `robots.txt`, datos estructurados (JSON-LD) en negocio y artículo, `loading="lazy"` en imágenes.

## Próximos pasos sugeridos (Fase 2: backend)

- Reemplazar `data/*.json` por una base de datos real (negocios, usuarios, valoraciones, distritos) y una API.
- Conectar `registrar.html` y `contacto.html` a un endpoint real (correo, base de datos o servicio como Formspree).
- Autenticación para dueños de negocio y panel de administración/moderación.
- Subida real de imágenes (actualmente se usan imágenes de referencia de `placehold.co` y avatares de `i.pravatar.cc`).
