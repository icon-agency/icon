/* filmstrip.js — the draggable strip of tilted cards: photos and fact
 * tiles drifting past on an endless marquee. Born as the homepage intro's
 * strip (js/home-c.js 3d, on gsap.ticker) and made a component of its own
 * when it was asked to go on other pages (user call, Sep 2026). The drift,
 * the drag, the momentum and the fling that re-points the drift are the
 * SHARED engine's (js/strip-drift.js); this file wraps one track and,
 * while a drag or its momentum is live, leans the cards with it — one
 * --strip-lean on the viewport and .is-active held, the CSS turning every
 * card to that angle and settling each back to its own tilt after
 * (components/filmstrip.css owns the rotation, the hover straighten and
 * the zoom — CSS does what CSS can).
 *
 * Reduced motion: no drift, no momentum, no lean — the strip sits still;
 * drag still moves it. Drupal: Drupal.behaviors.iconFilmstrip over
 * [data-filmstrip], after `icon/strip-drift`; the `filmstrip` SDC, its
 * cards the Photo and Fact card components. */
(function () {
  "use strict";

  if (!window.ICON || !window.ICON.stripDrift) return;
  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var LEAN_MAX = 10;   // deg, the lean at a hard fling
  var LEAN_AT = 1200;  // px/s that reaches the full lean

  document.querySelectorAll("[data-filmstrip]").forEach(function (viewport) {
    var track = viewport.querySelector(".filmstrip__track");
    if (!track || track.children.length === 0) return;

    // Duplicate the set once for a seamless wrap. Each clone carries ITS
    // ORIGINAL'S tilt, written inline: the CSS tilts by position in the row
    // (a pattern of four), and a set that is not a multiple of four would
    // put its clones a step off the pattern — every card snapping to a new
    // angle at the seam (user catch, Sep 2026: nine cards on the About
    // page; the homepage's twelve never showed it).
    var originals = Array.prototype.slice.call(track.children);
    originals.forEach(function (card) {
      var clone = card.cloneNode(true);
      clone.setAttribute("aria-hidden", "true");
      clone.style.setProperty("--r", getComputedStyle(card).getPropertyValue("--r"));
      track.appendChild(clone);
    });
    var cards = Array.prototype.slice.call(track.children);
    viewport.classList.add("is-ready");

    var loop = 0;
    var measure = function () {
      loop = cards.length > originals.length
        ? cards[originals.length].offsetLeft - cards[0].offsetLeft
        : track.scrollWidth / 2;
    };
    measure();
    window.addEventListener("resize", measure, { passive: true });
    Array.prototype.slice.call(track.querySelectorAll("img")).forEach(function (img) {
      if (!img.complete) img.addEventListener("load", measure, { once: true });
    });

    var active = false; // the lean is on
    var drift = window.ICON.stripDrift({
      reduce: reduce,
      render: function (state) {
        if (loop > 0) state.pos = ((state.pos % loop) + loop) % loop;
        track.style.transform = "translate3d(" + -state.pos + "px, 0, 0)";
        // the lean: on through a drag and while its momentum is still a
        // gesture, off once it has decayed to a drift
        var live = !reduce && (state.dragging || Math.abs(state.vel) > 60);
        if (live) {
          var v = Math.max(-LEAN_AT, Math.min(LEAN_AT, state.dragging ? state.dragVel : state.vel));
          viewport.style.setProperty("--strip-lean", (v / LEAN_AT * LEAN_MAX).toFixed(2) + "deg");
        }
        if (live !== active) {
          active = live;
          viewport.classList.toggle("is-active", active);
        }
      }
    });
    drift.attach(viewport, 1);
  });
})();
