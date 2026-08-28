# ICON Agency — Tailwind v4

Front-end build for the ICON Agency marketing site: semantic HTML + Tailwind v4 (token system, no `tailwind.config.js`) + minimal vanilla JS + GSAP. Built for a future vanilla Drupal 11+ theme with Drupal Canvas (SDC) handoff.

The canonical page is `templates/homeC.html` (light theme, fully fluid). The root `index.html` is the **design-system index** — a living reference for the tokens and components, wearing the production CSS.

## Layout

```
├── src/                  # CSS source
│   ├── main.css          # entry — imports theme → base → utilities → components → page layers
│   ├── theme/            # @theme tokens (colors, typography, spacing, radius, breakpoints, motion)
│   ├── base/             # reset, typography defaults, .content-page prose wrapper
│   ├── utilities/        # u-container/u-grid, effects, [data-animate] host, page layers
│   │                     #   (home-c.css = the canonical homepage; rules.css = the hairline
│   │                     #    system; design-system.css = the DS index chrome)
│   └── components/       # BEM components, one block per file (22 files — see src/main.css)
├── templates/            # homeC.html (canonical) · news.html · work-landing.html ·
│                         #   news-article(-b, -butterfly).html · prototypes: home, homeB,
│                         #   work, work-article
├── experiments/          # self-contained one-off explorations (own inline CSS, not built);
│                         #   approved ones get promoted into src/ + templates/
├── js/                   # one IIFE per behaviour → maps to a Drupal.behaviors entry
│                         #   (header, hero-loader, home-c, news, reveal, share,
│                         #    site-footer, theme-handover, velocity-lean, work,
│                         #    work-filter, work-landing · prototypes: hero,
│                         #    hero-sphere, tagline · dev-only: theme-toggle)
├── css/main.css          # build output (committed — GitHub Pages serves it)
├── assets/               # images + video (work portfolio, brand, banners)
├── docs/                 # working conventions + Drupal handoff docs
├── index.html            # design-system index (sidebar, foundations, component demos)
└── server.js             # dev-only static preview server (port 4100)
```

## Develop / build

```sh
npm install
npm run dev      # Tailwind v4 CLI watch → css/main.css (UNMINIFIED)
npm run build    # one-shot, minified → css/main.css
npm run verify   # alias for build
```

Local preview: `node server.js`, then open http://localhost:4100. `/` serves `index.html` — the design-system index, which links to every template and experiment.

## Theming

Three themes, all class-driven on `<html>` (never `<body>` — see `LESSONS.md`): **light is the default** (no class) on `homeC` and the DS index, while the news pages (`news`, `news-b`, the article templates) and `work-landing` **open dark** and hand over to light on scroll (`js/theme-handover.js`); `.dark` and `.theme-blue` are full token re-themes. The older prototypes (`home`, `homeB`, `work`, `work-article`) still open in `.dark`. A dev-only toggle (`js/theme-toggle.js`, remove before shipping) cycles the three.

## Conventions

Read `AGENTS.md` first — it owns the operating rules and points into `docs/`. See `LESSONS.md` for mistakes worth not repeating.
