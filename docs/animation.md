# Animation

Motion on this site is organised **per page bundle**: every behaviour is one JS
IIFE (→ one future `Drupal.behaviors` entry), every effect is
reduced-motion-guarded, and every reveal is no-JS safe (the hidden state lives
behind a `.js-animations` class added in `<head>`, so a no-JS page renders
fully visible). The systems below are ordered by how much of the site they
touch.

---

## 1. Shared scroll-reveal — `[data-animate]` + `js/reveal.js` (every page)

The workhorse. `src/utilities/animations.css` owns a simple opacity +
`translateY(20px)` rise (with a `--animate-delay` per-element stagger) plus a
**masked line-rise variant** — each line waits below its own overflow mask and
rises on a stagger (the hero lockup's gesture, packaged; worn by the two
"News & Insights" mastheads). `js/reveal.js` is the only JS: an
IntersectionObserver that adds `.is-visible` once per element — and the same
file drives the **hairline draw** (`.is-drawn` on `[data-rule]` /
`[data-rule-host]`; CSS in `utilities/rules.css`) and the **media reveals**
(`.is-revealed` on `[data-reveal-img]` frames wearing `.media-reveal`) —
both lifted here from per-page copies once a third page needed them.

Used on `homeC`, `news`, `work` and `work-article`. (The design-system index
deliberately loads no page JS — its inline demos show the genuine no-JS state.)
Under `prefers-reduced-motion: reduce` the CSS resets everything to visible.
Drupal: `Drupal.behaviors.iconReveal`.

## 2. The canonical homepage system (`templates/homeC.html`)

The homepage's choreography lives in **three** files:

- **`js/hero-loader.js`** — builds and lights the hero's kinetic-text loading
  screen (the `.text-box` "room" of 16 marquee rows). Deliberately its own
  file, loaded *before* the CDN scripts, so the loading screen exists on the
  first frame even when GSAP/Lenis round-trips are slow. Hands `js/home-c.js`
  a start time. A perspective tunnel is a vestibular trigger, so reduced
  motion means it is never even built.
- **`js/home-c.js`** — one IIFE, sections numbered in the file:
  Lenis smooth scroll (1) · hero stack-takeover: preload → card pile → viewport
  takeover → reel with cross-fades and Ken Burns stills (2) · header inversion
  over the hero (2b) · triggered headline exit (2c) · word-cascade text reveals
  (`[data-reveal-words]`, 3c) · the intro filmstrip — GSAP-ticker marquee,
  drag with momentum, hover-stall, DRAG badge (3d) · scroll-velocity card lean
  (3e) · cursor tilt on work/news cards (3f) · the clients logo marquee —
  two counter-drifting rows on ONE shared phase (row 2 reads it negated), so
  dragging either row moves both, mirrored, and a fling re-points the pair
  together; the filmstrip's drift and drag constants via the shared
  `STRIP_SPEED`, no hover-stall (removed by request), CSS keyframes kept as
  the no-JS/no-GSAP fallback — row 2's reverse from the same `--reverse`
  class — and switched off via `.is-js-marquee` so the two mechanisms never
  both run (3g) · GSAP
  **SplitText line-mask** text reveals, fired per element by IO — no
  ScrollTrigger (4).
- **`src/utilities/home-c.css`** — the reveal-fx primitives the JS toggles:
  `.media-reveal` (clip-path inset growing from the bottom-left corner + a
  zoom-settle; the transform stays on the *media*, never the observed frame —
  see LESSONS.md) and `.split-line` masks.

Libraries on this page: GSAP core + SplitText (CDN) and Lenis (CDN). Everything
degrades: no GSAP/SplitText → text renders plainly; no Lenis / reduced motion →
native scroll; no IO → everything visible.

## 3. The global footer — `js/site-footer.js` (every page)

Three concerns, all documented in the file header: a **replaying reveal**
(`.is-revealed` toggles both ways so the entrance re-runs on every return),
the **films inside the logo mask** (one video decoding at a time, only while
the panel is on screen, `preload="none"`), and a **scroll-velocity skew**
(one `--footer-skew` custom property written per frame — velocity is not
expressible as a CSS scroll timeline, so this is the one part that must stay
scripted). Ships standalone because the footer appears on pages that never
load the homepage bundle (and have no GSAP). Drupal:
`Drupal.behaviors.iconFooter` via `icon/site-footer`.

## 4. The news listing — `js/news.js` (`templates/news.html`)

No GSAP on this page. The news cards are the shared `components/news-card.css`;
their hover/parallax behaviours are ported to rAF here (home-c.js drives the
same cards with GSAP on the homepage). The category filter is chips-as-real-links
enhanced with `pushState`, and the card swap runs inside a **View Transition**
where supported (each card carries a `view-transition-name`, so survivors glide;
choreography in `news-list.css`). Unsupported browsers and reduced-motion users
get the instant toggle. Drupal: `Drupal.behaviors.iconNews` — the filter maps to
a Views exposed filter.

## 5. CSS scroll-driven animations (compositor parallax)

Two shapes, both pure CSS with LESSONS.md-verified plumbing:

- **Hero video parallax** (home-A prototype, `src/components/hero.css`):
  `animation-timeline: scroll(root)` translating the video at half scroll
  speed. `js/hero.js` keeps a continuous rAF-**lerp** fallback for browsers
  without scroll-driven animations, gated by
  `CSS.supports('animation-timeline: scroll()')` so the two never both run.
