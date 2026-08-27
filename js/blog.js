/* UNE Sports — listado y artículo individual del blog */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    fetch("data/blog.json")
      .then(function (r) { return r.json(); })
      .then(function (articulos) {
        if (document.getElementById("blogList")) initListado(articulos);
        if (document.getElementById("articleCuerpo")) initArticulo(articulos);
      });
  });

  function initListado(articulos) {
    var categorias = Array.from(new Set(articulos.map(function (a) { return a.categoria; })));
    var tabsWrap = document.getElementById("categoryTabs");
    tabsWrap.innerHTML = ['Todas'].concat(categorias).map(function (cat, i) {
      return '<button type="button" class="btn ' + (i === 0 ? "btn-primary" : "btn-ghost") + ' btn-sm" data-cat="' + escapeHtml(cat === 'Todas' ? '' : cat) + '">' + escapeHtml(cat) + '</button>';
    }).join(" ");

    var featured = articulos[0];
    document.getElementById("blogFeatured").innerHTML =
      '<article class="article-featured">' +
        '<img src="' + featured.imagen + '" alt="' + escapeHtml(featured.titulo) + '" loading="eager">' +
        '<div class="article-body">' +
          '<span class="article-cat">' + escapeHtml(featured.categoria) + '</span>' +
          '<h2 style="margin-bottom:0.5rem;"><a href="blog-articulo.html?slug=' + featured.slug + '">' + escapeHtml(featured.titulo) + '</a></h2>' +
          '<p class="article-excerpt">' + escapeHtml(featured.resumen) + '</p>' +
          '<div class="article-meta"><img src="' + featured.autorFoto + '" alt=""><span>' + escapeHtml(featured.autor) + ' · ' + formatDate(featured.fecha) + ' · ' + featured.tiempoLectura + ' de lectura</span></div>' +
          '<a href="blog-articulo.html?slug=' + featured.slug + '" class="btn btn-primary" style="margin-top:1rem; align-self:flex-start;">Leer artículo</a>' +
        '</div>' +
      '</article>';

    function renderList(filterCat) {
      var list = articulos.slice(1).filter(function (a) { return !filterCat || a.categoria === filterCat; });
      document.getElementById("blogList").innerHTML = list.map(articleCardHtml).join("") ||
        '<p style="color:var(--color-text-light);">No hay artículos en esta categoría todavía.</p>';
    }
    renderList("");

    tabsWrap.addEventListener("click", function (e) {
      var btn = e.target.closest("button[data-cat]");
      if (!btn) return;
      tabsWrap.querySelectorAll("button").forEach(function (b) { b.classList.remove("btn-primary"); b.classList.add("btn-ghost"); });
      btn.classList.remove("btn-ghost"); btn.classList.add("btn-primary");
      renderList(btn.getAttribute("data-cat"));
    });
  }

  function articleCardHtml(a) {
    return '<article class="article-card">' +
      '<a class="article-media" href="blog-articulo.html?slug=' + a.slug + '"><img src="' + a.imagen + '" alt="' + escapeHtml(a.titulo) + '" loading="lazy"></a>' +
      '<div class="article-body">' +
        '<span class="article-cat">' + escapeHtml(a.categoria) + '</span>' +
        '<h3><a href="blog-articulo.html?slug=' + a.slug + '">' + escapeHtml(a.titulo) + '</a></h3>' +
        '<p class="article-excerpt">' + escapeHtml(a.resumen) + '</p>' +
        '<div class="article-meta"><img src="' + a.autorFoto + '" alt=""><span>' + escapeHtml(a.autor) + ' · ' + a.tiempoLectura + '</span></div>' +
      '</div>' +
    '</article>';
  }

  function initArticulo(articulos) {
    var slug = new URLSearchParams(window.location.search).get("slug");
    var a = articulos.find(function (x) { return x.slug === slug; });
    if (!a) {
      document.getElementById("main").innerHTML = '<div class="container"><div class="empty-state"><h3>No encontramos este artículo</h3><a href="blog.html" class="btn btn-primary" style="margin-top:1rem;">Volver al blog</a></div></div>';
      return;
    }

    document.title = a.titulo + " | Blog UNE Sports";
    document.getElementById("pageTitle").textContent = document.title;
    document.getElementById("pageDescription").setAttribute("content", a.resumen);
    document.getElementById("pageCanonical").setAttribute("href", "https://www.unesports.pe/blog-articulo.html?slug=" + a.slug);
    document.getElementById("breadcrumbTitulo").textContent = a.titulo;
    document.getElementById("articleCategoria").textContent = a.categoria;
    document.getElementById("articleTitulo").textContent = a.titulo;
    document.getElementById("articleAutorFoto").src = a.autorFoto;
    document.getElementById("articleAutorFoto").alt = a.autor;
    document.getElementById("articleAutor").textContent = a.autor;
    document.getElementById("articleFechaLectura").textContent = formatDate(a.fecha) + " · " + a.tiempoLectura + " de lectura";
    document.getElementById("articleImagen").src = a.imagen;
    document.getElementById("articleImagen").alt = a.titulo;

    document.getElementById("articleCuerpo").innerHTML = a.contenido.map(function (block) {
      if (block.tipo === "titulo") return "<h2>" + escapeHtml(block.texto) + "</h2>";
      if (block.tipo === "cita") return "<blockquote>" + escapeHtml(block.texto) + "</blockquote>";
      if (block.tipo === "lista") return "<ul>" + block.items.map(function (i) { return "<li>" + escapeHtml(i) + "</li>"; }).join("") + "</ul>";
      return "<p>" + escapeHtml(block.texto) + "</p>";
    }).join("");

    document.getElementById("articleTags").innerHTML =
      '<span class="tag tag--primary">' + escapeHtml(a.categoria) + '</span><span class="tag">Deporte Formativo</span><span class="tag">Perú</span>';

    var relacionados = articulos.filter(function (x) { return x.slug !== a.slug; }).slice(0, 3);
    document.getElementById("relatedArticles").innerHTML = relacionados.map(articleCardHtml).join("");

    injectJsonLd(a);
  }

  function injectJsonLd(a) {
    var script = document.createElement("script");
    script.type = "application/ld+json";
    script.textContent = JSON.stringify({
      "@context": "https://schema.org",
      "@type": "Article",
      "headline": a.titulo,
      "description": a.resumen,
      "image": a.imagen,
      "author": { "@type": "Person", "name": a.autor },
      "datePublished": a.fecha
    });
    document.head.appendChild(script);
  }

  function formatDate(iso) {
    var d = new Date(iso + "T00:00:00");
    return d.toLocaleDateString("es-PE", { day: "numeric", month: "long", year: "numeric" });
  }
  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }
})();
