# AGENTS.md

## What this project is
Front-end build (semantic HTML + Tailwind v4 CSS + minimal vanilla JS + GSAP) for the **ICON Agency** marketing site. It is built to be handed off into a future **vanilla Drupal 11+ theme using Drupal Canvas** (Single Directory Components / SDC). It is **not** GovCMS and **not** a Mercury-derived theme.

Theming is class-driven on `<html>` (never `<body>` — the `@theme inline` aliases resolve at `:root`; see LESSONS.md). **Light is the default** (no class) on `templates/homeC.html` and the design-system `index.html`; the news pages (`templates/news.html`, its header-B variant `news-b.html` and the article templates) and the work landing (`templates/work-landing.html`) **open dark** (`class="dark"` in the markup) and hand over to light on scroll via the shared `js/theme-handover.js`. `.dark` and `.theme-blue` are full token re-themes; the `dark` variant is declared in `src/main.css` via `@custom-variant dark (&:is(.dark *))`, and the older prototypes (`home`, `homeB`, `work`, `work-article`) still open in `.dark`. A dev-only toggle (`js/theme-toggle.js`) cycles light → dark → blue and is removed at ship time.

## Core constraints
- **Tailwind v4 only.** No Sass pipeline. No `tailwind.config.js` — all configuration lives in `@theme` blocks in CSS.
- **Token-driven.** Every design value flows through `@theme` tokens. No hex / rgb / hsl / oklch outside `src/theme/colors.css`.
- **BEM for components.** Follow BEM naming (`.card`, `.card__title`, `.card--resource`). Plain CSS authored against tokens is the primary mode; Tailwind utilities are available alongside, but BEM components stay BEM.
- **Semantic HTML, progressive enhancement, accessibility by default.**
- **JS only where it earns it.** Keep JavaScript out of purely presentational behaviour — use it for scroll-driven, mount-driven, or interactive behaviour only. Each JS file is an IIFE that maps cleanly to a `Drupal.behaviors` entry.
- **Do not introduce:** a Sass pipeline, React, CSS-in-JS, inline `style=""` attributes, utility-class sprawl in markup, or CVA in Twig templates (the Drupal Canvas target rejects CVA — apply BEM classes directly).

## Read these files first
- `/docs/frontend-rules.md`
- `/docs/css-architecture.md`
- `/docs/tailwind-conventions.md`
- `/docs/drupal-handoff.md`
- `/docs/drupal-mapping-pattern.md`
- `/docs/field-naming.md`
- `/docs/wysiwyg-output.md`
- `/docs/accessibility-checklist.md`
- `/docs/content-rhythm.md`
- `/docs/animation.md`
- `/docs/definition-of-done.md`
- `/LESSONS.md` — the running corrections log. Read it **before** starting work and avoid repeating known mistakes.

## Planning rule
Before writing code for any new component, page, or multi-file change, propose a short implementation plan, list affected files, and wait for approval.

For single-file tweaks, small fixes, or copy changes, proceed directly.

Keep plans brief: 3–6 bullets maximum.

## Working style
- Reuse existing patterns before creating new ones. `src/components/` already holds a BEM component for most patterns. Look there first.
- Keep templates thin and styles predictable.
- When creating a component, document where it maps to Drupal (paragraph type → preprocess → SDC).
- When unsure, choose the simplest implementation that supports the vanilla Drupal / Canvas (SDC) handoff.

## Output expectations
- Clean, readable, semantic markup.
- CSS organized as `theme` → `base` → `utilities` → `components` → page-specific. The import order in `src/main.css` **is** the dependency graph — no layer references a layer above it.
- Responsive behaviour built on Tailwind v4's default breakpoints — `sm:640`, `md:768`, `lg:1024`, `xl:1280`, `2xl:1536` — plus the two custom breakpoints in `src/theme/breakpoints.css`: `3xl:1920` (`120rem`) and `4xl:2560` (`160rem`). `lg` (1024) is the mobile-menu / desktop cutoff. There is no `xs` or `wide` variant.
- Good keyboard and focus behaviour. The focus ring is defined once in `src/base/reset.css`: `:focus-visible { outline: 2px solid var(--color-icon-blue); outline-offset: 2px; }`. Do not re-style focus per component.
- Balanced line lengths and a stable spacing rhythm via the token scales (`--spacing-*` → `p-*`/`gap-*`/`m-*`, plus `--space-section*` and container tokens in `src/theme/spacing.css`).

## Build
- `npm run dev` — Tailwind v4 CLI watch mode, **unminified** → `css/main.css`
- `npm run build` — one-shot, minified → `css/main.css`
- `npm run verify` — alias for `build`

The build is driven by `src/main.css`, which `@import`s the token files, base styles, utilities, and every component file in order. `@source "../templates"` and `@source "../index.html"` tell Tailwind where to scan for utility classes used in markup. Local preview: `node server.js` on port 4100.

## Reference vs. live source
- **Live build source:** `src/`, `templates/`, `index.html`, `package.json`, `js/`. This is what Tailwind compiles and what ships. `templates/homeC.html` is the **canonical** template; `home.html`, `homeB.html`, `work.html` and `work-article.html` are earlier prototypes kept browsable but outside the system.
- **Reference only:** `experiments/` holds self-contained one-off explorations (each carries its own inline CSS and is not part of the Tailwind build). Approved experiments get **promoted** into `src/` + `templates/` (the homeC hero, intro strip, kinetic text box and footer all started here) — do not link production pages to experiment files or import from them.
- The v0 / Next.js export (`v40-1-icon/`) that seeded the port has been **removed from the repo**; comments citing it (e.g. "port of v40-1-icon/components/…") are provenance notes, not live paths.

## Before finishing
- Validate against `/docs/definition-of-done.md`.
- Note any Drupal preprocess or paragraph-type assumptions for the handoff component.
- Flag anything that should be confirmed by a Drupal developer.
