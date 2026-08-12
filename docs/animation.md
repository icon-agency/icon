# Animation

This site has **two** animation systems. They are not interchangeable — pick the
right one for the job, and always pair motion with a `prefers-reduced-motion`
reset.

1. **GSAP per-component timelines** — attached as `Drupal.behaviors` in each
   component's JS. Used for the hero headline reveal and the tagline cursor-pop
   trail. Mount-driven / interaction-driven effects.
2. **CSS scroll-driven parallax** — `animation-timeline: scroll()` on the hero
   video, sampled on the compositor in lockstep with scroll, with a continuous
   rAF-lerp JS fallback for browsers that lack it. Never a scroll-event handler.

There is also a third, **available-but-unused** primitive — the `[data-animate]`
scroll-reveal host in `src/utilities/animations.css`. It is wired and
documented below, but nothing currently uses it.

---

## System 1 — GSAP per-component timelines

GSAP (core from cdnjs; `ScrambleTextPlugin` and `InertiaPlugin` from jsDelivr,
all free in GSAP 3.13) drives the two bespoke entrance / interaction effects.
Each lives in its own IIFE that maps cleanly to a `Drupal.behaviors`.

### Where it lives

| Effect | JS | Plugin | Drupal behaviour / library |
|---|---|---|---|
| Hero headline scramble | `js/hero.js` | `ScrambleTextPlugin` | `Drupal.behaviors.iconHero` → `icon/hero` (deps `gsap`, `ScrambleTextPlugin`) |
| Tagline cursor-pop trail | `js/tagline.js` | `InertiaPlugin` | `Drupal.behaviors.iconTagline` → `icon/tagline` (dep `gsap`) |

`tagline.js` also runs two non-GSAP helpers in the same behaviour: a per-line
scroll reveal (`IntersectionObserver` toggling `.is-visible` on `[data-tagline]`)
and a desktop fit-to-width that binary-searches the headline font size so the
longest line fills its box.

### Hero headline scramble

**How to use.** The markup is three `.hero__line` elements inside `[data-hero]`;
each carries its final string in `data-text`. `js/hero.js` finds them, splits
each line into per-character `.hero__char` spans, and runs a single
`gsap.timeline()` that scrambles each line in sequentially (Make → what →
matters), one word per line, with each letter resolving in place.

**How it works.**
1. On boot, the `[data-hero-overlay]` gets `.is-covering` (a black cover).
2. The reveal waits for the first video readiness event (`loadeddata` /
   `canplay` / `error`), with a 3s safety timeout, then removes the cover.
3. Each `.hero__line` is split into fixed-width `.hero__char` spans — the width
   is measured per glyph with a `Range`, so every letter scrambles **in its
   final position** with no horizontal reflow.
4. The timeline fades each line in, then tweens each char's `scrambleText`
   (`chars: "lowerCase"`, `revealDelay: 0.18`) staggered by `charStagger`.
5. `onComplete` flattens the spans back to plain text so the headline stays
   responsive after the entrance.

Glyph widths are only measured once `document.fonts.ready` resolves (Miller Text
loads from Typekit), so the locked widths match the real display font.

### Tagline cursor-pop trail

**How to use.** Inside `[data-tagline]`, an empty `[data-tagline-pops]` element
is the trail's positioning context. As the pointer moves over it, work
thumbnails spawn and drift.

**How it works.**
1. `onMove` (bound to `mousemove` + `touchmove`, both `passive`) measures
   pointer travel and velocity. It only spawns once accumulated travel passes
   `SPACING` (72px) — a distance gate, not a time interval — so the trail
   density is consistent regardless of pointer speed.
2. Each spawn pops an `<img>` from a pool (capped at `MAX` 16 concurrent), fades
   and scales it in, then hands it the pointer's clamped momentum via
   `InertiaPlugin` (`inertia: { x, y, resistance: 520 }`) so it drifts and
   decelerates naturally. It holds, then fades out and recycles back to the pool.
3. Images are `aria-hidden` and preloaded; the pool avoids per-move allocation.

### Reduced motion / graceful degradation

Both effects bail to a static state. The hero shows the headline plainly and
removes the cover when `prefers-reduced-motion: reduce`, GSAP is missing, or
`ScrambleTextPlugin` is missing. The tagline `return`s before wiring the pops
under reduced-motion, no GSAP, or a missing `[data-tagline-pops]`; without
`InertiaPlugin` it falls back to a short `power2.out` drift. The per-line reveal
and fit-to-width still run, since neither is motion-heavy.

---

## System 2 — CSS scroll-driven parallax

