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
 *   1. building the room (28 rows: a 7-step depth ramp on each of 4 panels),
 *   2. timing it so the copy travels at one constant speed and the whole
 *      composition has arrived before the hero's cards start popping,
 *   3. handing js/home-c.js a start time to hang the rest of the show off.
 *
 * The rows are built here rather than authored into the template because they
 * are pure geometry — a Twig loop in Drupal, per docs/drupal-handoff.md — and
 * 112 nodes of hand-written markup would be a liability in a page anyone edits.
 * The box is decorative (aria-hidden) and never present without JS, so nothing
 * is lost from the no-JS baseline: the CSS keeps it display:none there.
 */
(function () {
  "use strict";

  var box = document.querySelector("[data-text-box]");
  if (!box) return;

  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // A tunnel of 28 marquees receding through a perspective camera is exactly
  // the vestibular trigger the media query exists for. Not built, not lit.
  if (reduce) return;

  /* ---- 1. the room ------------------------------------------------------- */

  // The depth ramp, measured off the original: each band's share of the depth
  // and the glyph size that fills 88% of it. Seven steps, biggest at the open
  // end, converging inward. Every panel shares them, which is what makes the
  // bands line up all the way round the box at every depth.
  var BANDS = [
    { h: 21.3, fs: 0.1874 },
    { h: 18.96, fs: 0.1669 },
    { h: 16.62, fs: 0.1463 },
    { h: 14.29, fs: 0.1257 },
    { h: 11.95, fs: 0.1051 },
    { h: 9.61, fs: 0.0846 },
    { h: 7.27, fs: 0.064 }
  ];

  // Rings arrive INSIDE OUT: the small ring at the vanishing point first,
  // growing outward to the big near rows at the frame's edges. See --zr below.
  //
  // ONE CONSTANT VELOCITY, not the original's speed ramp.
  //
  // A row's entry covers one panel-length at its marquee's own rate, and that
  // rate is half the run's width per --d seconds. Run width scales with the
  // band's font size, so holding --d proportional to --fs makes every row —
  // near or deep — travel at the same px/s AND finish its entry at the same
  // moment. The original varied --d independently, which at cover size meant
  // the near ring arrived at 4.7s and the last at 19.2s: fine as a slow burn
  // on its own page, useless as a loading screen with a ~5s budget, where it
  // would read as unfinished rather than as deliberate.
  //
  // Direction still alternates by depth, so it stays kinetic rather than one
  // conveyor; only the pace is now uniform.
  var K = 70; // seconds per unit of --fs — sets the single shared speed
  var PANEL_OFFSET = [0, 0.6, 0.9, 1.4]; // so the four walls do not beat as one
  var PANELS = ["ceiling", "right", "floor", "left"];

  var room = document.createElement("div");
  room.className = "text-box__room";
  room.setAttribute("aria-hidden", "true");

  var line = box.getAttribute("data-line") || "Make What Matters";
  var run = line + " " + line;

  PANELS.forEach(function (name, p) {
    var panel = document.createElement("div");
    panel.className = "text-box__panel text-box__panel--" + name;
    // The band dimension follows the panel's axis: ceiling/floor stack their
    // bands vertically (height), the walls run them horizontally along the
    // depth (width). Emitting height for all four collapsed the wall rows to
    // zero width — their runs are absolutely positioned, so the flex items
    // had no content to size against, and both walls vanished entirely.
    var dim = name === "left" || name === "right" ? "width" : "height";
    BANDS.forEach(function (band, z) {
      var row = document.createElement("div");
      // Alternate depths travel the other way — via the --flip class, which
      // mirrors the row's geometry, NOT animation-direction: reverse. Reverse
      // made the entry and the loop disagree: the line slid in one way and
      // then set off the other. The flipped row runs the same entry and the
      // same marquee in mirrored space, so it arrives already travelling the
      // direction it keeps. (Glyphs are un-mirrored in text-box.css.)
      row.className = "text-box__row" + (z % 2 ? " text-box__row--flip" : "");
      row.style.cssText =
        "--z:" + z +
        // distance from the vanishing point — the cascade runs inside out,
        // so the back ring goes first. Kept separate from --z so the band
        // geometry and the entry order can differ without renumbering.
        ";--zr:" + (BANDS.length - 1 - z) +
        ";" + dim + ":" + band.h + "%" +
        ";--fs:calc(var(--D) * " + band.fs + ")" +
        ";--d:" + (K * band.fs + PANEL_OFFSET[p]).toFixed(2) + "s";
      // two copies: the marquee travels exactly half the run, so the loop is
      // seamless and "half a width" is one whole line
      row.innerHTML =
        '<span class="text-box__run" aria-hidden="true"><span>' +
        run + "</span><span>" + run + "</span></span>";
      panel.appendChild(row);
    });
    room.appendChild(panel);
  });

  var back = document.createElement("div");
  back.className = "text-box__back";
  room.appendChild(back);
  box.appendChild(room);

  /* ---- 2. speed-match every entry to its own marquee ---------------------- */

  // CSS cannot read the run's width, and the marquee's rate is defined against
  // it, so the entry duration has to be measured. Recomputed on resize because
  // both the run width and the panel length are container-relative and their
  // ratio does not survive a reflow.
  var rows = [].slice.call(box.querySelectorAll(".text-box__row"));

  function retime() {
    var dist = Math.max(box.clientWidth, box.clientHeight);
    rows.forEach(function (row) {
      var el = row.querySelector(".text-box__run");
      if (!el) return;
      var d = parseFloat(getComputedStyle(row).getPropertyValue("--d")) || 8;
      var speed = (el.offsetWidth * 0.5) / d; // px per second
      if (!speed) return;
      el.style.setProperty("--in-dur", (dist / speed).toFixed(2) + "s");
    });
  }
  retime();

  var rt;
  var onResize = function () {
    clearTimeout(rt);
    rt = setTimeout(retime, 200);
  };
  window.addEventListener("resize", onResize, { passive: true });

  /* ---- 3. light it, and publish the clock -------------------------------- */

  box.classList.add("is-lit");

  // Mark the boot so home-c.js can clean up whichever way it ends. (The
  // header keeps its normal dark ink over the loading screen — the box is
  // white now — so unlike an earlier draft this does NOT drive the header's
  // light-on-dark inversion; that stays keyed to .is-live, when the video
  // actually covers the screen.)
  var heroEl = box.closest("[data-hero]");
  if (heroEl) heroEl.classList.add("is-booting");

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
  // it; this only reclaims the DOM afterwards. display:none has already
  // cancelled all 56 animations and dropped the layers by the time this runs.
  window.__heroLoaderTeardown = function () {
    window.removeEventListener("resize", onResize);
    clearTimeout(rt);
    var idle = window.requestIdleCallback || function (f) { return setTimeout(f, 200); };
    idle(function () {
      if (box.parentNode) box.parentNode.removeChild(box);
    });
  };

  // Backstop: nothing should ever leave 28 marquees running behind an opaque
  // reel. CONDITIONAL on the hero actually being live — an unconditional
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
