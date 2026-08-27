/* theme-handover.js — the dark-opening theme handover, SHARED. A page that
 * OPENS dark (class="dark" on <html> in the markup, so there is no light
 * flash) marks its head block with [data-theme-handover]; this observer
 * hands the page over to light as that head scrolls away. Two-way:
 * scrolling back up returns the dark opening, so the page reads the same
 * in both directions. Grown out of js/news-article.js when the /news
 * landing became the third template to open dark (article A, article B,
 * the listing) — the homeC blue→light handover pattern, revived.
 *
 * The flip is a class swap on <html> (never <body> — the @theme inline
 * aliases resolve at :root, LESSONS.md) and the base typography.css
 * cross-fade eases every ink/surface/border through it. Colour-only, so no
 * reduced-motion guard is needed (the cross-fade is exempt for the same
 * reason, per the note in base/typography.css).
 *
 * No-JS / no-IO: the page simply stays dark end to end — a legitimate
 * reading of the design rather than a broken in-between.
 *
 * NB the dev-only theme-toggle also writes these classes; last writer wins,
 * which is fine for a prototype affordance that ships removed.
 *
 * Drupal: Drupal.behaviors.iconThemeHandover via `icon/theme-handover`. */
(function () {
  "use strict";

  if (!("IntersectionObserver" in window)) return;

  // Two criteria, picked by the attribute's value:
  //
  //   ""  (default) — the head's HALFWAY point: half the head gone, theme
  //       gone (user call, the article pages). Measured as the head's
  //       MIDPOINT crossing the viewport top, not as intersectionRatio
  //       >= 0.5 — the ratio version broke the moment a head grew taller
  //       than the viewport (header B's stacked hero): such a head can
  //       never be 50% visible, so the page went light at load.
  //
  //   "end" — the element's BOTTOM edge crossing the viewport top: the
  //       dark opening holds while ANY of the marked section still shows
  //       (/news, whose opening spans the masthead AND the sticky lead
  //       band — user call: it changes once you get past the sticky
  //       section, so the hook rides the lead item, whose three-row grid
  //       area IS the band's scroll track).
  //
  // The dense threshold array keeps callbacks firing while the element
  // scrolls, and each callback reads the true geometry.
  var steps = [];
  for (var i = 0; i <= 50; i++) steps.push(i / 50);
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      var r = e.boundingClientRect;
      // A display:none carrier (the /news filter hiding the lead row)
      // delivers an all-zero rect — geometry about NOTHING must not decide
      // the theme, or a chip tap would strip the dark opening under the
      // reader. Hold the current theme until real geometry arrives.
      if (!r.width && !r.height) return;
      var pivot = e.target.getAttribute("data-theme-handover") === "end"
        ? r.bottom
        : r.top + r.height / 2;
      document.documentElement.classList.toggle("dark", pivot > 0);
    });
  }, { threshold: steps });

  // (Re)aim the observer at the current carrier. The /news filter re-hosts
  // the attribute onto the first VISIBLE story (mirroring Drupal, where the
  // filtered view simply renders it on its own first row) and announces the
  // move with this event — js/news.js is the only dispatcher.
  var bind = function () {
    var head = document.querySelector("[data-theme-handover]");
    if (!head) return;
    io.disconnect();
    io.observe(head);
  };
  window.addEventListener("icon:theme-rebind", bind);
  bind();
})();
