/* hero-loader.js — builds and lights the hero's kinetic-text loading screen.
 *
 * WHY THIS IS ITS OWN FILE, LOADED EARLY. templates/homeC.html pulls GSAP,
 * SplitText and Lenis from two CDNs as blocking scripts, and js/home-c.js sits
 * behind all three. A loading screen lit from there only appears once those
 * round-trips finish — so on a slow or blocked CDN the visitor's first frame is
 * a bare blue rectangle with no copy, which is the worst possible version of
 * this. This file is local and is loaded BEFORE them, so the box is built, lit
 * and running on the first frame regardless of what the CDNs are doing.
 *
 * It owns three things and nothing else:
 *   1. lighting the loading screen and marking the hero as booting,
 *   2. marking the frame the blue finishes covering the screen, which is the
 *      page's theme event (header + counter flip to white ink there),
 *   3. handing js/home-c.js the start time the rest of the show hangs off.
 *
 * IT USED TO BUILD A ROOM. A perspective tunnel of marquees played under the
 * pile while the media loaded — 16 rows of type on a four-step depth ramp,
 * latterly one device layer per side. Removed Aug 2026 (user call: "remove the
 * kinetic from the load, keep it simple"); the loader and the pops stay, so the
 * load is now the cards popping onto their pile over a clean ground, with the
 * (0)-(100) counter running in the client name's spot.
 *
 * Both rooms are preserved and still running in experiments/hero-backup.html
 * (as type) and experiments/hero-device.html (as the device), each with its own
 * frozen copy of this file — which is why none of that geometry is kept here.
 *
 * The box is decorative (aria-hidden) and never present without JS, so nothing
 * is lost from the no-JS baseline: the CSS keeps it display:none there.
 */
(function () {
  "use strict";

  var box = document.querySelector("[data-text-box]");
  if (!box) return;

  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // Nothing here is vestibular any more — the tunnel that justified this guard
  // is gone. It stays because under reduced motion js/home-c.js skips the whole
  // boot and goes straight to the reel, so there is no loading phase to cover:
  // lighting a screen torn down on the same frame would only flash.
  if (reduce) return;

  /* ---- 3. light it, and publish the clock -------------------------------- */

  box.classList.add("is-lit");

  // Mark the boot so home-c.js can clean up whichever way it ends.
  var heroEl = box.closest("[data-hero]");
  if (heroEl) heroEl.classList.add("is-booting");

  // THE THEME EVENT. The loading screen is a blue square that grows to cover
  // the viewport, so for its first half-second the page ground is still
  // showing around it and dark ink is correct; the moment it lands the whole
  // screen is --color-shader-blue and the header and the build counter have to
  // be white. .is-covered is that moment, and home-c.js's header sync (2b)
  // and .hero__count (home-c.css) both key off it.
  //
  // Taken from the pop's own animationend rather than a timer: the duration
  // lives in text-box.css, and a second copy of it here would be a number free
  // to drift away from the animation it is meant to describe. If the animation
  // never runs the class never lands, which degrades to exactly the previous
  // behaviour — dark ink until .is-live — rather than to white-on-white.
  if (heroEl) {
    box.addEventListener("animationend", function () {
      heroEl.classList.add("is-covered");
    }, { once: true });
  }

  // T0 is the anchor the whole show hangs off — deliberately NOT
  // performance.timeOrigin, which would include the HTML, the stylesheet and
  // two font services, and would silently eat the visible animation on a slow
  // load.
  //
  // Published SYNCHRONOUSLY, right after the class that starts the copy
  // moving. An earlier version waited a double rAF to mark the exact frame of
  // first motion, which was more precise and completely useless: js/home-c.js
  // reads this while parsing, long before those frames run, so it always found
  // undefined and fell back to its own clock — the box and the hero were never
  // sharing an anchor at all. Traced: "loaderT0=FALLBACK" on every load.
  window.__heroLoaderT0 = performance.now();

  // Teardown is CSS-driven off .is-live (see home-c.css) so no path can forget
  // it; this only reclaims the DOM afterwards.
  window.__heroLoaderTeardown = function () {
    var idle = window.requestIdleCallback || function (f) { return setTimeout(f, 200); };
    idle(function () {
      if (box.parentNode) box.parentNode.removeChild(box);
    });
  };

  // Backstop: the box should never be left sitting behind an opaque reel.
  // CONDITIONAL on the hero actually being live — an unconditional
  // timer here once removed the loading screen mid-show on a slow load, which
  // is the one moment it exists for. While the show is still running the box
  // is doing its job; check again later.
  var backstop = function () {
    var hero = box.closest("[data-hero]");
    if (hero && hero.classList.contains("is-live")) {
      if (window.__heroLoaderTeardown) window.__heroLoaderTeardown();
    } else if (box.parentNode) {
      setTimeout(backstop, 6000);
    }
  };
  setTimeout(backstop, 12000);
})();
