/* theme-toggle.js — TEMPORARY dev-only theme switch (fixed, bottom-right, icon-only).
 *
 * Standard pages: cycles THREE themes — light (no class) → dark (.dark) →
 * blue (.theme-blue) — on <html> (the @theme inline aliases resolve at :root,
 * so the class must live there; see LESSONS.md).
 * The /work/article folio (.theme-work-article): swaps the two override colours
 * via `.is-inverted` — purple-on-lilac ⇄ lilac-on-purple (see work-article.css).
 *
 * Persists the choice in localStorage and applies it before first paint (loaded
 * in <head>); the button is built once the DOM is ready. The icon previews the
 * NEXT theme in the cycle: moon (→ dark), droplet (→ blue), sun (→ light).
 * Legacy values from the two-state version map across ('on' → dark,
 * 'off' → light). Self-contained — remove this file and its <script> tags to
 * delete it. NOT for production.
 */
(function () {
  "use strict";

  var root = document.documentElement;
  var FOLIO = root.classList.contains("theme-work-article");

  /* Declared before EITHER branch registers its build callback: the folio
     branch returns early, and a ready-state DOM runs build() synchronously —
     both reach ICONS before a bottom-of-file assignment would have run. */
  var ICONS = {
    sun:
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>',
    moon:
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>',
    drop:
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>'
  };

  /* ---- Folio branch: unchanged two-state invert ------------------------- */
  if (FOLIO) {
    var FKEY = "icon-folio-invert";
    try {
      var fsaved = localStorage.getItem(FKEY);
      if (fsaved === "on") root.classList.add("is-inverted");
      else if (fsaved === "off") root.classList.remove("is-inverted");
    } catch (e) {}

    var fbuild = function () {
      if (document.querySelector(".dev-theme-toggle")) return;
      injectStyle();
      var btn = makeButton();
      var isInv = function () { return root.classList.contains("is-inverted"); };
      var render = function () {
        btn.innerHTML = isInv() ? ICONS.sun : ICONS.moon;
        btn.setAttribute("aria-label", isInv() ? "Switch to the light case-study colours" : "Switch to the dark case-study colours");
        btn.setAttribute("aria-pressed", isInv() ? "true" : "false");
      };
      render();
      btn.addEventListener("click", function () {
        root.classList.toggle("is-inverted");
        try { localStorage.setItem(FKEY, isInv() ? "on" : "off"); } catch (e) {}
        render();
      });
      document.body.appendChild(btn);
    };
    onReady(fbuild);
    return;
  }

  /* ---- Standard pages: light → dark → blue cycle ------------------------ */
  var KEY = "icon-theme";
  var THEMES = ["light", "dark", "blue"];

  function apply(theme) {
    root.classList.toggle("dark", theme === "dark");
    root.classList.toggle("theme-blue", theme === "blue");
  }

  function current() {
    if (root.classList.contains("theme-blue")) return "blue";
    if (root.classList.contains("dark")) return "dark";
    return "light";
  }

  // 1) Apply any saved preference ASAP (before paint when loaded in <head>).
  //    Legacy two-state values map across.
  try {
    var saved = localStorage.getItem(KEY);
    if (saved === "on") saved = "dark";
    if (saved === "off") saved = "light";
    if (THEMES.indexOf(saved) > -1) apply(saved);
  } catch (e) {}

  // 2) Build the floating icon button once the body exists.
  function build() {
    if (document.querySelector(".dev-theme-toggle")) return;
    injectStyle();
    var btn = makeButton();

    function next() {
      return THEMES[(THEMES.indexOf(current()) + 1) % THEMES.length];
    }
    function render() {
      var n = next();
      btn.innerHTML = n === "dark" ? ICONS.moon : n === "blue" ? ICONS.drop : ICONS.sun;
      btn.setAttribute("aria-label", "Switch to " + n + " theme");
    }
    render();

    btn.addEventListener("click", function () {
      var n = next();
      apply(n);
      try { localStorage.setItem(KEY, n); } catch (e) {}
      render();
    });

    document.body.appendChild(btn);
  }
  onReady(build);

  /* ---- shared bits ------------------------------------------------------ */

  function injectStyle() {
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
  }

  function makeButton() {
    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "dev-theme-toggle";
    return btn;
  }

  function onReady(fn) {
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", fn);
    else fn();
  }
})();
