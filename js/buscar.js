/* UNE Sports — lógica de la página de búsqueda */
(function () {
  "use strict";

  var PAGE_SIZE = 6;
  var state = {
    all: [],
    filtered: [],
    page: 1,
    sort: "relevancia",
    view: "list",
    userLocation: null,
    filters: { q: "", region: "", tipos: [], precio: "", rating: 0, servicios: [] }
  };

  var els = {};
  var map = null;
  var markers = [];

  document.addEventListener("DOMContentLoaded", function () {
    cacheEls();
    readParamsFromUrl();
    fetch("api/negocios.php")
      .then(function (r) { return r.json(); })
      .then(function (data) {
        state.all = data;
        buildFilterOptions(data);
        applyFilterValuesToUI();
        bindEvents();
        runSearch();
      })
      .catch(function () {
        els.resultsList.innerHTML = '<div class="empty-state"><p>No se pudieron cargar los resultados. Verifica tu conexión e inténtalo nuevamente.</p></div>';
        els.resultsCount.textContent = "Error al cargar resultados";
      });
  });

  function cacheEls() {
    els.resultsList = document.getElementById("resultsList");
    els.resultsCount = document.getElementById("resultsCount");
    els.pagination = document.getElementById("pagination");
    els.filterRegion = document.getElementById("filterRegion");
    els.filterTipoOptions = document.getElementById("filterTipoOptions");
    els.filterServiciosOptions = document.getElementById("filterServiciosOptions");
    els.sortSelect = document.getElementById("sortSelect");
    els.clearFilters = document.getElementById("clearFilters");
    els.viewListBtn = document.getElementById("viewListBtn");
    els.viewMapBtn = document.getElementById("viewMapBtn");
    els.listView = document.getElementById("listView");
    els.mapView = document.getElementById("mapView");
  }

  function readParamsFromUrl() {
    var params = new URLSearchParams(window.location.search);
    state.filters.q = params.get("q") || "";
    state.filters.region = params.get("region") || "";
    if (params.get("tipo")) state.filters.tipos = [params.get("tipo")];
  }

  function buildFilterOptions(data) {
    var regiones = uniqueSorted(data.map(function (n) { return n.region; }));
    regiones.forEach(function (r) {
      var opt = document.createElement("option");
      opt.value = r; opt.textContent = r;
      els.filterRegion.appendChild(opt);
    });

    var tipos = {};
    data.forEach(function (n) { tipos[n.tipo] = n.tipoLabel; });
    Object.keys(tipos).sort(function (a, b) { return tipos[a].localeCompare(tipos[b]); }).forEach(function (key) {
      els.filterTipoOptions.appendChild(checkboxRow("tipo", key, tipos[key], state.filters.tipos.indexOf(key) !== -1));
    });

    var servicios = {};
    data.forEach(function (n) { (n.servicios || []).forEach(function (s) { servicios[s] = true; }); });
    uniqueSorted(Object.keys(servicios)).slice(0, 10).forEach(function (s) {
      els.filterServiciosOptions.appendChild(checkboxRow("servicio", s, s, false));
    });
  }

  function checkboxRow(name, value, label, checked) {
    var wrap = document.createElement("label");
    wrap.className = "checkbox-row";
    wrap.innerHTML = '<input type="checkbox" data-filter="' + name + '" value="' + escapeHtml(value) + '" ' + (checked ? "checked" : "") + '><span>' + escapeHtml(label) + "</span>";
    return wrap;
  }

  function applyFilterValuesToUI() {
    els.filterRegion.value = state.filters.region;
  }

  function bindEvents() {
    els.filterRegion.addEventListener("change", function () { state.filters.region = els.filterRegion.value; runSearch(); });
    document.querySelectorAll('[data-filter="tipo"]').forEach(function (cb) {
      cb.addEventListener("change", function () {
        state.filters.tipos = Array.from(document.querySelectorAll('[data-filter="tipo"]:checked')).map(function (i) { return i.value; });
        runSearch();
      });
    });
    document.querySelectorAll('[data-filter="servicio"]').forEach(function (cb) {
      cb.addEventListener("change", function () {
        state.filters.servicios = Array.from(document.querySelectorAll('[data-filter="servicio"]:checked')).map(function (i) { return i.value; });
        runSearch();
      });
    });
    document.querySelectorAll('input[name="precio"]').forEach(function (radio) {
      radio.addEventListener("change", function () { state.filters.precio = radio.value; runSearch(); });
    });
    document.querySelectorAll('input[name="rating"]').forEach(function (radio) {
      radio.addEventListener("change", function () { state.filters.rating = parseFloat(radio.value); runSearch(); });
    });
    els.sortSelect.addEventListener("change", function () {
      state.sort = els.sortSelect.value;
      if (state.sort === "distancia") requestUserLocation();
      runSearch();
    });
    els.clearFilters.addEventListener("click", function () {
      state.filters = { q: "", region: "", tipos: [], precio: "", rating: 0, servicios: [] };
      els.filterRegion.value = "";
      document.querySelectorAll('[data-filter="tipo"], [data-filter="servicio"]').forEach(function (cb) { cb.checked = false; });
      document.querySelector('input[name="precio"][value=""]').checked = true;
      document.querySelector('input[name="rating"][value="0"]').checked = true;
      runSearch();
    });
    els.viewListBtn.addEventListener("click", function () { setView("list"); });
    els.viewMapBtn.addEventListener("click", function () { setView("map"); });
  }

  function setView(view) {
    state.view = view;
    els.viewListBtn.classList.toggle("is-active", view === "list");
    els.viewMapBtn.classList.toggle("is-active", view === "map");
    els.listView.style.display = view === "list" ? "" : "none";
    els.mapView.style.display = view === "map" ? "" : "none";
    if (view === "map") renderMap();
  }

  function requestUserLocation() {
    if (!navigator.geolocation || state.userLocation) return;
    navigator.geolocation.getCurrentPosition(function (pos) {
      state.userLocation = { lat: pos.coords.latitude, lng: pos.coords.longitude };
      runSearch();
    }, function () {
      UNE.toast("No pudimos acceder a tu ubicación. Mostrando orden por relevancia.");
    });
  }

  function runSearch() {
    var f = state.filters;
    state.filtered = state.all.filter(function (n) {
      if (f.q && !(n.nombre + " " + n.tipoLabel + " " + n.descripcion).toLowerCase().includes(f.q.toLowerCase())) return false;
      if (f.region && n.region !== f.region) return false;
      if (f.tipos.length && f.tipos.indexOf(n.tipo) === -1) return false;
      if (f.precio && n.precio !== f.precio) return false;
      if (f.rating && n.valoracion < f.rating) return false;
      if (f.servicios.length && !f.servicios.every(function (s) { return n.servicios.indexOf(s) !== -1; })) return false;
      return true;
    });

    sortResults();
    state.page = 1;
    render();
  }

  function sortResults() {
    var priceRank = { "$": 1, "$$": 2, "$$$": 3 };
    switch (state.sort) {
      case "valoracion":
        state.filtered.sort(function (a, b) { return b.valoracion - a.valoracion; });
        break;
      case "precio-asc":
        state.filtered.sort(function (a, b) { return priceRank[a.precio] - priceRank[b.precio]; });
        break;
      case "precio-desc":
        state.filtered.sort(function (a, b) { return priceRank[b.precio] - priceRank[a.precio]; });
        break;
      case "distancia":
        if (state.userLocation) {
          state.filtered.sort(function (a, b) { return distanceKm(state.userLocation, a) - distanceKm(state.userLocation, b); });
        }
        break;
      default:
        state.filtered.sort(function (a, b) { return (b.destacado === true) - (a.destacado === true) || b.valoracion - a.valoracion; });
    }
  }

  function distanceKm(origin, n) {
    var R = 6371;
    var dLat = toRad(n.lat - origin.lat), dLng = toRad(n.lng - origin.lng);
    var a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(origin.lat)) * Math.cos(toRad(n.lat)) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }
  function toRad(deg) { return deg * Math.PI / 180; }

  function render() {
    var total = state.filtered.length;
    els.resultsCount.textContent = total + (total === 1 ? " resultado encontrado" : " resultados encontrados");

    if (!total) {
      els.resultsList.innerHTML = '<div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg><h3>No encontramos resultados</h3><p>Prueba ajustando o limpiando los filtros.</p></div>';
      els.pagination.innerHTML = "";
      if (state.view === "map") renderMap();
      return;
    }

    var totalPages = Math.ceil(total / PAGE_SIZE);
    if (state.page > totalPages) state.page = totalPages;
    var start = (state.page - 1) * PAGE_SIZE;
    var pageItems = state.filtered.slice(start, start + PAGE_SIZE);

    els.resultsList.innerHTML = pageItems.map(resultCardHtml).join("");
    renderPagination(totalPages);
    if (state.view === "map") renderMap();
  }

  function resultCardHtml(n) {
    return '' +
      '<article class="result-row">' +
        '<a class="card-media" href="negocio.html?slug=' + n.slug + '">' +
          (n.destacado ? '<span class="card-badge">Destacado</span>' : "") +
          '<img src="' + n.imagenPrincipal + '" alt="' + escapeHtml(n.nombre) + '" loading="lazy" width="220" height="170">' +
        '</a>' +
        '<div class="card-body">' +
          '<span class="card-type">' + escapeHtml(n.tipoLabel) + '</span>' +
          '<h3 class="card-title"><a href="negocio.html?slug=' + n.slug + '">' + escapeHtml(n.nombre) + '</a></h3>' +
          '<div class="card-location"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-6.3 7-11.5A7 7 0 105 9.5C5 14.7 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.4"/></svg>' + escapeHtml(n.distrito) + ', ' + escapeHtml(n.region) + '</div>' +
          '<div class="card-services">' + (n.servicios || []).slice(0, 3).map(function (s) { return '<span class="tag">' + escapeHtml(s) + '</span>'; }).join("") + '</div>' +
          '<div class="rating"><span class="stars">' + UNE.renderStars(n.valoracion) + '</span><strong>' + n.valoracion.toFixed(1) + '</strong><span class="count">(' + n.numResenas + ' reseñas)</span></div>' +
          '<div class="card-footer"><span class="card-price">' + n.precio + '</span><a href="negocio.html?slug=' + n.slug + '" class="btn btn-primary btn-sm">Ver perfil</a></div>' +
        '</div>' +
      '</article>';
  }

  function renderPagination(totalPages) {
    if (totalPages <= 1) { els.pagination.innerHTML = ""; return; }
    var html = '<button ' + (state.page === 1 ? "disabled" : "") + ' data-page="prev">‹</button>';
    for (var i = 1; i <= totalPages; i++) {
      html += '<button class="' + (i === state.page ? "is-active" : "") + '" data-page="' + i + '">' + i + '</button>';
    }
    html += '<button ' + (state.page === totalPages ? "disabled" : "") + ' data-page="next">›</button>';
    els.pagination.innerHTML = html;
    els.pagination.querySelectorAll("button").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var p = btn.getAttribute("data-page");
        if (p === "prev") state.page--;
        else if (p === "next") state.page++;
        else state.page = parseInt(p, 10);
        render();
        els.resultsList.scrollIntoView({ behavior: "smooth", block: "start" });
      });
    });
  }

  function renderMap() {
    if (typeof L === "undefined") return;
    if (!map) {
      map = L.map("resultsMap").setView([-9.19, -75.02], 5.2);
      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; colaboradores de OpenStreetMap"
      }).addTo(map);
    }
    markers.forEach(function (m) { map.removeLayer(m); });
    markers = [];
    var bounds = [];
    state.filtered.forEach(function (n) {
      var marker = L.marker([n.lat, n.lng]).addTo(map);
      marker.bindPopup(
        '<strong>' + escapeHtml(n.nombre) + '</strong><br>' + escapeHtml(n.tipoLabel) + '<br>' +
        '⭐ ' + n.valoracion.toFixed(1) + ' (' + n.numResenas + ')<br>' +
        '<a href="negocio.html?slug=' + n.slug + '">Ver perfil →</a>'
      );
      markers.push(marker);
      bounds.push([n.lat, n.lng]);
    });
    if (bounds.length) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
    setTimeout(function () { map.invalidateSize(); }, 200);
  }

  function uniqueSorted(arr) {
    return Array.from(new Set(arr)).sort(function (a, b) { return a.localeCompare(b); });
  }

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }
})();
