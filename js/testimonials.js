/* testimonials.js — the quotes carousel (components/testimonials.css): one
 * quote showing at a time, the arrows stepping through them with wrap, the
 * counter reading "01 / 06", the arrow keys stepping too while the focus is
 * inside. The quotes are counted, not the children: in the Canvas editor an
 * empty slot holds a placeholder child (the Filmstrip's lesson). One quote
 * → data-count="1", and the CSS takes the bar away. In the editor's frame
 * every quote shows, stacked (.is-editing), to select and reorder.
 *
 * The carousel also turns itself (user ask, 22 Sep 2026: "add an auto next
 * testimonial, allow enough time to read"): each quote holds for its own
 * reading time — a floor of seven seconds, then a third of a second a word
 * — and only while it is in view, the pointer is off it, nothing inside
 * has the focus and the tab is showing; an arrow or a key restarts the
 * clock from the quote just chosen. Reduced motion turns nothing. */
(function () {
  "use strict";
  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  function pad(n) { return (n < 10 ? "0" : "") + n; }

  document.querySelectorAll("[data-testimonials]").forEach(function (section) {
    // The quotes wherever they sit in the track: a Testimonial (item) block
    // renders inside Drupal's block wrapper, so they are not the track's
    // own children (user catch, Sep 2026: one quote, the bar still there).
    var track = section.querySelector(".testimonials__track");
    var items = Array.prototype.slice.call(track ? track.querySelectorAll(".testimonial") : []);
    var count = items.length;
    section.setAttribute("data-count", String(count));
    var frame = window.frameElement;
    if (frame && frame.hasAttribute("data-canvas-preview")) {
      section.classList.add("is-editing");
      return;
    }
    if (!count) return;

    var indexEl = section.querySelector("[data-testimonials-index]");
    var countEl = section.querySelector("[data-testimonials-count]");
    var prev = section.querySelector("[data-testimonials-prev]");
    var next = section.querySelector("[data-testimonials-next]");
    if (countEl) countEl.textContent = pad(count);
    var current = 0;

    function show(i, animate) {
      current = (i + count) % count;
      items.forEach(function (item, k) {
        var on = k === current;
        item.setAttribute("aria-hidden", on ? "false" : "true");
        if (on) item.removeAttribute("inert"); else item.setAttribute("inert", "");
        item.classList.remove("is-showing");
        if (on && animate && !reduce) {
          // restart the entrance
          void item.offsetWidth;
          item.classList.add("is-showing");
        }
      });
      if (indexEl) indexEl.textContent = pad(current + 1);
    }
    show(0, false);
    if (count === 1) return;

    // ---- the auto next -------------------------------------------------
    var timer = null;
    var inView = false;
    var hovered = false;
    var focused = false;
    function dwell(i) {
      var quote = items[i].querySelector(".testimonial__quote");
      var words = quote ? quote.textContent.trim().split(/\s+/).length : 0;
      return Math.max(7000, 2500 + words * 330);
    }
    // data-auto says why the clock is off, or that it runs — for the
    // inspector, not the styles
    function stop(why) {
      if (timer) { clearTimeout(timer); timer = null; }
      section.setAttribute("data-auto", why || "off");
    }
    function arm() {
      stop(reduce ? "reduced-motion" : !inView ? "out-of-view" : hovered ? "hovered" : focused ? "focused" : document.hidden ? "hidden" : "on");
      if (reduce || !inView || hovered || focused || document.hidden) return;
      timer = setTimeout(function () {
        timer = null;
        show(current + 1, true);
        arm();
      }, dwell(current));
    }
    function step(i) { show(i, true); arm(); }

    if (prev) prev.addEventListener("click", function () { step(current - 1); });
    if (next) next.addEventListener("click", function () { step(current + 1); });
    section.addEventListener("keydown", function (e) {
      if (e.target.closest && e.target.closest("input, textarea, select")) return;
      if (e.key === "ArrowLeft") { e.preventDefault(); step(current - 1); }
      if (e.key === "ArrowRight") { e.preventDefault(); step(current + 1); }
    });
    section.addEventListener("pointerenter", function () { hovered = true; stop("hovered"); });
    section.addEventListener("pointerleave", function () { hovered = false; arm(); });
    section.addEventListener("focusin", function () { focused = true; stop("focused"); });
    section.addEventListener("focusout", function (e) {
      if (section.contains(e.relatedTarget)) return;
      focused = false; arm();
    });
    document.addEventListener("visibilitychange", function () { if (document.hidden) stop("hidden"); else arm(); });
    if ("IntersectionObserver" in window) {
      new IntersectionObserver(function (entries) {
        inView = entries[0].isIntersecting;
        if (inView) arm(); else stop("out-of-view");
      }, { threshold: 0.4 }).observe(section);
    } else {
      inView = true;
      arm();
    }
  });
})();
