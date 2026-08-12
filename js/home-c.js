/* home-c.js — Home C prototype behaviours (zypsy-inspired). One IIFE:
 *   1. Lenis smooth (momentum) scroll — off under reduced motion / if absent.
 *   2. Hero load sequence: masked "Make what matters" reveal, image zoom-settle,
 *      PiP fade-in (CSS transitions keyed off .is-ready).
 *   3. Image clip + scale reveals on scroll-in (IntersectionObserver → .is-revealed).
 *   4. GSAP SplitText line-mask text reveals — a per-element timeline fired by an
 *      IntersectionObserver (no ScrollTrigger, matching the project's approach).
 *
 * All reduced-motion guarded; no-JS / missing-lib safe (text + images render
 * plainly, images just appear). Drupal: would become Drupal.behaviors.iconHomeC.
 */
(function () {
  "use strict";

  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var hasIO = "IntersectionObserver" in window;
  var gsapOk = typeof window.gsap !== "undefined";
  var splitOk = gsapOk && typeof window.SplitText !== "undefined";
  if (splitOk) { try { window.gsap.registerPlugin(window.SplitText); } catch (e) {} }

  // ---- 1. Lenis smooth scroll --------------------------------------------
  var USE_LENIS = true;
  if (USE_LENIS && !reduce && typeof window.Lenis !== "undefined") {
    try {
      var lenis = new window.Lenis({ lerp: 0.1, smoothWheel: true });
      (function raf(t) { lenis.raf(t); window.requestAnimationFrame(raf); })();
    } catch (e) {}
  }

  // ---- 2. Hero load sequence ---------------------------------------------
  var hero = document.querySelector("[data-hero]");
  if (hero) {
    var hImg = hero.querySelector(".homec-hero__img");
    var ready = function () { hero.classList.add("is-ready"); };
    if (!hImg || hImg.complete) {
      // one extra frame so the pre-.is-ready state paints first (no flash of end state)
      window.requestAnimationFrame(function () { window.requestAnimationFrame(ready); });
    } else {
      hImg.addEventListener("load", ready);
      hImg.addEventListener("error", ready);
      window.setTimeout(ready, 2500); // safety net if the image never fires
    }
  }

  // ---- 2b. Header inversion over the dark hero ---------------------------
  // Force the fixed header to light-on-dark (.is-over-hero) while the hero is
  // behind it, so the wordmark + nav read on the dark media in any theme, then
  // revert to the page theme past the hero. The header starts inverted in markup
  // (the page opens on the hero — no load flash); this just toggles it off once
  // the hero's bottom clears the header, and back on when you scroll up. The
  // colour cross-fade is CSS (transitions on the header). Header height ≈ 80px.
  var siteHeader = document.querySelector(".site-header");
  if (siteHeader && hero) {
    var setOverHero = function () {
      siteHeader.classList.toggle("is-over-hero", hero.getBoundingClientRect().bottom > 80);
    };
    if (hasIO) {
      var heroHeaderIO = new IntersectionObserver(function (entries) {
        siteHeader.classList.toggle("is-over-hero", entries[0].isIntersecting);
      }, { rootMargin: "-80px 0px 0px 0px", threshold: 0 });
      heroHeaderIO.observe(hero);
    } else {
      window.addEventListener("scroll", setOverHero, { passive: true });
      setOverHero();
    }
  }

  // ---- 2c. Headline exit — TRIGGERED (not scroll-scrubbed) ---------------
  // Once you scroll past a small threshold, add .is-exiting on the hero: the CSS
  // then plays the whole "Make what matters" lockup out word-by-word IN FULL
  // (completing on its own even if you stop scrolling), and back in when you
  // return above the threshold. Skipped under reduced motion (headline stays).
  if (hero && !reduce) {
    var exitThreshold = function () { return Math.max(60, window.innerHeight * 0.12); };
    var syncHeroExit = function () {
      hero.classList.toggle("is-exiting", window.scrollY > exitThreshold());
    };
    window.addEventListener("scroll", syncHeroExit, { passive: true });
    syncHeroExit();
  }

  // ---- 3. Image clip + scale reveals -------------------------------------
  var figs = Array.prototype.slice.call(document.querySelectorAll("[data-reveal-img]"));
  if (figs.length) {
    if (!hasIO) {
      figs.forEach(function (el) { el.classList.add("is-revealed"); });
    } else {
      var iIO = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (!e.isIntersecting) return;
          e.target.classList.add("is-revealed");
          iIO.unobserve(e.target);
        });
      }, { threshold: 0.18, rootMargin: "0px 0px -8% 0px" });
      figs.forEach(function (el) { iIO.observe(el); });
    }
  }

  // ---- 4. SplitText line-mask text reveals -------------------------------
  var texts = Array.prototype.slice.call(document.querySelectorAll("[data-reveal-text]"));
  if (texts.length && splitOk && !reduce && hasIO) {
    var setup = function () {
      texts.forEach(function (el) {
        var split;
        try {
          split = new window.SplitText(el, { type: "lines", mask: "lines", linesClass: "hc-line" });
        } catch (e) { return; } // leave the text as authored on failure
        window.gsap.set(split.lines, { yPercent: 115 });
        var tIO = new IntersectionObserver(function (entries) {
          entries.forEach(function (e) {
            if (!e.isIntersecting) return;
            tIO.unobserve(e.target);
            window.gsap.to(split.lines, {
              yPercent: 0,
              duration: 0.9,
              ease: "power3.out",
              stagger: 0.09
            });
          });
        }, { threshold: 0.25 });
        tIO.observe(el);
      });
    };
    // Wait for web fonts so line-wrapping is measured against the display font.
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(setup);
    else setup();
  }
})();
