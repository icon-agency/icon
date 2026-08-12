/* hero.js — GSAP ScrambleText headline reveal + scroll parallax for the home
 * hero. Each line scrambles in (random chars resolving to the word), staggered.
 * Degrades gracefully: no GSAP/ScrambleText or reduced-motion → headline shows
 * immediately; no scroll-timeline support → JS parallax fallback.
 *
 * Drupal: Drupal.behaviors.iconHero via `icon/hero` (deps: gsap,
 * ScrambleTextPlugin). */
(function () {
  "use strict";

  var hero = document.querySelector("[data-hero]");
  if (!hero) return;

  var overlay = hero.querySelector("[data-hero-overlay]");
  var video = hero.querySelector("[data-hero-video]");
  var lines = Array.prototype.slice.call(hero.querySelectorAll(".video-hero__line"));
  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var gsap = window.gsap;
  var hasGsap = typeof gsap !== "undefined";
  var hasScramble = hasGsap && typeof window.ScrambleTextPlugin !== "undefined";
  if (hasScramble) gsap.registerPlugin(window.ScrambleTextPlugin);

  // Cover with black on boot; fade out once the video is ready.
  if (overlay && !reduce) overlay.classList.add("is-covering");

  function lineText(line) {
    return line.getAttribute("data-text") || line.textContent;
  }

  function showLinesPlain() {
    lines.forEach(function (l) {
      l.textContent = lineText(l);
      l.style.visibility = "visible";
      l.style.opacity = "1";
    });
  }

  // Split a line into per-character spans, each locked to its final glyph
  // width, so every letter scrambles IN its fixed final spot (no reflow).
  function buildChars(line) {
    var text = lineText(line);
    line.textContent = text;
    var node = line.firstChild;
    var widths = [];
    if (node && document.createRange) {
      var range = document.createRange();
      for (var k = 0; k < text.length; k++) {
        range.setStart(node, k);
        range.setEnd(node, k + 1);
        widths.push(range.getBoundingClientRect().width);
      }
    }
    line.textContent = "";
    var spans = [];
    for (var m = 0; m < text.length; m++) {
      var s = document.createElement("span");
      s.className = "video-hero__char";
      s.textContent = text[m];
      if (widths[m]) s.style.width = widths[m] + "px";
      line.appendChild(s);
      spans.push({ span: s, ch: text[m] });
    }
    return spans;
  }

  // After the entrance, restore plain text so the headline stays responsive
  // (the locked-width spans existed only for the animation).
  function flatten() {
    lines.forEach(function (l) {
      l.textContent = lineText(l);
      l.style.visibility = "visible";
      l.style.opacity = "1";
    });
  }

  // Sequential words (Make → what → matters); within each, every letter
  // scrambles in place.
  function scrambleIn() {
    var wordGap = 0.62, charStagger = 0.035, dur = 0.55;
    var tl = gsap.timeline({ onComplete: flatten });
    lines.forEach(function (line, i) {
      var wordDelay = i * wordGap;
      var spans = buildChars(line);
      tl.fromTo(line, { autoAlpha: 0 }, { autoAlpha: 1, duration: 0.3, ease: "power2.out" }, wordDelay);
      spans.forEach(function (o, j) {
        tl.to(o.span, {
          duration: dur,
          ease: "none",
          scrambleText: { text: o.ch, chars: "lowerCase", speed: 1, revealDelay: 0.18 }
        }, wordDelay + j * charStagger);
      });
    });
  }

  function reveal() {
    if (overlay) overlay.classList.remove("is-covering");
    if (reduce || !hasScramble) {
      showLinesPlain();
      return;
    }
    // Measure glyph widths only once the display font is ready.
    if (document.fonts && document.fonts.ready && document.fonts.ready.then) {
      document.fonts.ready.then(function () { window.setTimeout(scrambleIn, 150); });
    } else {
      window.setTimeout(scrambleIn, 400);
    }
  }

  // Reveal on first video readiness, with a 3s safety fallback.
  var revealed = false;
  function onReady() {
    if (revealed) return;
    revealed = true;
    reveal();
  }

  if (video) {
    video.muted = true;
    var p = video.play && video.play();
    if (p && p.catch) p.catch(function () {});
    video.addEventListener("loadeddata", onReady);
    video.addEventListener("canplay", onReady);
    video.addEventListener("error", onReady);
  }
  window.setTimeout(onReady, 3000);

  // Parallax: the smooth path is compositor-driven CSS (see hero.css,
  // animation-timeline: scroll()). Only drive it from JS when the browser
  // lacks scroll-driven animations — and then via a continuous rAF lerp loop,
  // which is smoother than reacting to scroll events (no event-cadence gaps).
  var supportsScrollTimeline =
    window.CSS && CSS.supports && CSS.supports("animation-timeline: scroll()");

  if (video && !reduce && !supportsScrollTimeline) {
    var current = 0;
    (function loop() {
      var target = window.scrollY * 0.5;
      current += (target - current) * 0.18;
      if (Math.abs(target - current) < 0.05) current = target;
      video.style.transform = "translate3d(0," + current.toFixed(2) + "px,0)";
      window.requestAnimationFrame(loop);
    })();
  }
})();
