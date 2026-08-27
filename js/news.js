/* news.js — News & Insights card + listing behaviours. One IIFE; the
 * listing-only concern (the filter) is guarded on its own markup, so the
 * shared card behaviours (tilt, lean) also run on the article pages'
 * "Next up" rail, which loads this file for exactly that.
 *
 * The CARDS are the shared components/news-card.css — the same component the
 * homepage renders — so their hover/parallax behaviours are ported here
 * rather than reinvented (home-c.js drives them with GSAP on the homepage;
 * these pages have no GSAP, so they run on rAF).
 *
 * 1. CATEGORY FILTER — a deliberate FORK of the work-filter.js mechanics
 *    rather than a shared module: that file hardcodes /work URLs and the
 *    work-grid's span-reflow, and in Drupal both pages collapse into one
 *    Views exposed filter anyway, so a JS abstraction here would outlive its
 *    usefulness. Re-anchored to /news:
 *    chips are real links (server-side fallback); JS intercepts, toggles
 *    `hidden` on each story <li> by data-category, marks the active chip
 *    with .is-active + aria-current="true" (removed from the rest, never set
 *    "false"), and syncs the URL (pushState /news or /news?category=slug)
 *    with popstate + ?category deep links honoured on load. The swap runs
 *    inside a View Transition where supported: every card carries a unique
 *    view-transition-name, so survivors GLIDE to their new pair positions
 *    while leavers/arrivals fade (choreography lives in news-list.css).
 *    Unsupported browsers and reduced-motion users get the instant toggle.
 *
 * Drupal: becomes Drupal.behaviors.iconNews attached via `icon/news`; the
 * filter maps to a Views exposed filter on field_news_list_category, and the single
 * card appearance is the Views display. */
