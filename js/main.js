/* UNE Sports — comportamiento compartido (header, footer, toasts, lightbox) */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    initNavToggle();
    initFooterYear();
    initNewsletterForm();
    initLightboxTriggers();
    highlightActiveNavLink();
  });

  /* Menú móvil */
  function initNavToggle() {
    var toggle = document.querySelector("[data-nav-toggle]");
    var nav = document.querySelector("[data-main-nav]");
    if (!toggle || !nav) return;
    toggle.addEventListener("click", function () {
      var isOpen = nav.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
    });
    nav.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        nav.classList.remove("is-open");
        toggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  function highlightActiveNavLink() {
    var current = (window.location.pathname.split("/").pop() || "index.html");
    document.querySelectorAll("[data-main-nav] .nav-links a").forEach(function (link) {
      var href = link.getAttribute("href");
      if (href === current || (current === "" && href === "index.html")) {
        link.classList.add("is-active");
      }
    });
  }

  function initFooterYear() {
    var el = document.querySelector("[data-current-year]");
    if (el) el.textContent = new Date().getFullYear();
  }

  function initNewsletterForm() {
    var form = document.querySelector("[data-newsletter-form]");
    if (!form) return;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var input = form.querySelector("input[type='email']");
      if (!input || !input.value) return;
      UNE.toast("¡Gracias por suscribirte! Revisa tu correo pronto.");
      form.reset();
    });
  }

  /* Toast global */
  function ensureToastEl() {
    var toast = document.querySelector(".toast");
    if (!toast) {
      toast = document.createElement("div");
      toast.className = "toast";
      toast.setAttribute("role", "status");
      document.body.appendChild(toast);
    }
    return toast;
  }

  function showToast(message, duration) {
    var toast = ensureToastEl();
    toast.textContent = message;
    toast.classList.add("is-visible");
    window.clearTimeout(toast._timer);
    toast._timer = window.setTimeout(function () {
      toast.classList.remove("is-visible");
    }, duration || 3200);
  }

  /* Lightbox reutilizable: cualquier <a data-lightbox-group="x" href="imagen.jpg">
     Usa delegación de eventos en document para funcionar también con galerías
     que se inyectan de forma asíncrona (por ejemplo, después de un fetch). */
  function initLightboxTriggers() {
    document.addEventListener("click", function (e) {
      var trigger = e.target.closest("[data-lightbox-group]");
      if (!trigger) return;
      e.preventDefault();
      var group = trigger.getAttribute("data-lightbox-group");
      var members = Array.from(document.querySelectorAll('[data-lightbox-group="' + group + '"]'));
      var images = members.map(function (el) { return el.getAttribute("href") || el.getAttribute("data-src"); });
      var index = members.indexOf(trigger);
      UNE.openLightbox(images, index);
    });
  }

  function buildLightbox() {
    var existing = document.querySelector(".lightbox");
    if (existing) return existing;
    var el = document.createElement("div");
    el.className = "lightbox";
    el.innerHTML =
      '<button class="lightbox-close" aria-label="Cerrar">' + iconClose() + "</button>" +
      '<button class="lightbox-prev" aria-label="Anterior">' + iconChevron("left") + "</button>" +
      '<img alt="Imagen ampliada">' +
      '<button class="lightbox-next" aria-label="Siguiente">' + iconChevron("right") + "</button>" +
      '<div class="lightbox-counter"></div>';
    document.body.appendChild(el);

    var state = { images: [], index: 0 };
    var img = el.querySelector("img");
    var counter = el.querySelector(".lightbox-counter");

    function render() {
      img.src = state.images[state.index];
      counter.textContent = (state.index + 1) + " / " + state.images.length;
    }
    el.querySelector(".lightbox-close").addEventListener("click", close);
    el.addEventListener("click", function (e) { if (e.target === el) close(); });
    el.querySelector(".lightbox-prev").addEventListener("click", function () {
      state.index = (state.index - 1 + state.images.length) % state.images.length;
      render();
    });
    el.querySelector(".lightbox-next").addEventListener("click", function () {
      state.index = (state.index + 1) % state.images.length;
      render();
    });
    document.addEventListener("keydown", function (e) {
      if (!el.classList.contains("is-open")) return;
      if (e.key === "Escape") close();
      if (e.key === "ArrowLeft") el.querySelector(".lightbox-prev").click();
      if (e.key === "ArrowRight") el.querySelector(".lightbox-next").click();
    });

    function close() { el.classList.remove("is-open"); document.body.style.overflow = ""; }

    el._open = function (images, index) {
      state.images = images;
      state.index = index || 0;
      render();
      el.classList.add("is-open");
      document.body.style.overflow = "hidden";
    };
    return el;
  }

  function iconClose() {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>';
  }
  function iconChevron(dir) {
    var d = dir === "left" ? "M15 6l-6 6 6 6" : "M9 6l6 6-6 6";
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="' + d + '"/></svg>';
  }

  /* API pública compartida */
  window.UNE = window.UNE || {};
  window.UNE.toast = showToast;
  window.UNE.openLightbox = function (images, index) {
    var lb = buildLightbox();
    lb._open(images, index);
  };
  window.UNE.formatWhatsappLink = function (phone, message) {
    var clean = (phone || "").replace(/[^0-9]/g, "");
    return "https://wa.me/" + clean + (message ? "?text=" + encodeURIComponent(message) : "");
  };
  window.UNE.starIcon = function (filled) {
    return '<svg viewBox="0 0 20 20" fill="' + (filled ? "currentColor" : "none") + '" stroke="currentColor" stroke-width="1.4"><path d="M10 1.5l2.6 5.4 5.9.7-4.3 4.1 1.1 5.9L10 14.8l-5.3 2.8 1.1-5.9-4.3-4.1 5.9-.7z"/></svg>';
  };
  window.UNE.renderStars = function (rating) {
    var full = Math.round(rating);
    var html = "";
    for (var i = 1; i <= 5; i++) html += window.UNE.starIcon(i <= full);
    return html;
  };
})();
