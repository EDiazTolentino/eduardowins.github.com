/* UNE Sports — validación del formulario de registro de negocio */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    var form = document.getElementById("registroForm");
    if (!form) return;

    initUploadPreview();

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

      if (!valid) {
        UNE.toast("Revisa los campos marcados en rojo.");
        var firstError = form.querySelector(".has-error");
        if (firstError) firstError.scrollIntoView({ behavior: "smooth", block: "center" });
        return;
      }

      var servicios = Array.from(document.querySelectorAll('#serviciosCheckbox input:checked')).map(function (i) { return i.value; });
      var payload = {
        nombre: document.getElementById("nombreNegocio").value.trim(),
        tipo: document.getElementById("tipoNegocio").value,
        precio: document.getElementById("rangoPrecio").value,
        region: document.getElementById("region").value.trim(),
        provincia: document.getElementById("provincia").value.trim(),
        distrito: document.getElementById("distrito").value.trim(),
        direccion: document.getElementById("direccion").value.trim(),
        telefono: document.getElementById("telefono").value.trim(),
        whatsapp: document.getElementById("whatsapp").value.trim(),
        email: document.getElementById("email").value.trim(),
        horario: document.getElementById("horarioResumen").value.trim(),
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
