/**
 * app.js — Comportamiento general del sitio (público y admin):
 * menú móvil, botón compartir y registro de clics de contacto.
 */
(function () {
  'use strict';

  // Menú móvil
  var toggle = document.getElementById('menu-toggle');
  var menu = document.getElementById('menu-principal');
  if (toggle && menu) {
    toggle.addEventListener('click', function () {
      var abierto = menu.classList.toggle('cabecera__nav--abierto');
      toggle.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });
  }

  // Contador animado de impacto (inicio: academias/regiones/distritos)
  document.querySelectorAll('[data-contador]').forEach(function (el) {
    var meta = parseInt(el.getAttribute('data-contador'), 10) || 0;
    var duracion = 1200;
    var inicio = null;
    function paso(marca) {
      if (!inicio) inicio = marca;
      var progreso = Math.min((marca - inicio) / duracion, 1);
      el.textContent = Math.floor(progreso * meta);
      if (progreso < 1) {
        requestAnimationFrame(paso);
      } else {
        el.textContent = meta;
      }
    }
    requestAnimationFrame(paso);
  });

  // Botón compartir (registrar.php y negocio.php)
  function activarCompartir(id) {
    var boton = document.getElementById(id);
    if (!boton) return;
    boton.addEventListener('click', function () {
      var url = boton.getAttribute('data-url') || window.location.href;
      if (navigator.share) {
        navigator.share({ title: document.title, url: url }).catch(function () {});
      } else if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function () {
          boton.textContent = 'Enlace copiado';
          setTimeout(function () { boton.textContent = 'Compartir'; }, 2000);
        });
      }
      var negocioId = boton.getAttribute('data-negocio');
      if (negocioId) {
        registrarEvento('clic_compartir', negocioId);
      }
    });
  }
  activarCompartir('boton-compartir');
  activarCompartir('boton-compartir-ficha');

  // Registro de clics de contacto (tel: / WhatsApp) en la ficha pública
  function registrarEvento(tipo, negocioId) {
    try {
      var datos = JSON.stringify({ tipo: tipo, negocio_id: negocioId });
      if (navigator.sendBeacon) {
        navigator.sendBeacon('/api/evento.php', new Blob([datos], { type: 'application/json' }));
      } else {
        fetch('/api/evento.php', { method: 'POST', body: datos, headers: { 'Content-Type': 'application/json' }, keepalive: true });
      }
    } catch (e) { /* no bloquear la navegación por un error de analítica */ }
  }

  document.querySelectorAll('[data-evento][data-negocio]').forEach(function (el) {
    el.addEventListener('click', function () {
      registrarEvento(el.getAttribute('data-evento'), el.getAttribute('data-negocio'));
    });
  });
})();