The hero video translates down at half the scroll speed over the hero's height.
This is **compositor-driven CSS**, not a JS scroll handler — see LESSONS.md.

### Where it lives

- CSS: `src/components/hero.css` (`@keyframes hero-video-parallax` +
  `animation-timeline: scroll(root)`).
- JS fallback: `js/hero.js`, the rAF-lerp loop at the bottom.

### How it works

The primary path is pure CSS. The element is `[data-hero-video]` (class
`.hero__video`):

```css
@keyframes hero-video-parallax {
  to { transform: translate3d(0, 50vh, 0); }
}

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

The browser samples this on the compositor, in exact lockstep with scroll. It
reproduces `translateY = scrollY * 0.5` precisely (at scrollY 400, computed
translateY = 200px) but never stutters or "swims" the way a main-thread handler
does.

### Fallback

For browsers without scroll-driven animations (Firefox, today), `js/hero.js`
runs a **continuous rAF lerp** loop — never a scroll-event handler:

```js
var current = 0;
(function loop() {
  var target = window.scrollY * 0.5;
  current += (target - current) * 0.18;
  if (Math.abs(target - current) < 0.05) current = target;
  video.style.transform = "translate3d(0," + current.toFixed(2) + "px,0)";
  window.requestAnimationFrame(loop);
})();
```

It is gated by `CSS.supports('animation-timeline: scroll()')` so the CSS path
and the JS path can never both run. The lerp smooths over the gaps that a naive
`scroll`-event handler leaves between events — which is the whole reason we don't
use one (LESSONS.md).

### Reduced motion

The `@supports` block is nested inside
`@media (prefers-reduced-motion: no-preference)`, so the CSS parallax simply
never applies under reduced motion. The JS fallback is guarded by the same
`reduce` check before its loop starts.

---

## Available primitive — `[data-animate]` scroll reveal (currently unused)

`src/utilities/animations.css` ships a generic scroll-reveal host: a simple
opacity + `translateY(20px)` rise, gated on a `.js-animations` class so the page
is fully visible before JS boots (no-JS safe). It is **wired but not currently
used** by any template — document it as an available primitive, not the active
system.

```css
.js-animations [data-animate] {
  opacity: 0;
  transform: translateY(20px);
  transition:
    opacity var(--duration-slow) var(--ease-standard),
    transform var(--duration-slow) var(--ease-standard);
  transition-delay: var(--animate-delay, 0ms);
}

.js-animations [data-animate].is-visible {
  opacity: 1;
  transform: none;
}
```

To activate it you would add `.js-animations` to `<html>` on boot, an
`IntersectionObserver` to add `.is-visible` on enter, and `[data-animate]` to the
target elements (with an optional `--animate-delay` per element). Under
`prefers-reduced-motion: reduce` the same file resets `[data-animate]` to visible
with no transition.

The file also holds the shared keyframes (`grain`, `scroll`, `fadeIn`,
`slideIn`) used by decorative utilities.

---

## Rules

1. **Always pair motion with a `prefers-reduced-motion` reset.** Every system
   here does; new motion must too.
2. **Parallax runs on the compositor, never on a `scroll` handler.** Use
   `animation-timeline: scroll()`; where it's unsupported, a continuous rAF
   **lerp** loop — gated by `CSS.supports(...)` so the two never both run. (See
   LESSONS.md for why a rAF-throttled scroll handler still stutters.)
3. **GSAP is the only JS animation library.** Name it explicitly; load its
   plugins as library deps. Don't hand-roll tweens that GSAP already does.
4. **One behaviour per component.** Each effect is an IIFE keyed to a single
   `Drupal.behaviors` so unused components ship no dead animation code.
5. **Don't use JS for what CSS handles** — hover, focus rings, simple
   transitions stay in CSS. JS animation is for mount-driven, interaction-driven,
   or scroll-driven effects only.

---

## Drupal port

When this lands in the vanilla Drupal 11+ Canvas (SDC) theme:

- **Hero** → SDC `hero`; `js/hero.js` becomes `Drupal.behaviors.iconHero`,
  attached via `icon/hero` (deps `gsap`, `ScrambleTextPlugin`). The CSS parallax
  ships with the component stylesheet.
- **Tagline** → SDC `tagline`; `js/tagline.js` becomes
  `Drupal.behaviors.iconTagline` via `icon/tagline` (dep `gsap`,
  `InertiaPlugin`).
- The `[data-animate]` host CSS belongs in the theme's global stylesheet; if a
  component adopts it, its observer wiring rides in that component's behaviour
  rather than a shared global so unused components stay dead-code-free.
