/* theme-handover.js — the OPENING-THEME handover, SHARED. A page that opens
 * in a theme (a class on <html> in the markup, so there is no flash) marks
 * its head block with [data-theme-handover]; this observer hands the page
 * over to the light default as that head scrolls away. WHICH class hands
 * over is the carrier's business: [data-theme-handover-class] names it
 * ("theme-work-article" on the work articles, whose chameleon leaves with
 * the hero — user call, Sep 2026); absent, it is the news pages' "dark". Two-way:
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
  //   "gone" — the marked element's BOTTOM crossing the viewport TOP:
  //       the opening theme holds until the element has fully scrolled
  //       off, then hands over (the work articles' hero banner — user
  //       call, Sep 2026: the midpoint flip came too early).
  //
  //   "end" — the marked section's END arriving at the FOLD: dark while
  //       the element's bottom still sits below the viewport's bottom
  //       edge, light the moment the content after it starts to show
  //       (/news, whose opening spans the masthead AND the sticky lead
  //       band — user calls, twice refined: first "once you get past the
  //       sticky section", then "swap to light when these [the rows after
  //       the band] first appear". The hook rides the lead item, whose
  //       three-row grid area IS the band's scroll track).
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
      // rootBounds is null only for cross-document roots — never here
      var fold = e.rootBounds ? e.rootBounds.height : window.innerHeight;
      // "end" carries a floor: a viewport tall enough to show PAST the
      // marked section at rest (a big monitor vs the /news sticky band)
      // must still OPEN dark — the handover needs somewhere to hand over
      // FROM. At the exact top the opening always stands; the first real
      // scroll lets the criterion speak.
      var mode = e.target.getAttribute("data-theme-handover");
      var opening = mode === "end"
        ? r.bottom > fold || window.scrollY < 1
        : mode === "gone"
          ? r.bottom > 0
          : r.top + r.height / 2 > 0;
      var cls = e.target.getAttribute("data-theme-handover-class") || "dark";
      document.documentElement.classList.toggle(cls, opening);
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
