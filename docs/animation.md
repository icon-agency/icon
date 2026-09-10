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

The same file owns the **word cascade** (`[data-reveal-words]`, CSS in
`animations.css`): each word is wrapped in a mask and rises through it on a
per-word stagger. It began as the homepage intro's gesture (home-c.js, 3c) and
was lifted here when every page H1 took it — the /work and /news mastheads
and both article titles (user call, Sep 2026) — so a headline enters the same
way wherever it is. Plain-text hosts get an aria-label shim; hosts with links
are split in place so the links stay reachable.

Used on every template. (The design-system index
deliberately loads no page JS — its inline demos show the genuine no-JS state.)
Under `prefers-reduced-motion: reduce` the CSS resets everything to visible.
Drupal: `Drupal.behaviors.iconReveal`.

## 2. The canonical homepage system (`templates/homeC.html`)

The homepage's choreography lives in **three** files:

- **`js/hero-loader.js`** — builds and lights the hero's loading screen: the
  blue that opens as a square from the viewport's bottom-left corner (a
  two-axis scale anchored there — NOT a clip-path, which runs on the main
  thread and stalled behind the hero's boot; the page transition's and the
  media reveals' corner — user call, Sep 2026) and covers the screen in 650ms on the page wipe's
  decelerate — the takeover curve's 200ms hold read as a stuck square from a
  corner;
  the `.text-box` "room" of 16 marquee rows it once held is gone. Deliberately its own
  file, loaded *before* the CDN scripts, so the loading screen exists on the
  first frame even when GSAP/Lenis round-trips are slow. Hands `js/home-c.js`
  a start time. A perspective tunnel is a vestibular trigger, so reduced
  motion means it is never even built.
- **`js/home-c.js`** — one IIFE, sections numbered in the file:
  Lenis smooth scroll (1) · hero stack-takeover: preload → card pile → viewport
  takeover → reel with cross-fades and Ken Burns stills (2) · header inversion
  over the hero (2b) · triggered headline exit (2c) · (the word cascade of 3c
  now lives in `js/reveal.js`, §1) · the intro filmstrip — GSAP-ticker marquee,
  drag with momentum, hover-stall, DRAG badge (3d) · scroll-velocity card lean
  (3e) · cursor tilt on work/news cards (3f) · the clients logo marquee —
  two counter-drifting rows on ONE shared phase (row 2 reads it negated), so
  dragging either row moves both, mirrored, and a fling re-points the pair
  together; the filmstrip's drift and drag constants via the shared
  `STRIP_SPEED`, no hover-stall (removed by request), CSS keyframes kept as
  the no-JS/no-GSAP fallback — row 2's reverse from the same `--reverse`
  class — and switched off via `.is-js-marquee` so the two mechanisms never
  both run (3g) · GSAP
  (the SplitText line-mask reveals of section 4 were removed in Sep 2026:
  nothing ever carried `[data-reveal-text]`, and the page was loading the
  SplitText CDN script for them).
- **`src/utilities/home-c.css`** — the reveal-fx primitives the JS toggles:
  `.media-reveal` (clip-path inset growing from the bottom-left corner + a
  zoom-settle; the transform stays on the *media*, never the observed frame —
  see LESSONS.md) and `.split-line` masks.

