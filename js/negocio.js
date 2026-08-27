/* UNE Sports — lógica del perfil de negocio */
(function () {
  "use strict";

  var slug = new URLSearchParams(window.location.search).get("slug");
  var negocio = null;
  var allNegocios = [];

  document.addEventListener("DOMContentLoaded", function () {
    fetch("data/negocios.json")
      .then(function (r) { return r.json(); })
      .then(function (data) {
        allNegocios = data;
        negocio = data.find(function (n) { return n.slug === slug; });
        if (!negocio) {
          document.getElementById("main").innerHTML = '<div class="container"><div class="empty-state"><h3>No encontramos este negocio</h3><p>Es posible que el enlace sea incorrecto.</p><a href="buscar.html" class="btn btn-primary" style="margin-top:1rem;">Volver a buscar</a></div></div>';
          return;
        }
        renderAll(negocio);
      });
  });

  function renderAll(n) {
    document.title = n.nombre + " - " + n.tipoLabel + " en " + n.distrito + " | UNE Sports";
    document.getElementById("pageTitle").textContent = document.title;
    document.getElementById("pageDescription").setAttribute("content", n.descripcion.slice(0, 155));
    document.getElementById("pageCanonical").setAttribute("href", "https://www.unesports.pe/negocio.html?slug=" + n.slug);
    document.getElementById("breadcrumbNombre").textContent = n.nombre;

    renderGallery(n);
    renderHeader(n);
    renderDescripcion(n);
    renderServicios(n);
    renderHorario(n);
    renderMap(n);
    renderRatingSummary(n);
    renderReviews(n);
    renderSidebar(n);
    renderSimilares(n);
    bindReviewForm(n);
    injectJsonLd(n);
  }

  function renderGallery(n) {
    var wrap = document.getElementById("profileGalleryWrap");
    var imgs = n.galeria.slice(0, 5);
    var html = '<div class="profile-gallery">' + imgs.map(function (src, i) {
      var extra = (i === 4 && n.galeria.length > 5) ? '<div class="gallery-more">+' + (n.galeria.length - 5) + ' fotos</div>' : "";
      return '<a href="' + src + '" data-lightbox-group="galeria"><img src="' + src + '" alt="' + escapeHtml(n.nombre) + ' - foto ' + (i + 1) + '" loading="' + (i === 0 ? "eager" : "lazy") + '">' + extra + '</a>';
    }).join("") + "</div>";
    wrap.innerHTML = html;
  }

  function renderHeader(n) {
    document.getElementById("profileNombre").textContent = n.nombre;
    document.getElementById("profileTipo").textContent = n.tipoLabel;
    document.getElementById("profileUbicacion").textContent = n.distrito + ", " + n.region;
    document.getElementById("profilePrecio").textContent = n.precio;
    document.getElementById("profileRating").innerHTML =
      '<span class="stars">' + UNE.renderStars(n.valoracion) + '</span><strong>' + n.valoracion.toFixed(1) + '</strong><span class="count">(' + n.numResenas + ' reseñas)</span>';
    document.getElementById("profileVerificado").innerHTML = n.verificado
      ? '<span class="verified-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>Verificado</span>' : "";
  }

  function renderDescripcion(n) { document.getElementById("profileDescripcion").textContent = n.descripcion; }

  function renderServicios(n) {
    document.getElementById("profileServicios").innerHTML = n.servicios.map(function (s) {
      return '<div class="service-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>' + escapeHtml(s) + '</div>';
    }).join("");
  }

  function renderHorario(n) {
    document.getElementById("profileHorario").innerHTML = n.horario.map(function (h) {
      return "<tr><td>" + escapeHtml(h.dia) + "</td><td>" + escapeHtml(h.hora) + "</td></tr>";
    }).join("");
  }

  function renderMap(n) {
    if (typeof L === "undefined") return;
    var map = L.map("profileMap", { scrollWheelZoom: false }).setView([n.lat, n.lng], 15);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      attribution: "&copy; colaboradores de OpenStreetMap"
    }).addTo(map);
    L.marker([n.lat, n.lng]).addTo(map).bindPopup("<strong>" + escapeHtml(n.nombre) + "</strong><br>" + escapeHtml(n.direccion)).openPopup();
    setTimeout(function () { map.invalidateSize(); }, 300);
    document.getElementById("profileDireccion").textContent = n.direccion;
  }

  function getStoredReviews(slugKey) {
    try { return JSON.parse(localStorage.getItem("une-reviews-" + slugKey) || "[]"); } catch (e) { return []; }
  }
  function saveStoredReviews(slugKey, reviews) {
    localStorage.setItem("une-reviews-" + slugKey, JSON.stringify(reviews));
  }

  function allReviews(n) { return getStoredReviews(n.slug).concat(n.resenas.slice().reverse()); }

  function renderRatingSummary(n) {
    var reviews = allReviews(n);
    var counts = [0, 0, 0, 0, 0];
    reviews.forEach(function (r) { counts[Math.round(r.valoracion) - 1]++; });
    var total = reviews.length || 1;

    document.getElementById("ratingBigNumber").textContent = n.valoracion.toFixed(1);
    document.getElementById("ratingBigStars").innerHTML = UNE.renderStars(n.valoracion);
    document.getElementById("ratingBigCount").textContent = n.numResenas + " reseñas";

    var barsHtml = "";
    for (var star = 5; star >= 1; star--) {
      var pct = Math.round((counts[star - 1] / total) * 100);
      barsHtml += '<div class="rating-bar-row"><span>' + star + ' ★</span><div class="bar"><span style="width:' + pct + '%"></span></div><span>' + counts[star - 1] + '</span></div>';
    }
    document.getElementById("ratingBars").innerHTML = barsHtml;
  }

  function renderReviews(n) {
    var reviews = allReviews(n);
    document.getElementById("reviewsList").innerHTML = reviews.map(function (r) {
      var initial = (r.autor || "?").trim().charAt(0).toUpperCase();
      var avatar = r.avatar ? '<img src="' + r.avatar + '" alt="" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">' : '<div class="review-avatar">' + initial + '</div>';
      return '<div class="review">' + avatar +
        '<div style="flex:1;"><div class="review-head"><strong>' + escapeHtml(r.autor) + '</strong><span class="review-date">' + formatDate(r.fecha) + '</span></div>' +
        '<span class="stars">' + UNE.renderStars(r.valoracion) + '</span><p>' + escapeHtml(r.comentario) + '</p></div></div>';
    }).join("") || '<p style="color:var(--color-text-light);">Aún no hay reseñas. ¡Sé el primero en compartir tu experiencia!</p>';
  }

  function bindReviewForm(n) {
    document.getElementById("reviewForm").addEventListener("submit", function (e) {
      e.preventDefault();
      var nombre = document.getElementById("reviewNombre").value.trim();
      var estrellas = parseInt(document.getElementById("reviewEstrellas").value, 10);
      var comentario = document.getElementById("reviewComentario").value.trim();
      if (!nombre || !comentario) return;

      var reviews = getStoredReviews(n.slug);
      reviews.push({ autor: nombre, valoracion: estrellas, fecha: new Date().toISOString().slice(0, 10), comentario: comentario });
      saveStoredReviews(n.slug, reviews);
      n.numResenas += 1;

      renderReviews(n);
      renderRatingSummary(n);
      this.reset();
      UNE.toast("¡Gracias por tu reseña! Se publicó correctamente.");
    });
  }

  function renderSidebar(n) {
    document.getElementById("contactPerson").innerHTML =
      '<img src="' + n.contacto.foto + '" alt="' + escapeHtml(n.contacto.nombre) + '"><div><strong>' + escapeHtml(n.contacto.nombre) + '</strong><span>' + escapeHtml(n.contacto.cargo) + '</span></div>';

    var waMsg = "Hola, vi su perfil de " + n.nombre + " en UNE Sports y quisiera más información.";
    document.getElementById("btnWhatsapp").href = UNE.formatWhatsappLink(n.whatsapp, waMsg);
    document.getElementById("btnLlamar").href = "tel:" + n.telefono.replace(/\s+/g, "");

    document.getElementById("btnCopiarTelefono").addEventListener("click", function () {
      copyText(n.telefono);
      UNE.toast("Teléfono copiado: " + n.telefono);
    });

    document.getElementById("sidebarDireccion").textContent = n.direccion;
    document.getElementById("sidebarTelefono").textContent = n.telefono;
    document.getElementById("sidebarEmail").textContent = n.email;

    var pageUrl = window.location.href;
    document.getElementById("shareWhatsapp").href = UNE.formatWhatsappLink("", "Mira este negocio en UNE Sports: " + n.nombre + " - " + pageUrl);
    document.getElementById("shareEmail").href = "mailto:?subject=" + encodeURIComponent(n.nombre + " en UNE Sports") + "&body=" + encodeURIComponent("Te comparto este perfil: " + pageUrl);
    document.getElementById("shareCopy").addEventListener("click", function () {
      copyText(pageUrl);
      UNE.toast("Enlace copiado al portapapeles");
    });
  }

  function renderSimilares(n) {
    var similares = allNegocios.filter(function (o) { return o.tipo === n.tipo && o.slug !== n.slug; }).slice(0, 3);
    if (!similares.length) similares = allNegocios.filter(function (o) { return o.slug !== n.slug; }).slice(0, 3);
    document.getElementById("similaresGrid").innerHTML = similares.map(function (s) {
      return '<article class="card"><div class="card-media"><a href="negocio.html?slug=' + s.slug + '"><img src="' + s.imagenPrincipal + '" alt="' + escapeHtml(s.nombre) + '" loading="lazy" width="900" height="650"></a></div>' +
        '<div class="card-body"><span class="card-type">' + escapeHtml(s.tipoLabel) + '</span><h3 class="card-title"><a href="negocio.html?slug=' + s.slug + '">' + escapeHtml(s.nombre) + '</a></h3>' +
        '<div class="card-location"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-6.3 7-11.5A7 7 0 105 9.5C5 14.7 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.4"/></svg>' + escapeHtml(s.distrito) + ', ' + escapeHtml(s.region) + '</div>' +
        '<div class="rating"><span class="stars">' + UNE.renderStars(s.valoracion) + '</span><strong>' + s.valoracion.toFixed(1) + '</strong></div>' +
        '<div class="card-footer"><span class="card-price">' + s.precio + '</span><a href="negocio.html?slug=' + s.slug + '" class="btn btn-ghost btn-sm">Ver perfil</a></div></div></article>';
    }).join("");
  }

  function injectJsonLd(n) {
    var script = document.createElement("script");
    script.type = "application/ld+json";
    script.textContent = JSON.stringify({
      "@context": "https://schema.org",
      "@type": "SportsActivityLocation",
      "name": n.nombre,
      "description": n.descripcion,
      "image": n.imagenPrincipal,
      "telephone": n.telefono,
      "priceRange": n.precio,
      "address": { "@type": "PostalAddress", "streetAddress": n.direccion, "addressLocality": n.distrito, "addressRegion": n.region, "addressCountry": "PE" },
      "geo": { "@type": "GeoCoordinates", "latitude": n.lat, "longitude": n.lng },
      "aggregateRating": { "@type": "AggregateRating", "ratingValue": n.valoracion, "reviewCount": n.numResenas }
    });
    document.head.appendChild(script);
  }

  function copyText(text) {
    if (navigator.clipboard) navigator.clipboard.writeText(text);
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
