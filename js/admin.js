(() => {
  const sidebar = document.querySelector("#sidebar");
  const menuButton = document.querySelector(".menu-button");
  const scrim = document.querySelector(".sidebar-scrim");

  if (sidebar && menuButton && scrim) {
    const close = () => {
      sidebar.classList.remove("open");
      menuButton.setAttribute("aria-expanded", "false");
    };

    menuButton.addEventListener("click", () => {
      const open = sidebar.classList.toggle("open");
      menuButton.setAttribute("aria-expanded", String(open));
    });
    scrim.addEventListener("click", close);
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") close();
    });
  }

  document.querySelectorAll(".status-select").forEach((select) => {
    select.addEventListener("change", () => {
      select.className = `status-select status-${select.value}`;
    });
  });
})();