(function () {
  "use strict";

  var list = document.querySelector("[data-news-list]");

  /* ---- 1. category filter (listing page only) --------------------------- */
  if (list) (function () {
  var items = Array.prototype.slice.call(list.querySelectorAll(".news-list__item"));
  var chips = Array.prototype.slice.call(document.querySelectorAll("[data-filter]"));
  var slugs = chips.map(function (c) { return c.dataset.filter; });
  var status = document.querySelector("[data-news-status]");
  var pagerStatus = document.querySelector(".pager__status");
  var current = "all";

  // Fluid filter swap: with unique view-transition-names the browser tracks
  // each card across the DOM change — survivors morph to their new slot,
  // leavers/arrivals crossfade. Names are assigned up front (harmless no-op
  // where unsupported); chips get names too so the active state hands over
  // softly instead of snapping while the page is frozen mid-transition.
  var fluid =
    typeof document.startViewTransition === "function" &&
    !window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  items.forEach(function (li, i) {
    li.style.viewTransitionName = "nl-card-" + (i + 1);
  });
  chips.forEach(function (chip, i) {
    chip.style.viewTransitionName = "nl-chip-" + (i + 1);
  });

  // Route every user-triggered filter change through here; the load-time
  // deep-link apply stays instant (nothing to animate FROM yet).
  function transitionTo(slug) {
    if (!fluid) { apply(slug); return; }
    document.startViewTransition(function () { apply(slug); });
  }

  function apply(slug) {
    // an unknown ?category (typo'd deep link) must not silently empty the
    // page with no active chip — fall back to the full list
    if (slugs.indexOf(slug) === -1) slug = "all";
    current = slug;
    // Moving focus BEFORE anything is hidden: hiding the element that holds
    // focus drops it to <body>, so the next Tab restarts at the skip link.
    var active = document.activeElement;
    if (active && list.contains(active)) {
      var owner = active.closest(".news-list__item");
      if (owner && !(slug === "all" || owner.dataset.category === slug)) {
        var chip = chips.filter(function (c) { return c.dataset.filter === slug; })[0];
        if (chip) chip.focus();
      }
    }

    var shown = 0;
    items.forEach(function (li) {
      var show = slug === "all" || li.dataset.category === slug;
      if (show) {
        li.removeAttribute("hidden");
        shown += 1;
        // Filtering is a re-layout, not an entrance (the phrasing and the
        // behaviour are work-filter.js's): a card the scroll observer never
        // reached is still at opacity 0, and filtering it INTO view must not
        // leave a blank slot waiting for a scroll that already happened.
        li.classList.add("is-visible");
        var fig = li.querySelector("[data-reveal-img]");
        if (fig) fig.classList.add("is-revealed");
      } else {
        li.setAttribute("hidden", "");
      }
    });
    chips.forEach(function (chip) {
      var active = chip.dataset.filter === slug;
      chip.classList.toggle("is-active", active);
      if (active) chip.setAttribute("aria-current", "true");
      else chip.removeAttribute("aria-current");
    });
    // The dark opening's handover hook rides the first VISIBLE story — its
    // grid area is the sticky band's track, and news-list.css re-picks the
    // lead the same way — so filtering re-hosts the attribute and tells
    // js/theme-handover.js to re-aim its observer. Without this, hiding the
    // carrier hands the observer an all-zero rect (display:none) and the
    // theme freezes. (Drupal: no JS needed — the filtered view simply
    // renders the attribute on its own first row.)
    var carrier = list.querySelector("[data-theme-handover]");
    var lead = items.filter(function (li) { return !li.hasAttribute("hidden"); })[0];
    if (carrier && lead && carrier !== lead) {
      lead.setAttribute("data-theme-handover", carrier.getAttribute("data-theme-handover"));
      carrier.removeAttribute("data-theme-handover");
      window.dispatchEvent(new Event("icon:theme-rebind"));
    }
    // announce the result (sr-only live region) and keep the pager status
    // honest — the static "Page 1 of 1 (12 stories)" is wrong once filtered
    var msg = slug === "all"
      ? "Showing all " + items.length + " stories"
      : "Showing " + shown + " " + (shown === 1 ? "story" : "stories");
    if (status) status.textContent = msg;
    if (pagerStatus) {
      pagerStatus.textContent = slug === "all"
        ? "Page 1 of 1 (" + items.length + " stories)"
        : "Page 1 of 1 (" + shown + " " + (shown === 1 ? "story" : "stories") + ")";
    }
  }

  chips.forEach(function (chip) {
    chip.addEventListener("click", function (e) {
      // modified clicks (cmd/ctrl/shift/middle) keep native link behaviour
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
      e.preventDefault();
      var slug = chip.dataset.filter;
      if (slug === current) return; // no duplicate history entries
      transitionTo(slug);
      try {
        // NB pushes the Drupal path (/news): reloading it 404s on the static
        // preview server — same accepted prototype trade as work-filter.js
        window.history.pushState(
          { category: slug },
          "",
          slug === "all" ? "/news" : "/news?category=" + slug
        );
      } catch (err) {}
    });
  });

  window.addEventListener("popstate", function () {
    transitionTo(new URLSearchParams(window.location.search).get("category") || "all");
  });

  // Honour a ?category deep link. If the slug is unknown apply() falls back to
  // the full list — and the URL has to be corrected to match, or it keeps
  // advertising a filter that is not applied AND makes the All chip a dead
  // control (its click handler would see slug === current and return, having
  // already called preventDefault, so neither the filter nor the href runs).
  var initial = new URLSearchParams(window.location.search).get("category");
  if (initial && initial !== "all") {
    apply(initial);
    if (current !== initial) {
      try {
        window.history.replaceState({ category: current }, "", "/news");
      } catch (err) {}
    }
  }
  })();

  /* ---- 2. card cursor tilt ------------------------------------------------
     home-c.js 3f, verbatim behaviour on the shared .news-card: feed --mx/--my
     (-1..1 across the card) from the pointer so the CSS can lean the frame a
     couple of degrees toward the cursor while the mask draws in. Written on
     the CARD and inherited; reset on leave so the frame settles flat.
     rAF-coalesced; pointer devices only, and skipped under reduced motion. */
  if (
    !window.matchMedia("(prefers-reduced-motion: reduce)").matches &&
    window.matchMedia("(hover: hover)").matches
  ) {
    // document-wide, not list-scoped: on the article pages the cards live in
    // the "Next up" rail and there is no [data-news-list] at all
    Array.prototype.slice.call(document.querySelectorAll(".news-card")).forEach(function (card) {
      var raf = 0, mx = 0, my = 0;
      var write = function () {
        raf = 0;
        card.style.setProperty("--mx", mx.toFixed(3));
        card.style.setProperty("--my", my.toFixed(3));
      };
      card.addEventListener("pointermove", function (e) {
        var r = card.getBoundingClientRect();
        if (!r.width || !r.height) return;
        // -1 at the left/top edge, +1 at the right/bottom
        mx = ((e.clientX - r.left) / r.width) * 2 - 1;
        my = ((e.clientY - r.top) / r.height) * 2 - 1;
        if (!raf) raf = requestAnimationFrame(write);
      }, { passive: true });
      card.addEventListener("pointerleave", function () {
        if (raf) { cancelAnimationFrame(raf); raf = 0; }
        mx = my = 0;
        write();
      });
    });
  }

  /* ---- 3. media clip + scale reveals — MOVED to js/reveal.js ------------
     The [data-reveal-img] observer was lifted to reveal.js (loaded by every
     page) when the news article became its third consumer. */

  /* ---- 4. scroll-velocity lean -------------------------------------------
     The homepage card lean (home-c.js 3e), ported without GSAP: sample scroll
     velocity, decay it when scrolling stops, lerp toward a clamped lean and
     write ONE custom property on the host per frame — every card inherits
     var(--nl-lean). The host is the listing where there is one, else the
     article's "Next up" rail (each page ships its own CSS consumer:
     news-list.css rows / news-article.css rail cards). The rAF loop only
     runs while there is motion to settle, and an observer idles the whole
     thing when the host is offscreen. */
  var leanHost = list || document.querySelector(".news-article__related");
  if (leanHost && !window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    (function () {
      var MAX = 1.2;          // deg — full-width rows swing more px per degree
      var VEL_AT_MAX = 2600;  // scroll px/s that reaches the full lean
      var clamp = function (v, lo, hi) { return v < lo ? lo : v > hi ? hi : v; };
      var inView = !("IntersectionObserver" in window);
      if (!inView) {
        new IntersectionObserver(function (entries) {
          inView = entries[0].isIntersecting;
        }, { rootMargin: "20% 0px" }).observe(leanHost);
      }

      var lastY = window.scrollY, lastT = performance.now();
      var vel = 0, cur = 0, raf = 0, prevT = 0;

      var tick = function (now) {
        raf = 0;
        var dt = Math.min(0.05, (now - (prevT || now)) / 1000);
        prevT = now;
        // decay: the scroll event stops firing, so nothing else zeroes this
        vel *= Math.pow(0.86, dt * 60);
        if (Math.abs(vel) < 20) vel = 0;
        var target = inView ? clamp((vel / VEL_AT_MAX) * MAX, -MAX, MAX) : 0;
        cur += (target - cur) * Math.min(1, dt * 7);
        if (Math.abs(cur) < 0.01 && !vel) cur = 0;
        leanHost.style.setProperty("--nl-lean", cur.toFixed(3) + "deg");
        if (vel || Math.abs(cur) > 0.005) raf = requestAnimationFrame(tick);
        else prevT = 0; // settled — loop stops until the next scroll
      };

      window.addEventListener("scroll", function () {
        var now = performance.now();
        var dt = (now - lastT) / 1000;
        if (dt > 0) vel = clamp((window.scrollY - lastY) / dt, -12000, 12000);
        lastY = window.scrollY;
        lastT = now;
        if (!raf) raf = requestAnimationFrame(tick);
      }, { passive: true });
    })();
  }
})();
