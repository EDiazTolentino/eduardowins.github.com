/**
 * mapa.js — Mapa Leaflet de solo lectura para la ficha pública (§8).
 * Requiere que Leaflet ya esté cargado (ver pages/negocio.php).
 */
(function () {
  'use strict';
  var contenedor = document.getElementById('mapa-ficha');
  if (!contenedor || typeof L === 'undefined') return;

  var lat = parseFloat(contenedor.getAttribute('data-lat'));
  var lng = parseFloat(contenedor.getAttribute('data-lng'));
  var nombre = contenedor.getAttribute('data-nombre') || '';
  if (isNaN(lat) || isNaN(lng)) return;

  var mapa = L.map(contenedor, { scrollWheelZoom: false }).setView([lat, lng], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(mapa);
  L.marker([lat, lng]).addTo(mapa).bindPopup(nombre);
})();
