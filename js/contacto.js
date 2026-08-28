/* UNE Sports — validación del formulario de contacto y mapa de oficina */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    initMap();
    initForm();
  });

  function initMap() {
    var el = document.getElementById("contactoMap");
    if (!el || typeof L === "undefined") return;
    var map = L.map("contactoMap", { scrollWheelZoom: false }).setView([-12.0955, -77.0335], 15);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      attribution: "&copy; colaboradores de OpenStreetMap"
    }).addTo(map);
    L.marker([-12.0955, -77.0335]).addTo(map).bindPopup("UNE Sports — Oficina central").openPopup();
    setTimeout(function () { map.invalidateSize(); }, 300);
  }

  function initForm() {
    var form = document.getElementById("contactoForm");
    if (!form) return;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var valid = true;
      form.querySelectorAll("input[required], select[required], textarea[required]").forEach(function (field) {
        var wrap = field.closest(".form-field");
        var ok = field.value.trim() !== "";
        if (field.type === "email" && ok) ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value.trim());
        wrap.classList.toggle("has-error", !ok);
        if (!ok) valid = false;
      });
      if (!valid) { UNE.toast("Revisa los campos marcados en rojo."); return; }

      var payload = {
        nombre: document.getElementById("cNombre").value.trim(),
        email: document.getElementById("cEmail").value.trim(),
        asunto: document.getElementById("cAsunto").value,
        mensaje: document.getElementById("cMensaje").value.trim()
      };

      fetch("api/contacto.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      })
        .then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
        .then(function (result) {
          if (!result.ok) throw new Error(result.body.error || "No se pudo enviar el mensaje.");
          document.getElementById("contactoSuccess").classList.add("is-visible");
          form.querySelectorAll("input, select, textarea, button[type='submit']").forEach(function (f) { f.disabled = true; });
          UNE.toast("¡Gracias! Recibimos tu mensaje.");
        })
        .catch(function (err) {
          UNE.toast(err.message || "No se pudo enviar el mensaje, intenta nuevamente.");
        });
    });
  }
})();
