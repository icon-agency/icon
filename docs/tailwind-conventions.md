# Tailwind v4 Conventions

Tailwind v4 here is **primarily a token system**, not a markup-utility system. Tokens enter through `@theme`, get exposed as CSS custom properties, and get generated into utilities. Components are still BEM. Utilities are a convenience layer, not a substitute.

This page documents the rules and the small set of conventions that keep the two layers from fighting each other.

---

## No `tailwind.config.js`

Tailwind v4 is CSS-first. Configuration lives in `@theme` blocks inside `src/theme/*.css`. There is **no** JS config file, and adding one is a regression.

If you need to extend the theme, add a token to the relevant `src/theme/*.css` file. Tailwind generates a matching utility automatically.

---

## `@theme` vs `@theme inline`

Both forms appear in the codebase, and the choice is load-bearing:

- `@theme { --color-shader-blue: #142cfe; }` — Tailwind generates the utility (`bg-shader-blue`) from the **literal value** at compile time. Use it for fixed, non-theming values.
- `@theme inline { --color-icon-blue: var(--icon-blue); }` — Tailwind emits the utility as `color: var(--icon-blue)`, so swapping the upstream `--icon-blue` variable at runtime (e.g. when `.dark` flips on `<html>`) changes the rendered colour **without a rebuild**.

This is why `src/theme/colors.css` keeps raw oklch/hex/rgb values in `:root` (light — the canonical default), `.dark`, and `.theme-blue`, then maps the semantic and brand roles through `@theme inline`. A theme class on `<html>` re-themes `text-icon-black`, `bg-icon-blue`, `bg-background`, `border-input`, etc. live — the class must sit on `<html>`, not `<body>`, because the registered aliases resolve at `:root` (see LESSONS.md). The fixed `--color-white` / `--color-black` / `--color-shader-blue` are declared in a plain `@theme` block because they never re-theme.

The same split appears elsewhere: `radius.css` maps `--radius-sm/md/lg/xl` through `@theme inline` (they derive from the `:root` `--radius`), while `--radius-icon: 4px` is a plain `@theme` literal. `typography.css` maps `--font-sans` / `--font-serif` inline and declares the fluid `--text-*` scale as plain `@theme`.

**Rule of thumb:** if the value is computed from or aliases another custom property that may change at runtime, use `@theme inline`. If it's a final literal, use plain `@theme`.

---

## `@source` directives

Tailwind only generates utilities for classes it can find in source files. `src/main.css` declares:

```css
@source "../templates";
@source "../index.html";
```

If a new directory contains markup using Tailwind utilities (a new `pages/` folder, embedded partials, etc.), add an `@source` line. Without it, the utilities won't exist at runtime.

---

## When to use a utility vs. a BEM class

| Situation | Use |
|---|---|
| One-off layout (`lg:grid-cols-2 gap-lg`) on a section wrapper | Tailwind utility |
| Reusable visual style (card, hero, site header) | BEM component file in `src/components/` |
| Spacing on a layout grid (`u-grid`, `gap-xl`) | Tailwind utility |
| Spacing inside a component (`padding`, `gap`) | Component CSS reading `var(--spacing-*)` |
| Conditional state class for JS (`is-open`, `is-visible`) | BEM state class — never a Tailwind utility |
| Theming swap (light surface vs dark surface) | Semantic role token (`bg-background`, `text-foreground`) that already follows `.dark` — not a hand-written modifier |

The mental model: **if the same class would repeat on three or more elements, it belongs in a component file.** Utilities are for the seam between components.

---

## `@apply` rules

`@apply` is allowed but kept rare. Acceptable uses:

- Inside `@layer base` to apply utility-styled defaults to bare elements.
- Inside `@layer components` to compress a long chain of utilities that genuinely don't deserve their own variable.
- Never in markup (Tailwind's `@apply` is a CSS feature, not a markup feature).

Component CSS in this project almost always reads `var(--token)` directly rather than `@apply`. That keeps components portable into the Drupal Canvas (SDC) theme without any Tailwind dependency on the consumer side.

---

## Available utility surface

Because of the `@theme` token set, the following are available in markup alongside BEM classes:

- **Colours** — semantic roles (`bg-background`, `text-foreground`, `bg-card`, `border-input`, `bg-muted`, `text-destructive`), brand (`text-icon-black`, `bg-icon-blue`, `border-icon-black`, `text-icon-grey`), and fixed (`bg-white`, `text-black`, `bg-shader-blue`). Slash-opacity works (`bg-icon-blue/40`). The semantic and brand roles follow the `.dark` / `.theme-blue` overrides automatically.
- **Typography** — fluid `text-xs` through `text-9xl` (wired to the `--text-*` clamp scale), plus `font-sans` (Kumbh Sans) and `font-serif` (`miller-text`, used italic via `.miller-text`).
- **Spacing** — named scale `p-3xs`/`p-2xs`/`p-xs`/`p-sm`/`p-md`/`p-lg`/`p-xl`/`p-2xl`/`p-3xl` (and the `m-*` / `gap-*` equivalents), generated alongside Tailwind's default numeric scale (`p-4`, `gap-8` — still available).
- **Radius** — `rounded-sm`, `rounded-md`, `rounded-lg`, `rounded-xl`, plus the brand `rounded-icon` (4px) for media/cards.
- **Breakpoints** — default variants `sm:` (640) · `md:` (768) · `lg:` (1024, the mobile-menu/desktop cutoff) · `xl:` (1280) · `2xl:` (1536), plus the two custom additions `3xl:` (1920) and `4xl:` (2560) from `src/theme/breakpoints.css`. There is no `xs:` or `wide:` variant. In component CSS use `@variant 3xl { … }` or the literal pixel value — `var(--breakpoint-*)` cannot be used inside `@media`.

If a utility doesn't generate (typo, missing token), check whether the token exists in `src/theme/*.css` and whether the markup is covered by an `@source` path.

---

## Variants: no CVA

The reference design systems lean on CVA for component variants. **This project does not.** ICON applies BEM class names directly, and variant selection is a data concern resolved before render:

- Conditional/modifier classes are computed in preprocess (`$variables['modifier_class']`) or via plain Twig (`{% set classes = ... %}`).
- `html_cva()` does **not** appear in templates.
- The `cva` Drupal module is **not** a dependency.

See `drupal-mapping-pattern.md`.

---

## What to do when something doesn't fit

If a pattern doesn't fit into BEM + tokens + sparse utilities, stop and ask. Common cases:

- "I need to override Tailwind's preflight" — write a `@layer base` rule in `src/base/` instead.
- "I need a new colour" — add it to `src/theme/colors.css` (the only file where raw colour values may appear); Tailwind generates the utility automatically.
- "I need a one-off breakpoint" — first check whether the existing breakpoints cover it. If genuinely not, add it to `src/theme/breakpoints.css` rather than hard-coding a pixel value.
- "I need a fluid value not in the scale" — author it as a `clamp()` directly in the component file with a `/* Custom fluid value: <reasoning> */` comment, **or** add a token to `src/theme/spacing.css`.
