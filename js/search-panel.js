/* search-panel.js — the pill's search extends below itself as you type
 * (components/search.css, .site-search), the way an iPad's search field
 * does: a panel on the pill's frosted ground, the results in their groups
 * — Work, News, Pages, People — a picture, an eyebrow, the title and the
 * excerpt each. The panel IS the search (user call, 19 Oct 2026: no
 * results page) — it lists everything the index finds, scrolling, under a
 * sticky row of filters (user ask, 20 Oct 2026: "mini filters for search?
 * All, News, Work, Pages. All can be a mix based on relevance, sticky
 * under search"): All is the one list in the index's order, each row
 * naming its kind in the eyebrow; a kind's chip is that group alone. Reads
 * the JSON the form names (data-search-suggest; icon_site's
 * SearchController::suggest()), debounced, from two characters. The arrow
 * keys move through the rows from the field, Enter follows a focused row
 * and does nothing from the field (user call, 20 Oct 2026: opening the
 * top hit "feels odd"), Esc clears and closes; the panel closes with the face (the header's
 * close, an outside tap) — it watches the form's `inert`. No JS, or no
 * endpoint (the design-system templates): the form submits to /search,
 * the plain results page kept for that case alone; with the endpoint the
 * form never submits — Enter on a term the face reopened with brings the
 * panel out instead. Reduced motion takes
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
  var filter = "all"; // the pressed chip; All again when the panel closes
  var last = null; // the data on show, re-rendered on a chip

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
    filter = "all";
    last = null;
  }

  function row(item, kind) {
    var html = '<li class="site-search__item"><a class="site-search__link" href="' + esc(item.url) + '" tabindex="-1">';
    if (item.image) html += '<img class="site-search__image" src="' + esc(item.image) + '" alt="" loading="lazy" width="100" height="100" />';
    html += '<span class="site-search__text">';
    var eyebrow = kind ? kind + (item.eyebrow ? " · " + item.eyebrow : "") : item.eyebrow;
    if (eyebrow) html += '<span class="site-search__eyebrow">' + esc(eyebrow) + "</span>";
    html += '<span class="site-search__title">' + esc(item.title) + "</span>";
    if (item.excerpt) html += '<span class="site-search__excerpt">' + String(item.excerpt).replace(/<(?!\/?mark\b)[^>]*>/g, "") + "</span>";
    return html + "</span></a></li>";
  }

  // the chips: All, then the sections of the nav in the nav's order (user
  // call, 20 Oct 2026: "the filters need to match, i.e. All WORK EXPERTISE
  // ABOUT NEWS CONTACT"), only those with something for the term ("hide
  // filters that are not relevant to the search"), the pressed one held
  function chips(data) {
    var html = '<div class="site-search__filters" role="group" aria-label="' + esc(Drupal.t("Show")) + '">';
    var order = data.sections || data.groups;
    var shown = order.filter(function (c) { return data.groups.some(function (g) { return g.key === c.key; }); });
    [{ key: "all", label: Drupal.t("All") }].concat(shown).forEach(function (c) {
      html += '<button type="button" class="site-search__chip" data-filter="' + esc(c.key) + '" aria-pressed="' + (c.key === filter ? "true" : "false") + '">' + esc(c.label) + "</button>";
    });
    return html + "</div>";
  }

  function render(data) {
    if (!data.groups || !data.groups.length) {
      panel.innerHTML = '<p class="site-search__empty">' + esc(Drupal.t("Nothing for “@keys” yet — keep typing, or try another word.", { "@keys": data.keys })) + "</p>";
      open();
      return;
    }
    last = data;
    var has = data.groups.some(function (g) { return g.key === filter; });
    if (filter !== "all" && !has) filter = "all";
    var html = chips(data);
    if (filter === "all") {
      // one list in the index's order, each row naming its kind
      html += '<ul class="site-search__list">';
      (data.all || []).forEach(function (item) { html += row(item, item.group_label); });
      html += "</ul>";
    } else {
      data.groups.forEach(function (group) {
        if (group.key !== filter) return;
        html += '<ul class="site-search__list" aria-label="' + esc(group.label) + '">';
        group.items.forEach(function (item) { html += row(item); });
        html += "</ul>";
      });
    }
    panel.innerHTML = html;
    panel.scrollTop = 0;
    open();
  }

  panel.addEventListener("click", function (e) {
    var chip = e.target.closest("[data-filter]");
    if (!chip || !last) return;
    filter = chip.getAttribute("data-filter");
    // after the click has finished its travel: the re-render takes the
    // chip out of the page, and the header's outside-click close would
    // read the detached chip as outside the search
    setTimeout(function () {
      render(last);
      panel.querySelector('[data-filter="' + filter + '"]').focus();
    }, 0);
  });

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

  // the arrow keys walk the rows from the field; Enter follows a focused
  // row, and from the field just keeps the panel (the form never submits
  // while the panel shows — the results are already there)
  function rows() { return Array.prototype.slice.call(panel.querySelectorAll(".site-search__link")); }
  form.addEventListener("submit", function (e) {
    // a term the face reopened with (the page kept it) has had no input
    // event, so the panel is not out yet: Enter brings it out, never the
    // page (user call, 20 Oct 2026)
    e.preventDefault();
    lastKeys = "";
    search();
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

  // the face closing (the header's close, an outside tap) takes the panel;
  // the face opening with a term still in it brings the panel straight out
  new MutationObserver(function () {
    if (form.hasAttribute("inert")) close();
    else if (input.value.trim().length >= 2) search();
  }).observe(form, { attributes: true, attributeFilter: ["inert"] });
})();
