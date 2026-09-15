/* expertise-links.js — the scroll highlight of the Expertise links
 * component: the "Our expertise" label is CSS-sticky (components/
 * expertise-links.css), so it locks in the viewport while the list scrolls
 * up past it; this inks whichever link sits beside the locked label
 * (nearest row centre). Works in every phase — before the label sticks it
 * sits level with the first item, after its travel it rests beside the
 * last. rAF-coalesced, one scroll listener for every instance on the page.
 * Born in js/home-c.js as the homepage intro's; a component of its own
 * since the list was asked onto the Expertise landing page (user call, Sep
 * 2026). Drupal: Drupal.behaviors.iconExpertiseLinks over
 * [data-expertise-links]; the `expertise-links` SDC, embedded by `intro`. */
(function () {
  "use strict";

  var rows = Array.prototype.slice.call(document.querySelectorAll("[data-expertise-links]"))
    .map(function (row) {
      return {
        label: row.querySelector(".expertise-links__label"),
        links: Array.prototype.slice.call(row.querySelectorAll(".expertise-links__list a")),
      };
    })
    .filter(function (r) { return r.label && r.links.length; });
  if (!rows.length) return;

  var raf = 0;
  var update = function () {
    raf = 0;
    rows.forEach(function (r) {
      var lr = r.label.getBoundingClientRect();
      var labelMid = lr.top + lr.height / 2;
      var best = 0, bestDist = Infinity;
      r.links.forEach(function (a, i) {
        var b = a.getBoundingClientRect();
        var d = Math.abs(b.top + b.height / 2 - labelMid);
        if (d < bestDist) { bestDist = d; best = i; }
      });
      r.links.forEach(function (a, i) { a.classList.toggle("is-active", i === best); });
    });
  };
  var schedule = function () { if (!raf) raf = requestAnimationFrame(update); };
  window.addEventListener("scroll", schedule, { passive: true });
  window.addEventListener("resize", schedule, { passive: true });
  update();
})();
