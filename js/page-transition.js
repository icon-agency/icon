/* page-transition.js — the cross-document View Transition's two scripted
 * parts (the fade itself is CSS: src/utilities/page-transition.css).
 *
 *   1. DIRECTION — a Back (a traverse to an earlier history entry) adds the
 *      `back` type, so the new page comes down from above instead of rising.
 *   2. THE MORPH — leaving a listing for an article, the card whose link is
 *      the destination has its media named `feature-media` for the outgoing
 *      snapshot, and the article names its banner the same for the incoming
 *      one, so the tile grows into the banner. Coming back, the article names
 *      its banner on the way out and the listing names the matching card on
 *      the way in, so it shrinks back. Only a pair that is on screen at both
 *      ends is named — an off-screen partner would fly in from nowhere. The
 *      outgoing page leaves a note in sessionStorage so the incoming page
 *      knows whether it has a partner; otherwise it names nothing and joins
 *      the plain fade.
 *
 * The homepage is skipped: its hero loader is an entrance of its own. The
 * name is stripped again once the transition has run (and before any new
 * naming), so a page restored from the back-forward cache never carries a
 * second bearer — a duplicate name voids the whole transition.
 *
 * The scroll reveals are held while the page fades in (html.is-page-entering
 * → --animate-hold), so the page settles once. A named banner is revealed
 * outright (.is-revealed before first paint), or the morph would land on a
 * clipped, invisible image.
 *
 * A plain IIFE in <head>, not a behaviour: pagereveal fires at the new page's
 * FIRST render, before DOMContentLoaded, so an attach() would miss it. The
 * DOM is complete by then because html.html.twig render-blocks on the footer
 * (<link rel="expect" href="#site-footer" blocking="render">).
 * Reduced motion: the CSS turns the transition off, so neither event carries
 * a viewTransition and both handlers return. */
(function () {
  "use strict";

  if (!("onpagereveal" in window)) return;

  var KEY = "icon:page-transition";
  var NAME = "feature-media";
  var BANNER = ".work-article__banner, .news-article__hero, .news-article__media";
  var CARD = ".work__item, .news-card";

  function path(url) {
    try { return new URL(url, location.href).pathname.replace(/\/$/, "") || "/"; } catch (e) { return null; }
  }

  function onScreen(el) {
    var r = el.getBoundingClientRect();
    return r.bottom > 0 && r.top < window.innerHeight && r.right > 0 && r.left < window.innerWidth;
  }

  /** The media of the card whose link goes to `url`, if it is on screen. */
  function cardMedia(url) {
    var to = path(url);
    if (!to) return null;
    var links = document.querySelectorAll(CARD);
    for (var i = 0; i < links.length; i++) {
      if (path(links[i].getAttribute("href")) !== to) continue;
      var media = links[i].querySelector(".media-reveal");
      return media && onScreen(media) ? media : null;
    }
    return null;
  }

  function banner() {
    var el = document.querySelector(BANNER);
    return el && onScreen(el) ? el : null;
  }

  /** Nothing else may wear the name — a second bearer voids the transition. */
  function unname() {
    var worn = document.querySelectorAll('[style*="view-transition-name"]');
    for (var i = 0; i < worn.length; i++) {
      if (worn[i].style.viewTransitionName === NAME) worn[i].style.viewTransitionName = "";
    }
  }

  function name(el) {
    unname();
    el.style.viewTransitionName = NAME;
    el.classList.add("is-revealed");
  }

  /** The note the outgoing page leaves the incoming one: its morph partner,
   *  if any. */
  function note(morph) {
    try {
      if (morph) sessionStorage.setItem(KEY, morph);
      else sessionStorage.removeItem(KEY);
    } catch (e) {}
  }

  function readNote() {
    try {
      var v = sessionStorage.getItem(KEY);
      sessionStorage.removeItem(KEY);
      return v;
    } catch (e) { return null; }
  }

  function isBack(activation) {
    return activation.navigationType === "traverse" &&
      activation.from && activation.entry && activation.entry.index < activation.from.index;
  }

  function quiet(vt) {
    // A skipped or voided transition rejects these; nothing waits on them.
    if (vt.ready) vt.ready.catch(function () {});
    if (vt.finished) vt.finished.catch(function () {});
  }

  window.addEventListener("pageswap", function (e) {
    if (!e.viewTransition || !e.activation || !e.activation.entry) return;
    quiet(e.viewTransition);
    unname();
    var to = e.activation.entry.url;
    var media = cardMedia(to);
    var art = media ? null : banner();
    if (media) name(media);
    else if (art) name(art);
    note(media ? "card" : art ? "banner" : null);
  });

  window.addEventListener("pagereveal", function (e) {
    var vt = e.viewTransition;
    if (!vt) return;
    quiet(vt);

    // The homepage has its own entrance.
    if (document.querySelector("[data-text-box]")) {
      readNote();
      vt.skipTransition();
      return;
    }

    var from = readNote();
    // Only the swap event carries its activation; on reveal it is the
    // Navigation API's.
    var activation = window.navigation && window.navigation.activation;
    var partner = null;
    if (from === "card") partner = banner();
    else if (from === "banner" && activation && activation.from) partner = cardMedia(activation.from.url);
    if (partner) name(partner);

    if (vt.types && activation && isBack(activation)) vt.types.add("back");

    var html = document.documentElement;
    html.classList.add("is-page-entering");
    // The name is for this one transition; a page kept alive in the
    // back-forward cache would otherwise still wear it next time.
    vt.finished.then(function () {
      html.classList.remove("is-page-entering");
      unname();
    });
  });
})();
