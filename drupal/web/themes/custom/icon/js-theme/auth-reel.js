/* auth-reel.js — THEME-AUTHORED (not generated): plays the homepage hero's
 * slides on the account pages' banner (page--user.html.twig .auth__reel).
 * The hero's own reel rule (js/home-c.js section 2a): a setTimeout chain,
 * a film holds for six seconds, a still for three, then the next slide
 * cross-fades in (src/auth.css .auth__slide) and the client name swaps the
 * way the hero's label does (.is-out, then the new text). One slide just
 * loops. Reduced motion: the first slide stays, nothing turns. Lives in
 * js-theme/ because js/ is generated from the repo-root behaviours. */
((Drupal, once) => {
  var HOLD_IMAGE = 3000, HOLD_VIDEO = 6000, XFADE = 100;

  Drupal.behaviors.iconAuthReel = {
    attach(context) {
      once("auth-reel", "[data-auth-reel]", context).forEach((reel) => {
        var slides = Array.prototype.slice.call(reel.querySelectorAll(".auth__slide"));
        var label = document.querySelector("[data-auth-client]");
        var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        if (!slides.length) return;

        var index = Math.max(0, slides.findIndex(function (s) { return s.classList.contains("is-active"); }));

        function filmOf(slide) { return slide.querySelector("video"); }
        function holdFor(slide) { return filmOf(slide) ? HOLD_VIDEO : HOLD_IMAGE; }

        function play(slide) {
          var film = filmOf(slide);
          if (!film) return;
          try { film.currentTime = 0; } catch (e) { /* not seekable yet */ }
          var p = film.play();
          if (p && p.catch) p.catch(function () { /* autoplay refused: the first frame stays */ });
        }

        function setLabel(text) {
          if (!label) return;
          label.classList.add("is-out");
          window.setTimeout(function () {
            label.textContent = text;
            label.classList.remove("is-out");
          }, 150);
        }

        function activate(i) {
          var leaving = slides[index];
          index = i;
          var slide = slides[index];
          slides.forEach(function (s) { s.classList.toggle("is-active", s === slide); });
          setLabel(slide.getAttribute("data-client") || "");
          play(slide);
          var old = leaving !== slide && filmOf(leaving);
          // the old film stops once it has faded out under the new slide
          if (old) window.setTimeout(function () { old.pause(); }, XFADE + 200);
        }

        if (reduce) {
          // the first slide is enough; a lone film would loop, so still it
          slides.forEach(function (s) { var f = filmOf(s); if (f) f.pause(); });
          return;
        }
        if (slides.length < 2) { play(slides[index]); return; }

        // setTimeout chain, not setInterval: each slide holds for its own
        // duration, so a film sits longer than a still.
        var next = function () {
          activate((index + 1) % slides.length);
          window.setTimeout(next, holdFor(slides[index]) + XFADE);
        };
        play(slides[index]);
        window.setTimeout(next, holdFor(slides[index]) + XFADE);

        // a tab that comes back to the front resumes the film it is showing
        document.addEventListener("visibilitychange", function () {
          if (document.visibilityState !== "visible") return;
          var film = filmOf(slides[index]);
          if (film && film.paused) film.play().catch(function () {});
        });
      });
    },
  };
})(Drupal, once);
