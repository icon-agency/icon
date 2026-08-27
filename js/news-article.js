/* news-article.js — the article's theme handover. The page OPENS dark and
 * hands over to light as the head scrolls away — the homeC blue→light
 * handover pattern, revived for this template (that one was cut; see the
 * comment atop templates/homeC.html). Two-way: scrolling back up returns
 * the dark opening, so the page reads the same in both directions.
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
 * Drupal: Drupal.behaviors.iconNewsArticle via `icon/news-article`. */
(function () {
  "use strict";

  var head = document.querySelector(".news-article__head");
  if (!head || !("IntersectionObserver" in window)) return;

  // The handover fires at the head's HALFWAY point — half the head gone,
  // theme gone (user call) — measured as the head's MIDPOINT crossing the
  // viewport top, not as intersectionRatio >= 0.5. The ratio version broke
  // the moment a head grew taller than the viewport (header B's stacked
  // hero): such a head can never be 50% visible, so the page went light at
  // load. The dense threshold array keeps callbacks firing while the head
  // scrolls, and each callback reads the true geometry. Identical
  // behaviour to the old maths for heads shorter than the viewport.
  var steps = [];
  for (var i = 0; i <= 50; i++) steps.push(i / 50);
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      var r = e.boundingClientRect;
      document.documentElement.classList.toggle("dark", r.top + r.height / 2 > 0);
    });
  }, { threshold: steps });
  io.observe(head);
})();
