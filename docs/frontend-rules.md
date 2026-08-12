# Front-End Rules

This doc OWNS the hard rules, the markup/naming/JS quality bar, and the real
breakpoint table. For the layered import-order/dependency-graph model see
`css-architecture.md`; for `@theme` vs `@theme inline`, `@source`, `@apply` and
the utility-vs-BEM decision table see `tailwind-conventions.md`. This file does
not restate those.

## Principles
- Design for Drupal Canvas handoff via Single Directory Components (SDC).
- Prefer simple structures over clever abstractions.
- Components should be portable and easy to re-template in Twig.
- CSS is layered, predictable, and token-driven through `@theme`.
- BEM is the public class API. Tailwind utilities are a convenience layer for
  layout and spacing, not a substitute for component CSS.

## Hard rules
- **No hex, rgb, or hsl values outside `src/theme/colors.css`.** All colour
  reaches the rest of the codebase as `var(--color-*)` or generated utilities
  (`bg-background`, `text-icon-black`, `border-border`, etc.).
- **No `tailwind.config.js`.** All Tailwind v4 configuration lives in `@theme`
  blocks inside `src/theme/*.css` (see `tailwind-conventions.md`).
- **No raw `@media` queries with hard-coded pixel values.** Use Tailwind's
  default breakpoints plus the project's `3xl`/`4xl` additions (see the table
  below). In markup use Tailwind variants (`md:`, `lg:`, `3xl:`); in component
  CSS prefer `@media (min-width: var(--breakpoint-3xl))` for the custom steps.
- **No utility-class sprawl in markup.** A handful of layout/spacing utilities
  are fine (`u-container`, `u-grid`, `md:grid-cols-2`, `gap-lg`). Repeated
  visual styling belongs in a BEM component file under `src/components/`.
- **No inline `style=""` attributes** except for genuinely dynamic values set
  from JS (parallax offsets, animation delays via CSS custom properties).
- **No new tokens without updating `src/theme/*.css`** and recording the change.
- **No CVA in Twig.** The Drupal theme target applies BEM class names directly.
  Conditional classes are computed in preprocess or inline as plain Twig
  (`{% set classes = ... %}`).

## Project breakpoints
This design uses Tailwind v4's **default** breakpoints, plus two large custom
additions declared in `src/theme/breakpoints.css`. There is no `xs`/`wide`
variant.

```
sm:   640px   (Tailwind default)
md:   768px   (Tailwind default)
lg:   1024px  (Tailwind default — mobile-menu / desktop cutoff)
xl:   1280px  (Tailwind default)
2xl:  1536px  (Tailwind default)
3xl:  1920px  (custom: --breakpoint-3xl: 120rem)
4xl:  2560px  (custom: --breakpoint-4xl: 160rem)
```

`lg` (1024) is the mobile-menu → desktop cutoff (the nav and SERVICES drawer
switch here). In markup, use the variant prefixes:

```html
<div class="grid-cols-1 md:grid-cols-2 3xl:gap-3xl">…</div>
```

In hand-written component CSS, reference the custom property for the large
steps so the value stays single-sourced:

```css
@media (min-width: var(--breakpoint-3xl)) { /* ≥1920 */ }
```

## Markup
- Use semantic landmarks: `header`, `nav`, `main`, `aside`, `footer`, `section`.
- Maintain a logical heading structure.
- Use `<button>` for actions and `<a>` for navigation.
- Avoid wrapper divs that exist only to receive utilities — promote them to a
  BEM element or block instead.
- BEM names look like `.feature-card`, `.feature-card__title`,
  `.feature-card--highlighted`.

## Naming
- BEM for components — block (`.card`), element (`.card__title`), modifier
  (`.card--resource`).
- The component file under `src/components/<name>.css` matches the BEM block
  exactly.
- Utility-primitive helpers are prefixed `u-` (`u-container`, `u-grid`) to mark
  them as primitives. The `u-` prefix is intentional — do not "fix" it.
- Component variants (e.g. `card--news`, `card--resource`) live in the same
  component file unless the modifier file grows beyond ~300 lines, in which case
  split it as `_card-news.css`.

## Responsive design
- Build mobile-first: write the small-screen rule plain, layer breakpoints up.
- Spacing and typography scale through the fluid token scales (`--text-*`,
  `--space-section*`); reach for those before fixed values.
- Do not rely on fixed heights unless truly necessary.
- Text-wrap balancing (`text-wrap: balance` on h1–h4, `pretty` on p/li/dd) is a
  base-layer rule in `src/base/typography.css` — never re-apply it per component.
- Aim for a polished layout at every breakpoint.

## JavaScript
- Use only where needed for interaction, scroll-driven animation, or progressive
  enhancement. Avoid JS for layout or purely visual state.
- Each script is an IIFE that maps cleanly to a `Drupal.behaviors` entry; hook
  into `Drupal.behaviors` rather than running on `DOMContentLoaded` once ported.
- Respect `prefers-reduced-motion` — always pair motion with a reset.
- **For scroll-driven motion, prefer a CSS scroll-driven animation**
  (`animation-timeline: scroll()`) so the browser samples it on the compositor
  in lockstep with scroll. Where that is unsupported, fall back to a continuous
  rAF **lerp** loop (`current += (target - current) * factor`), gated with
  `CSS.supports('animation-timeline: scroll()')` so the two never both run. Do
  **not** drive a transform from a `scroll` event handler, even rAF-throttled —
  it lags a frame and visibly stutters (see `LESSONS.md` and `animation.md`).
- GSAP timelines (hero ScrambleText, tagline cursor-pop/fit-to-width) attach as
  behaviours per component; see `animation.md`.

## Quality bar
- Visually polished across every breakpoint (mobile through `4xl`).
- Readable, balanced typography on a stable spacing rhythm.
- Accessible focus and keyboard behaviour. The focus ring is
  `:focus-visible { outline: 2px solid var(--color-icon-blue); outline-offset: 2px; }`
  in `src/base/reset.css` — do not override it per component.
- `npm run build` produces a clean compile with no warnings.
