/* filmstrip.js — the draggable strip of tilted cards: photos and fact
 * tiles drifting past on an endless marquee. Born as the homepage intro's
 * strip (js/home-c.js 3d, on gsap.ticker) and made a component of its own
 * when it was asked to go on other pages (user call, Sep 2026): no GSAP
 * here — the engine is the gallery scroller's (js/work-scroller.js), the
 * vanilla rebuild of this very marquee, minus its click-to-centre and its
 * height governor, which a strip of 4:5 cards has no use for. A deliberate
 * second copy of that engine rather than a shared module: the third
 * consumer is the moment to lift it (docs/animation.md rule 4).
 *
 *   - drifts at the site's shared strip speed (55px/s, the clients
 *     marquee's number), wrapping on a duplicated track;
 *   - drag moves it 1:1 and hands release velocity to a decaying momentum;
 *     a fling RE-POINTS the drift (velocity for a real throw, net
 *     displacement for a slow carry, a tap changes nothing);
 *   - while a drag or its momentum is live the cards LEAN together with it:
 *     this writes one --strip-lean on the viewport and holds .is-active, and
 *     the CSS turns every card to that angle, settling each back to its own
 *     tilt when the class comes off (components/filmstrip.css owns the
 *     rotation, the hover straighten and the zoom — CSS does what CSS can).
 *
 * Reduced motion: no autoplay and no lean — the strip sits still; drag still
 * moves it. Drupal: Drupal.behaviors.iconFilmstrip over [data-filmstrip];
 * the `filmstrip` SDC, its cards the Photo and Fact card components. */
(function () {
  "use strict";

  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var SPEED = 55;      // px/s — the shared strip speed
  var LEAN_MAX = 10;   // deg, the lean at a hard fling
  var LEAN_AT = 1200;  // px/s that reaches the full lean

  document.querySelectorAll("[data-filmstrip]").forEach(function (viewport) {
    var track = viewport.querySelector(".filmstrip__track");
    if (!track || track.children.length === 0) return;

    // Duplicate the set once for a seamless wrap. The clones keep the
    // nth-child tilt pattern by construction — a set of 4n cards repeats
    // exactly, any other count simply continues the sequence.
    var originals = Array.prototype.slice.call(track.children);
    originals.forEach(function (card) {
      var clone = card.cloneNode(true);
      clone.setAttribute("aria-hidden", "true");
      track.appendChild(clone);
    });
    var cards = Array.prototype.slice.call(track.children);
    viewport.classList.add("is-ready");

    // ---- the marquee engine ----------------------------------------------
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

    var pos = 0;        // marquee position, px
    var vel = 0;        // momentum px/s after a drag
    var dir = 1;        // drift direction — a fling re-points it
    var dragging = false;
    var dragVel = 0;
    var active = false; // the lean is on

    var last = performance.now();
    var tick = function (now) {
      var dt = Math.min(0.05, (now - last) / 1000);
      last = now;
      if (!dragging) {
        var auto = reduce ? 0 : SPEED * dir;
        pos += (auto + vel) * dt;
        vel *= Math.pow(0.9, dt * 60);
        if (Math.abs(vel) < 1) vel = 0;
      }
      if (loop > 0) pos = ((pos % loop) + loop) % loop;
      track.style.transform = "translate3d(" + -pos + "px, 0, 0)";

      // the lean: on through a drag and while its momentum is still a
      // gesture, off once it has decayed to a drift
      var live = !reduce && (dragging || Math.abs(vel) > 60);
      if (live) {
        var v = Math.max(-LEAN_AT, Math.min(LEAN_AT, dragging ? dragVel : vel));
        viewport.style.setProperty("--strip-lean", (v / LEAN_AT * LEAN_MAX).toFixed(2) + "deg");
      }
      if (live !== active) {
        active = live;
        viewport.classList.toggle("is-active", active);
      }
      requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);

    // ---- drag ------------------------------------------------------------
    var startX = 0, startPos = 0, lastX = 0, lastT = 0, moved = 0;
    viewport.addEventListener("pointerdown", function (e) {
      dragging = true;
      moved = 0;
      startX = lastX = e.clientX;
      startPos = pos;
      lastT = performance.now();
      dragVel = 0;
      vel = 0;
      viewport.classList.add("is-dragging");
      try { viewport.setPointerCapture(e.pointerId); } catch (err) {}
    });
    viewport.addEventListener("pointermove", function (e) {
      if (!dragging) return;
      var now = performance.now();
      var dx = e.clientX - lastX;
      moved += Math.abs(dx);
      pos = startPos - (e.clientX - startX);
      if (now - lastT > 0) dragVel = (-dx) / ((now - lastT) / 1000);
      lastX = e.clientX;
      lastT = now;
    });
    var endDrag = function () {
      if (!dragging) return;
      dragging = false;
      viewport.classList.remove("is-dragging");
      vel = reduce ? 0 : Math.max(-2200, Math.min(2200, dragVel));
      // the drift follows the drag — the clients marquee's rule verbatim
      if (Math.abs(vel) > 40) dir = vel > 0 ? 1 : -1;
      else if (Math.abs(pos - startPos) > 6) dir = pos > startPos ? 1 : -1;
      dragVel = 0;
    };
    viewport.addEventListener("pointerup", endDrag);
    viewport.addEventListener("pointercancel", endDrag);
    viewport.addEventListener("lostpointercapture", endDrag);
    // images must not start a native drag mid-gesture (Firefox ghosts)
    viewport.addEventListener("dragstart", function (e) { e.preventDefault(); });
    // a real drag must not land as a click on a card
    viewport.addEventListener("click", function (e) {
      if (moved > 6) { e.preventDefault(); e.stopPropagation(); }
    }, true);
  });
})();
