/* page-transition.js — the cross-document View Transition's scripted parts
 * (THE SIMPLE MOVE, back by user call, 22 Sep 2026: "put back the simple one
 * with the background transition, text fade out and new page build in.
 * locked logo/navigation as is" — the disc-and-lockup wipe of 15–16 Sep is
 * gone; git holds it)
 * (the fade itself is CSS: src/utilities/page-transition.css).
 *
 *   1. THE GROUND — the outgoing page notes its painted background as it
 *      leaves (read live, so a dark-opening page that has handed over to
 *      light on scroll reports light); the incoming page reads its own at
 *      first render. Alike, the CSS crossfades. Different, it adds the
 *      `fade-through` type: the old fades out to the new ground first, and
 *      the new content fades in after it.
 *   2. THE HOLD — the scroll reveals wait while the page fades in
 *      (html.is-page-entering → --animate-hold), so the page settles once.
 *   3. THE HOMEPAGE — its blue loading screen is full-size and unlit on the
 *      first frame (text-box.css shows it while html.is-page-entering), so
 *      the fade brings the blue in whole; js/hero-loader.js, reading
 *      window.ICON.pageEntering, lights it as already landed and fires the
 *      cover moment when the fade finishes, instead of popping it again.
 *
 * A plain IIFE in <head>, not a behaviour: pagereveal fires at the new page's
 * FIRST render, before DOMContentLoaded, so an attach() would miss it. The
 * DOM is complete by then because html.html.twig render-blocks on the footer
 * (<link rel="expect" href="#site-footer" blocking="render">). On reveal the
 * activation is the Navigation API's; only the swap event carries its own.
 * Reduced motion: the CSS turns the transition off, so neither event carries
 * a viewTransition and both handlers return. */
(function () {
  "use strict";

  if (!("onpagereveal" in window)) return;
  // NOT IN A FRAME. The Canvas editor and its preview render the page in an
  // iframe they re-fill on every change; each refill is a navigation between
  // two documents that both opt in, so the move played inside the editor
  // over and over (user report, Sep 2026: "Why is this page flashing?"). A
  // framed document opts out before its first render (a later
  // @view-transition rule wins) and does nothing else.
  var framed = true;
  try { framed = window.self !== window.top; } catch (err) { framed = true; }
  if (framed || document.documentElement.hasAttribute("data-canvas-preview")) {
    var off = document.createElement("style");
    off.textContent = "@view-transition { navigation: none; }";
    (document.head || document.documentElement).appendChild(off);
    return;
  }

  var KEY = "icon:page-transition";

  /** The page's ground as [r, g, b]: the first painted background down
   *  from <html>. */
  function ground() {
    var els = [document.body, document.documentElement];
    for (var i = 0; i < els.length; i++) {
      var m = /rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)(?:[\s,/]+([\d.]+))?/.exec(getComputedStyle(els[i]).backgroundColor);
      if (!m || (m[4] !== undefined && parseFloat(m[4]) === 0)) continue;
      return [+m[1], +m[2], +m[3]];
    }
    return [255, 255, 255];
  }

  /** Two grounds close enough to read as one. */
  function alike(a, b) {
    return Math.max(Math.abs(a[0] - b[0]), Math.abs(a[1] - b[1]), Math.abs(a[2] - b[2])) < 64;
  }

  function note() {
    try { sessionStorage.setItem(KEY, JSON.stringify({ ground: ground() })); } catch (e) {}
  }

  function readNote() {
    try {
      var v = sessionStorage.getItem(KEY);
      sessionStorage.removeItem(KEY);
      return v ? JSON.parse(v) : {};
    } catch (e) { return {}; }
  }

  function quiet(vt) {
    // A skipped or voided transition rejects these; nothing waits on them.
    if (vt.ready) vt.ready.catch(function () {});
    if (vt.finished) vt.finished.catch(function () {});
  }

  window.addEventListener("pageswap", function (e) {
    if (!e.viewTransition) return;
    quiet(e.viewTransition);
    note();
  });

  window.addEventListener("pagereveal", function (e) {
    var vt = e.viewTransition;
    if (!vt) return;
    quiet(vt);

    window.ICON = window.ICON || {};
    window.ICON.pageEntering = vt.finished.catch(function () {});

    var from = readNote();
    if (vt.types && from.ground && !alike(from.ground, ground())) vt.types.add("fade-through");

    var html = document.documentElement;
    html.classList.add("is-page-entering");
    vt.finished.then(function () { html.classList.remove("is-page-entering"); });
  });
})();
