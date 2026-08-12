/* reveal.js — generic scroll-reveal: adds .is-visible to [data-animate] elements
 * as they scroll into view, driving the opacity+rise host in
 * src/utilities/animations.css. The CSS owns the transition (and the
 * --animate-delay stagger); this only toggles the class once per element.
 *
 * No-JS safe: without .js-animations the host renders elements fully visible.
 * Reduced-motion safe: the CSS host already resets [data-animate] to visible
 * with no transition under prefers-reduced-motion, so toggling the class is inert.
 *
 * This is the missing companion to js/work.js — work.js only drives [data-reveal]
 * swipes, .section-title underlines, and .project-card hover-video; nothing else
 * revealed [data-animate]. Used by the /work and /work/article page chrome.
 * Drupal: Drupal.behaviors.iconReveal. */
(function () {
  "use strict";

  var nodes = Array.prototype.slice.call(document.querySelectorAll("[data-animate]"));
  if (!nodes.length) return;

  // No IntersectionObserver → reveal everything immediately (no-JS equivalent).
  if (!("IntersectionObserver" in window)) {
    nodes.forEach(function (el) { el.classList.add("is-visible"); });
    return;
  }

  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (!e.isIntersecting) return;
      e.target.classList.add("is-visible");
      io.unobserve(e.target);
    });
  }, { threshold: 0.1, rootMargin: "0px 0px -50px 0px" });

  nodes.forEach(function (el) { io.observe(el); });
})();
