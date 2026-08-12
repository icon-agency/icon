/* theme-toggle.js — TEMPORARY dev-only theme switch (fixed, bottom-right, icon-only).
 *
 * Standard pages: toggles `.dark` on <html>.
 * The /work/article folio (.theme-work-article): swaps the two override colours
 * via `.is-inverted` — purple-on-lilac ⇄ lilac-on-purple (see work-article.css).
 *
 * Either way it persists the choice in localStorage and applies it before first
 * paint (loaded in <head>); the button is built once the DOM is ready. The icon
 * shows a moon when a "light" state is active (click → dark) and a sun when a
 * "dark" state is active (click → light). Self-contained — remove this file and
 * its four <script> tags to delete it.
 *
 * NOT for production.
 */
(function () {
  "use strict";

  var root = document.documentElement;
  var FOLIO = root.classList.contains("theme-work-article");
  // On the folio, the "dark" state is the inverted (dark-surface) palette.
  var CLASS = FOLIO ? "is-inverted" : "dark";
  var KEY = FOLIO ? "icon-folio-invert" : "icon-theme";

  // 1) Apply any saved preference ASAP (before paint when loaded in <head>).
  try {
    var saved = localStorage.getItem(KEY);
    if (saved === "on") root.classList.add(CLASS);
    else if (saved === "off") root.classList.remove(CLASS);
  } catch (e) {}

  function isDark() { return root.classList.contains(CLASS); }

  // 2) Build the floating icon button once the body exists.
  function build() {
    if (document.querySelector(".dev-theme-toggle")) return;

    var style = document.createElement("style");
    style.textContent =
      ".dev-theme-toggle{position:fixed;right:1rem;bottom:1rem;z-index:2147483647;" +
      "display:inline-flex;align-items:center;justify-content:center;" +
      "width:2.75rem;height:2.75rem;padding:0;border:1px solid rgba(127,127,127,.45);" +
      "border-radius:9999px;background:rgba(127,127,127,.2);color:inherit;cursor:pointer;" +
      "-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);" +
      "box-shadow:0 4px 16px rgba(0,0,0,.25)}" +
      ".dev-theme-toggle:hover{background:rgba(127,127,127,.3)}" +
      ".dev-theme-toggle svg{width:1.15rem;height:1.15rem;flex:none;display:block}";
    document.head.appendChild(style);

    var SUN =
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>';
    var MOON =
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>';

    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "dev-theme-toggle";

    function label() {
      if (FOLIO) return isDark() ? "Switch to the light case-study colours" : "Switch to the dark case-study colours";
      return isDark() ? "Switch to light theme" : "Switch to dark theme";
    }
    function render() {
      btn.innerHTML = isDark() ? SUN : MOON; // icon only — no label text
      btn.setAttribute("aria-label", label());
      btn.setAttribute("aria-pressed", isDark() ? "true" : "false");
    }
    render();

    btn.addEventListener("click", function () {
      root.classList.toggle(CLASS);
      try { localStorage.setItem(KEY, isDark() ? "on" : "off"); } catch (e) {}
      render();
    });

    document.body.appendChild(btn);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", build);
  } else {
    build();
  }
})();
