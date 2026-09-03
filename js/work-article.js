/* work-article.js — page behaviour for the Work article (folio case study).
 *
 * Two jobs:
 *
 * 1. The scroll-velocity SKEW — the /work landing's card gesture (user
 *    call, Sep 2026), via the SHARED engine (js/velocity-lean.js). One
 *    --wa-skew on the article root per frame; the banner, the gallery
 *    figures and the Next up cards consume it in work-article.css. Same
 *    3deg cap as the landing and the news rows, so every skew on the site
 *    moves alike. Reduced-motion guarding lives HERE, with the consumer —
 *    the engine never decides whether motion exists (its header's contract).
 *
 * 2. The banner BREAKOUT (user call, Sep 2026): .is-wide on the hero banner
 *    while at least half of it is on screen, off again once half has left
 *    by either edge. One IntersectionObserver, one 0.5 threshold, both
 *    directions, with HYSTERESIS: wide at 55% or more, back at 45% or
 *    less, nothing in between — one line would flutter when a scroll
 *    settles on it (the skew's decaying transform nudges the measured box
 *    by a pixel or two). The CSS owns the travel (work-article.css).
 *
 *    The height is pinned from the first frame (.is-pinned + --wa-banner-h,
 *    the height the aspect gives the inset frame), in both states, and
 *    only rewritten on resize — read the aspect with the pin lifted for a
 *    style pass, so a breakpoint crossing is honoured. Pinning only while
 *    wide strobed: the aspect took the height back mid-transition on the
 *    way out, the bottom edge moved, the observer re-fired. Runs under
 *    reduced motion too — the CSS drops the transition, the state still
 *    flips — because a wider frame is layout, not motion.
 *
 *    AND it follows the header (user calls: "wait for the logo hide and
 *    home icon animation", then "it isn't returning to its original
 *    size"). The banner is wide only while BOTH hold: half of it on
 *    screen, and the header in its scrolled state — the hero-exit swap
 *    js/header.js runs past 100px, the wordmark sliding out as the home
 *    glyph opens. The swap has to have RUN and FINISHED before the first
 *    widening (the glyph's max-width transitionend, its longest leg; a
 *    timer stands in if the event never comes), so a desktop page — where
 *    the banner is already past half-visible at load — opens inset and
 *    widens after the wordmark has gone. And when the wordmark comes BACK
 *    (scrolled to the top again) the banner goes back to inset with it,
 *    even though it is still half on screen: a live condition, not a
 *    one-time gate, which is what "returning to its original size" needs.
 *    Under reduced motion the swap is instant, so nothing waits — but the
 *    header state is still followed.
 *
 * Drupal: Drupal.behaviors.iconWorkArticle, depending on icon/velocity-lean.
 */
(function () {
  "use strict";

  var article = document.querySelector(".work-article");
  if (!article) return;

  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---- 1. skew ---------------------------------------------------------- */
  if (!reduced && window.ICON && window.ICON.velocityLean) {
    window.ICON.velocityLean(article, article, "--wa-skew", 3);
  }

  /* ---- 2. banner breakout ----------------------------------------------- */
  var banner = article.querySelector(".work-article__banner");
  if (!banner || !("IntersectionObserver" in window)) return;

  var onScreen = false; // the observer's verdict: half the banner in view
  var swapped = false;  // the header's hero-exit swap has run AND finished

  function apply() {
    banner.classList.toggle("is-wide", onScreen && swapped);
  }

  /* follow the header: swapped goes true once show-home has arrived and
     its transition ended, false the moment show-home leaves */
  var nav = document.querySelector(".site-nav");
  var glyph = nav && nav.querySelector(".site-nav__home");
  if (!glyph) {
    swapped = true; // no header on the page: nothing to follow
  } else {
    var waiting = false, belt = 0;
    function finish() {
      if (!waiting) return;
      waiting = false;
      clearTimeout(belt);
      glyph.removeEventListener("transitionend", onEnd);
      swapped = true;
      apply();
    }
    function onEnd(e) {
      if (e.propertyName === "max-width") finish();
    }
    function onNavChange() {
      var on = nav.classList.contains("show-home");
      if (!on) {
        if (waiting) { waiting = false; clearTimeout(belt); glyph.removeEventListener("transitionend", onEnd); }
        if (swapped) { swapped = false; apply(); }
        return;
      }
      if (swapped || waiting) return;
      if (reduced) { swapped = true; apply(); return; } // no transition to wait for
      waiting = true;
      belt = setTimeout(finish, 500); // belt: if transitionend never arrives
      glyph.addEventListener("transitionend", onEnd);
    }
    new MutationObserver(onNavChange).observe(nav, { attributes: true, attributeFilter: ["class"] });
    onNavChange(); // a page that loads already scrolled
  }

  // "3 / 2" or "auto 16 / 9" → 0.666…, read with the pin lifted so the
  // aspect is the frame's own at this width; falls back to the live box
  function readRatio() {
    banner.classList.remove("is-pinned");
    var m = /(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)/.exec(getComputedStyle(banner).aspectRatio || "");
    var r = m ? parseFloat(m[2]) / parseFloat(m[1]) : (banner.offsetWidth ? banner.offsetHeight / banner.offsetWidth : 0);
    banner.classList.add("is-pinned");
    return r;
  }

  // the inset frame's height, from the article's content width — the same
  // number whether the frame is wide or not, which is the whole point
  function pinHeight() {
    var ratio = readRatio();
    var cs = getComputedStyle(article);
    var contentWidth = article.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
    // on the ARTICLE, not the banner: the gallery scroller caps its cards
    // at this height too (work-article.css), so it has to inherit
    article.style.setProperty("--wa-banner-h", Math.round(contentWidth * ratio) + "px");
  }

  pinHeight(); // from the first frame, so no later flip moves an edge

  new IntersectionObserver(function (entries) {
    var r = entries[entries.length - 1].intersectionRatio;
    var next = r >= 0.55 ? true : r <= 0.45 ? false : onScreen; // hysteresis
    if (next === onScreen) return;
    onScreen = next;
    apply();
  }, { threshold: [0.45, 0.55] }).observe(banner);

  // synchronous on purpose: js/work-scroller.js re-measures its cards on
  // the same event and reads the cap this writes, so it must land first
  // (this file loads first; one read + one write per event is cheap)
  window.addEventListener("resize", pinHeight);
})();
