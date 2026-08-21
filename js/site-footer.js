/* site-footer.js — the global footer's motion. One IIFE, two concerns:
 *
 * 1. LOAD REVEAL — adds .is-revealed the first time the panel enters the
 *    viewport; CSS owns the staggered fade-up and the lockup's word rise.
 *    Without JS (or without IntersectionObserver) the footer is simply
 *    visible: the hidden state lives behind .js-animations, exactly as
 *    reveal.css and tagline.css do it.
 *
 * 2. THE FILMS IN THE MARK — cross-fades the hero's banner videos behind the
 *    logo's mask. Videos are the expensive part of this footer, so exactly one
 *    decodes at a time and only while the panel is on screen; they ship
 *    preload="none", which means nothing is even fetched until the first
 *    play() here. A backgrounded tab stops them too.
 *
 * 3. SCROLL-VELOCITY SKEW — samples scroll speed and writes ONE custom
 *    property, --footer-skew, on the footer per frame; the blocks inside
 *    shear by it (the lockup harder than the rest). Velocity is not
 *    expressible as a CSS scroll timeline — those are position-driven — so
 *    this is the one part that must be scripted.
 *
 * Why its own file rather than a section of home-c.js: the footer is global
 * and ships on pages that never load the homepage bundle (and have no GSAP),
 * so its behaviour has to travel with it. The velocity sampler below is
 * deliberately the same shape as the one in news.js — if a third consumer
 * appears, that is the moment to lift it into a shared primitive rather than
 * copy it again.
 *
 * Drupal: Drupal.behaviors.iconFooter, attached via the `icon/site-footer`
 * library alongside the footer region template.
 */
(function () {
  "use strict";

  var footer = document.querySelector(".site-footer");
  if (!footer) return;

  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---- 1. load reveal ---------------------------------------------------- */

  if (reduce || !("IntersectionObserver" in window)) {
    footer.classList.add("is-revealed");
  } else {
    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (e) {
          if (!e.isIntersecting) return;
          e.target.classList.add("is-revealed");
          io.unobserve(e.target);
        });
      },
      // a sliver is enough: the panel is taller than most viewports, so
      // waiting for a percentage of it would fire late or never
      { rootMargin: "0px 0px -10% 0px" }
    );
    io.observe(footer);
  }

  /* ---- 2. the films in the mark ------------------------------------------ */

  (function () {
    var stage = footer.querySelector(".site-footer__logo");
    if (!stage) return;
    var slides = [].slice.call(stage.querySelectorAll(".site-footer__slide"));
    if (!slides.length) return;

    // The hero's own video hold (js/home-c.js: HOLD_VIDEO), so the same
    // footage keeps the same pace in both places. A setTimeout chain rather
    // than setInterval, also as the hero does it — a slide that stalls should
    // delay the next one, not have it fire underneath.
    var HOLD = 6000;
    var i = 0, timer = 0, live = false;

    function show(el) {
      el.classList.add("is-current");
      try { el.currentTime = 0; } catch (e) {} // start clean, not mid-loop
      if (reduce) return; // reduced motion holds a first frame
      var p = el.play();
      if (p && p.catch) p.catch(function () {}); // autoplay policy: ignore
    }

    function step() {
      var prev = slides[i];
      i = (i + 1) % slides.length;
      prev.classList.remove("is-current");
      prev.pause();
      show(slides[i]);
      timer = setTimeout(step, HOLD);
    }

    function start() {
      if (live) return;
      live = true;
      show(slides[i]);
      if (!reduce && slides.length > 1) timer = setTimeout(step, HOLD);
    }

    function stop() {
      live = false;
      clearTimeout(timer);
      timer = 0;
      slides.forEach(function (v) { v.pause(); });
    }

    if ("IntersectionObserver" in window) {
      new IntersectionObserver(function (entries) {
        entries[0].isIntersecting ? start() : stop();
      }, { rootMargin: "10% 0px" }).observe(stage);
    } else {
      start();
    }

    document.addEventListener("visibilitychange", function () {
      if (document.hidden) stop();
    });
  })();

  /* ---- 3. scroll-velocity skew ------------------------------------------- */

  if (reduce) return;

  var MAX = 1.8; // deg — past ~2 the shear stops reading as motion and starts
                 // reading as a broken layout, and the lockup multiplies it
  var VEL_AT_MAX = 2600; // scroll px/s that reaches the full shear
  var clamp = function (v, lo, hi) { return v < lo ? lo : v > hi ? hi : v; };

  // idle the whole thing while the footer is off screen
  var inView = !("IntersectionObserver" in window);
  if (!inView) {
    new IntersectionObserver(function (entries) {
      inView = entries[0].isIntersecting;
    }, { rootMargin: "20% 0px" }).observe(footer);
  }

  var lastY = window.scrollY, lastT = performance.now();
  var vel = 0, cur = 0, raf = 0, prevT = 0;

  var tick = function (now) {
    raf = 0;
    var dt = Math.min(0.05, (now - (prevT || now)) / 1000);
    prevT = now;
    // decay: the scroll event stops firing, so nothing else zeroes this
    vel *= Math.pow(0.86, dt * 60);
    if (Math.abs(vel) < 20) vel = 0;
    var target = inView ? clamp((vel / VEL_AT_MAX) * MAX, -MAX, MAX) : 0;
    cur += (target - cur) * Math.min(1, dt * 7);
    if (Math.abs(cur) < 0.01 && !vel) cur = 0;
    footer.style.setProperty("--footer-skew", cur.toFixed(3) + "deg");
    if (vel || Math.abs(cur) > 0.005) raf = requestAnimationFrame(tick);
    else prevT = 0; // settled — the loop stops until the next scroll
  };

  window.addEventListener("scroll", function () {
    var now = performance.now();
    var dt = (now - lastT) / 1000;
    if (dt > 0) vel = clamp((window.scrollY - lastY) / dt, -12000, 12000);
    lastY = window.scrollY;
    lastT = now;
    if (!raf) raf = requestAnimationFrame(tick);
  }, { passive: true });
})();
