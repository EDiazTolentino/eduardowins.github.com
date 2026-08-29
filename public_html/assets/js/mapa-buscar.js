/**
 * mapa-buscar.js — Vista de mapa del buscador, con agrupamiento de
 * marcadores (Leaflet.markercluster). Carga solo los resultados del
 * filtro activo (ver api/buscar.php).
 */
(function () {
  'use strict';
  var contenedor = document.getElementById('mapa-buscar');
  if (!contenedor || typeof L === 'undefined') return;

  var mapa = L.map(contenedor).setView([-9.19, -75.02], 6); // centro aproximado del Perú
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(mapa);

  var grupo = (typeof L.markerClusterGroup === 'function') ? L.markerClusterGroup() : L.layerGroup();

  fetch(contenedor.getAttribute('data-fuente'))
    .then(function (r) { return r.json(); })
    .then(function (marcadores) {
      if (!marcadores.length) return;
      var limites = [];
      marcadores.forEach(function (m) {
        var marcador = L.marker([m.lat, m.lng]);
        var insignia = m.verificado ? '<span style="color:#FF8300;font-weight:700;">Verificada</span><br>' : '';
        marcador.bindPopup(insignia + '<strong>' + m.nombre + '</strong><br><a href="/negocio/' + m.slug + '">Ver ficha</a>');
        grupo.addLayer(marcador);
        limites.push([m.lat, m.lng]);
      });
      mapa.addLayer(grupo);
      if (limites.length) mapa.fitBounds(limites, { maxZoom: 14, padding: [30, 30] });
    })
    .catch(function () { /* el mapa queda vacío si falla la consulta */ });
})();
