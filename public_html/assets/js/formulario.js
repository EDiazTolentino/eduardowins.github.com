/**
 * formulario.js — Autocompletado de distrito en un solo gesto (§7A),
 * usado en el formulario público de captura rápida /registrar.
 */
(function () {
  'use strict';

  var input = document.getElementById('distrito_texto');
  var lista = document.getElementById('distrito-sugerencias');
  var campoDepartamento = document.getElementById('departamento_id');
  var campoProvincia = document.getElementById('provincia_id');
  var campoDistrito = document.getElementById('distrito_id');
  if (!input || !lista) return;

  var temporizador = null;

  input.addEventListener('input', function () {
    // Si el usuario edita el texto a mano, invalida la selección previa.
    campoDepartamento.value = '';
    campoProvincia.value = '';
    campoDistrito.value = '';

    clearTimeout(temporizador);
    var q = input.value.trim();
    if (q.length < 2) {
      lista.hidden = true;
      lista.innerHTML = '';
      return;
    }
    temporizador = setTimeout(function () { buscar(q); }, 250);
  });

  function buscar(q) {
    fetch('/api/ubigeo.php?q=' + encodeURIComponent(q))
      .then(function (r) { return r.json(); })
      .then(function (resultados) {
        lista.innerHTML = '';
        if (!resultados.length) {
          lista.hidden = true;
          return;
        }
        resultados.forEach(function (r) {
          var boton = document.createElement('button');
          boton.type = 'button';
          boton.textContent = r.etiqueta;
          boton.addEventListener('click', function () {
            input.value = r.etiqueta;
            campoDepartamento.value = r.departamento_id;
            campoProvincia.value = r.provincia_id;
            campoDistrito.value = r.distrito_id;
            lista.hidden = true;
            lista.innerHTML = '';
          });
          lista.appendChild(boton);
        });
        lista.hidden = false;
      })
      .catch(function () { lista.hidden = true; });
  }

  document.addEventListener('click', function (evento) {
    if (evento.target !== input && !lista.contains(evento.target)) {
      lista.hidden = true;
    }
  });
})();
