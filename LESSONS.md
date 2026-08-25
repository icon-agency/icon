# LESSONS.md

Running log of corrections for the **ICON Agency** build. Each entry prevents a
future mistake. Read before starting work. Append (don't edit) when something
goes wrong.

> Fresh for this project. The previous contents were lessons from a different
> (GovCMS) build and no longer apply here.

---

- **Scroll parallax must run on the compositor, not the main thread.** Driving a
  parallax transform from a `scroll` event handler — even rAF-throttled — sets
  the element's transform a frame behind the page's real scroll position, so it
  visibly stutters / "swims", worst on trackpad + momentum scrolling. Fix: a CSS
  scroll-driven animation, which the browser samples on the compositor in
  lockstep with scroll:
  ```css
  @keyframes hero-video-parallax { to { transform: translate3d(0, 50vh, 0); } }
  @media (prefers-reduced-motion: no-preference) {
    @supports (animation-timeline: scroll()) {
      .hero__video {
        animation: hero-video-parallax linear both;
        animation-timeline: scroll(root);
        animation-range: 0 100vh;       /* over the hero's height */
      }
    }
  }
  ```
  This reproduces `translateY = scrollY * 0.5` exactly (verified: at scrollY 400,
  computed translateY = 200px) but is smooth. Keep a JS fallback only for
  browsers without scroll-driven animations (Firefox, today), and there run a
  continuous rAF **lerp** loop (`current += (target - current) * 0.18`) rather
  than reacting to scroll events — smoother than the naive handler. Gate it with
  `CSS.supports('animation-timeline: scroll()')` so the two never both run.
  (`hero.css` / `js/hero.js`.)

- **Tailwind v4's `--minify` (Lightning CSS) drops the FIRST of a
  vendor-prefixed/unprefixed pair** when both appear in one declaration block.
  Declare the `-webkit-` form FIRST so the standard property survives:
  ```css
  -webkit-backdrop-filter: blur(24px);
  backdrop-filter: blur(24px);
  ```
  Load-bearing for every frosted surface here (`.overlay-frosted-glass`,
  `.site-nav__pill`, `.icon-button`, `.menu-toggle`, `.site-nav__submenu`). For
  multi-function `filter` / `backdrop-filter` / `transform` values the minifier
  also strips the space separator (`blur(14px)saturate(140%)` → invalid syntax,
  declaration dropped); wrap those in a custom property, which it leaves intact.
  (Inherited gotcha — same Tailwind v4 / Lightning CSS engine.)

- **A JS-computed layout offset goes stale when the layout shifts.** The desktop
  nav aligns the SERVICES drawer under its trigger via a JS-set `--submenu-offset`
  (`trigger.left − drawer.left`). Computing it only on open + `resize` left it
  stale when the scroll-revealed home glyph expands and pushes SERVICES right —
  the items only re-aligned on the next hover. Fix: recompute on every geometry
  change with a **ResizeObserver on the row** (catches the glyph transition AND
  web-font reflow), plus a scroll re-sync *while open* for smooth tracking, all
  **rAF-coalesced** to one read+write per frame. Observe the element you REACT to
  (the row), never the one you MUTATE (the drawer) — writing the offset resizes
  the drawer, so observing that would loop. And guard the width: pin the drawer
  (`width: 0; min-width: 100%`) so a long label can't widen the pill and feed
  back into the offset. (`js/header.js` / `src/components/site-header.css`.)

- **A per-route theme override must rebind on `<html>`, not `<body>` — Tailwind
  v4 registers the `--color-*` aliases and resolves them at `:root`.** The
  `/work/article` case study flips to a light palette via a `.theme-work-article`
  scope that re-binds the brand vars (`--icon-black`, `--background`, …) to the
  `--work-article-*` values. Placed on `<body>` it re-themed the prose (which
  reads the RAW `var(--icon-black)`) but NOT anything reading the Tailwind alias
  `var(--color-icon-black)` or a generated utility — those stayed dark. Reason:
  `@theme inline` registers `--color-icon-black: var(--icon-black)`; a registered
  custom property computes its value at the element where it's declared (`:root`
  = `<html>`, where `.dark` set `--icon-black`), then inherits that *resolved*
  color down — a `<body>`-level rebind is too late. Fix: put the scope on the
  same `<html>` element as `.dark` (`class="dark theme-work-article"`); it wins
  by source order (imported last) and both the raw vars and the `--color-*`
  aliases/utilities re-theme. Corollary: hand-written component CSS that must
  follow such a scope can also just read the raw `var(--icon-black)` directly
  (as `prose.css` does). (`src/utilities/work-article.css` /
  `templates/work-article.html`.)

