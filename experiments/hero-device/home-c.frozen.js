/* FROZEN COPY — js/home-c.js as it stood on 31 Aug 2026 (commit 622b927),
 * for experiments/hero-device.html. Do not edit; do not sync.
 *
 * Copied whole rather than trimmed to the hero: every other block in
 * home-c.js is element-guarded and no-ops on a page without its markup.
 */
/* home-c.js — Home C prototype behaviours (zypsy-inspired). One IIFE:
 *   1. Lenis smooth (momentum) scroll — off under reduced motion / if absent.
 *   2. Hero load sequence: masked "Make what matters" reveal, image zoom-settle,
 *      PiP fade-in (CSS transitions keyed off .is-ready).
 *   3. Image clip + scale reveals — now js/reveal.js's shared observer.
 *   3g. Clients logo marquee: the strip's drift + drag, shared STRIP_SPEED.
 *   4. GSAP SplitText line-mask text reveals — a per-element timeline fired by an
 *      IntersectionObserver (no ScrollTrigger, matching the project's approach).
 *
 * All reduced-motion guarded; no-JS / missing-lib safe (text + images render
 * plainly, images just appear). Drupal: would become Drupal.behaviors.iconHomeC.
 */
(function () {
  "use strict";

  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  // One drift speed for every hand-draggable strip on the page — the intro
  // filmstrip (3d) and the clients logo marquee (3g) read this same number,
  // so "they scroll at the same pace" is true by construction, not by tuning.
  var STRIP_SPEED = 55; // px/s
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

  // ---- 2. Hero — stack takeover (k95-timed build) ------------------------
  // One continuous clock (k95.it's loader rhythm): media preloads in parallel
  // (each raced against a timeout so one slow file can't stall the start),
  // then every card opens on a fixed schedule — 680ms eased growth, next card
  // starting 200ms in, so the pile builds as an overlapping wave. A counter
  // ((0)→(100), bottom-left in the client name's spot) is the same clock made
  // visible. Beat on the finished pile, then the last card swallows the
  // viewport (1s expo.inOut) and hands off to the reel: 3s/6s per piece,
  // 0.1s cross-fades, videos playing while active, stills on a CSS Ken Burns.
  // .is-live lands at the takeover's end — lockup cascade, scrim, and the
  // counter→client-name crossfade. Reduced motion: no choreography, no
  // counter, just the reel.
  var hero = document.querySelector("[data-hero]");
  if (hero) {
    (function () {
      var cards = Array.prototype.slice.call(hero.querySelectorAll(".hero__card"));
      var slides = Array.prototype.slice.call(hero.querySelectorAll(".hero__slide"));
      var label = hero.querySelector("[data-client-label]");
      var count = hero.querySelector("[data-boot-count]");
      // Stills get a glance; films get room to actually play a beat.
      var HOLD_IMAGE = 3000, HOLD_VIDEO = 6000, XFADE = 100;
      var holdFor = function (el) { return el.tagName === "VIDEO" ? HOLD_VIDEO : HOLD_IMAGE; };
      if (!cards.length || !slides.length) return;

      var wait = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };

      // A REAL load gate. The kinetic-text box is the loading screen now, so
      // this has to mean "this media can actually be shown", not "something
      // arrived". Each element is gated on its own, and every promise fails
      // OPEN on error — a 404 must not hold the page.
      //
      // img.decode() rather than .complete: complete is true for a failed
      // image, true for an empty src, and true BEFORE the bitmap is decoded,
      // so popping on it can still flash. decode() is the honest signal.
      //
      // For video, readyState >= 3 (HAVE_FUTURE_DATA) is "can start playing".
      // The last card is the one the morph hands to the reel, so it is held to
      // a harder test: enough BUFFERED to cover the pile beat plus the whole
      // morph without stalling. canplaythrough would be the obvious choice and
      // is deliberately not used — it is the browser's estimate from measured
      // download rate, not a fact about what is in the buffer.
      var mediaReady = function (el, deep) {
        var m = el.tagName === "IMG" || el.tagName === "VIDEO"
          ? el : el.querySelector("img, video");
        return new Promise(function (res) {
          if (!m) return res();
          var done = function () { res(); };
          if (m.tagName === "IMG") {
            if (m.decode) return m.decode().then(done, done);
            if (m.complete) return done();
            m.addEventListener("load", done, { once: true });
            m.addEventListener("error", done, { once: true });
            return;
          }
          // readyState >= 3 is HAVE_FUTURE_DATA ("can start playing"); >= 4 is
          // HAVE_ENOUGH_DATA, the browser's own "can play through without
          // stalling". The deep ones — the last card and the reel slide it
          // hands to — hold out for 4.
          //
          // An earlier version asked for a buffered range covering the beat
          // plus the morph instead, on the theory that a measured fact beats
          // the browser's estimate. It does not work: browsers deliberately
          // STOP buffering a paused video after a second or two, so the range
          // never grew and the gate sat there until MAX_WAIT fired at 8s —
          // traced at 9.5s for a fully cached file. readyState is the signal
          // that actually moves for media nobody is playing yet.
          var enough = function () { return m.readyState >= (deep ? 4 : 3); };
          if (enough()) return done();
          var onTick = function () { if (enough()) { cleanup(); done(); } };
          var cleanup = function () {
            m.removeEventListener("progress", onTick);
            m.removeEventListener("canplay", onTick);
            m.removeEventListener("canplaythrough", onTick);
            m.removeEventListener("loadeddata", onTick);
            m.removeEventListener("error", fail);
          };
          var fail = function () { cleanup(); done(); };
          m.addEventListener("progress", onTick);
          m.addEventListener("canplay", onTick);
          m.addEventListener("canplaythrough", onTick);
          m.addEventListener("loadeddata", onTick);
          m.addEventListener("error", fail, { once: true });
        });
      };

      // Label swap: a fast snap-fade — out, swap text, in (reduced motion /
      // first set: instant).
      var labelTimer = null;
      var setLabel = function (text) {
        if (!label) return;
        var inner = label.firstElementChild;
        if (!inner) {
          inner = document.createElement("span");
          inner.className = "hero__client-text";
          label.appendChild(inner);
        }
        if (inner.textContent === text) return;
        if (reduce || !inner.textContent) {
          inner.textContent = text;
          return;
        }
        if (labelTimer) clearTimeout(labelTimer);
        inner.classList.add("is-out");
        labelTimer = setTimeout(function () {
          labelTimer = null;
          inner.textContent = text;
          inner.classList.remove("is-out"); // fade the new name straight in
        }, 160); // just past the 0.15s fade-out
      };

      var activate = function (i) {
        slides.forEach(function (s, j) {
          var on = i === j;
          s.classList.toggle("is-active", on);
          if (s.tagName === "VIDEO") {
            if (on) {
              try { s.currentTime = 0; } catch (e) {}
              s.play().catch(function () {});
            } else {
              s.pause();
            }
          }
        });
        setLabel(slides[i].getAttribute("data-client"));
      };

      // Background tabs: the browser pauses muted videos in hidden tabs, so a
      // page loaded in the background would surface with a frozen reel. On
      // return, nudge the active slide back into playback.
      document.addEventListener("visibilitychange", function () {
        if (document.visibilityState !== "visible") return;
        var active = hero.querySelector(".hero__slide.is-active");
        if (active && active.tagName === "VIDEO" && active.paused) {
          active.play().catch(function () {});
        }
      });

      // setTimeout chain, not setInterval: each slide holds for its own
      // duration, so a film can sit longer than a still.
      var loop = function (start) {
        var i = start;
        var next = function () {
          i = (i + 1) % slides.length;
          activate(i);
          setTimeout(next, holdFor(slides[i]) + XFADE);
        };
        setTimeout(next, holdFor(slides[i]) + XFADE);
      };

      if (reduce) {
        cards.forEach(function (c) { c.classList.add("is-pop", "is-stacked"); });
        hero.classList.add("is-live");
        // Gated, same as the full path: activating an unloaded 6MB video
        // showed an empty reel until it painted. (Pre-existing; the loading
        // screen work just made the double standard obvious.)
        //
        // PRIMED, also same as the full path — and here it is load-bearing,
        // not just faster: this gate has no cap, and iOS parks a paused
        // video's readyState below the gate's bar (preload="auto" ignored),
        // so without the play() kick reduced-motion iOS would deadlock on a
        // blank hero forever. activate(0) plays it properly when the gate
        // settles; until then it plays invisibly (not yet .is-active).
        if (slides[0].tagName === "VIDEO") slides[0].play().catch(function () {});
        mediaReady(slides[0]).then(function () {
          activate(0);
          loop(0);
        });
        return;
      }

      // Refresh mid-page: the browser restores the old scroll position, which
      // would play the boot inside a receded, curtain-covered hero (it looks
      // chopped). Two cases:
      //  · restored less than half a viewport deep — the visitor was still at
      //    the hero: turn restoration off, snap to top, run the show in full;
      //  · deeper — they had moved on: skip the show and jump the hero to its
      //    end state, keeping their place.
      var restoredY = window.scrollY;
      if (restoredY > 0) {
        if (restoredY < window.innerHeight * 0.5) {
          if ("scrollRestoration" in history) history.scrollRestoration = "manual";
          window.scrollTo(0, 0);
        } else {
          cards.forEach(function (c) { c.classList.add("is-pop", "is-stacked"); });
          hero.classList.add("is-live");
          activate(slides.length - 1);
          loop(slides.length - 1);
          return;
        }
      }

      // The rhythm. k95's values, slowed ~35% now that the pops play over the
      // kinetic-text loading screen rather than a bare panel — the pile reads
      // as deliberate at this pace instead of snapping shut.
      var DUR = 920;      // one card's open (matches the CSS transition)
      var STAGGER = 270;  // next card starts this far in — opens overlap
      var HOLD = 800;     // beat on the finished pile
      var TAKEOVER = 1000; // matches the takeover transition in CSS
      // The device leads the pile and gets a beat to itself before the work
      // pieces follow (user call, Aug 2026: "pause a tad"). Added ON TOP of the
      // normal STAGGER, so the first gap is STAGGER + LEAD_HOLD and every gap
      // after it is the usual STAGGER.
      var LEAD_HOLD = 600;

      // The cards start the moment their media allows — no mandatory beat for
      // the text box first (it had 1400ms; cut to 0 on request, Aug 2026 —
      // the box now plays under and between the pops rather than before
      // them). The lever stays because the show's shape is one edit away.
      var MIN_TEXT = 0;
      // ...and nobody waits forever. This cap carries the whole "no skip
      // button" decision: past it the cards pop regardless of what has
      // loaded, so the worst-case hold is ~5.5s of kinetic text before the
      // show proceeds. A card whose media never arrived degrades to its blue
      // ground rather than a hole (.hero__card paints --color-shader-blue).
      var MAX_WAIT = 5500;

      // T0 is the frame the copy actually started moving on, published by
      // js/hero-loader.js. Falling back to now() covers reduced motion and any
      // path where the box was never built.
      var T0 = window.__heroLoaderT0 || performance.now();
      var capAt = T0 + MAX_WAIT;

      // Gate each card on its own media, and the LAST REEL SLIDE too. That
      // last one matters more than it looks: the morph lifts .hero__show to
      // z-index 3, so from its first frame the visible pixels are the reel's,
      // not the card's — and .hero__show has no background of its own. An
      // unpainted reel would animate a white rectangle across the viewport.
      var lastCard = cards.length - 1;
      var ready = cards.map(function () { return false; });
      var settle = function (i) {
        return function () { ready[i] = true; bumpCount(); };
      };
      // PRIME the videos. iOS ignores preload="auto" on a paused video —
      // readyState parks at HAVE_METADATA, so on every phone the video gates
      // above sat until the cap: the pile popped at 5.5s and the counter
      // froze at (40) (the two images were the only gates that could settle).
      // Playback is the one thing that reliably makes Safari buffer, and a
      // muted playsinline video may be play()ed without a gesture. The card
      // is invisible pre-pop, so nothing shows; paused again the moment its
      // gate settles (the pop re-plays the last card itself). Low Power Mode
      // rejects the play() — caught, and the cap below still fails open.
      var prime = function (host) {
        var v = host.tagName === "VIDEO" ? host : host.querySelector("video");
        if (v) v.play().catch(function () {});
        return v;
      };
      cards.forEach(function (c, i) {
        var v = prime(c);
        mediaReady(c, i === lastCard).then(function () {
          if (v) v.pause();
          settle(i)();
        });
      });
      var reelReady = false;
      var reelVid = prime(slides[slides.length - 1]);
      mediaReady(slides[slides.length - 1], true).then(function () {
        if (reelVid) reelVid.pause();
        reelReady = true;
        bumpCount();
      });
      // The cap is fail-open by policy (a stalled file must not hold the
      // page); the counter follows the same policy. When the cap passes,
      // whatever has not settled is not going to before the show proceeds —
      // the loading phase is OVER, so the counter completes instead of
      // freezing at a number nobody can act on.
      setTimeout(function () {
        ready.forEach(function (_, i) { ready[i] = true; });
        reelReady = true;
        bumpCount();
      }, Math.max(0, capAt - performance.now()));

      // THE COUNTER IS REAL NOW. It was a fake clock driven off the animation's
      // own progress; with load genuinely gating the show, it reports what has
      // actually arrived. It moves unevenly, the way real loaders do.
      var totalGates = cards.length + 1;
      var loadPct = 0;
      var bumpCount = function () {
        var done = ready.filter(Boolean).length + (reelReady ? 1 : 0);
        loadPct = Math.max(loadPct, Math.round((done / totalGates) * 100));
      };
      if (count) count.textContent = "(0)";

      // Escape skips, from the first frame. Deliberately NO visible control:
      // the hard MAX_WAIT cap above keeps the worst case short enough that a
      // button would outlast its usefulness — that cap carries the WCAG 2.2.2
      // obligation the button used to. Escape stays as the keyboard courtesy.
      // The same jump the deep-scroll restore takes: pile present, takeover
      // already done, reel running. .is-live is what tears the box down.
      var forceReveal = function () {
        cards.forEach(function (c) { c.classList.add("is-pop", "is-stacked"); });
        hero.classList.add("is-live");
        activate(slides.length - 1);
        loop(slides.length - 1);
      };
      var skipped = false;
      var skip = function () {
        // Both guards matter. `skipped` stops a double-tap; `is-live` stops
        // Escape AFTER the show has finished normally — the listener is still
        // attached then, and forceReveal() would start a second reel loop
        // running alongside the first, double-advancing the slides forever.
        if (skipped || hero.classList.contains("is-live")) return;
        skipped = true;
        forceReveal();
      };
      var onKey = function (e) { if (e.key === "Escape") skip(); };
      document.addEventListener("keydown", onKey);
      // picked up by the is-live observer below, so the listener dies with
      // the loading screen no matter which path ended it
      window.__heroCleanup = function () {
        document.removeEventListener("keydown", onKey);
      };

      (function () {
        var t0 = null;
        var added = 0;
        var nextSlot = 0;
        var tick = function (now) {
          if (skipped) return;
          // The pile holds until the box has had its beat, then each card
          // waits for its OWN media — but never jumps its slot. Readiness can
          // only ever DELAY a card, never advance it past the one in front:
          // .hero__card is grid-area 1/1 with paint order = DOM order, so a
          // late card 2 materialising under an already-present card 4 reads as
          // a rendering glitch, and the tilts are positional (:nth-child) so
          // reordering would move the takeover's geometry too.
          var elapsed = now - T0;
          if (elapsed >= MIN_TEXT) {
            while (added < cards.length) {
              var due = now >= nextSlot;
              var canPop = ready[added] || now >= capAt;
              if (!due || !canPop) break;
              cards[added].classList.add("is-pop", "is-stacked");
              // The LAST card is the one the reel continues from — its video
              // plays on the pile so motion never stops across the hand-off.
              if (added === cards.length - 1) {
                var lastVid = cards[added].querySelector("video");
                if (lastVid) lastVid.play().catch(function () {});
              }
              added += 1;
              // `added` was just incremented, so === 1 means "the device just
              // popped" — hold before the work pieces start.
              nextSlot = now + STAGGER + (added === 1 ? LEAD_HOLD : 0);
              if (t0 === null) t0 = now;
            }
          }
          if (count && !hero.classList.contains("is-live")) {
            count.textContent = "(" + loadPct + ")";
          }
          // Progress of the LAST card's open. The subtrahend is the time the
          // pile spent starting cards before it — every gap is STAGGER, plus
          // the device's one-off LEAD_HOLD. Miss the LEAD_HOLD here and p hits
          // 1 early, so the beat and the morph would start while the last card
          // was still growing.
          var p = added < cards.length || t0 === null
            ? 0
            : Math.min(1, (now - t0 - ((cards.length - 1) * STAGGER + LEAD_HOLD)) / DUR);
          if (p < 1) {
            window.requestAnimationFrame(tick);
            return;
          }
          // build done → beat → morph → reel
          wait(HOLD)
            .then(function () {
              // THE MORPH — window + counter-transform, one clock.
              // The outer (.hero__show, overflow hidden) is transformed onto
              // the card's rectangle; the inner counter-transforms so the
              // media stays undistorted and pixel-matched to the card at the
              // start. Every frame writes BOTH transforms from one eased
              // progress, so the mask cannot outrun the media — transforms
              // only, no clip-path (main-thread) in the loop.
              var last = cards[cards.length - 1];
              var lastVid = last.querySelector("video");
              var show = hero.querySelector(".hero__show");
              var inner = show.querySelector(".hero__show-inner");
              // Card rect in hero-local offsets (transform-independent).
              var W = hero.clientWidth, H = hero.clientHeight;
              var w = last.offsetWidth, h = last.offsetHeight;
              var top = last.offsetTop, left = last.offsetLeft;
              var sc = Math.max(w / W, h / H); // content scale at the card

              activate(slides.length - 1);
              // Continue the card's playback rather than restart: sync the
              // reel video to wherever the card's copy has reached.
              var reelVid = slides[slides.length - 1];
              if (lastVid && reelVid.tagName === "VIDEO") {
                try { reelVid.currentTime = lastVid.currentTime; } catch (e) {}
              }

              // e = eased progress 0→1. Window tweens card-rect → full bleed;
              // content scale tweens sc → 1 about the window's centre; the
              // inner transform is the exact algebraic remainder.
              var apply = function (e) {
                var ww = w + (W - w) * e, wh = h + (H - h) * e;
                var wx = left * (1 - e), wy = top * (1 - e);
                var sx = ww / W, sy = wh / H;
                var c = sc + (1 - sc) * e;
                var cx = wx + ww / 2 - (W * c) / 2;
                var cy = wy + wh / 2 - (H * c) / 2;
                show.style.transform =
                  "translate(" + wx + "px," + wy + "px) scale(" + sx + "," + sy + ")";
                inner.style.transform =
                  "translate(" + (cx - wx) / sx + "px," + (cy - wy) / sy + "px)" +
                  " scale(" + c / sx + "," + c / sy + ")";
              };

              hero.classList.add("is-opening");
              show.style.borderRadius = "var(--radius-icon)";
              apply(0);

              return new Promise(function (resolve) {
                var cleanup = function () {
                  show.style.transform = "";
                  inner.style.transform = "";
                  show.style.borderRadius = "";
                  resolve();
                };
                if (gsapOk) {
                  var st = { p: 0 };
                  window.gsap.to(st, {
                    p: 1,
                    duration: TAKEOVER / 1000,
                    ease: "expo.inOut",
                    onUpdate: function () { apply(st.p); },
                    onComplete: cleanup
                  });
                } else {
                  // No GSAP: same endpoints via CSS transitions.
                  hero.classList.add("is-opening--css");
                  void show.offsetHeight; // commit the start state
                  apply(1);
                  setTimeout(cleanup, TAKEOVER);
                }
              });
            })
            .then(function () {
              hero.classList.add("is-live"); // lockup rises; stage retires beneath
              loop(slides.length - 1);
            });
        };
        window.requestAnimationFrame(tick);
      })();
    })();
  }

  // The loading screen's DOM is reclaimed as soon as the hero goes live,
  // whichever path got it there — the morph, the CSS fallback, the restore
  // branch or a skip. The CSS rule in home-c.css has already cancelled all 16
  // marquees and dropped their layers by this point (display:none); this only
  // frees the ~64 nodes and unhooks the resize listener.
  if (hero) {
    (function () {
      if (hero.classList.contains("is-live")) {
        if (window.__heroLoaderTeardown) window.__heroLoaderTeardown();
        return;
      }
      var mo = new MutationObserver(function () {
        if (!hero.classList.contains("is-live")) return;
        mo.disconnect();
        hero.classList.remove("is-booting");
        if (window.__heroCleanup) window.__heroCleanup();
        if (window.__heroLoaderTeardown) window.__heroLoaderTeardown();
      });
      mo.observe(hero, { attributes: true, attributeFilter: ["class"] });
    })();
  }

  // ---- 2b. Header inversion over the hero --------------------------------
  // The hero opens on the PAGE ground while the pile builds, so the header
  // reads in the normal theme there — it flips to light-on-dark
  // (.is-over-hero) once the takeover has covered the screen (.is-live), and
  // back once the intro curtain has ridden up past it. The hero is PINNED
  // (sticky curtain), so "past the hero" is measured off the INTRO's top
  // edge, not the hero's — a pinned hero never scrolls away. Class-toggle
  // scroll listener (header.js pattern); the colour cross-fade is CSS.
  var siteHeader = document.querySelector(".site-header");
  var introSec = document.querySelector(".intro");
  if (siteHeader && hero) {
    var syncOverHero = function () {
      var covered = introSec
        ? introSec.getBoundingClientRect().top <= 80 // curtain has reached the header
        : hero.getBoundingClientRect().bottom <= 80;
      siteHeader.classList.toggle(
        "is-over-hero",
        !covered && hero.classList.contains("is-live")
      );
    };
    window.addEventListener("scroll", syncOverHero, { passive: true });
    window.addEventListener("resize", syncOverHero);
    // …and again the moment the takeover lands, since .is-live is the other input.
    if ("MutationObserver" in window) {
      new MutationObserver(syncOverHero).observe(hero, {
        attributes: true,
        attributeFilter: ["class"]
      });
    }
    syncOverHero();
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

  // ---- 2d. Headline lockup — DrawSVG outline ------------------------------
  // The lockup draws itself on when the takeover lands: the box travels first,
  // the letterforms follow on a stagger, then each letter CROSS-FADES from its
  // outline to its fill on its own timeline. GSAP DrawSVGPlugin (free since
  // 3.13, loaded beside SplitText) draws the outline; the fade is plain opacity.
  //
  // TWO-WAY: the whole thing reverses when you scroll off the hero and plays
  // forward again when you come back (user call, Aug 2026).
  //
  // Per-letter is why the markup is grouped: a letter's fill is its subpath
  // plus its counters joined (so the winding keeps the holes open), and the
  // group pairs that fill with the strokes it replaces. One combined fill path
  // could only ever animate as a single object.
  //
  // Two things this depends on, both set up in the markup: the SVG is INLINE
  // (DrawSVG animates stroke-dasharray on real nodes and cannot reach inside an
  // <img>), and the letters are STROKED rather than filled (a fill has no
  // stroke to draw). The fill is a separate question for later — right now the
  // finished state is an outline.
  //
  // The strokes are zeroed at INIT, not at .is-live. The CSS gate flips the
  // lockup's opacity to 1 the instant .is-live lands, so a lockup still sitting
  // at full length would flash complete for a frame before the draw reset it.
  //
  // Everything degrades to "already drawn": no GSAP, no plugin, or reduced
  // motion all leave the strokes alone, and a stroke with no dasharray is a
  // finished line. Nothing here has to run for the lockup to be correct.
  var lockupSvg = document.querySelector("[data-lockup-draw]");
  var drawOk = gsapOk && typeof window.DrawSVGPlugin !== "undefined";
  if (drawOk) { try { window.gsap.registerPlugin(window.DrawSVGPlugin); } catch (e) { drawOk = false; } }

  if (hero && lockupSvg && drawOk && !reduce) {
    (function () {
      var box = lockupSvg.querySelector(".hero__lockup-box");
      var strokes = Array.prototype.slice.call(
        lockupSvg.querySelectorAll(".hero__lockup-stroke")
      );
      var letters = Array.prototype.slice.call(
        lockupSvg.querySelectorAll(".hero__lockup-letter")
      );
      if (!box || !strokes.length || !letters.length) return;

      window.gsap.set([box].concat(strokes), { drawSVG: "0%" });
      // Init flips both to the OPPOSITE of the resting state, because the CSS
      // rest is where the animation ends, not where it starts. The opacity gate
      // opens on .is-live, so anything still at its finished value would show
      // for a frame before the timeline reset it.
      window.gsap.set(lockupSvg.querySelectorAll(".hero__lockup-fill"), { opacity: 0 });
      window.gsap.set(strokes, { opacity: 1 });

      // BUILT ONCE AND KEPT, paused — not replayed. Scrolling away reverses
      // this timeline (each letter's fill hands back to its outline, the
      // letters un-draw, the box un-draws) and scrolling back up plays it
      // forward again from wherever the reversal reached. Reversing mid-flight
      // is the same call, so a scroll that changes its mind halfway simply
      // turns the playhead around instead of restarting.
      var tl = window.gsap.timeline({ paused: true });
      (function () {
        tl.to(box, { drawSVG: "100%", duration: 0.9, ease: "power2.inOut" })
          // Overlapping the box rather than waiting for it: the frame is still
          // closing as the first letters start, which reads as one gesture
          // instead of two queued ones.
          .to(strokes, {
            drawSVG: "100%",
            duration: 0.7,
            ease: "power2.out",
            stagger: 0.045
          }, "-=0.35")
          .addLabel("fill", "-=0.15");

        // THE FILL: every letter on its OWN timeline — a quick fade up of its
        // fill against a fade out of its own outline, the two crossing inside
        // that letter and nowhere else. Both tweens for a letter are placed at
        // the SAME slot, so the hand-off is per letter rather than one wipe
        // travelling across all of them.
        //
        // RANDOM ORDER (user call, Aug 2026), not the reading order the groups
        // sit in. Shuffled explicitly rather than with GSAP's
        // stagger:{from:"random"}: that shuffles per TWEEN, and a letter's fill
        // and its outline are two tweens — they would each draw their own order
        // and the hand-off would come apart. Assigning the slot once and giving
        // it to both keeps every letter's pair welded together.
        //
        // Shuffled at BUILD time, so the order is fixed for the page: reversing
        // retreats through the same order it arrived in, and scrolling back
        // plays that same order forward. A fresh shuffle per replay would make
        // the reverse contradict what you just watched.
        // Tuned together (user call, Aug 2026: "slightly longer fade, random
        // stagger tightened up"). The two pull in opposite directions on how
        // many letters are in flight at once: a longer FADE and a shorter STEP
        // both raise it. At 0.4 over 0.03 roughly thirteen of the fourteen
        // overlap, so the lockup reads as one soft settle rather than a
        // countable sequence — which is the point of randomising the order.
        // Total is barely changed (13*0.03 + 0.4 = 0.79s against 0.87s before);
        // it is the density that moved, not the length.
        var STEP = 0.03;    // slot-to-slot offset — tighter
        var FADE = 0.4;     // one letter's cross-fade — a touch longer
        var slots = letters.map(function (_, i) { return i; });
        for (var si = slots.length - 1; si > 0; si--) {   // Fisher-Yates
          var sj = Math.floor(Math.random() * (si + 1));
          var tmp = slots[si]; slots[si] = slots[sj]; slots[sj] = tmp;
        }
        letters.forEach(function (g, i) {
          var at = "fill+=" + (slots[i] * STEP).toFixed(3);
          tl.to(g.querySelector(".hero__lockup-fill"),
                { opacity: 1, duration: FADE, ease: "power1.out" }, at)
            .to(g.querySelectorAll(".hero__lockup-stroke"),
                { opacity: 0, duration: FADE, ease: "power1.out" }, at);
        });
      })();

      // .is-exiting is the direction signal, and 2c already toggles it BOTH
      // ways off a scroll threshold (~12svh) — it has had nothing responding
      // to it since the word-rise rules it used to drive were replaced by the
      // device. Forward while the hero is held, reversed once you scroll off.
      //
      // The observer never disconnects: unlike the one-shot it replaced, this
      // has to keep listening for the life of the page. Both calls are
      // idempotent, so the repeated class mutations the header and theme
      // handover make on this same element cost nothing.
      var sync = function () {
        if (!hero.classList.contains("is-live")) return;
        if (hero.classList.contains("is-exiting")) tl.reverse();
        else tl.play();
      };
      new MutationObserver(sync).observe(hero, {
        attributes: true,
        attributeFilter: ["class"]
      });
      // .is-live may already be set — the reduced-motion and Escape-skip paths
      // add it synchronously, and this block can run after either.
      sync();
    })();
  }

  // ---- 3. Image clip + scale reveals — MOVED to js/reveal.js -------------
  // The [data-reveal-img] observer now lives with the page's other
  // scroll-reveals (reveal.js, which this page loads), lifted there when the
  // news article became its third consumer. The reveal-fx CSS stays in
  // home-c.css.

  // ---- 3c. Word-cascade text reveals ([data-reveal-words]) ---------------
  // Promoted from the approved Prose experiment: each word is wrapped in a
  // mask (.sw > .sw__i) indexed with --w; CSS staggers the rise on
  // .is-revealed. The splitter walks TEXT NODES only, so inline elements
  // (links) survive intact — their words are masked inside them. Plain-text
  // elements get the SplitText-style aria shim (aria-label + hidden words);
  // elements containing links must NOT (it would hide the links from AT), so
  // there the split spans simply read in document order. Skipped under reduced
  // motion (text is left exactly as authored).
  var wordEls = Array.prototype.slice.call(document.querySelectorAll("[data-reveal-words]"));
  if (wordEls.length && !reduce) {
    wordEls.forEach(function (el) {
      var hasLinks = !!el.querySelector("a");
      if (!hasLinks) {
        // Respect an authored aria-label (e.g. "More work" on a link whose
        // visible text is just "More") — only shim one from the text when
        // the markup didn't provide its own.
        if (!el.hasAttribute("aria-label")) {
          el.setAttribute("aria-label", el.textContent.replace(/\s+/g, " ").trim());
        }
      }
      var idx = 0;
      var walk = function (node) {
        if (node.nodeType === 3) {
          var frag = document.createDocumentFragment();
          node.textContent.split(/(\s+)/).forEach(function (part) {
            if (!part) return;
            if (!part.trim()) { frag.appendChild(document.createTextNode(" ")); return; }
            var w = document.createElement("span");
            w.className = "sw";
            if (!hasLinks) w.setAttribute("aria-hidden", "true");
            var wi = document.createElement("span");
            wi.className = "sw__i";
            wi.textContent = part;
            wi.style.setProperty("--w", idx++);
            w.appendChild(wi);
            frag.appendChild(w);
          });
          node.parentNode.replaceChild(frag, node);
        } else if (node.nodeType === 1) {
          Array.prototype.slice.call(node.childNodes).forEach(walk);
        }
      };
      Array.prototype.slice.call(el.childNodes).forEach(walk);
    });
    if (!hasIO) {
      wordEls.forEach(function (el) { el.classList.add("is-revealed"); });
    } else {
      var wIO = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (!e.isIntersecting) return;
          e.target.classList.add("is-revealed");
          wIO.unobserve(e.target);
        });
      }, { threshold: 0.2, rootMargin: "0px 0px -8% 0px" });
      wordEls.forEach(function (el) { wIO.observe(el); });
    }
  }

  // ---- 3d. Intro filmstrip (promoted from the Strip experiment;
  // weareboring.nl reference). An infinite marquee on gsap.ticker: a position
  // counter wraps over half the duplicated track width. Pointer drag overrides
  // it 1:1 and hands its release velocity to a decaying momentum; while a drag
  // (or its momentum) is live the cards STRAIGHTEN out of their scattered
  // tilts and lean together with it, settling back once it dies — every card
  // rotation is owned by one per-card lerp here, so hover, drag and settle
  // never fight. A fling RE-POINTS the drift, so the strip carries on in the
  // direction you threw it — the clients marquee's rule (3g), adopted here so
  // the page's two marquees behave alike (user call, Aug 2026). Hovering a
  // card straightens and lifts it; hovering no longer stalls the autoplay, and
  // the DRAG badge is gone — the clients marquee never had either. In the band
  // below, the
  // sticky "Our expertise" label locks in the viewport while the list scrolls
  // past it, and whichever item sits beside it is inked. Reduced motion: no
  // autoplay, lean, or badge; drag still works.
  (function () {
    // Autoplay resilience for the logo-mark video: browsers leave muted videos
    // paused when they load below the fold or while the tab is hidden — kick
    // play() on enter-view and tab-visible.
    var mark = document.querySelector("[data-intro-mark]");
    if (mark) {
      var kick = function () {
        if (mark.paused && document.visibilityState === "visible") mark.play().catch(function () {});
      };
      if (hasIO) {
        var mIO = new IntersectionObserver(function (entries) {
          entries.forEach(function (e) { if (e.isIntersecting) kick(); });
        }, { threshold: 0.1 });
        mIO.observe(mark);
      }
      document.addEventListener("visibilitychange", kick);
    }

    var viewport = document.querySelector("[data-strip]");
    if (viewport && gsapOk) {
      var track = viewport.querySelector(".intro__track");

      // Duplicate the set once for a seamless half-width wrap.
      var originals = Array.prototype.slice.call(track.children);
      originals.forEach(function (card) {
        var clone = card.cloneNode(true);
        clone.setAttribute("aria-hidden", "true");
        track.appendChild(clone);
      });

      var cards = Array.prototype.slice.call(track.children);
      // Hand each card's tilt to gsap transforms (tweening the --r custom
      // property strips its unit mid-tween and kills `rotate: var(--r)`).
      var bases = [], curs = [];
      cards.forEach(function (card, i) {
        var base = parseFloat(getComputedStyle(card).getPropertyValue("--r")) || 0;
        bases[i] = base;
        curs[i] = base;
        card.style.rotate = "0deg";
        window.gsap.set(card, { rotation: base });
      });
      var hoverCard = null;

      var loop = 0;
      var measure = function () { loop = track.scrollWidth / 2; };
      measure();
      window.addEventListener("resize", measure, { passive: true });

      var pos = 0;         // marquee position, px
      var SPEED = STRIP_SPEED; // autoplay px/s — shared with the clients marquee
      var vel = 0;         // momentum px/s after a drag
      var lean = 0;        // smoothed drag tilt
      var dir = 1;         // drift direction — a fling re-points it (3g's rule)
      var dragging = false;
      var dragVel = 0;

      window.gsap.ticker.add(function (time, deltaMS) {
        var dt = deltaMS / 1000;
        if (!dragging) {
          var auto = reduce ? 0 : SPEED * dir;
          pos += (auto + vel) * dt;
          vel *= Math.pow(0.9, dt * 60); // exponential decay, frame-rate independent
          if (Math.abs(vel) < 1) vel = 0;
        }
        if (loop > 0) pos = ((pos % loop) + loop) % loop;

        var active = !reduce && (dragging || Math.abs(vel) > 60);
        var tiltTarget = active ? window.gsap.utils.clamp(-10, 10, (dragging ? dragVel : vel) / 120) : 0;
        lean += (tiltTarget - lean) * Math.min(1, dt * 8);
        for (var i = 0; i < cards.length; i++) {
          var target = active ? lean : (cards[i] === hoverCard ? 0 : bases[i]);
          curs[i] += (target - curs[i]) * Math.min(1, dt * (active ? 10 : 5));
          window.gsap.set(cards[i], { rotation: curs[i] });
        }
        window.gsap.set(track, { x: -pos });
      });

      // Drag (manual, with tracked velocity for the momentum handoff).
      var startX = 0, startPos = 0, lastX = 0, lastT = 0, moved = 0;
      viewport.addEventListener("pointerdown", function (e) {
        dragging = true;
        moved = 0;
        startX = lastX = e.clientX;
        startPos = pos;
        lastT = performance.now();
        dragVel = 0;
        vel = 0;
        viewport.classList.add("is-dragging");
        try { viewport.setPointerCapture(e.pointerId); } catch (err) {}
      });
      viewport.addEventListener("pointermove", function (e) {
        if (!dragging) return;
        var now = performance.now();
        var dx = e.clientX - lastX;
        moved += Math.abs(dx);
        pos = startPos - (e.clientX - startX);
        if (now - lastT > 0) dragVel = (-dx) / ((now - lastT) / 1000);
        lastX = e.clientX;
        lastT = now;
      });
      var endDrag = function () {
        if (!dragging) return;
        dragging = false;
        viewport.classList.remove("is-dragging");
        vel = reduce ? 0 : window.gsap.utils.clamp(-2200, 2200, dragVel);
        // THE DRIFT FOLLOWS THE DRAG, the clients marquee's rule verbatim
        // (3g): velocity decides it for a real fling, net displacement for a
        // slow carry, and a tap — under either threshold — changes nothing.
        if (Math.abs(vel) > 40) dir = vel > 0 ? 1 : -1;
        else if (Math.abs(pos - startPos) > 6) dir = pos > startPos ? 1 : -1;
        dragVel = 0;
      };
      viewport.addEventListener("pointerup", endDrag);
      viewport.addEventListener("pointercancel", endDrag);
      // A real drag must not land as a click on a card.
      viewport.addEventListener("click", function (e) {
        if (moved > 6) { e.preventDefault(); e.stopPropagation(); }
      }, true);

      // Hover straightens the hovered card and lifts it. The marquee no
      // longer STALLS on hover — removed with the DRAG badge (user call, Aug
      // 2026), matching the clients marquee, which never had either.
      viewport.addEventListener("mouseover", function (e) {
        var card = e.target.closest(".intro__card");
        if (!card || dragging) return;
        hoverCard = card; // the ticker lerps its rotation to 0
        // Straighten AND zoom (user call, Aug 2026) — 1.04 was too slight to
        // read next to the straightening, which is the louder half of the
        // gesture. The card is already lifted to z-index 3 below, so it grows
        // over its neighbours rather than into them.
        window.gsap.to(card, { scale: 1.12, duration: reduce ? 0 : 0.45, ease: "power3.out" });
        card.style.zIndex = 3;
      });
      viewport.addEventListener("mouseout", function (e) {
        var card = e.target.closest(".intro__card");
        if (!card || (e.relatedTarget && card.contains(e.relatedTarget))) return;
        if (hoverCard === card) hoverCard = null; // ticker settles it back
        window.gsap.to(card, {
          scale: 1,
          duration: reduce ? 0 : 0.5,
          ease: "power3.out",
          onComplete: function () { card.style.zIndex = ""; }
        });
      });

    }

    // Expertise scroll-highlight: the "Our expertise" label is CSS-sticky, so
    // it locks in the viewport while the list scrolls up past it; here we ink
    // whichever item sits beside the locked label (nearest row centre). Works
    // in every phase — before the label sticks it sits level with the first
    // item, after its travel it rests beside the last. rAF-coalesced.
    var exLinks = Array.prototype.slice.call(document.querySelectorAll(".intro__expertise-list a"));
    var exLabel = document.querySelector(".intro__expertise-label");
    if (exLinks.length && exLabel) {
      var exRaf = 0;
      var exUpdate = function () {
        exRaf = 0;
        var lr = exLabel.getBoundingClientRect();
        var labelMid = lr.top + lr.height / 2;
        var best = 0, bestDist = Infinity;
        exLinks.forEach(function (a, i) {
          var r = a.getBoundingClientRect();
          var d = Math.abs(r.top + r.height / 2 - labelMid);
          if (d < bestDist) { bestDist = d; best = i; }
        });
        exLinks.forEach(function (a, i) { a.classList.toggle("is-active", i === best); });
      };
      var exSchedule = function () { if (!exRaf) exRaf = requestAnimationFrame(exUpdate); };
      window.addEventListener("scroll", exSchedule, { passive: true });
      window.addEventListener("resize", exSchedule, { passive: true });
      exUpdate();
    }
  })();

  // ---- 3e. Scroll-velocity card SKEW ------------------------------------
  // The GreenSock skew-on-scroll gesture (user call, Aug 2026, referencing
  // codepen GreenSock/eYpGLYL): scroll speed drives a skewY on the cards and
  // it springs back to flat as the scroll settles. This replaced a velocity
  // ROTATION — the same sampler, the same decay, a different transform: a
  // rotation twisted the whole card off-square, where a skew shears it and
  // reads as speed rather than as a wonky tile.
  //
  // The cap is 3deg, NOT the reference's 20. That demo skews full-width text
  // rows in a plain stack, where a big shear reads as motion blur; these are
  // cards in a grid with hairlines between them, and skewY displaces the
  // edges by width*tan(angle) — on a 649px card that is 34px edge-to-edge at
  // 3deg and 91px at 8deg, which swings the corners into the neighbouring row.
  // 3 sits a step above the 2.5deg rotation it replaces (the shear per degree
  // is about the same as the old twist's, so the previous caps were already
  // calibrated for this element width) — enough that the gesture reads as a
  // shear rather than a wobble, without breaking the grid.
  //
  // NB the news row also carries view-timeline-name: --news-card, so the
  // transform written here lands on the very element anchoring the image
  // parallax — worth re-checking if the cap grows.
  //
  // gsap.quickSetter, as the reference uses: it skips the tween machinery
  // and writes the transform directly, which matters on a per-frame setter.
  // One shared velocity sampler + one ticker drives every group.
  // Skipped under reduced motion / without gsap.
  (function () {
    if (!gsapOk || reduce) return;

    var GROUPS = [
      { cards: ".news-card", within: ".news", max: 3 },
      { cards: ".work__item", within: ".work", max: 3 }
    ];

    var groups = [];
    GROUPS.forEach(function (cfg) {
      var host = document.querySelector(cfg.within);
      if (!host) return;
      var cards = Array.prototype.slice.call(host.querySelectorAll(cfg.cards));
      if (!cards.length) return;
      // One setter for the whole group; transform-origin is set once so the
      // shear pivots on the card's centre rather than a corner.
      window.gsap.set(cards, { transformOrigin: "center center", force3D: true });
      var g = {
        cards: cards,
        set: window.gsap.quickSetter(cards, "skewY", "deg"),
        max: cfg.max, cur: 0, inView: !hasIO
      };
      if (hasIO) {
        // Only lerp while the section is near the viewport.
        new IntersectionObserver(function (entries) {
          g.inView = entries[0].isIntersecting;
        }, { rootMargin: "20% 0px" }).observe(host);
      }
      groups.push(g);
    });
    if (!groups.length) return;

    var VEL_AT_MAX = 2600;   // scroll px/s that reaches a group's full skew
    var lastY = window.scrollY;
    var lastT = performance.now();
    var vel = 0;             // sampled scroll velocity, px/s

    window.addEventListener("scroll", function () {
      var now = performance.now();
      var dt = (now - lastT) / 1000;
      if (dt > 0) {
        // clamp out the huge spikes a programmatic jump would produce
        vel = window.gsap.utils.clamp(-12000, 12000, (window.scrollY - lastY) / dt);
      }
      lastY = window.scrollY;
      lastT = now;
    }, { passive: true });

    window.gsap.ticker.add(function (time, deltaMS) {
      var dt = deltaMS / 1000;
      // Decay the sampled velocity so the skew settles when scrolling stops
      // (the scroll event stops firing, so nothing else would zero it).
      vel *= Math.pow(0.86, dt * 60);
      if (Math.abs(vel) < 20) vel = 0;
      var norm = vel / VEL_AT_MAX;
      for (var g = 0; g < groups.length; g++) {
        var grp = groups[g];
        if (!grp.inView) continue;
        var target = window.gsap.utils.clamp(-grp.max, grp.max, norm * grp.max);
        grp.cur += (target - grp.cur) * Math.min(1, dt * 7);
        if (Math.abs(grp.cur) < 0.01) grp.cur = 0;
        grp.set(grp.cur);
      }
    });
  })();

  // ---- 3f. Card cursor tilt (work + news) -------------------------------
  // Feeds the frame's --mx/--my (-1..1 across the card) from the pointer, so
  // the CSS can lean the mask a couple of degrees toward the cursor while the
  // mask itself draws in. Values are written on the ITEM and inherit down, and
  // are reset on leave so the frame settles flat. rAF-coalesced; pointer
  // devices only, and skipped under reduced motion.
  if (!reduce && window.matchMedia("(hover: hover)").matches) {
    Array.prototype.slice.call(document.querySelectorAll(".work__item, .news-card")).forEach(function (item) {
      var raf = 0, mx = 0, my = 0;
      var apply = function () {
        raf = 0;
        item.style.setProperty("--mx", mx.toFixed(3));
        item.style.setProperty("--my", my.toFixed(3));
      };
      item.addEventListener("pointermove", function (e) {
        var r = item.getBoundingClientRect();
        if (!r.width || !r.height) return;
        // -1 at the left/top edge, +1 at the right/bottom
        mx = ((e.clientX - r.left) / r.width) * 2 - 1;
        my = ((e.clientY - r.top) / r.height) * 2 - 1;
        if (!raf) raf = requestAnimationFrame(apply);
      }, { passive: true });
      item.addEventListener("pointerleave", function () {
        if (raf) { cancelAnimationFrame(raf); raf = 0; }
        mx = my = 0;
        apply();
      });
    });
  }

  // ---- 3g. Clients logo marquee — counter-drifting, mirror-linked rows ---
  // The intro filmstrip's drift + drag (3d: same STRIP_SPEED, same 1:1 drag,
  // same decaying momentum), but the two rows are ONE mechanism, not two:
  // a single shared phase drives both, row 1 reading it straight and row 2
  // negated (the .clients__scroller--reverse polarity). Everything the user
  // asked of the pair falls out of that one number:
  //   · at rest the rows counter-drift — one phase, two signs;
  //   · dragging EITHER row moves the other identically, mirrored, live —
  //     they cannot even drift out of sync, because there is nothing to sync;
  //   · a fling re-points the shared drift, so both rows swap direction
  //     together (each still leading with its own sign).
  // Deliberately NOT the strip's hover-stall — pause-on-hover was removed
  // from this marquee by request (docs/design-system-plan.md) — nor its
  // card-tilt machinery. The CSS keyframes are the no-JS/no-gsap fallback
  // (row 2's reverse comes from the same --reverse class there), switched
  // off via .is-js-marquee so the two mechanisms never both run.
  // Reduced motion: no drift; drag works, and the mirror-link stands — it
  // is user-initiated motion, the same footing as the drag itself.
  (function () {
    var scrollers = Array.prototype.slice.call(document.querySelectorAll(".clients__scroller"));
    if (!scrollers.length || !gsapOk) return;

    var pos = 0;   // THE shared phase — the only moving part
    var vel = 0;
    var dir = 1;
    var draggingOn = null; // the scroller currently held, if any

    var rows = [];
    scrollers.forEach(function (scroller) {
      var tracks = Array.prototype.slice.call(scroller.querySelectorAll(".clients__track"));
      if (tracks.length !== 2) return;
      scroller.classList.add("is-js-marquee");
      var row = {
        el: scroller,
        tracks: tracks,
        // row 2 carries --reverse: it reads the phase negated, in the CSS
        // fallback and here alike, so the two mechanisms agree about it
        polarity: scroller.classList.contains("clients__scroller--reverse") ? -1 : 1,
        loop: 0
      };
      var measure = function () { row.loop = tracks[0].offsetWidth; };
      measure();
      window.addEventListener("resize", measure, { passive: true });
      rows.push(row);
    });
    if (!rows.length) return;

    window.gsap.ticker.add(function (time, deltaMS) {
      var dt = deltaMS / 1000;
      if (!draggingOn) {
        var auto = reduce ? 0 : STRIP_SPEED * dir;
        pos += (auto + vel) * dt;
        vel *= Math.pow(0.9, dt * 60); // same decay as the strip
        if (Math.abs(vel) < 1) vel = 0;
      }
      for (var i = 0; i < rows.length; i++) {
        var r = rows[i];
        if (!(r.loop > 0)) continue;
        var local = ((r.polarity * pos) % r.loop + r.loop) % r.loop;
        window.gsap.set(r.tracks[0], { x: -local });
        window.gsap.set(r.tracks[1], { x: -local });
      }
    });

    rows.forEach(function (row) {
      var scroller = row.el;
      var startX = 0, startPos = 0, lastX = 0, lastT = 0, moved = 0, dragVel = 0;
      scroller.addEventListener("pointerdown", function (e) {
        draggingOn = scroller;
        moved = 0;
        startX = lastX = e.clientX;
        startPos = pos;
        lastT = performance.now();
        dragVel = 0;
        vel = 0;
        // both rows are moving under this one gesture — show it on both
        rows.forEach(function (r) { r.el.classList.add("is-dragging"); });
        try { scroller.setPointerCapture(e.pointerId); } catch (err) {}
      });
      scroller.addEventListener("pointermove", function (e) {
        if (draggingOn !== scroller) return;
        var now = performance.now();
        var dx = e.clientX - lastX;
        moved += Math.abs(dx);
        // 1:1 under the hand that is dragging; the other row gets the
        // negation through its polarity at render
        pos = startPos - row.polarity * (e.clientX - startX);
        if (now - lastT > 0) dragVel = (-row.polarity * dx) / ((now - lastT) / 1000);
        lastX = e.clientX;
        lastT = now;
      });
      var endDrag = function () {
        if (draggingOn !== scroller) return;
        draggingOn = null;
        rows.forEach(function (r) { r.el.classList.remove("is-dragging"); });
        vel = window.gsap.utils.clamp(-2200, 2200, dragVel); // momentum under reduce too, like the strip
        // THE DRIFT FOLLOWS THE DRAG — in shared-phase terms, so both rows
        // re-point together, each still reading its own sign. Velocity for a
        // real fling, net displacement for a slow carry, a tap changes
        // nothing.
        if (Math.abs(vel) > 40) dir = vel > 0 ? 1 : -1;
        else if (Math.abs(pos - startPos) > 6) dir = pos > startPos ? 1 : -1;
        dragVel = 0;
      };
      scroller.addEventListener("pointerup", endDrag);
      scroller.addEventListener("pointercancel", endDrag);
      // a real drag must not land as a click if these cards ever become links
      scroller.addEventListener("click", function (e) {
        if (moved > 6) { e.preventDefault(); e.stopPropagation(); }
      }, true);
    });
  })();

  // ---- 4. SplitText line-mask text reveals -------------------------------
  var texts = Array.prototype.slice.call(document.querySelectorAll("[data-reveal-text]"));
  if (texts.length && splitOk && !reduce && hasIO) {
    var setup = function () {
      texts.forEach(function (el) {
        var split;
        try {
          split = new window.SplitText(el, { type: "lines", mask: "lines", linesClass: "split-line" });
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
