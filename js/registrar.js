/* UNE Sports — validación del formulario de registro de negocio */
(function () {
  "use strict";

  var MAX_DEPORTES = 5;
  var CATEGORIAS_CON_DEPORTE = ["Academia Deportiva", "Escuela Deportiva"];

  var DEPORTES = {
    "Equipo": ["Fútbol", "Baloncesto", "Balonmano", "Rugby 7", "Voleibol", "Hockey sobre césped", "Flag football", "Lacrosse", "Fútbol americano", "Rugby", "Fútbol sala", "Fútbol playa", "Netball", "Korfbal", "Polo"],
    "Raqueta": ["Tenis", "Tenis de mesa", "Bádminton", "Squash", "Pádel", "Pickleball", "Racquetball", "Frontón"],
    "Bate y pelota": ["Béisbol", "Sóftbol", "Críquet"],
    "Combate": ["Judo", "Taekwondo", "Boxeo", "Lucha (libre y grecorromana)", "Esgrima", "Artes Marciales Mixtas (MMA)", "Jiu-jitsu brasileño (BJJ)", "Kickboxing", "Muay thai", "Karate", "Sumo", "Kung fu", "Capoeira"],
    "Motor": ["Motocross", "Superbike"],
    "Atletismo y fuerza": ["Atletismo", "Ciclismo (ruta, pista, MTB y BMX)", "Halterofilia (levantamiento de pesas)", "Powerlifting", "CrossFit", "Strongman", "Fisicoculturismo"],
    "Acuáticos": ["Natación (piscina y aguas abiertas)", "Saltos (clavados)", "Natación artística", "Waterpolo", "Surf", "Vela", "Remo", "Piragüismo (canotaje)"],
    "Precisión y gimnasia": ["Tiro con arco", "Tiro deportivo", "Golf", "Gimnasia (artística, rítmica y trampolín)"],
    "Mesa y puntería": ["Billar (pool, snooker, carambola)", "Dardos", "Bolos (bowling)", "Bochas"],
    "Mente": ["Ajedrez", "Damas", "Go", "Bridge", "Deportes electrónicos (eSports)"],
    "Aventura y montaña": ["Escalada deportiva", "Paracaidismo", "Parapente", "Vuelo sin motor", "Salto BASE", "Montañismo / alpinismo de expedición"]
  };

  document.addEventListener("DOMContentLoaded", function () {
    var form = document.getElementById("registroForm");
    if (!form) return;

    initUploadPreview();
    initUbicacionSelects();
    initDeporteField();

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var valid = true;
      form.querySelectorAll("input[required], select[required], textarea[required]").forEach(function (field) {
        var wrap = field.closest(".form-field") || field.closest("label.terms-row");
        var fieldValid = field.type === "checkbox" ? field.checked : field.value.trim() !== "";
        if (field.type === "email" && fieldValid) fieldValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value.trim());
        if (!fieldValid) {
          valid = false;
          if (wrap && wrap.classList.contains("form-field")) wrap.classList.add("has-error");
        } else if (wrap && wrap.classList.contains("form-field")) {
          wrap.classList.remove("has-error");
        }
      });

      var turnos = Array.from(document.querySelectorAll('#turnosCheckbox input:checked')).map(function (i) { return i.value; });
      var turnosWrap = document.getElementById("turnosCheckbox").closest(".form-field");
      if (!turnos.length) {
        valid = false;
        turnosWrap.classList.add("has-error");
      } else {
        turnosWrap.classList.remove("has-error");
      }

      var tipoValue = document.getElementById("tipoNegocio").value;
      var necesitaDeporte = CATEGORIAS_CON_DEPORTE.indexOf(tipoValue) !== -1;
      var deportes = Array.from(document.querySelectorAll('#deporteGrupos input:checked')).map(function (i) { return i.value; });
      var deporteWrap = document.getElementById("deporteWrap");
      if (necesitaDeporte && !deportes.length) {
        valid = false;
        deporteWrap.classList.add("has-error");
      } else {
        deporteWrap.classList.remove("has-error");
      }

      if (!valid) {
        UNE.toast("Revisa los campos marcados en rojo.");
        var firstError = form.querySelector(".has-error");
        if (firstError) firstError.scrollIntoView({ behavior: "smooth", block: "center" });
        return;
      }

      var servicios = Array.from(document.querySelectorAll('#serviciosCheckbox input:checked')).map(function (i) { return i.value; });
      var payload = {
        nombre: document.getElementById("nombreNegocio").value.trim(),
        tipo: tipoValue,
        precioSoles: parseFloat(document.getElementById("precioSoles").value),
        turnos: turnos,
        deportes: necesitaDeporte ? deportes : [],
        region: document.getElementById("region").value.trim(),
        provincia: document.getElementById("provincia").value.trim(),
        distrito: document.getElementById("distrito").value.trim(),
        direccion: document.getElementById("direccion").value.trim(),
        telefono: document.getElementById("telefono").value.trim(),
        whatsapp: document.getElementById("whatsapp").value.trim(),
        email: document.getElementById("email").value.trim(),
        contactoNombre: document.getElementById("contactoNombre").value.trim(),
        descripcion: document.getElementById("descripcion").value.trim(),
        servicios: servicios
      };

      var submitBtn = document.getElementById("submitBtn");
      submitBtn.disabled = true;
      submitBtn.textContent = "Enviando...";

      fetch("api/registrar.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      })
        .then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
        .then(function (result) {
          if (!result.ok) throw new Error(result.body.error || "No se pudo enviar el registro.");
          form.style.display = "none";
          document.getElementById("formSuccess").classList.add("is-visible");
        })
        .catch(function (err) {
          UNE.toast(err.message || "No se pudo enviar el registro, intenta nuevamente.");
          submitBtn.disabled = false;
          submitBtn.textContent = "Enviar registro";
        });
    });

    form.querySelectorAll("input, select, textarea").forEach(function (field) {
      field.addEventListener("input", function () {
        var wrap = field.closest(".form-field");
        if (wrap) wrap.classList.remove("has-error");
      });
    });
  });

  function initDeporteField() {
    var tipoSelect = document.getElementById("tipoNegocio");
    var wrap = document.getElementById("deporteWrap");
    var gruposEl = document.getElementById("deporteGrupos");
    var contadorEl = document.getElementById("deporteContador");
    if (!tipoSelect) return;

    gruposEl.innerHTML = Object.keys(DEPORTES).map(function (grupo) {
      var opciones = DEPORTES[grupo].map(function (nombre) {
        return '<label class="checkbox-row"><input type="checkbox" name="deporte" value="' + nombre + '"><span>' + nombre + '</span></label>';
      }).join("");
      return '<details class="filter-group"><summary>' + grupo + '</summary><div class="checkbox-grid" style="margin-top:0.5rem;">' + opciones + '</div></details>';
    }).join("");

    var checkboxes = Array.from(gruposEl.querySelectorAll('input[name="deporte"]'));

    function updateContador() {
      var checked = checkboxes.filter(function (c) { return c.checked; });
      contadorEl.textContent = checked.length;
      checkboxes.forEach(function (c) { c.disabled = !c.checked && checked.length >= MAX_DEPORTES; });
    }
    checkboxes.forEach(function (c) { c.addEventListener("change", updateContador); });

    tipoSelect.addEventListener("change", function () {
      var visible = CATEGORIAS_CON_DEPORTE.indexOf(tipoSelect.value) !== -1;
      wrap.style.display = visible ? "" : "none";
      if (!visible) {
        checkboxes.forEach(function (c) { c.checked = false; });
        updateContador();
        wrap.classList.remove("has-error");
      }
    });
  }

  function initUbicacionSelects() {
    var regionSelect = document.getElementById("region");
    var provinciaSelect = document.getElementById("provincia");
    var distritoSelect = document.getElementById("distrito");
    if (!regionSelect) return;

    var ubicaciones = [];

    function fillSelect(select, values, placeholder) {
      select.innerHTML = '<option value="">' + placeholder + '</option>';
      values.forEach(function (v) {
        var opt = document.createElement("option");
        opt.value = v; opt.textContent = v;
        select.appendChild(opt);
      });
    }

    function uniqueSorted(arr) {
      return Array.from(new Set(arr)).sort(function (a, b) { return a.localeCompare(b); });
    }

    fetch("api/ubicaciones.php")
      .then(function (r) { return r.json(); })
      .then(function (data) {
        ubicaciones = data;
        var regiones = uniqueSorted(ubicaciones.map(function (u) { return u.region; }));
        fillSelect(regionSelect, regiones, "Selecciona una región");
        regionSelect.disabled = false;
      })
      .catch(function () {
        UNE.toast("No se pudo cargar el listado de regiones. Recarga la página.");
      });

    regionSelect.addEventListener("change", function () {
      var provincias = uniqueSorted(
        ubicaciones.filter(function (u) { return u.region === regionSelect.value; }).map(function (u) { return u.provincia; })
      );
      fillSelect(provinciaSelect, provincias, provincias.length ? "Selecciona una provincia" : "Primero elige una región");
      provinciaSelect.disabled = !provincias.length;
      fillSelect(distritoSelect, [], "Primero elige una provincia");
      distritoSelect.disabled = true;
    });

    provinciaSelect.addEventListener("change", function () {
      var distritos = uniqueSorted(
        ubicaciones
          .filter(function (u) { return u.region === regionSelect.value && u.provincia === provinciaSelect.value; })
          .map(function (u) { return u.distrito; })
      );
      fillSelect(distritoSelect, distritos, distritos.length ? "Selecciona un distrito" : "Primero elige una provincia");
      distritoSelect.disabled = !distritos.length;
    });
  }

  function initUploadPreview() {
    var input = document.getElementById("fotosInput");
    var box = document.getElementById("uploadBox");
    var preview = document.getElementById("uploadPreview");
    if (!input) return;

    input.addEventListener("change", function () {
      renderPreview(Array.from(input.files).slice(0, 5));
    });

    ["dragover", "dragenter"].forEach(function (evt) {
      box.addEventListener(evt, function (e) { e.preventDefault(); box.classList.add("is-dragover"); });
    });
    ["dragleave", "drop"].forEach(function (evt) {
      box.addEventListener(evt, function (e) { e.preventDefault(); box.classList.remove("is-dragover"); });
    });
    box.addEventListener("drop", function (e) {
      var files = Array.from(e.dataTransfer.files).filter(function (f) { return f.type.startsWith("image/"); }).slice(0, 5);
      renderPreview(files);
    });

    function renderPreview(files) {
      preview.innerHTML = "";
      files.forEach(function (file) {
        var reader = new FileReader();
        reader.onload = function (e) {
          var img = document.createElement("img");
          img.src = e.target.result;
          img.alt = file.name;
          preview.appendChild(img);
        };
        reader.readAsDataURL(file);
      });
    }
  }
})();
