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

      form.style.display = "none";
      document.getElementById("formSuccess").classList.add("is-visible");
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
