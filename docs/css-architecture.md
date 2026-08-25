# CSS Architecture

The stylesheet is layered. Each layer consumes only the layers below it. The dependency graph is literally the import order in `src/main.css` — no circular dependencies, nothing in a lower layer references a higher layer by name.

```
theme       →  design tokens (@theme / @theme inline / :root — colours, type, space, radius, breakpoints, motion)
base        →  element resets, global typography, prose wrapper
utilities   →  layout primitives (.u-container, .u-grid), bespoke effects, animation host
components  →  BEM components (one block per file)
page        →  page layers (canonical homepage system, work-article theme, hairline system, DS chrome)
```

Tailwind v4 is the build engine. It runs the `@import "tailwindcss"` core (preflight + utility generation), reads every `@theme` / `@theme inline` block to know which tokens become utilities, scans the `@source` paths to know which utilities are actually used, then emits CSS to `css/main.css` (`npm run dev` watches unminified; `npm run build` minifies). For `@theme` vs `@theme inline`, `@source`, and `@apply`, see [tailwind-conventions.md](tailwind-conventions.md).

---

## What goes in each layer

### `src/theme/` — tokens

Design values only. Every number, colour, font value, breakpoint, and motion constant in the system starts here. No selectors except `@theme`, `@theme inline`, and the theme scopes `:root` / `.dark` / `.theme-blue`.

