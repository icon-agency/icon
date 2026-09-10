/* team-profiles.js — the team panel: open a profile from its card, step
 * previous / next, close by the X, by Escape or by a click beside the panel,
 * and keep the ADDRESS in step with it the whole way.
 *
 * The grid's links point at the panel's own address (/about/<slug>), which
 * the server accepts (icon_site's TeamProfilePathProcessor serves the About
 * page for it), so a link opened in a new tab, or sent to someone, lands
 * here with that address — and this reads it on load and opens the panel.
 * From then on every open, step and close is a pushState, so Back and
 * Forward walk the panels the way the reference site does
 * (basicagency.com/about). The document title follows the person.
 *
 * The panel is a native <dialog> shown modally: focus is held inside, Escape
 * fires `cancel` (intercepted, so the address is written too), and the click
 * beside the panel is a click on the dialog's own transparent box. Reduced
 * motion: the CSS drops the slide; nothing to do here.
 * Drupal: Drupal.behaviors.iconTeamProfiles via `icon/team-profiles`. */
(function () {
  "use strict";

  var overlay = document.querySelector("[data-team-overlay]");
  if (!overlay || typeof overlay.showModal !== "function") return;

  var base = overlay.getAttribute("data-team-base") || "/about";
  var profiles = Array.prototype.slice.call(overlay.querySelectorAll("[data-team-profile]"));
  var slugs = profiles.map(function (p) { return p.getAttribute("data-team-profile"); });
  if (!slugs.length) return;
  var indexEl = overlay.querySelector("[data-team-index]");
  var closeBtn = overlay.querySelector("[data-team-close]");
  var prevBtn = overlay.querySelector("[data-team-prev]");
  var nextBtn = overlay.querySelector("[data-team-next]");
  var pageTitle = document.title;
  var siteName = (pageTitle.split(" — ").pop() || "").trim();
  var current = -1;
  var deepLink = new RegExp("^" + base.replace(/[.*+?^${}()|[\]\\]/g, "\\$&") + "/([a-z0-9-]+)/?$");

  function pad(n) { return (n < 10 ? "0" : "") + n; }

  function show(i) {
    profiles.forEach(function (p, k) { p.hidden = k !== i; });
    current = i;
    if (indexEl) indexEl.textContent = pad(i + 1);
    var name = profiles[i].querySelector("[data-team-name]");
    document.title = name && siteName ? name.textContent.trim() + " — " + siteName : pageTitle;
    // the panel scrolls; a new person starts at the top of it
    var panel = overlay.firstElementChild;
    if (panel) panel.scrollTop = 0;
  }

  function open(slug, push) {
    var i = slugs.indexOf(slug);
    if (i < 0) return false;
    show(i);
    if (!overlay.open) {
      overlay.showModal();
      document.documentElement.classList.add("is-team-open");
    }
    if (push) history.pushState({ team: slug }, "", base + "/" + slug);
    if (closeBtn) closeBtn.focus();
    return true;
  }

  function close(push) {
    if (!overlay.open) return;
    overlay.close();
    document.documentElement.classList.remove("is-team-open");
    document.title = pageTitle;
    if (push) history.pushState({ team: null }, "", base);
    // hand focus back to the card that was opened
    if (current >= 0) {
      var link = document.querySelector('[data-team-open="' + slugs[current] + '"]');
      if (link) link.focus();
    }
  }

  function step(d) {
    if (current < 0) return;
    open(slugs[(current + d + slugs.length) % slugs.length], true);
  }

  document.addEventListener("click", function (e) {
    var a = e.target.closest ? e.target.closest("[data-team-open]") : null;
    if (!a) return;
    // a modified click keeps its native meaning (new tab, new window)
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
    e.preventDefault();
    open(a.getAttribute("data-team-open"), true);
  });
  if (closeBtn) closeBtn.addEventListener("click", function () { close(true); });
  if (prevBtn) prevBtn.addEventListener("click", function () { step(-1); });
  if (nextBtn) nextBtn.addEventListener("click", function () { step(1); });
  // beside the panel: the dialog's own box, which is the whole viewport
  overlay.addEventListener("click", function (e) { if (e.target === overlay) close(true); });
  // Escape: the dialog would close itself; intercepted so the address follows
  overlay.addEventListener("cancel", function (e) { e.preventDefault(); close(true); });
  overlay.addEventListener("keydown", function (e) {
    if (e.key === "ArrowRight") step(1);
    else if (e.key === "ArrowLeft") step(-1);
  });

  // Back / Forward: the address is the truth
  window.addEventListener("popstate", function () {
    var m = location.pathname.match(deepLink);
    if (m && slugs.indexOf(m[1]) >= 0) open(m[1], false);
    else close(false);
  });

  // arrived at a panel's own address
  var here = location.pathname.match(deepLink);
  if (here && slugs.indexOf(here[1]) >= 0) {
    open(here[1], false);
    history.replaceState({ team: here[1] }, "", location.pathname + location.search);
  }
})();