- **`overflow: hidden` silently freezes CSS scroll-driven parallax by promoting an
  ancestor to a scroll container.** The card parallax (`.project-card__image`,
  `.image-grid__media`, `.case-study__featured-media`) used
  `animation-timeline: view()`. `view()` resolves its scrollport to the subject's
  NEAREST ancestor scroll container — and each image sits inside a frame with
  `overflow: hidden` (which IS a scroll container), so `view()` measured the image
  against its own non-scrolling frame and pinned the animation at 50% (zero
  movement, on every template). Compounding it: `body { overflow-x: hidden }` (the
  reset.css horizontal-overflow guard) forces `overflow-y: auto`, making `<body>`
  a scroll container too — so even a named `view-timeline` on an outer wrapper
  anchored to `<body>`, which never scrolls (the document root does), and stayed
  frozen. Fix needs BOTH parts: (1) `body { overflow-x: clip }` — `clip` clips the
  same overflow WITHOUT becoming a scroll container (it doesn't force the other
  axis to `auto`); (2) declare `view-timeline-name: --card-parallax` on the outer
  `.reveal` (whose nearest scroll container is now the document root) and point
  each media at it with `animation-timeline: --card-parallax` instead of bare
  `view()`. Verified: images now translate ±~5–10% as the card scrolls through the
  viewport. The hero video was never affected because it uses `scroll(root)`
  explicitly. Measure scroll-driven transforms after a double `requestAnimationFrame`
  — `getComputedStyle` read immediately after `scrollTo` lags one frame behind the
  compositor and looks frozen even when it isn't. (`src/base/reset.css`,
  `src/components/reveal.css`, `project-card.css`, `image-grid.css`,
  `src/utilities/work-article.css`.)

- **A `clip-path` that hides an element deadlocks its own IntersectionObserver
  reveal.** Home C's image reveal started each frame at `clip-path: inset(100%)`
  (zero visible area) and observed THAT frame, revealing it once it crossed an 18%
  intersection threshold. It never revealed: the clip counts against the
  intersection geometry, so the observed element's `intersectionRatio` is pinned
  at 0 (verified: clipped frame ratio 0 while an identical unclipped one reported
  1) and never reaches the threshold — and it can't un-clip until it does. A
  near-identical `[data-animate]` observer with no clip on its targets (reveal.js)
  fired fine, which is what isolated the cause. Fix: never clip the OBSERVED
  element to nothing — observe an unclipped wrapper and put the
  `clip-path`/`transform` reveal on a CHILD (here the `<img>`/`<video>` inside
  `.homec-reveal`), so the frame keeps its full box for the observer.
  (`src/utilities/home-c.css`, `js/home-c.js`.)

- **Lightning CSS drops one of `translate` / `transform` when a rule sets
  both.** The footer logo's entrance was authored as `translate: 0 110%` for the
  rise plus `transform: perspective(1400px) rotateX(-52deg)` for the swivel —
  separate properties precisely so they compose rather than overwrite. The
  source was correct and the un-minified behaviour was correct; the BUILT
  `css/main.css` carried only the rotate. The rise vanished, silently, with no
  warning and no build error, so the logo unfolded on the spot instead of
  swivelling up. Rules that set `translate` alone are untouched (the footer's
  block fade-ups still work) — it is specifically the pairing the optimiser
  tries to merge. Fix: when an element needs both, write ONE `transform` with
  the functions in order (`perspective() translateY() rotateX()`), and reserve
  the separate properties for cases where two different agents own them (JS
  writing `rotate` while CSS animates `transform`, as the news cards do).
  Verify by grepping the BUILT file, not the source — `grep -o
  '\.selector{[^}]*}' css/main.css` — because this class of bug only exists
  after minification. (Same engine as the `--minify` shorthand hazard above.)
  (`src/components/site-footer.css`.)

- **A `readyState` gate on a paused video deadlocks on iOS — Safari ignores
  `preload="auto"` and parks paused video at `HAVE_METADATA`, so a gate
  waiting for `readyState >= 3` never settles on a phone.** The hero boot
  gated each card (and the reel's hand-off slide) this way: on mobile the
  two video gates sat until the 5.5s `MAX_WAIT` cap on every load — the pile
  stalled, and the (real) load counter froze at (40) because only the two
  image gates could ever settle, which read as "the loading is fake". Worse,
  the reduced-motion path had the same gate with NO cap: a permanent blank
  hero on reduced-motion iOS. Fix: **prime the video** — a `muted playsinline`
  video may be `play()`ed without a gesture, and playback is the one thing
  that reliably makes Safari buffer; pause it the moment its gate settles
  (invisible pre-pop, so nothing shows). Low Power Mode rejects the `play()`
  — caught, and the cap still fails open. And when a fail-open cap fires,
  **resolve the counter with it** (the loading phase is over by policy) —
  a real counter that can freeze below 100 is worse than an honest cap.
  (`js/home-c.js` hero boot; found on a phone, Aug 2026.)
