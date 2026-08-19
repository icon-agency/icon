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

  // ---- 2. Hero — stack takeover -----------------------------------------
  // The work pieces load in one by one: each POPS in at the viewport centre
  // (only once its own media is displayable, raced against a timeout so one
  // slow file can't stall the line), then shrinks onto the pile. After the
  // last lands there's a 0.5s beat, then it scales up to cover the viewport
  // and hands off to the reel: 3s per piece, 0.1s cross-fades, videos playing
  // while active, stills on a CSS Ken Burns. .is-live lands exactly at the end
  // of the takeover — it starts the lockup's word cascade, the scrim and the
  // client name. Reduced motion: no choreography, just the reel.
  var hero = document.querySelector("[data-hero]");
  if (hero) {
    (function () {
      var cards = Array.prototype.slice.call(hero.querySelectorAll(".hero__card"));
      var slides = Array.prototype.slice.call(hero.querySelectorAll(".hero__slide"));
      var label = hero.querySelector("[data-client-label]");
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
        if (label) label.textContent = slides[i].getAttribute("data-client");
      };

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

      var seq = Promise.resolve();
      cards.forEach(function (card) {
        seq = seq
          .then(function () { return mediaReady(card); })
          .then(function () {
            card.classList.add("is-pop");
            return wait(190); // a quick punch in…
          })
          .then(function () {
            card.classList.add("is-stacked"); // …then straight onto the pile
            return wait(90);
          });
      });
      seq
        .then(function () { return wait(1100); }) // hold on the finished pile…
        .then(function () {
          var last = cards[cards.length - 1];
          // uniform scale that covers the viewport from the card's laid-out size
          var scale = Math.max(
            window.innerWidth / last.offsetWidth,
            window.innerHeight / last.offsetHeight
          ) * 1.02;
          last.style.setProperty("--cover-scale", scale);
          last.classList.add("is-takeover");
          return wait(750); // ride the takeover transition
        })
        .then(function () {
          activate(slides.length - 1); // reel starts on the takeover piece
          hero.classList.add("is-live"); // stage fades off; lockup rises
          loop(slides.length - 1);
        });
    })();
  }

  // ---- 2b. Header inversion over the hero --------------------------------
  // The hero opens on the PAGE ground (white in the light theme) while the
  // pile builds, so the header must read in the normal theme there — it only
  // flips to light-on-dark (.is-over-hero) once the takeover has covered the
  // screen with media (.is-live on the hero). Two inputs, so both are folded
  // into one sync: the hero must be behind the header AND live. The colour
  // cross-fade is CSS (transitions on the header). Header height ≈ 80px.
  var siteHeader = document.querySelector(".site-header");
  if (siteHeader && hero) {
    var heroBehindHeader = hero.getBoundingClientRect().bottom > 80;
    var syncOverHero = function () {
      siteHeader.classList.toggle(
        "is-over-hero",
        heroBehindHeader && hero.classList.contains("is-live")
      );
    };
    if (hasIO) {
      var heroHeaderIO = new IntersectionObserver(function (entries) {
        heroBehindHeader = entries[0].isIntersecting;
        syncOverHero();
      }, { rootMargin: "-80px 0px 0px 0px", threshold: 0 });
      heroHeaderIO.observe(hero);
    } else {
      window.addEventListener("scroll", function () {
        heroBehindHeader = hero.getBoundingClientRect().bottom > 80;
        syncOverHero();
      }, { passive: true });
    }
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
        el.setAttribute("aria-label", el.textContent.replace(/\s+/g, " ").trim());
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
      { cards: ".news__card", within: ".news", max: 7 },
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