- `colors.css` — **the only file where raw colour values (hex / rgb / oklch) may appear.** Raw values live in `:root` (light — the default on the canonical pages), `.dark`, and `.theme-blue`; shadcn neutral roles are mapped through `@theme inline` so a runtime theme class on `<html>` re-themes without a rebuild. Produces: semantic roles `--color-background/foreground/card/popover/primary/secondary/muted/accent/destructive/border/input/ring`, `--color-chart-1..5`, `--color-sidebar-*`; brand `--color-icon-black` (#111) / `--color-icon-grey` / `--color-icon-blue`; fixed `--color-white/--color-black/--color-shader-blue`. Also defines the non-`@theme` component roles (`--nav-bg-scrolled`, `--pager-surface`/`--pager-surface-strong`, `--work-article-text`/`--work-article-bg`) and the hairline tokens `--rule` / `--rule-on-color` consumed by `utilities/rules.css`.
- `typography.css` — `--font-sans` (Kumbh Sans, Google) and `--font-serif` ("miller-text", Typekit kit `bhv7yrj`) via `@theme inline`; the fluid `--text-xs … --text-9xl` scale via `@theme` (wired to the `text-*` utilities); plus `:root` semantic sizes (`--heading-1/-2/-3`, `--card-title`, `--card-subtitle`), the three **voice roles** — `--voice-mid` (the site's one "sentence" size), `--voice-meta` (the quiet meta line) and `--voice-display`, the display voice shared by the big caps mastheads and the homepage intro statement (one `clamp()`; `--logo-h` remains as the wordmark's stepped ladder, consumed by the news lead's sticky offset) — and `--font-weight-*`, `--line-height-*`, `--letter-spacing-*`.
- `spacing.css` — named `--spacing-3xs … --spacing-3xl` via `@theme` (generate `p-md`, `gap-lg`, `m-xl`, … alongside Tailwind's numeric scale); plus `:root` fluid rhythm `--space-section`, `--space-section-sm`, the fluid clamp ramp `--space-3xs … --space-2xl` (one spacing curve for the homepage's internal rhythm), and layout `--container-max` (90rem), `--container-pad`. The canonical homepage overrides the container pair per-page (`.page-home` runs uncapped with a wider fluid gutter — see `utilities/home-c.css`).
- `radius.css` — `:root --radius` (0.625rem) base, with `--radius-sm/md/lg/xl` derived from it via `@theme inline`, plus the brand `--radius-icon` (4px) via `@theme`.
- `breakpoints.css` — only the two large additions, `--breakpoint-3xl` (120rem / 1920px) and `--breakpoint-4xl` (160rem / 2560px). Everything else uses Tailwind v4's default `sm/md/lg/xl/2xl`. See the breakpoint table in [frontend-rules.md](frontend-rules.md).
- `motion.css` — `--ease-standard/accelerate/decelerate` via `@theme` (exposed as `ease-*`); plus `:root` `--duration-fast/normal/slow/slower`, composed `--transition-base/--transition-slow`, and effect knobs `--overlay-blur/--overlay-opacity/--video-filter`.

Tokens declared in `@theme` / `@theme inline` become Tailwind utilities (`bg-background`, `text-icon-black`, `text-2xl`, `gap-lg`, `rounded-xl`). The `:root`-only tokens (e.g. `--heading-1`, `--space-section`, `--transition-base`) are deliberately **not** utility-generating — they exist to be consumed directly as `var(--token)` in component and base CSS.

### `src/base/` — element defaults

Element-level defaults, all inside `@layer base`. No classes except the `.content-page` prose wrapper.

- `reset.css` — additive on top of Tailwind's preflight: `text-size-adjust`, `body { overflow-x: clip }` (**`clip`, not `hidden`** — `hidden` promotes `<body>` to a scroll container and silently freezes every `view()`-timeline animation; see LESSONS.md), block-level media defaults, the focus ring `:focus-visible { outline: 2px solid var(--color-icon-blue); outline-offset: 2px }`, and a reduced-motion `scroll-behavior` guard.
- `typography.css` — body font/size/colour (Kumbh Sans @ `--text-base`), a global colour/background cross-fade so theme flips (light ⇄ dark ⇄ blue) ease rather than snap, `h1` weight + `--heading-1` size, `text-wrap: balance` on `h1`–`h4`, `text-wrap: pretty` on `p`/`li`/`dd`. These wrap rules are a base-layer concern — never re-applied per component.
- `prose.css` — the `.content-page` wrapper that styles every element CKEditor can emit, via brand tokens (`--icon-black`, `--icon-grey`, `--icon-blue`, `--secondary`, `--border`, `--font-serif`, `--radius-icon`). Includes a `prefers-reduced-motion` block. See [wysiwyg-output.md](wysiwyg-output.md).

### `src/utilities/` — narrow-purpose helpers

Single-concern helpers that don't belong to any one component.

- `container.css` — `.u-container`, `.u-container--wide`, `.u-grid` (12-track default). These are the **only** hand-written layout primitives; the `u-` prefix marks them as primitives (not BEM, not Tailwind).
- `effects.css` — bespoke cross-component helpers ported from the v0 build: `.miller-text`, `.fluid-heading`, `.icon-tagline`, `.text-card-title`, `.text-card-subtitle`, `.icon-video-filter`, `.homepage-icon-black`, `.scrollbar-hide`, `.overlay-frosted-glass`, `.animated-grain`, plus `.dark` overrides for the imagery filters and a reduced-motion guard.
- `animations.css` — the `[data-animate]` scroll-reveal host (gated by `.js-animations`, toggled with `.is-visible`, no-JS-safe), its **masked line-rise variant** (the mastheads' word-by-word rise), the shared keyframes, and reduced-motion guards. The host is **actively used** — `js/reveal.js` drives it on every template. See [animation.md](animation.md).

Utilities are **not** layout shortcuts in the Tailwind sense. There is no `.mt-4`, `.text-center`, `.flex` here — Tailwind's own utility generator already covers those when needed in markup.

### `src/components/` — BEM components

One BEM block per file. Each file owns everything that starts with that block name. Variants live in the same file unless they grow large enough to justify splitting. Current files (in import order): `site-header`, `hero` (the home-A `.video-hero`), `hero-sphere`, `logo-loop`, `tagline`, `reveal`, `section-title`, `more-link`, `project-card`, `work-section`, `work-grid`, `page-header`, `filter-bar`, `pager`, `image-grid`, `pull-quote`, `more-work`, `site-footer`, `quiet-link`, `text-box`, `news-card`, `news-list`. Each file opens with a comment naming its markup home, its behaviour file, and its Drupal mapping — that header is the component's documentation.

The canonical homepage's blocks (`.hero`, `.intro`, `.work`, `.clients`, `.news`, `.arrow-link`, `.media-reveal`, `.page-home`) live in the page layer (`utilities/home-c.css`) rather than one-file-per-block — promoted to public API names, catalogued in the design-system index, and split out per block when they move to SDCs. When a homepage block gets a second consumer it is promoted to `src/components/` (that is how `news-card.css` happened).

Components author plain CSS against the `var(--token)` API. `@apply` is allowed but rare — most rules read like:

```css
.text-card-title {
  font-size: var(--card-title);
  font-weight: var(--font-weight-medium);
  color: var(--color-icon-black);
}
```

This makes components portable into the Drupal theme without any Sass / Tailwind-specific syntax. See [drupal-handoff.md](drupal-handoff.md).

### Page layers

Imported last so they cascade over everything. Five files, each a deliberate scope:

- `home-page.css` — tiny layout shims for the home-A prototype.
- `work-article.css` — the `/work/article` case study: the `.theme-work-article` per-route light theme (on `<html>`, alongside `.dark` — see LESSONS.md) plus folio-specific layout.
- `rules.css` — **the hairline system**: every rule on the site is 1px of `--rule`, and `[data-rule]` lines draw in from the left on scroll (background-gradient technique for border-drawn rules so nothing re-layouts; scaleX for pseudo-element rules). Cross-page, but imported here because it must out-cascade component borders.
- `home-c.css` — the **canonical homepage's page system** (`templates/homeC.html`): `.page-home` shell (uncapped fluid container), hero stack-takeover + reel + curtain, intro strip, work grid, clients marquee, news rail, and the shared reveal-fx primitives (`.media-reveal` crop-zoom, `.split-line` masks). Its block names are the site's public API and the future SDC names.
- `design-system.css` — chrome for the design-system index (`/index.html`): sidebar shell, scrollspy states, swatches, specimens. Demos inside the page wear the production classes; this file styles only the DS scaffolding.

Prefer adding a modifier to a component over a page-level override; a page layer earns a new rule only when the rule genuinely belongs to that page.

---

## Rules

1. **No layer skips a layer upward.** A token file never references a component class. A component never overrides a token value.
2. **Pages are not a dumping ground.** If you find yourself writing page-level CSS to tweak a component, add a modifier to the component instead.
3. **Components compose; they don't redefine.** A hero reuses a shared helper (`.overlay-frosted-glass`, `.miller-text`); it doesn't restyle it.
4. **One BEM block per file.** A component file owns everything starting with its block name. Split only when variants grow unwieldy.
5. **Imports live in `src/main.css` in layer order.** Tokens first, page-specific last. The order is the dependency graph.

---

## `src/main.css`

The entry file is the dependency graph, verbatim:

```css
/* ICON Agency — build entry.
 * Tailwind v4 runs the core, reads every @theme block to know which tokens
 * become utilities, scans the @source paths for used classes, and emits the
 * minified result to css/main.css.
 *
 * The import order below IS the dependency graph:
 *   theme → base → utilities → components → page-specific
 * No layer references a layer above it.
 */

@import "tailwindcss";

/* Where Tailwind looks for utility classes used in markup. */
@source "../templates";
@source "../index.html";

/* Class-based dark mode (.dark on <html>) — the site default. */
@custom-variant dark (&:is(.dark *));

/* Tokens */
@import "./theme/colors.css";
@import "./theme/typography.css";
@import "./theme/spacing.css";
@import "./theme/radius.css";
@import "./theme/breakpoints.css";
@import "./theme/motion.css";

/* Base */
@import "./base/reset.css";
@import "./base/typography.css";
@import "./base/prose.css";

/* Utilities */
@import "./utilities/container.css";
@import "./utilities/effects.css";
@import "./utilities/animations.css";

/* Components — one BEM block per file */
@import "./components/site-header.css";
@import "./components/hero.css";
@import "./components/hero-sphere.css";
@import "./components/logo-loop.css";
@import "./components/tagline.css";
@import "./components/reveal.css";
@import "./components/section-title.css";
@import "./components/more-link.css";
@import "./components/project-card.css";
@import "./components/work-section.css";
@import "./components/work-grid.css";
@import "./components/page-header.css";
@import "./components/filter-bar.css";
@import "./components/pager.css";
@import "./components/image-grid.css";
@import "./components/pull-quote.css";
@import "./components/more-work.css";
@import "./components/site-footer.css";
@import "./components/quiet-link.css";
@import "./components/text-box.css";
@import "./components/news-card.css";
@import "./components/news-list.css";

/* Page-specific overrides — keep tiny */
@import "./utilities/home-page.css";
@import "./utilities/work-article.css";
@import "./utilities/rules.css";
@import "./utilities/home-c.css";
@import "./utilities/design-system.css";
```