Libraries on this page: GSAP core (CDN) and Lenis (CDN). Everything
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
- **Card/media parallax** (`project-card` and the case-study
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

## 7. Page transitions — `src/utilities/page-transition.css` + `js/page-transition.js` (every page)

The move from one page to the next, as **cross-document View Transitions**
(`@view-transition { navigation: auto }`). Drupal serves whole pages, so
nothing becomes a single-page app: the browser snapshots the old page, the new
one loads, and CSS animates between them.

**The move is a fade, in every case** (user call, Sep 2026 — a bottom-left
corner wipe and a card-to-banner morph were built, tried and taken out
again; the new page's own reveals, the word cascade and the media reveals,
do the arriving):

- **Grounds alike — a crossfade.** The old page fades out in 300ms on the
  standard ease, the new fades in over 450ms on the decelerate ease.
- **A ground that changes — a fade through.** The old page fades out to the
  new page's ground first (the canvas, which is that ground) and the new
  content fades in after it (a 300ms delay), so the two are never mixed into
  a third colour. The outgoing page notes its painted background as it
  leaves — live, so a dark-opening page that has handed over to light on
  scroll reports light — and the incoming page reads its own at first render
  and sets the `fade-through` type when they differ (`js/page-transition.js`).
- **The chrome holds.** The wordmark (`.site-logo`) and the pill's nav face
  (`.site-nav__pill--nav` — not the search face, its twin in the flip cube: a
  name worn twice voids the whole transition; and not the wrapper: a named
  element renders in isolation, and a descendant's backdrop blur would see
  only the wrapper's transparent contents) carry their own transition names,
  so they sit outside the fade, one fixed anchor across pages.

The JS is a **plain IIFE in `<head>`**, not a behaviour: `pagereveal` fires
at the new page's first render, before `DOMContentLoaded`, so an `attach()`
would miss it. The DOM is complete by then because the shell render-blocks
on the footer (`<link rel="expect" href="#site-footer" blocking="render">`).
On reveal the activation is `navigation.activation` — only the swap event
carries its own.

While the new page fades in, `html.is-page-entering` sets `--animate-hold`
(350ms), which the `[data-animate]` host, the word cascade and the media
reveal add to their delay, so the page settles once. The homepage is not
skipped: its blue loading screen is full-size and unlit on the first frame
(visible only while `html.is-page-entering`; otherwise invisible until lit,
so a direct load never flashes it), so the fade brings the blue in whole,
and `js/hero-loader.js`, reading `window.ICON.pageEntering`, lights it as
already landed (`.is-landed`, no animation) and fires the cover moment when
the fade finishes.

Reduced motion sets `navigation: none` — an instant swap. Firefox has no
cross-document transitions yet and simply navigates. Both pages must opt in,
so an admin page (no main.css) never transitions. Drupal: `icon/page-transition`
(`header: true`).

## 8. Team profiles overlay — `src/components/team-profiles.css` + `js/team-profiles.js` (the About page)

A member's profile is a native `<dialog>` that slides in from the left edge
over the page: the panel moves `translate: -100% 0` → `0 0` with
`@starting-style` on `[open]` (`--duration-slow`, `--ease-decelerate`), the
backdrop is a tint of the footer ground that fades in with it, and the page
behind loses its scroll (`html.is-team-open`). Closing slides it back out
the same way: the dialog transitions `display` and `overlay` with
`allow-discrete`, so it stays painted in the top layer until the panel has
gone (a browser without discrete transitions removes it at once). The
arrows and the counter sit in the bar at the top.

**A profile arrives the way a page does** (user call, Sep 2026): the name
on the word cascade (`[data-reveal-words]`), the bio and the LinkedIn link
on the `[data-animate]` rise with a stagger, the portrait through the
shared `.media-reveal` mask — the site's own entrances, none new. On an
open they hold for the panel's slide (`--animate-hold`, 250ms, set on the
panel by the script); on a step they play at once. **Stepping is the page
move's fade**: `js/team-profiles.js` swaps the profile inside
`document.startViewTransition()`, the portrait and the text wear a
`view-transition-name` each (only the shown profile renders, so each name
is worn once), and `page-transition.css` gives the pair the page's own
crossfade — old out in 300ms on the standard ease, new in over 450ms on
the decelerate — with the new profile's entrances playing once it lands.
The script only RE-ARMS the entrances: the classes come off while the
profile is hidden and go back on a frame after it shows, from a forced
start state. The portrait leans toward the cursor (the news card's 2.2deg,
through `js/cursor-tilt.js`) on the figure, with the mask on a frame inside
it — the tilted box and the masked box are never the same element. The
GRID's cards lean with the scroll (`--team-skew` on the grid from
`js/velocity-lean.js`, 3deg, consumed on the card link); the panel itself
does not — the page behind does not scroll while it is open, and the
panel's own scroll is not the engine's. Reduced motion: no fade, no
entrances, no tilt, no lean — an instant swap. The grid's cards wear the shared `[data-animate]` rise with a stagger
(`--animate-delay` per card) and the `.media-reveal` mask on the portrait;
hover scales the portrait inside its square (pointer devices only).
Reduced motion drops the slide and the mask. The URL, title and counter
are the script's — see `docs/drupal-handoff.md` for the routing.

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
   code. When two files need the same helper, the *third* consumer is the
   moment to lift it into a shared primitive — not before. The velocity
   sampler is the worked example: it lived in `site-footer.js` and `news.js`
   until the work landing became its third consumer, and is now
   `js/velocity-lean.js` (`window.ICON.velocityLean`), loaded before the
   files that call it. The cursor tilt followed the same path: news.js and
   work-landing.js each carried the loop until the team panel's portrait
   made three, and it is now `js/cursor-tilt.js` (`window.ICON.cursorTilt`).
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
| Header (scroll state, the search flip, EXPERTISE drawer offset — a drop-up below md, where the pill sits at the bottom; the parked mobile menu's handlers stay) | `js/header.js` | — | `iconHeader` → `icon/header` |
| Shared scroll-reveal | `js/reveal.js` | — | `iconReveal` → `icon/reveal` |
| Homepage system | `js/home-c.js` | gsap, SplitText, lenis | `iconHomeC` → `icon/home-c` |
| Hero loading screen | `js/hero-loader.js` | — (deliberately) | part of the hero SDC; its 16 rows become a Twig loop |
| Global footer | `js/site-footer.js` | — | `iconFooter` → `icon/site-footer` |
| Page transitions (the ground comparison + the reveal hold; the fade is CSS) | `js/page-transition.js` | — | plain IIFE in `<head>` → `icon/page-transition` (`header: true`) |
| News listing (filter + card motion) | `js/news.js` | — | `iconNews` → `icon/news` |
| Dark-opening theme handover (every dark-opening page: news listing, both articles, work landing) | `js/theme-handover.js` | — | `iconThemeHandover` → `icon/theme-handover` |
| Article Share rail (copy link + email) | `js/share.js` | — | `iconShare` → `icon/share` |
| Subscribe bar hint state (news-b and work-landing-b mastheads, the footer) | `js/subscribe-reveal.js` | — | `iconSubscribeReveal` → `icon/subscribe-reveal` |
| Work landing (filter + card motion) | `js/work-landing.js` | — | `iconWorkLanding` → `icon/work-landing` |
| Scroll-velocity engine (shared: footer skew, news + work listing lean, work article skew, gallery scroller, the team grid) | `js/velocity-lean.js` | — | `iconVelocityLean` → `icon/velocity-lean` (a dependency of its consumers) |
| Cursor-tilt engine (shared: news cards, work landing tiles, the team panel's portrait) | `js/cursor-tilt.js` | — | plain IIFE → `icon/cursor-tilt` (a dependency of its consumers) |
| Work article (chameleon skew + the hero banner's breakout to the viewport edges, an IO flip at one 0.5 threshold with the travel in CSS; wide only while the header is in its scrolled state, so it opens inset and returns to inset at the top) | `js/work-article.js` | velocity-lean | `iconWorkArticle` → `icon/work-article` |
| Work article gallery scroller | `js/work-scroller.js` | velocity-lean | `iconWorkScroller` → `icon/work-scroller` |
| Work article click-to-play film | `js/work-video.js` | — | `iconWorkVideo` → `icon/work-video` |
| Team profiles overlay (the About page: open / step / close, the address and title; the step's View Transition and the re-armed entrances) | `js/team-profiles.js` | reveal, cursor-tilt, velocity-lean | `iconTeamProfiles` → `icon/team-profiles` |
| Work section + listing filter (prototype: templates/home.html, work.html) | `js/work.js`, `js/work-filter.js` | — | not ported |
| Home-A hero (prototype) | `js/hero.js` | gsap, ScrambleText | `iconHero` → `icon/hero` |
| Tagline (prototype) | `js/tagline.js` | gsap, Inertia | `iconTagline` → `icon/tagline` |
| HomeB sphere (prototype) | `js/hero-sphere.js` | three, gsap, ScrollTrigger | `iconHeroSphere` → `icon/hero-sphere` |

CDN loading (cdnjs/jsDelivr for GSAP + plugins, jsDelivr for Lenis) is a
prototype convenience; self-hosting is a performance choice at port time, not a
compliance requirement (this is not GovCMS). The `[data-animate]` host CSS
belongs in the theme's global stylesheet; each adopter's observer wiring rides
in that component's behaviour.
