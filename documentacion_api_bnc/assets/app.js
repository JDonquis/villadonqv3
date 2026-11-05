(function () {
  "use strict";

  var body = document.body;
  var sidebar = document.getElementById("sidebar");
  var overlay = document.querySelector(".sidebar-overlay");
  var menuToggle = document.querySelector(".menu-toggle");
  var searchInput = document.getElementById("nav-search");
  var navLinks = Array.prototype.slice.call(
    document.querySelectorAll(".side-nav a[href^='#']")
  );
  var groups = Array.prototype.slice.call(
    document.querySelectorAll("[data-nav-group]")
  );
  var spySections = Array.prototype.slice.call(
    document.querySelectorAll("[data-spy]")
  );
  var toTop = document.querySelector(".to-top");

  /* Mobile navigation */

  function setMenu(open) {
    if (open) {
      body.classList.add("nav-open");
      if (overlay) overlay.hidden = false;
      if (menuToggle) menuToggle.setAttribute("aria-expanded", "true");
    } else {
      body.classList.remove("nav-open");
      if (overlay) overlay.hidden = true;
      if (menuToggle) menuToggle.setAttribute("aria-expanded", "false");
    }
  }

  if (menuToggle) {
    menuToggle.addEventListener("click", function () {
      setMenu(!body.classList.contains("nav-open"));
    });
  }

  if (overlay) {
    overlay.addEventListener("click", function () {
      setMenu(false);
    });
  }

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") setMenu(false);
    if (
      event.key === "/" &&
      searchInput &&
      document.activeElement !== searchInput &&
      !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)
    ) {
      event.preventDefault();
      searchInput.focus();
    }
  });

  if (sidebar) {
    sidebar.addEventListener("click", function (event) {
      if (event.target.closest("a") && window.matchMedia("(max-width: 900px)").matches) {
        setMenu(false);
      }
    });
  }

  /* Sidebar filter */

  function normalize(value) {
    return (value || "")
      .toString()
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "");
  }

  function filterNavigation() {
    if (!searchInput) return;
    var query = normalize(searchInput.value.trim());

    Array.prototype.forEach.call(
      document.querySelectorAll(".side-nav [data-search]"),
      function (link) {
        var matches = !query || normalize(link.getAttribute("data-search")).indexOf(query) !== -1;
        link.classList.toggle("hidden", !matches);
      }
    );

    groups.slice().reverse().forEach(function (group) {
      var own = group.querySelector(":scope > .nav-group-title");
      var ownVisible = own && !own.classList.contains("hidden");
      var childVisible = Array.prototype.some.call(
        group.querySelectorAll(":scope > .nav-children .nav-link"),
        function (link) {
          return !link.classList.contains("hidden");
        }
      );
      group.hidden = !ownVisible && !childVisible;
    });
  }

  if (searchInput) {
    searchInput.addEventListener("input", filterNavigation);
    searchInput.addEventListener("search", filterNavigation);
  }

  /* Copy buttons */

  function legacyCopy(text) {
    var area = document.createElement("textarea");
    area.value = text;
    area.setAttribute("readonly", "");
    area.style.position = "fixed";
    area.style.top = "-1000px";
    document.body.appendChild(area);
    area.select();
    try {
      document.execCommand("copy");
    } catch (error) {
      /* ignore */
    }
    document.body.removeChild(area);
  }

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text).catch(function () {
        legacyCopy(text);
      });
    }
    legacyCopy(text);
    return Promise.resolve();
  }

  Array.prototype.forEach.call(
    document.querySelectorAll("[data-copy]"),
    function (button) {
      var defaultLabel = button.textContent;
      button.addEventListener("click", function () {
        var target = document.getElementById(button.getAttribute("data-copy"));
        if (!target) return;
        var text = target.innerText || target.textContent || "";
        copyText(text).then(
          function () {
            button.textContent = "Copiado";
            button.classList.add("copied");
            window.setTimeout(function () {
              button.textContent = defaultLabel;
              button.classList.remove("copied");
            }, 1600);
          },
          function () {
            button.textContent = "Error";
            window.setTimeout(function () {
              button.textContent = defaultLabel;
            }, 1600);
          }
        );
      });
    }
  );

  /* Scroll spy */

  var ticking = false;

  function updateActiveLink() {
    ticking = false;
    if (!navLinks.length) return;

    var currentId = "";
    for (var i = 0; i < spySections.length; i += 1) {
      var section = spySections[i];
      if (section.getBoundingClientRect().top <= 145) {
        currentId = section.id;
      } else {
        break;
      }
    }

    navLinks.forEach(function (link) {
      var isActive =
        currentId && link.getAttribute("href") === "#" + currentId;
      link.classList.toggle("active", !!isActive);
    });

    if (toTop) toTop.classList.toggle("visible", window.scrollY > 500);
  }

  function requestUpdate() {
    if (!ticking) {
      ticking = true;
      window.requestAnimationFrame(updateActiveLink);
    }
  }

  window.addEventListener("scroll", requestUpdate, { passive: true });
  window.addEventListener("resize", requestUpdate);
  updateActiveLink();

  if (toTop) {
    toTop.addEventListener("click", function () {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }
})();
