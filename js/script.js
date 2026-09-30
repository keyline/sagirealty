document.addEventListener("DOMContentLoaded", () => {
  const header = document.getElementById("siteHeader");
  const menuToggle = document.querySelector(".menu-toggle");
  const mobileNav = document.querySelector(".mobile-nav");
  const year = document.getElementById("year");
  const form = document.querySelector(".lead-form");
  const modal = document.getElementById("imageModal");
  const modalImage = document.getElementById("modalImage");
  const modalClose = document.querySelector(".modal-close");

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

  // Client-side form validation + AJAX submission.
  form?.addEventListener("submit", async (e) => {
    e.preventDefault();

    const globalError = form.querySelector(".form-global-error");
    globalError.textContent = "";

    if (window.location.protocol === "file:") {
      globalError.textContent = "This form requires a PHP server. Open the website through a PHP localhost URL, not directly as a file.";
      return;
    }

    const fields = {
      name: form.querySelector("#name"),
      phone: form.querySelector("#phone"),
      email: form.querySelector("#email")
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
    setError(fields.phone, phone.length !== 10 ? "Please enter a valid 10-digit mobile number." : "");
    setError(fields.email, !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? "Please enter a valid email address." : "");

    if (!consent.checked) {
      globalError.textContent = "Please accept the contact consent to continue.";
      valid = false;
    }

    valid = !form.querySelector(".field.invalid") && consent.checked;

    if (!valid) return;

    const button = form.querySelector(".btn-submit");
    button.disabled = true;
    button.classList.add("loading");
    button.querySelector(".btn-label").textContent = "Sending...";

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
        window.location.href = result.redirect || "thank-you.html";
        return;
      }

      globalError.textContent = result.message || "Unable to submit your enquiry. Please try again.";
    } catch (error) {
      globalError.textContent = error.message || "Something went wrong. Please try again or contact the sales team.";
    } finally {
      button.disabled = false;
      button.classList.remove("loading");
      button.querySelector(".btn-label").textContent = "Send My Enquiry";
    }
  });
});
