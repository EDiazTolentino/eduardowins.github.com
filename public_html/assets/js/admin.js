/**
 * admin.js — Comportamiento del backoffice: bandeja de leads (marcar
 * todos) y ficha completa (autoguardado, mapa editable, geocodificación,
 * horarios dinámicos, límite de 5 disciplinas, plantilla de WhatsApp).
 */
(function () {
  'use strict';

  // -------------------------------------------------------------
  // Bandeja de leads: marcar/desmarcar todos
  // -------------------------------------------------------------
  var marcarTodos = document.getElementById('marcar-todos');
  if (marcarTodos) {
    marcarTodos.addEventListener('change', function () {
      document.querySelectorAll('input[name="negocio_ids[]"]').forEach(function (casilla) {
        casilla.checked = marcarTodos.checked;
      });
    });
  }

  // -------------------------------------------------------------
  // Ficha completa: mostrar solo las categorías del tipo de registro
  // elegido (formativo/servicio); en el servidor no se restringe, así
  // que esto es solo para ordenar el formulario, no una validación.
  // -------------------------------------------------------------
  var radiosTipoRegistro = document.querySelectorAll('input[name="tipo_registro"]');
  var casillasCategoria = document.querySelectorAll('.opcion-casilla[data-tipo]');
  function actualizarCategoriasVisibles() {
    var marcado = document.querySelector('input[name="tipo_registro"]:checked');
    if (!marcado || !casillasCategoria.length) return;
    casillasCategoria.forEach(function (label) {
      label.hidden = label.getAttribute('data-tipo') !== marcado.value;
    });
  }
  if (radiosTipoRegistro.length) {
    radiosTipoRegistro.forEach(function (radio) { radio.addEventListener('change', actualizarCategoriasVisibles); });
    actualizarCategoriasVisibles();
  }

  // -------------------------------------------------------------
  // Ficha completa: límite de 5 disciplinas (validación en cliente;
  // el servidor también lo valida en admin/negocio-editar.php)
  // -------------------------------------------------------------
  var casillasDeporte = document.querySelectorAll('.casilla-deporte');
  if (casillasDeporte.length) {
    casillasDeporte.forEach(function (casilla) {
      casilla.addEventListener('change', function () {
        var marcadas = document.querySelectorAll('.casilla-deporte:checked');
        if (marcadas.length > 5) {
          casilla.checked = false;
          window.alert('Puedes seleccionar como máximo 5 disciplinas.');
        }
      });
    });
  }

  // -------------------------------------------------------------
  // Ficha completa: filas de horario dinámicas
  // -------------------------------------------------------------
  var listaHorarios = document.getElementById('lista-horarios');
  var botonAgregarHorario = document.getElementById('agregar-horario');
  var DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
  var TURNOS = ['mañana', 'tarde', 'noche'];

  function crearFilaHorario() {
    var fila = document.createElement('div');
    fila.className = 'fila-horario';

    var selectDia = document.createElement('select');
    selectDia.name = 'horario_dia[]';
    DIAS.forEach(function (nombre, i) {
      var opcion = document.createElement('option');
      opcion.value = String(i + 1);
      opcion.textContent = nombre;
      selectDia.appendChild(opcion);
    });

    var selectTurno = document.createElement('select');
    selectTurno.name = 'horario_turno[]';
    TURNOS.forEach(function (turno) {
      var opcion = document.createElement('option');
      opcion.value = turno;
      opcion.textContent = turno.charAt(0).toUpperCase() + turno.slice(1);
      selectTurno.appendChild(opcion);
    });

    var inicio = document.createElement('input');
    inicio.type = 'time'; inicio.name = 'horario_inicio[]';
    var fin = document.createElement('input');
    fin.type = 'time'; fin.name = 'horario_fin[]';

    var botonQuitar = document.createElement('button');
    botonQuitar.type = 'button'; botonQuitar.className = 'boton-quitar-fila'; botonQuitar.textContent = '✕';
    botonQuitar.addEventListener('click', function () { fila.remove(); });

    [selectDia, selectTurno, inicio, fin, botonQuitar].forEach(function (el) { fila.appendChild(el); });
    return fila;
  }

  if (botonAgregarHorario && listaHorarios) {
    botonAgregarHorario.addEventListener('click', function () {
      listaHorarios.appendChild(crearFilaHorario());
    });
    document.querySelectorAll('.boton-quitar-fila').forEach(function (boton) {
      boton.addEventListener('click', function () { boton.closest('.fila-horario').remove(); });
    });
  }

  // -------------------------------------------------------------
  // Ficha completa: mapa editable con marcador arrastrable
  // -------------------------------------------------------------
  var contenedorMapa = document.getElementById('mapa-editor');
  var campoLatitud = document.getElementById('latitud');
  var campoLongitud = document.getElementById('longitud');
  var marcador = null;
  var mapaEditor = null;

  if (contenedorMapa && typeof L !== 'undefined') {
    var latInicial = parseFloat(contenedorMapa.getAttribute('data-lat')) || -12.05;
    var lngInicial = parseFloat(contenedorMapa.getAttribute('data-lng')) || -77.03;

    mapaEditor = L.map(contenedorMapa).setView([latInicial, lngInicial], campoLatitud.value ? 15 : 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(mapaEditor);

    marcador = L.marker([latInicial, lngInicial], { draggable: true }).addTo(mapaEditor);
    marcador.on('dragend', function () {
      var pos = marcador.getLatLng();
      campoLatitud.value = pos.lat.toFixed(7);
      campoLongitud.value = pos.lng.toFixed(7);
    });
    mapaEditor.on('click', function (evento) {
      marcador.setLatLng(evento.latlng);
      campoLatitud.value = evento.latlng.lat.toFixed(7);
      campoLongitud.value = evento.latlng.lng.toFixed(7);
    });
  }

  // -------------------------------------------------------------
  // Ficha completa: buscar coordenadas por dirección (Nominatim,
  // una sola consulta manual por clic, nunca automatizada — §2)
  // -------------------------------------------------------------
  var botonGeocodificar = document.getElementById('boton-geocodificar');
  if (botonGeocodificar) {
    botonGeocodificar.addEventListener('click', function () {
      var direccion = (document.getElementById('direccion') || {}).value || '';
      var distrito = document.getElementById('distrito_id');
      var textoDistrito = distrito ? distrito.options[distrito.selectedIndex].text : '';
      var consulta = [direccion, textoDistrito, 'Perú'].filter(Boolean).join(', ');
      if (!consulta.trim()) {
        window.alert('Escribe al menos la dirección o selecciona un distrito.');
        return;
      }
      botonGeocodificar.disabled = true;
      botonGeocodificar.textContent = 'Buscando…';
      fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(consulta))
        .then(function (r) { return r.json(); })
        .then(function (resultados) {
          if (resultados && resultados.length) {
            var lat = parseFloat(resultados[0].lat);
            var lng = parseFloat(resultados[0].lon);
            campoLatitud.value = lat.toFixed(7);
            campoLongitud.value = lng.toFixed(7);
            if (mapaEditor && marcador) {
              marcador.setLatLng([lat, lng]);
              mapaEditor.setView([lat, lng], 16);
            }
          } else {
            window.alert('No se encontraron coordenadas para esa dirección. Puedes ubicarla manualmente en el mapa.');
          }
        })
        .catch(function () { window.alert('No se pudo consultar el servicio de mapas. Intenta de nuevo.'); })
        .finally(function () {
          botonGeocodificar.disabled = false;
          botonGeocodificar.textContent = 'Buscar coordenadas por dirección';
        });
    });
  }

  // -------------------------------------------------------------
  // Ubicación: selects encadenados departamento → provincia → distrito
  // -------------------------------------------------------------
  var selectDepartamento = document.getElementById('departamento_id');
  var selectProvincia = document.getElementById('provincia_id');
  var selectDistrito = document.getElementById('distrito_id');

  function llenarSelect(select, items, placeholder) {
    select.innerHTML = '';
    var vacio = document.createElement('option');
    vacio.value = ''; vacio.textContent = placeholder;
    select.appendChild(vacio);
    items.forEach(function (item) {
      var opcion = document.createElement('option');
      opcion.value = item.id; opcion.textContent = item.nombre;
      select.appendChild(opcion);
    });
  }

  if (selectDepartamento && selectProvincia && selectDistrito && selectDepartamento.dataset.ubigeo) {
    selectDepartamento.addEventListener('change', function () {
      if (!selectDepartamento.value) return;
      fetch('/api/ubigeo.php?departamento_id=' + selectDepartamento.value)
        .then(function (r) { return r.json(); })
        .then(function (provincias) {
          llenarSelect(selectProvincia, provincias, 'Selecciona');
          llenarSelect(selectDistrito, [], 'Selecciona');
        });
    });
    selectProvincia.addEventListener('change', function () {
      if (!selectProvincia.value) return;
      fetch('/api/ubigeo.php?provincia_id=' + selectProvincia.value)
        .then(function (r) { return r.json(); })
        .then(function (distritos) { llenarSelect(selectDistrito, distritos, 'Selecciona'); });
    });
  }

  // -------------------------------------------------------------
  // Enlace de WhatsApp con plantilla editable (panel lateral)
  // -------------------------------------------------------------
  var enlaceWhatsApp = document.getElementById('enlace-whatsapp-plantilla');
  var plantillaWhatsApp = document.getElementById('plantilla-whatsapp');
  if (enlaceWhatsApp && plantillaWhatsApp) {
    enlaceWhatsApp.addEventListener('click', function (evento) {
      evento.preventDefault();
      var telefono = enlaceWhatsApp.getAttribute('data-telefono');
      var mensaje = plantillaWhatsApp.value;
      window.open('https://wa.me/51' + telefono + '?text=' + encodeURIComponent(mensaje), '_blank', 'noopener');
    });
  }

  // -------------------------------------------------------------
  // Autoguardado del formulario de contenido (§7B)
  // -------------------------------------------------------------
  var formularioNegocio = document.getElementById('formulario-negocio');
  var CAMPOS_AUTOGUARDADO = [
    'nombre_comercial', 'razon_social', 'ruc', 'descripcion', 'direccion', 'referencia',
    'telefono_publico', 'telefono_publico_2', 'email_publico', 'web', 'facebook', 'instagram',
    'tiktok', 'youtube', 'contacto_nombre', 'contacto_cargo', 'contacto_telefono', 'contacto_email',
    'afiliacion_federacion', 'anio_fundacion', 'departamento_id', 'provincia_id', 'distrito_id',
    'latitud', 'longitud', 'rango_precio', 'precio_mensual_ref', 'capacidad_alumnos',
    'alumnos_actuales', 'num_entrenadores', 'tiene_matricula', 'ofrece_beca', 'clase_prueba_gratis',
    'local_propio', 'seguro_accidentes', 'protocolo_salvaguarda', 'personal_certificado',
    'requiere_examen_medico', 'tipo_registro', 'modalidad', 'atiende_genero',
  ];

  if (formularioNegocio && window.UNE_CSRF_TOKEN) {
    var indicador = document.getElementById('autoguardado-estado');
    var barraRelleno = document.querySelector('.barra-completitud__relleno');
    var textoPorcentaje = document.getElementById('completitud-porcentaje');

    function recogerCampos() {
      var datos = {};
      CAMPOS_AUTOGUARDADO.forEach(function (nombre) {
        var el = formularioNegocio.elements.namedItem(nombre);
        if (!el) return;
        if (el.type === 'checkbox') {
          datos[nombre] = el.checked;
        } else if (el.type === 'radio') {
          var marcado = formularioNegocio.querySelector('input[name="' + nombre + '"]:checked');
          if (marcado) datos[nombre] = marcado.value;
        } else {
          datos[nombre] = el.value;
        }
      });
      return datos;
    }

    function autoguardar() {
      var negocioId = formularioNegocio.getAttribute('data-negocio-id');
      fetch('/api/guardar-paso.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ negocio_id: negocioId, csrf_token: window.UNE_CSRF_TOKEN, campos: recogerCampos() }),
      })
        .then(function (r) { return r.json(); })
        .then(function (respuesta) {
          if (!respuesta.ok) return;
          if (indicador) indicador.textContent = respuesta.guardadoEn ? 'Guardado a las ' + respuesta.guardadoEn : 'Guardado';
          if (typeof respuesta.completitud === 'number') {
            if (barraRelleno) barraRelleno.style.width = respuesta.completitud + '%';
            if (textoPorcentaje) textoPorcentaje.textContent = respuesta.completitud;
          }
        })
        .catch(function () { if (indicador) indicador.textContent = 'No se pudo autoguardar (sin conexión).'; });
    }

    setInterval(autoguardar, 30000);
    formularioNegocio.addEventListener('change', function () {
      clearTimeout(formularioNegocio._temporizadorAutoguardado);
      formularioNegocio._temporizadorAutoguardado = setTimeout(autoguardar, 1500);
    });

    document.addEventListener('keydown', function (evento) {
      var teclaS = evento.key === 's' || evento.key === 'S';
      var teclaEnter = evento.key === 'Enter';
      if ((evento.ctrlKey || evento.metaKey) && teclaS) {
        evento.preventDefault();
        formularioNegocio.requestSubmit();
      }
      if ((evento.ctrlKey || evento.metaKey) && teclaEnter) {
        evento.preventDefault();
        var campoSiguiente = document.getElementById('campo-siguiente');
        if (campoSiguiente) campoSiguiente.value = 'siguiente_lead';
        formularioNegocio.requestSubmit();
      }
    });
  }
})();
