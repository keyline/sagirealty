document.addEventListener("DOMContentLoaded", () => {
  const header = document.getElementById("siteHeader");
  const menuToggle = document.querySelector(".menu-toggle");
  const mobileNav = document.querySelector(".mobile-nav");
  const year = document.getElementById("year");
  const forms = document.querySelectorAll(".lead-form");
  const modal = document.getElementById("imageModal");
  const modalImage = document.getElementById("modalImage");
  const modalClose = document.querySelector(".modal-close");
  const brochureModal = document.getElementById("brochureModal");
  const brochureForm = brochureModal?.querySelector(".brochure-form");
  const brochureClose = brochureModal?.querySelector(".brochure-close");
  const brochurePath = "assets/pa-aroha-brochure.pdf";
  let brochureAction = null;
  let brochureTrigger = null;

  // Site-wide smooth scrolling. Native scrolling remains as the CDN fallback.
  const lenis = typeof window.Lenis === "function"
    ? new window.Lenis({
        autoRaf: true,
        autoToggle: true,
        smoothWheel: true,
        lerp: 0.09,
        wheelMultiplier: 0.9,
        anchors: {
          offset: -82
        },
        allowNestedScroll: true,
        stopInertiaOnNavigate: true
      })
    : null;

  if (year) year.textContent = new Date().getFullYear();

  window.addEventListener("scroll", () => {
    header.classList.toggle("scrolled", window.scrollY > 20);
  }, {passive:true});

  menuToggle?.addEventListener("click", () => {
    const open = mobileNav.classList.toggle("open");
    menuToggle.setAttribute("aria-expanded", open ? "true" : "false");
  });

  mobileNav?.querySelectorAll("a").forEach(link => {
    link.addEventListener("click", () => {
      mobileNav.classList.remove("open");
      menuToggle?.setAttribute("aria-expanded", "false");
    });
  });

  // WOW.js scroll reveals with restrained staggered timing.
  document.querySelectorAll(".highlight-card, .amenity, .gallery-item, .faq-list details")
    .forEach((element, index) => {
      element.setAttribute("data-wow-delay", `${(index % 4) * 0.08}s`);
    });

  if (typeof window.WOW === "function") {
    new window.WOW({
      boxClass: "reveal",
      animateClass: "animate__animated",
      offset: 70,
      mobile: true,
      live: false
    }).init();
  } else {
    document.querySelectorAll(".reveal").forEach(element => {
      element.style.visibility = "visible";
    });
  }

  // Gallery modal
  document.querySelectorAll(".gallery-item").forEach(item => {
    item.addEventListener("click", () => {
      modalImage.src = item.dataset.image;
      modal.classList.add("open");
      modal.setAttribute("aria-hidden", "false");
      document.body.classList.add("modal-open");
      lenis?.stop();
    });
  });

  const closeModal = () => {
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");
    lenis?.start();
    setTimeout(() => modalImage.src = "", 150);
  };

  modalClose?.addEventListener("click", closeModal);
  modal?.addEventListener("click", e => {
    if (e.target === modal) closeModal();
  });
  document.addEventListener("keydown", e => {
    if (e.key === "Escape" && modal.classList.contains("open")) closeModal();
  });

  const closeBrochureModal = () => {
    brochureModal.classList.remove("open");
    brochureModal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");
    lenis?.start();
    brochureAction = null;
    brochureTrigger?.focus();
  };

  document.querySelectorAll("[data-brochure-action]").forEach(button => {
    button.addEventListener("click", () => {
      brochureAction = button.dataset.brochureAction;
      brochureTrigger = button;
      brochureForm.reset();
      brochureForm.querySelectorAll(".field.invalid").forEach(field => field.classList.remove("invalid"));
      brochureForm.querySelectorAll(".field-error, .form-global-error").forEach(error => error.textContent = "");
      brochureForm.querySelector(".btn-label").textContent = brochureAction === "download" ? "Download Brochure" : "View Brochure";
      brochureModal.classList.add("open");
      brochureModal.setAttribute("aria-hidden", "false");
      document.body.classList.add("modal-open");
      lenis?.stop();
      brochureForm.querySelector('[name="name"]').focus();
    });
  });

  brochureClose?.addEventListener("click", closeBrochureModal);
  brochureModal?.addEventListener("click", e => {
    if (e.target === brochureModal) closeBrochureModal();
  });
  document.addEventListener("keydown", e => {
    if (e.key === "Escape" && brochureModal?.classList.contains("open")) closeBrochureModal();
    if (e.key !== "Tab" || !brochureModal?.classList.contains("open")) return;
    const focusable = [...brochureModal.querySelectorAll('button:not(:disabled), input:not([tabindex="-1"]), select')];
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  });

  // Client-side form validation + AJAX submission.
  forms.forEach(form => form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const globalError = form.querySelector(".form-global-error");
    globalError.textContent = "";

    if (window.location.protocol === "file:") {
      globalError.textContent = "This form requires a PHP server. Open the website through a PHP localhost URL, not directly as a file.";
      return;
    }

    const fields = {
      name: form.querySelector('[name="name"]'),
      phone: form.querySelector('[name="phone"]'),
      email: form.querySelector('[name="email"]')
    };
    let valid = true;

    const setError = (input, message) => {
      const field = input.closest(".field");
      const error = field.querySelector(".field-error");
      field.classList.toggle("invalid", Boolean(message));
      error.textContent = message || "";
    };

    const name = fields.name.value.trim();
    const phone = fields.phone.value.replace(/\D/g, "");
    const email = fields.email.value.trim();
    const consent = form.querySelector('input[name="consent"]');

    setError(fields.name, name.length < 2 ? "Please enter your name." : "");
    setError(fields.phone, !/^[6-9][0-9]{9}$/.test(phone) ? "Please enter a valid 10-digit Indian mobile number." : "");
    setError(fields.email, !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? "Please enter a valid email address." : "");

    if (!consent.checked) {
      globalError.textContent = "Please accept the contact consent to continue.";
      valid = false;
    }

    valid = !form.querySelector(".field.invalid") && consent.checked;

    if (!valid) return;

    const button = form.querySelector(".btn-submit");
    const label = button.querySelector(".btn-label");
    const defaultLabel = label.textContent;
    button.disabled = true;
    button.classList.add("loading");
    label.textContent = "Sending...";

    try {
      const isGitHubPages = window.location.hostname.endsWith(".github.io");
      const submissionUrl = isGitHubPages && form.dataset.staticAction
        ? form.dataset.staticAction
        : form.action;

      const response = await fetch(submissionUrl, {
        method: "POST",
        body: new FormData(form),
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          "Accept": "application/json"
        }
      });

      const responseText = await response.text();
      let result;

      try {
        result = JSON.parse(responseText);
      } catch (parseError) {
        throw new Error(
          isGitHubPages
            ? "The enquiry service returned an invalid response. Please try again shortly."
            : "The server did not execute submit.php. Use PHP hosting or start the local PHP server."
        );
      }

      const submitted = result.success === true || result.success === "true";

      if (response.ok && submitted) {
        if (form === brochureForm) {
          const requestedAction = brochureAction;
          closeBrochureModal();
          if (requestedAction === "download") {
            const link = document.createElement("a");
            link.href = brochurePath;
            link.download = "SAGI-Realty-Brochure.pdf";
            document.body.appendChild(link);
            link.click();
            link.remove();
          } else if (requestedAction === "view") {
            window.location.assign(brochurePath);
          }
          return;
        }
        window.location.href = result.redirect || "thank-you.html";
        return;
      }

      globalError.textContent = result.message || "Unable to submit your enquiry. Please try again.";
    } catch (error) {
      globalError.textContent = error.message || "Something went wrong. Please try again or contact the sales team.";
    } finally {
      button.disabled = false;
      button.classList.remove("loading");
      label.textContent = defaultLabel;
    }
  }));
});
