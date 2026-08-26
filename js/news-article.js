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

  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      document.documentElement.classList.toggle("dark", e.isIntersecting);
    });
  });
  io.observe(head);
})();
