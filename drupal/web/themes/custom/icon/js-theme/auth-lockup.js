/* auth-lockup.js — THEME-AUTHORED (not generated): draws the "Make what
 * matters" lockup on the account pages. A transcription of js/home-c.js
 * section 2d — the same box-first draw, the same 0.045 letter stagger, the
 * same per-letter random cross-fade from outline to fill (STEP 0.03, FADE
 * 0.4) — minus the hero's scroll gating: there is no takeover here, so it
 * plays once when the page lands. Everything degrades to "already drawn":
 * no GSAP, no plugin, or reduced motion leave the CSS resting state (fills
 * opaque, strokes transparent) untouched. Lives in js-theme/ because js/ is
 * generated from the repo-root behaviours (scripts/theme-js.mjs). */
((Drupal, once) => {
  Drupal.behaviors.iconAuthLockup = {
    attach(context) {
      once("auth-lockup", "[data-lockup-draw]", context).forEach((svg) => {
        var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        var gsapOk = typeof window.gsap !== "undefined";
        var drawOk = gsapOk && typeof window.DrawSVGPlugin !== "undefined";
        if (drawOk) { try { window.gsap.registerPlugin(window.DrawSVGPlugin); } catch (e) { drawOk = false; } }
        if (!drawOk || reduce) return;

        var box = svg.querySelector(".hero__lockup-box");
        var strokes = Array.prototype.slice.call(svg.querySelectorAll(".hero__lockup-stroke"));
        var letters = Array.prototype.slice.call(svg.querySelectorAll(".hero__lockup-letter"));
        if (!box || !strokes.length || !letters.length) return;

        // Init to the OPPOSITE of the resting state: nothing drawn, no fill.
        window.gsap.set([box].concat(strokes), { drawSVG: "0%" });
        window.gsap.set(svg.querySelectorAll(".hero__lockup-fill"), { opacity: 0 });
        window.gsap.set(strokes, { opacity: 1 });

        var tl = window.gsap.timeline({ delay: 0.35 });
        tl.to(box, { drawSVG: "100%", duration: 0.9, ease: "power2.inOut" })
          .to(strokes, { drawSVG: "100%", duration: 0.7, ease: "power2.out", stagger: 0.045 }, "-=0.35")
          .addLabel("fill", "-=0.15");

        // Every letter on its own timeline, in a random order fixed at build:
        // its fill fades up as its own outline fades out, welded together by
        // sharing one slot.
        var STEP = 0.03;
        var FADE = 0.4;
        var slots = letters.map(function (_, i) { return i; });
        for (var si = slots.length - 1; si > 0; si--) {
          var sj = Math.floor(Math.random() * (si + 1));
          var tmp = slots[si]; slots[si] = slots[sj]; slots[sj] = tmp;
        }
        letters.forEach(function (g, i) {
          var at = "fill+=" + (slots[i] * STEP).toFixed(3);
          tl.to(g.querySelector(".hero__lockup-fill"), { opacity: 1, duration: FADE, ease: "power1.out" }, at)
            .to(g.querySelectorAll(".hero__lockup-stroke"), { opacity: 0, duration: FADE, ease: "power1.out" }, at);
        });
      });
    },
  };
})(Drupal, once);
