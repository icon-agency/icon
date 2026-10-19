/* search-panel.js — the pill's search extends below itself as you type
 * (components/search.css, .site-search), the way an iPad's search field
 * does: a panel on the pill's frosted ground, the results in their groups
 * — Work, News, Pages, People — a picture, an eyebrow, the title and the
 * excerpt each. The panel IS the search (user call, 19 Oct 2026: no
 * results page) — it lists everything the index finds, scrolling. Reads
 * the JSON the form names (data-search-suggest; icon_site's
 * SearchController::suggest()), debounced, from two characters. The arrow
 * keys move through the rows from the field, Enter follows the row — or,
 * from the field, the first row, as the iPad's search opens its top hit —
 * Esc clears and closes; the panel closes with the face (the header's
 * close, an outside tap) — it watches the form's `inert`. No JS, or no
 * endpoint (the design-system templates): the form submits to /search,
 * the plain results page kept for that case alone. Reduced motion takes
 * the extend away. Drupal.behaviors.iconSearchPanel. */
(function () {
  "use strict";
  var form = document.querySelector("[data-search-face][data-search-suggest]");
  if (!form) return;
  var input = form.querySelector("[data-search-input]");
  var endpoint = form.getAttribute("data-search-suggest");
  var nav = form.closest(".site-nav") || form.parentElement;
  if (!input || !nav) return;

  var panel = document.createElement("div");
  panel.className = "site-search";
  panel.id = "site-search";
  panel.hidden = true;
  panel.setAttribute("role", "region");
  panel.setAttribute("aria-label", input.getAttribute("aria-label") || "Search results");
  nav.appendChild(panel);
  input.setAttribute("aria-controls", "site-search");
  input.setAttribute("aria-expanded", "false");

  var timer = null;
  var request = 0;
  var lastKeys = "";

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" }[c]; });
  }

  // under the pill, the pill's width (a floor from the CSS): the pill sits
  // inside the nav's 3D cube, so the panel hangs from the nav instead and
  // takes the face's place from its rectangle
  function place() {
    var n = nav.getBoundingClientRect();
    var f = form.getBoundingClientRect();
    panel.style.setProperty("--search-left", (f.left - n.left) + "px");
    panel.style.setProperty("--search-width", f.width + "px");
    panel.style.setProperty("--search-top", (f.bottom - n.top) + "px");
    // down to the same air below as the face has above it
    panel.style.setProperty("--search-height", Math.max(240, window.innerHeight - f.bottom - f.top) + "px");
  }
  window.addEventListener("resize", function () { if (!panel.hidden) place(); });

  function open() {
    place();
    if (!panel.hidden) return;
    panel.hidden = false;
    form.classList.add("is-extended");
    input.setAttribute("aria-expanded", "true");
    // a tick, not an animation frame: a hidden tab never paints one
    setTimeout(function () { panel.classList.add("is-open"); }, 20);
  }
  function close() {
    if (panel.hidden) return;
    panel.classList.remove("is-open");
    form.classList.remove("is-extended");
    input.setAttribute("aria-expanded", "false");
    panel.hidden = true;
    panel.innerHTML = "";
    lastKeys = "";
  }

  function render(data) {
    if (!data.groups || !data.groups.length) {
      panel.innerHTML = '<p class="site-search__empty">' + esc(Drupal.t("Nothing for “@keys” yet — keep typing, or try another word.", { "@keys": data.keys })) + "</p>";
      open();
      return;
    }
    var html = "";
    data.groups.forEach(function (group) {
      html += '<section class="site-search__group" aria-label="' + esc(group.label) + '">';
      html += '<h2 class="site-search__label">' + esc(group.label) + (group.total > group.items.length ? ' <span class="site-search__count">' + esc(group.total) + "</span>" : "") + "</h2>";
      html += '<ul class="site-search__list">';
      group.items.forEach(function (item) {
        html += '<li class="site-search__item"><a class="site-search__link" href="' + esc(item.url) + '" tabindex="-1">';
        html += item.image ? '<img class="site-search__image" src="' + esc(item.image) + '" alt="" loading="lazy" width="100" height="100" />' : '<span class="site-search__image site-search__image--blank" aria-hidden="true"></span>';
        html += '<span class="site-search__text">';
        if (item.eyebrow) html += '<span class="site-search__eyebrow">' + esc(item.eyebrow) + "</span>";
        html += '<span class="site-search__title">' + esc(item.title) + "</span>";
        if (item.excerpt) html += '<span class="site-search__excerpt">' + String(item.excerpt).replace(/<(?!\/?mark\b)[^>]*>/g, "") + "</span>";
        html += "</span></a></li>";
      });
      html += "</ul></section>";
    });
    panel.innerHTML = html;
    open();
  }

  function search() {
    var keys = input.value.trim();
    if (keys.length < 2) { close(); return; }
    if (keys === lastKeys) return;
    lastKeys = keys;
    var id = ++request;
    panel.setAttribute("aria-busy", "true");
    fetch(endpoint + "?q=" + encodeURIComponent(keys), { headers: { Accept: "application/json" } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (id !== request || !data) return;
        panel.removeAttribute("aria-busy");
        render(data);
      })
      .catch(function () { panel.removeAttribute("aria-busy"); });
  }

  input.addEventListener("input", function () {
    clearTimeout(timer);
    timer = setTimeout(search, 180);
  });

  // the arrow keys walk the rows from the field; Enter follows a row, and
  // from the field the first row (the form never submits while rows show)
  function rows() { return Array.prototype.slice.call(panel.querySelectorAll(".site-search__link")); }
  form.addEventListener("submit", function (e) {
    if (panel.hidden) return;
    var first = rows()[0];
    e.preventDefault();
    if (first) window.location.href = first.href;
  });
  function focusRow(delta) {
    var list = rows();
    if (!list.length) return;
    var i = list.indexOf(document.activeElement);
    var next = i < 0 ? (delta > 0 ? 0 : list.length - 1) : (i + delta + list.length) % list.length;
    list[next].focus();
  }
  form.addEventListener("keydown", function (e) {
    if (panel.hidden) return;
    if (e.key === "ArrowDown") { e.preventDefault(); focusRow(1); }
    else if (e.key === "ArrowUp") { e.preventDefault(); focusRow(-1); }
    else if (e.key === "Escape") { input.value = ""; close(); }
  });
  panel.addEventListener("keydown", function (e) {
    if (e.key === "ArrowDown") { e.preventDefault(); focusRow(1); }
    else if (e.key === "ArrowUp") { e.preventDefault(); focusRow(-1); }
    else if (e.key === "Escape") { input.focus(); input.value = ""; close(); }
  });

  // the face closing (the header's close, an outside tap) takes the panel
  new MutationObserver(function () {
    if (form.hasAttribute("inert")) close();
  }).observe(form, { attributes: true, attributeFilter: ["inert"] });
})();