- **Card/media parallax** (`project-card`, `image-grid`, the case-study
  featured media): a **named `view-timeline`** declared on the outer `.reveal`
  wrapper, consumed by the media via `animation-timeline: --card-parallax`.
  Named, not bare `view()`, because each medium sits inside an
  `overflow: hidden` frame that would otherwise become the timeline's
  scrollport and freeze it — and `body` must stay `overflow-x: clip`, not
  `hidden`, for the same reason (both in LESSONS.md).

Never a `scroll`-event handler — even rAF-throttled, it lags a frame and
visibly swims.

## 6. Prototype-only GSAP timelines (outside the system)

Kept for the browsable prototypes; not part of the canonical bundle:

- `js/hero.js` — home-A ScrambleText headline (per-glyph widths pre-measured so
  letters resolve in place; flattens back to plain text on complete).
- `js/tagline.js` — cursor-pop image trail (InertiaPlugin, distance-gated
  spawning, pooled `<img>`s) + per-line reveal + fit-to-width sizing.
- `js/hero-sphere.js` — homeB's Three.js orbiting photo-sphere (the one place
  ScrollTrigger and Three.js appear).

All bail to a static state under reduced motion or when their library is
missing.

---

## Rules

1. **Always pair motion with a `prefers-reduced-motion` reset.** Every system
   here does; new motion must too. Reveals must also be **no-JS safe**: hide
   only behind `.js-animations`, so the unenhanced page is fully visible.
2. **Parallax and scroll-linked motion run on the compositor, never on a
   `scroll` handler.** Use `animation-timeline` (named `view-timeline` when the
   subject sits inside an overflow-hidden frame); where unsupported, a
   continuous rAF **lerp** loop gated by `CSS.supports(...)`. The one scripted
   exception is *velocity*-driven motion (footer skew, card lean) — a scroll
   timeline is position-driven, so velocity must be sampled in JS, rAF-coalesced.
3. **GSAP is the only JS animation library** (SplitText on the homepage;
   ScrambleText/Inertia in prototypes). **Lenis is the only scroll library**
   (canonical homepage only). Three.js appears only in the homeB prototype.
   Don't hand-roll tweens GSAP already does — but plain rAF is fine where a
   page deliberately ships without GSAP (news.js).
4. **One behaviour per component/page concern**, each an IIFE keyed to a single
   future `Drupal.behaviors` entry, so unused components ship no dead animation
   code. When two files need the same helper (the velocity sampler in
   site-footer.js and news.js), the *third* consumer is the moment to lift it
   into a shared primitive — not before.
5. **Don't use JS for what CSS handles** — hover, focus, and theme
   cross-fades stay in CSS; observer-triggered reveals (including the hairline
   draw) keep the *transition* in CSS and use JS only to flip a class. JS
   animation is for mount-driven, interaction-driven, velocity-driven, or
   observer-triggered effects only.
6. **Never clip or transform the element an IntersectionObserver watches** —
   observe a stable wrapper, animate the child (LESSONS.md). And measure
   scroll-driven transforms only after a double rAF.

---

## Drupal port

| Behaviour | File | Library dep | Drupal.behaviors → library |
|---|---|---|---|
| Header (scroll state, mobile menu, drawer offset) | `js/header.js` | — | `iconHeader` → `icon/header` |
| Shared scroll-reveal | `js/reveal.js` | — | `iconReveal` → `icon/reveal` |
| Homepage system | `js/home-c.js` | gsap, SplitText, lenis | `iconHomeC` → `icon/home-c` |
| Hero loading screen | `js/hero-loader.js` | — (deliberately) | part of the hero SDC; its 16 rows become a Twig loop |
| Global footer | `js/site-footer.js` | — | `iconFooter` → `icon/site-footer` |
| News listing (filter + card motion) | `js/news.js` | — | `iconNews` → `icon/news` |
| Dark-opening theme handover (every dark-opening page: news listing, both articles, work landing) | `js/theme-handover.js` | — | `iconThemeHandover` → `icon/theme-handover` |
| Article Share rail (copy link + email) | `js/share.js` | — | `iconShare` → `icon/share` |
| Header-B Subscribe disclosure (news-b) | `js/subscribe-reveal.js` | — | `iconSubscribeReveal` → `icon/subscribe-reveal` |
| Work landing (filter + card motion) | `js/work-landing.js` | — | `iconWorkLanding` → `icon/work-landing` |
| Work section (swipe, underline, hover-video) | `js/work.js` | — | `iconWork` → `icon/work` |
| Work listing filter | `js/work-filter.js` | — | `iconWorkFilter` → `icon/work-filter` |
| Home-A hero (prototype) | `js/hero.js` | gsap, ScrambleText | `iconHero` → `icon/hero` |
| Tagline (prototype) | `js/tagline.js` | gsap, Inertia | `iconTagline` → `icon/tagline` |
| HomeB sphere (prototype) | `js/hero-sphere.js` | three, gsap, ScrollTrigger | `iconHeroSphere` → `icon/hero-sphere` |
| Theme toggle | `js/theme-toggle.js` | — | **dev-only — never ported** |

CDN loading (cdnjs/jsDelivr for GSAP + plugins, jsDelivr for Lenis) is a
prototype convenience; self-hosting is a performance choice at port time, not a
compliance requirement (this is not GovCMS). The `[data-animate]` host CSS
belongs in the theme's global stylesheet; each adopter's observer wiring rides
in that component's behaviour.
