/* work-scroller.js — the gallery scroller (born on The Athlete's Foot
 * posters, Sep 2026). The homepage intro filmstrip's marquee, re-built
 * vanilla (no GSAP on article pages) and WITHOUT the tilts:
 *
 *   - drifts at the site's shared strip speed (55px/s, the intro strip and
 *     clients marquee number), wrapping on a duplicated track;
 *   - drag moves it 1:1 and hands release velocity to a decaying momentum;
 *     a fling RE-POINTS the drift (velocity for a real throw, net
 *     displacement for a slow carry, a tap changes nothing) — the clients
 *     marquee's rule verbatim;
 *   - clicking a card glides it to the viewport centre, zooms it a step
 *     (.is-focused) and PAUSES the drift; clicking it again, clicking the
 *     band, or dragging resumes; clicking another card re-centres on it;
 *   - the card height considers the browser viewport so the full image is
 *     always visible, capped by the TALLEST image's natural height (small
 *     clippings never upscale) — whichever is smaller governs.
 *
 * Reduced motion: no autoplay — the strip sits still, drag and the
 * click-to-centre still work (centring jumps rather than glides).
 * Drupal: Drupal.behaviors.iconWorkScroller over [data-work-scroller];
 * a work_scroller paragraph — media items + optional ground colour. */
