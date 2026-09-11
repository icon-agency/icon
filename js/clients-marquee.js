/* clients-marquee.js — the client logos: two counter-drifting, mirror-
 * linked rows. Lived in js/home-c.js (3g) until Sep 2026, when the marquee
 * went on the About page and took the whole homepage bundle — GSAP and
 * Lenis smooth scroll — with it, and Lenis then scrolled the page behind
 * the team panel; the marquee is its own behaviour now, on the SHARED
 * engine (js/strip-drift.js), and needs nothing else.
 *
 * The two rows are ONE mechanism, not two: a single shared phase drives
 * both, row 1 reading it straight and row 2 negated (the
 * .clients__scroller--reverse polarity). Everything asked of the pair falls
 * out of that one number:
 *   · at rest the rows counter-drift — one phase, two signs;
 *   · dragging EITHER row moves the other identically, mirrored, live —
 *     they cannot even drift out of sync, because there is nothing to sync;
 *   · a fling re-points the shared drift, so both rows swap direction
 *     together (each still leading with its own sign).
 * Deliberately no hover-stall — pause-on-hover was removed from this
 * marquee by request (docs/design-system-plan.md) — and no card tilt. The
 * CSS keyframes are the no-JS fallback (row 2's reverse comes from the
 * same --reverse class there), switched off via .is-js-marquee so the two
 * mechanisms never both run. Reduced motion: no drift; drag works, and the
 * mirror-link stands — it is user-initiated motion, the same footing as
 * the drag itself.
 * Drupal: Drupal.behaviors.iconClientsMarquee via `icon/clients-marquee`
 * (after `icon/strip-drift`), attached by the `clients` SDC. */
(function () {
  "use strict";

  if (!window.ICON || !window.ICON.stripDrift) return;
  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  var scrollers = Array.prototype.slice.call(document.querySelectorAll(".clients__scroller"));
  if (!scrollers.length) return;

  var rows = [];
  scrollers.forEach(function (scroller) {
    var tracks = Array.prototype.slice.call(scroller.querySelectorAll(".clients__track"));
    if (tracks.length !== 2) return;
    scroller.classList.add("is-js-marquee");
    var row = {
      el: scroller,
      tracks: tracks,
      // row 2 carries --reverse: it reads the phase negated, in the CSS
      // fallback and here alike, so the two mechanisms agree about it
      polarity: scroller.classList.contains("clients__scroller--reverse") ? -1 : 1,
      loop: 0
    };
    var measure = function () { row.loop = tracks[0].offsetWidth; };
    measure();
    window.addEventListener("resize", measure, { passive: true });
    rows.push(row);
  });
  if (!rows.length) return;

  var drift = window.ICON.stripDrift({
    reduce: reduce,
    render: function (state) {
      for (var i = 0; i < rows.length; i++) {
        var r = rows[i];
        if (!(r.loop > 0)) continue;
        var local = ((r.polarity * state.pos) % r.loop + r.loop) % r.loop;
        var x = "translate3d(" + -local + "px, 0, 0)";
        r.tracks[0].style.transform = x;
        r.tracks[1].style.transform = x;
      }
    },
    // both rows are moving under this one gesture — show it on both
    onDragStart: function () { rows.forEach(function (r) { r.el.classList.add("is-dragging"); }); },
    onDragEnd: function () { rows.forEach(function (r) { r.el.classList.remove("is-dragging"); }); }
  });
  rows.forEach(function (row) { drift.attach(row.el, row.polarity); });
})();
