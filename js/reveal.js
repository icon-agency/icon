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

  /* ---- rules draw themselves in --------------------------------------------
   * Every hairline on the site ([data-rule], and the clients marquee's
   * pseudo-element pair via [data-rule-host]) grows from its left edge as it
   * arrives. One observer for the lot so the whole page draws at one speed and
   * nothing has to remember to opt in beyond the attribute.
   *
   * Latched, not toggled: a rule is a structural line between two pieces of
   * content, and re-drawing it every time it re-enters would make the page
   * feel like it was still loading. Unobserved once drawn.
   */
  var ruleEls = Array.prototype.slice.call(
    document.querySelectorAll("[data-rule], [data-rule-host]")
  );
  if (ruleEls.length) {
    if (!("IntersectionObserver" in window)) {
      ruleEls.forEach(function (el) { el.classList.add("is-drawn"); });
    } else {
      var ruleIO = new IntersectionObserver(
        function (entries) {
          entries.forEach(function (e) {
            if (!e.isIntersecting) return;
            e.target.classList.add("is-drawn");
            ruleIO.unobserve(e.target);
          });
        },
        // a sliver is enough: these are 1px lines, so waiting for a ratio of
        // them would never fire
        { rootMargin: "0px 0px -5% 0px" }
      );
      ruleEls.forEach(function (el) { ruleIO.observe(el); });
    }
  }
})();