(function () {
  "use strict";

  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var SPEED = 55; // px/s — the shared strip speed

  var scrollers = document.querySelectorAll("[data-work-scroller]");
  scrollers.forEach(function (viewport) {
    var track = viewport.querySelector(".work-scroller__track");
    if (!track || track.children.length === 0) return;

    // Duplicate the set once for a seamless wrap.
    var originals = Array.prototype.slice.call(track.children);
    originals.forEach(function (card) {
      var clone = card.cloneNode(true);
      clone.setAttribute("aria-hidden", "true");
      clone.setAttribute("tabindex", "-1");
      track.appendChild(clone);
    });
    var cards = Array.prototype.slice.call(track.children);
    viewport.classList.add("is-ready");

    // ---- height: min(tallest natural image, viewport allowance) ----------
    var imgs = Array.prototype.slice.call(track.querySelectorAll("img"));
    var setHeight = function () {
      var tallest = 0, maxRatio = 0;
      imgs.forEach(function (img) {
        if (img.naturalHeight > tallest) tallest = img.naturalHeight;
        if (img.naturalHeight > 0) maxRatio = Math.max(maxRatio, img.naturalWidth / img.naturalHeight);
      });
      var pad = parseFloat(getComputedStyle(viewport).paddingTop) || 0;
      // three governors, smallest wins: the tallest image's natural height
      // (small clippings never upscale), the viewport height with the
      // band's padding and nav breathing — and the viewport WIDTH via the
      // WIDEST item's aspect (user call: on portrait phones the width
      // governs, so the full poster is visible edge to edge).
      var availH = window.innerHeight - pad * 2 - 96;
      var availW = viewport.clientWidth - pad * 2;
      var byWidth = maxRatio > 0 ? availW / maxRatio : Infinity;
      var h = Math.max(160, Math.min(tallest || availH, availH, byWidth));
      viewport.style.setProperty("--ws-card-h", h + "px");
    };
    imgs.forEach(function (img) {
      if (!img.complete) img.addEventListener("load", function () { setHeight(); measure(); }, { once: true });
    });

    // ---- the marquee engine ----------------------------------------------
    var loop = 0;
    var measure = function () {
      loop = cards.length > originals.length
        ? cards[originals.length].offsetLeft - cards[0].offsetLeft
        : track.scrollWidth / 2;
    };
    setHeight();
    measure();
    window.addEventListener("resize", function () { setHeight(); measure(); }, { passive: true });

    var pos = 0;        // marquee position, px
    var vel = 0;        // momentum px/s after a drag
    var dir = 1;        // drift direction — a fling re-points it
    var dragging = false;
    var dragVel = 0;
    var focused = null; // the centred card, while the drift is paused
    var glide = null;   // {from, to, start, dur} while centring

    var unfocus = function () {
      if (!focused) return;
      focused.classList.remove("is-focused");
      focused = null;
      glide = null;
      viewport.classList.remove("is-focused");
    };

    var last = performance.now();
    var tick = function (now) {
      var dt = Math.min(0.05, (now - last) / 1000);
      last = now;
      if (glide) {
        var t = Math.min(1, (now - glide.start) / glide.dur);
        var e = 1 - Math.pow(1 - t, 3); // easeOutCubic
        pos = glide.from + (glide.to - glide.from) * e;
        if (t >= 1) glide = null;
      } else if (!dragging && !focused) {
        var auto = reduce ? 0 : SPEED * dir;
        pos += (auto + vel) * dt;
        vel *= Math.pow(0.9, dt * 60);
        if (Math.abs(vel) < 1) vel = 0;
      }
      if (loop > 0 && !glide) pos = ((pos % loop) + loop) % loop;
      track.style.transform = "translate3d(" + -pos + "px, 0, 0)";
      requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);

    // ---- centring --------------------------------------------------------
    var centreOn = function (card) {
      unfocus();
      focused = card;
      card.classList.add("is-focused");
      viewport.classList.add("is-focused");
      var target = card.offsetLeft + card.offsetWidth / 2 - viewport.clientWidth / 2;
      // nearest wrapped equivalent of the target to the current position
      var norm = loop > 0 ? ((pos % loop) + loop) % loop : pos;
      var best = target, bestD = Infinity;
      [-1, 0, 1].forEach(function (k) {
        var cand = target + k * loop;
        if (Math.abs(cand - norm) < bestD) { bestD = Math.abs(cand - norm); best = cand; }
      });
      pos = norm;
      vel = 0;
      glide = reduce
        ? { from: best, to: best, start: performance.now(), dur: 1 }
        : { from: norm, to: best, start: performance.now(), dur: 650 };
      if (reduce) pos = best;
    };

    // ---- taps ------------------------------------------------------------
    // Selection is decided in the POINTER path, not the click event: with
    // setPointerCapture the browser retargets the synthesized click at the
    // viewport, so the card is never the click target — clicks felt dead
    // (user catch). The card is recorded at pointerdown, and a gesture that
    // stayed under the tap slop acts on it at pointerup.
    var TAP_SLOP = 10; // px of accumulated movement that still counts as a tap
    var tap = function (card) {
      if (!card) { unfocus(); return; }            // the band: resume
      if (card === focused) { unfocus(); return; } // the centred card: resume
      centreOn(card);                              // any card: centre and pause
    };

    // ---- drag ------------------------------------------------------------
    var startX = 0, startPos = 0, lastX = 0, lastT = 0, moved = 0;
    var downCard = null;
    viewport.addEventListener("pointerdown", function (e) {
      dragging = true;
      moved = 0;
      glide = null;
      downCard = e.target.closest(".work-scroller__card");
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
    var endDrag = function (e) {
      if (!dragging) return;
      dragging = false;
      viewport.classList.remove("is-dragging");
      if (moved <= TAP_SLOP && e && e.type === "pointerup") {
        // a tap (mouse click or touch tap alike) — act on the pressed card
        tap(downCard);
        downCard = null;
        dragVel = 0;
        return;
      }
      unfocus(); // a real drag restarts the drift
      vel = reduce ? 0 : Math.max(-2200, Math.min(2200, dragVel));
      // the drift follows the drag — the clients marquee's rule verbatim
      if (Math.abs(vel) > 40) dir = vel > 0 ? 1 : -1;
      else if (Math.abs(pos - startPos) > 6) dir = pos > startPos ? 1 : -1;
      dragVel = 0;
      downCard = null;
    };
    viewport.addEventListener("pointerup", endDrag);
    viewport.addEventListener("pointercancel", endDrag);
    // a lost capture (tab switch, gesture stolen mid-drag) must not leave
    // the strip stuck in the dragging state
    viewport.addEventListener("lostpointercapture", endDrag);
    // images must not start a native drag mid-gesture (Firefox ghosts)
    viewport.addEventListener("dragstart", function (e) { e.preventDefault(); });

    // ---- keyboard --------------------------------------------------------
    // The pointer path owns real clicks; the click event is kept ONLY for
    // keyboard activation (Enter/Space on a card button fires click with
    // detail 0). Pointer-born clicks are swallowed — already handled above.
    viewport.addEventListener("click", function (e) {
      if (e.detail > 0) { e.preventDefault(); e.stopPropagation(); return; }
      tap(e.target.closest(".work-scroller__card"));
    });
  });
})();
