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

  // ---- 1b. News: a sideways gesture must never scroll the PAGE -----------
  // A trackpad swipe is never purely horizontal — it carries an incidental
  // deltaY. Lenis (and native scroll) read deltaY, so a sideways swipe over
  // the news section scrolled the page vertically: in pinned mode that drove
  // the sweep and then kept going past the section; in the short-viewport
  // fallback it chained past the row's last card. One rule fixes both —
  // if the gesture is horizontal-dominant we own it completely:
  //   · fallback (track is a real scroller) → pan the row, clamped at its ends
  //   · pinned (track is animation-driven)  → do nothing; the page holds still
  // Vertical-dominant gestures fall through untouched, so ordinary scrolling
  // (and the pin's own choreography) behaves exactly as before.
  // Listener sits on the section and stops propagation, so Lenis's
  // window-level handler never sees the event. CSS overscroll-behavior-x
  // covers the equivalent touch case.
  var newsSection = document.querySelector(".news");
  var newsTrack = newsSection && newsSection.querySelector(".news__track");
  if (newsTrack) {
    var newsFallbackMq = window.matchMedia(
      "(max-height: 780px) and (min-aspect-ratio: 1/1), (min-aspect-ratio: 2/1)"
    );
    var newsTimelineOk =
      window.CSS && CSS.supports && CSS.supports("animation-timeline: scroll()");
    var trackIsScroller = function () {
      return !newsTimelineOk || reduce || newsFallbackMq.matches;
    };
    var syncNewsPrevent = function () {
      if (trackIsScroller()) newsTrack.setAttribute("data-lenis-prevent-wheel", "");
      else newsTrack.removeAttribute("data-lenis-prevent-wheel");
    };
    syncNewsPrevent();
    if (newsFallbackMq.addEventListener) {
      newsFallbackMq.addEventListener("change", syncNewsPrevent);
    }

    // Snap has to be suspended while WE drive scrollLeft: scroll snapping is
    // re-applied after every programmatic scroll, so `proximity` yanked the row
    // straight back to the nearest card and it never moved. Restore it once the
    // gesture settles, which doubles as the snap-to-card landing.
    var snapTimer;
    newsSection.addEventListener(
      "wheel",
      function (e) {
        if (Math.abs(e.deltaX) <= Math.abs(e.deltaY)) return; // vertical intent
        e.preventDefault();
        e.stopPropagation();
        if (!trackIsScroller()) return; // pinned: swallow it, page holds still
        newsTrack.style.scrollSnapType = "none";
        newsTrack.scrollLeft += e.deltaX; // clamps at 0 / max — never chains out
        clearTimeout(snapTimer);
        snapTimer = setTimeout(function () {
          newsTrack.style.scrollSnapType = "";
        }, 140);
      },
      { passive: false }
    );
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

      var mediaReady = function (card) {
        return new Promise(function (res) {
          var m = card.querySelector("img, video");
          if (!m) return res();
          if (m.tagName === "IMG") {
            if (m.complete) return res();
            m.addEventListener("load", res, { once: true });
            m.addEventListener("error", res, { once: true });
          } else {
            if (m.readyState >= 2) return res();
            m.addEventListener("loadeddata", res, { once: true });
            m.addEventListener("error", res, { once: true });
          }
          setTimeout(res, 1500); // never let one file stall the line
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
        activate(0);
        loop(0);
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

      // Fixed rhythm (k95 values, scaled to our card count). The CSS owns the
      // easing; this clock only schedules class-adds and drives the counter.
      var DUR = 680;      // one card's open (matches the CSS transition)
      var STAGGER = 200;  // next card starts this far in — opens overlap
      var HOLD = 800;     // beat on the finished pile
      var TAKEOVER = 1000; // matches the takeover transition in CSS
      var animEnd = DUR + (cards.length - 1) * STAGGER;

      if (count) count.textContent = "(0)";
      Promise.all(cards.map(mediaReady)).then(function () {
        var t0 = performance.now();
        var added = 0;
        var tick = function (now) {
          var t = now - t0;
          while (added < cards.length && t >= added * STAGGER) {
            cards[added].classList.add("is-pop", "is-stacked");
            // The LAST card is the one the reel continues from — its video
            // plays on the pile so motion never stops across the hand-off.
            if (added === cards.length - 1) {
              var lastVid = cards[added].querySelector("video");
              if (lastVid) lastVid.play().catch(function () {});
            }
            added += 1;
          }
          var p = Math.min(1, t / animEnd);
          if (count) count.textContent = "(" + Math.round(p * 100) + ")";
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
      });
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

  // ---- 2d. Hero theme: blue at the top, light once you scroll on ---------
  // The page opens on .theme-blue (set in the markup so it paints blue with no
  // flash) and hands over to the light theme as the intro curtain takes the
  // viewport. Anchored to the INTRO's top edge (the hero is pinned and never
  // scrolls away): blue while the intro is still below the viewport's
  // midpoint, light after — symmetric, so scrolling back returns the blue.
  // The cross-fade is the universal theme transition (src/base/typography.css).
  //
  // Stands down if the visitor has explicitly picked a theme with the dev
  // toggle (js/theme-toggle.js persists `icon-theme`), so that tool still wins.
  if (hero && introSec) {
    var themePinned = false;
    try { themePinned = !!localStorage.getItem("icon-theme"); } catch (e) {}

    if (!themePinned) {
      var syncHeroTheme = function () {
        document.documentElement.classList.toggle(
          "theme-blue",
          introSec.getBoundingClientRect().top > window.innerHeight * 0.75
        );
      };
      window.addEventListener("scroll", syncHeroTheme, { passive: true });
      window.addEventListener("resize", syncHeroTheme);
      syncHeroTheme();
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

  // ---- 3b. Clients marquee pause (WCAG 2.2.2) ----------------------------
  // Toggles .is-paused on the section (CSS pauses all three tracks) and keeps
  // aria-pressed + the label in sync. The button is CSS-hidden under reduced
  // motion, where the marquee never animates.
  var clientsPause = document.querySelector("[data-clients-pause]");
  if (clientsPause) {
    var clientsSection = clientsPause.closest(".clients");
    clientsPause.addEventListener("click", function () {
      var paused = clientsSection.classList.toggle("is-paused");
      clientsPause.setAttribute("aria-pressed", paused ? "true" : "false");
      clientsPause.setAttribute("aria-label", paused ? "Play client name animation" : "Pause client name animation");
    });
  }

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
  // never fight. Hovering the strip stalls the autoplay and straightens the
  // hovered card; a DRAG badge chases the cursor. In the band below, the
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
      var badge = document.querySelector("[data-strip-badge]");

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
      var SPEED = 55;      // autoplay px/s
      var vel = 0;         // momentum px/s after a drag
      var lean = 0;        // smoothed drag tilt
      var stalled = false; // hover stall
      var dragging = false;
      var dragVel = 0;

      window.gsap.ticker.add(function (time, deltaMS) {
        var dt = deltaMS / 1000;
        if (!dragging) {
          var auto = (reduce || stalled) ? 0 : SPEED;
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
        dragVel = 0;
      };
      viewport.addEventListener("pointerup", endDrag);
      viewport.addEventListener("pointercancel", endDrag);
      // A real drag must not land as a click on a card.
      viewport.addEventListener("click", function (e) {
        if (moved > 6) { e.preventDefault(); e.stopPropagation(); }
      }, true);

      // Hover: stall the marquee + straighten the hovered card.
      viewport.addEventListener("mouseenter", function () { stalled = true; });
      viewport.addEventListener("mouseleave", function () { stalled = false; });
      viewport.addEventListener("mouseover", function (e) {
        var card = e.target.closest(".intro__card");
        if (!card || dragging) return;
        hoverCard = card; // the ticker lerps its rotation to 0
        window.gsap.to(card, { scale: 1.04, duration: reduce ? 0 : 0.45, ease: "power3.out" });
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

      // DRAG badge chases the cursor over the strip.
      if (badge && !reduce) {
        var bx = window.gsap.quickTo(badge, "x", { duration: 0.35, ease: "power3.out" });
        var by = window.gsap.quickTo(badge, "y", { duration: 0.35, ease: "power3.out" });
        viewport.addEventListener("pointermove", function (e) { bx(e.clientX); by(e.clientY); });
        viewport.addEventListener("mouseenter", function (e) {
          window.gsap.set(badge, { x: e.clientX, y: e.clientY });
          window.gsap.to(badge, { opacity: 1, scale: 1, duration: 0.3, ease: "back.out(2)" });
        });
        viewport.addEventListener("mouseleave", function () {
          window.gsap.to(badge, { opacity: 0, scale: 0.6, duration: 0.25, ease: "power2.in" });
        });
      }
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

  // ---- 3e. Scroll-velocity card lean ------------------------------------
  // The same fluid tilt as the intro strip's drag lean, but driven by SCROLL
  // SPEED instead of pointer speed: the cards lean into the movement and
  // straighten as the scroll settles. Applied to the Latest track (whose
  // horizontal sweep is a CSS scroll-timeline on the TRACK, so owning rotation
  // on the CARDS keeps the two from fighting) and to the work tiles. The work
  // tiles get a much smaller cap: they are half the grid wide, so the same
  // angle would swing their corners into the neighbouring tile.
  // One shared velocity sampler + one ticker drives every group.
  // Skipped under reduced motion / without gsap.
  (function () {
    if (!gsapOk || reduce) return;

    var GROUPS = [
      { cards: ".news__card", within: ".news", max: 3.5 },
      { cards: ".work__item", within: ".work", max: 2.5 }
    ];

    var groups = [];
    GROUPS.forEach(function (cfg) {
      var host = document.querySelector(cfg.within);
      if (!host) return;
      var cards = Array.prototype.slice.call(host.querySelectorAll(cfg.cards));
      if (!cards.length) return;
      var g = { cards: cards, max: cfg.max, cur: 0, inView: !hasIO };
      if (hasIO) {
        // Only lerp while the section is near the viewport.
        new IntersectionObserver(function (entries) {
          g.inView = entries[0].isIntersecting;
        }, { rootMargin: "20% 0px" }).observe(host);
      }
      groups.push(g);
    });
    if (!groups.length) return;

    var VEL_AT_MAX = 2600;   // scroll px/s that reaches a group's full lean
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
      // Decay the sampled velocity so the lean settles when scrolling stops
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
        for (var i = 0; i < grp.cards.length; i++) {
          window.gsap.set(grp.cards[i], { rotation: grp.cur });
        }
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
    Array.prototype.slice.call(document.querySelectorAll(".work__item, .news__card")).forEach(function (item) {
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

  // ---- 3g. Latest: horizontal input drives the pinned sweep -------------
  // The track's sideways sweep is a CSS view timeline hung off the pin, i.e.
  // it advances with VERTICAL scroll. A trackpad swipe (or shift+wheel) sends
  // deltaX, which the page would otherwise ignore — `.page-home` clips
  // overflow-x, so there is nothing to scroll sideways. Rather than animate
  // the track from a second source (which would fight the timeline and drift
  // out of sync), horizontal delta is folded into the VERTICAL scroll: the one
  // timeline still owns the sweep, and both gestures feel identical.
  // Active whenever any part of the panel is ON SCREEN — not just once the pin
  // is fully engaged. Gating on "fully pinned" meant the gesture died while the
  // cards were still in view on the way in and out, which reads as broken.
  // Never when the reduced-motion/small-screen fallback has made the track
  // natively scrollable.
  var newsSticky = document.querySelector(".news__sticky");
  if (newsSticky) {
    window.addEventListener("wheel", function (e) {
      // leave vertical-dominant gestures completely alone
      if (Math.abs(e.deltaX) <= Math.abs(e.deltaY)) return;
      // the fallback turns the track into a real scroller — let it do its job
      if (window.getComputedStyle(newsSticky).position !== "sticky") return;
      var r = newsSticky.getBoundingClientRect();
      if (r.bottom <= 0 || r.top >= window.innerHeight) return; // panel off screen
      e.preventDefault();
      if (lenis && typeof lenis.scrollTo === "function") {
        // feed Lenis its own target so the two never disagree about position
        lenis.scrollTo(lenis.targetScroll + e.deltaX, { immediate: true });
      } else {
        window.scrollBy(0, e.deltaX);
      }
    }, { passive: false });
  }

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
