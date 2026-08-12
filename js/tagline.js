/* tagline.js — the home tagline section:
 *   1. per-line scroll reveal (staggered)
 *   2. desktop fit-to-width sizing of the headline (longest line fills its box)
 *   3. cursor-pop work images (GSAP)
 * All guarded for reduced-motion / no GSAP / no IO.
 * Drupal: Drupal.behaviors.iconTagline (depends on gsap). */
(function () {
  "use strict";

  var section = document.querySelector("[data-tagline]");
  if (!section) return;

  var heading = section.querySelector("[data-tagline-heading]");
  var pops = section.querySelector("[data-tagline-pops]");
  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // ---- 1. Per-line reveal -------------------------------------------------
  if ("IntersectionObserver" in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          section.classList.add("is-visible");
          io.disconnect();
        }
      });
    }, { threshold: 0.1 });
    io.observe(section);
  } else {
    section.classList.add("is-visible");
  }

  // ---- 2. Fit the longest line to the container width on desktop ----------
  var LONGEST = "behaviours, and grow market share in the";
  function fitHeading() {
    if (!heading) return;
    if (window.innerWidth < 1024) {
      heading.style.fontSize = ""; // fall back to the CSS clamp on mobile
      return;
    }
    var cs = getComputedStyle(heading);
    var probe = document.createElement("span");
    probe.style.cssText =
      "visibility:hidden;position:absolute;white-space:nowrap;font-weight:500;" +
      "letter-spacing:0;font-family:" + cs.fontFamily;
    probe.textContent = LONGEST;
    document.body.appendChild(probe);

    var min = 16, max = 120, best = min;
    var width = heading.clientWidth;
    while (min <= max) {
      var mid = (min + max) >> 1;
      probe.style.fontSize = mid + "px";
      if (probe.offsetWidth <= width) { best = mid; min = mid + 1; }
      else { max = mid - 1; }
    }
    document.body.removeChild(probe);
    heading.style.fontSize = best * 0.95 + "px"; // 95% for breathing room
  }
  fitHeading();
  var fitTick = false;
  window.addEventListener("resize", function () {
    if (fitTick) return;
    fitTick = true;
    window.requestAnimationFrame(function () { fitHeading(); fitTick = false; });
  });

  // ---- 3. Cursor pops — distance-gated image trail with momentum ----------
  if (reduce || typeof window.gsap === "undefined" || !pops) return;

  var gsap = window.gsap;
  var hasInertia = typeof window.InertiaPlugin !== "undefined";
  if (hasInertia) gsap.registerPlugin(window.InertiaPlugin);

  var IMAGES = [
    "/assets/work/icanquit-app.png",
    "/assets/work/money-man-news.png",
    "/assets/work/swimming-therapy.png",
    "/assets/work/health-services-campaign.png",
    "/assets/work/space-agency-website.png",
    "/assets/work/pilates-whittlesea.png"
  ];
  var SIZE = 130;        // px
  var SPACING = 72;      // px of pointer travel between trail images
  var MAX = 16;          // max concurrent trail images
  var HOLD = 0.6;        // s fully visible before fading (lets the last read)
  var FADE = 0.6;        // s fade-out
  var VEL_FACTOR = 0.1;  // fraction of pointer velocity handed to the drift
  var VEL_MAX = 1100;    // px/s cap (keeps fast moves from flinging too far)

  IMAGES.forEach(function (src) { var i = new Image(); i.src = src; });

  var pool = [], active = 0, idx = 0;
  var last = null, lastTime = 0, accum = 0;

  function recycle(node) {
    node.remove();
    pool.push(node);
    active = Math.max(0, active - 1);
  }

  function clampVel(v) { return Math.max(-VEL_MAX, Math.min(VEL_MAX, v)); }

  function spawn(x, y, vx, vy) {
    if (active >= MAX) return;

    var img = pool.pop() || document.createElement("img");
    img.className = "tagline__pop";
    img.alt = "";
    img.setAttribute("aria-hidden", "true");
    img.decoding = "async";
    img.src = IMAGES[idx++ % IMAGES.length];

    var rect = pops.getBoundingClientRect();
    img.style.width = SIZE + "px";
    img.style.height = SIZE + "px";
    img.style.left = (x - rect.left - SIZE / 2) + "px";
    img.style.top = (y - rect.top - SIZE / 2) + "px";
    pops.appendChild(img);
    active++;

    gsap.killTweensOf(img);
    gsap.set(img, { x: 0, y: 0, scale: 0.6, autoAlpha: 0, rotation: gsap.utils.random(-6, 6) });
    gsap.to(img, { scale: 1, autoAlpha: 1, duration: 0.3, ease: "power3.out" });

    // Carry the pointer's momentum, then decelerate to a natural rest.
    var dvx = clampVel(vx * VEL_FACTOR);
    var dvy = clampVel(vy * VEL_FACTOR);
    if (hasInertia) {
      gsap.to(img, { inertia: { x: { velocity: dvx }, y: { velocity: dvy }, resistance: 520 } });
    } else {
      gsap.to(img, { x: dvx * 0.25, y: dvy * 0.25, duration: 0.9, ease: "power2.out" });
    }

    gsap.to(img, {
      autoAlpha: 0, scale: 0.92, duration: FADE, delay: HOLD, ease: "power2.in",
      onComplete: function () { recycle(img); }
    });
  }

  function pointer(e) {
    if (e.touches) {
      if (!e.touches[0]) return null;
      return { x: e.touches[0].clientX, y: e.touches[0].clientY };
    }
    return { x: e.clientX, y: e.clientY };
  }

  function onMove(e) {
    var p = pointer(e);
    if (!p) return;

    var r = pops.getBoundingClientRect();
    if (p.x < r.left || p.x > r.right || p.y < r.top || p.y > r.bottom) { last = null; return; }

    var now = performance.now();
    if (last === null) { last = p; lastTime = now; return; }

    var dx = p.x - last.x, dy = p.y - last.y;
    var dt = now - lastTime;
    var dist = Math.sqrt(dx * dx + dy * dy);
    var vx = dt > 0 ? (dx / dt) * 1000 : 0;
    var vy = dt > 0 ? (dy / dt) * 1000 : 0;

    accum += dist;
    var steps = Math.floor(accum / SPACING);
    if (steps > 0) {
      steps = Math.min(steps, 4); // cap bursts on a fast flick
      for (var i = 1; i <= steps; i++) {
        var t = i / steps;
        spawn(last.x + dx * t, last.y + dy * t, vx, vy);
      }
      accum -= steps * SPACING;
    }
    last = p;
    lastTime = now;
  }

  window.addEventListener("mousemove", onMove, { passive: true });
  window.addEventListener("touchmove", onMove, { passive: true });
})();
