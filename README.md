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
│   └── components/       # BEM components, one block per file (24 files — see src/main.css)
├── templates/            # homeC.html (canonical) · news-b.html · work-landing-b.html ·
│                         #   (news.html / work-landing.html are the header-A alternates) ·
│                         #   news-article(-b, -butterfly).html · work-article-master.html
│                         #   + the client folios (work-icanquit, work-nike-marathon,
│                         #   work-moad-democracy, work-athletes-foot) · prototypes: home,
│                         #   homeB, work
├── experiments/          # self-contained one-off explorations (own inline CSS, not built);
│                         #   approved ones get promoted into src/ + templates/
├── js/                   # one IIFE per behaviour → maps to a Drupal.behaviors entry
│                         #   (header, hero-loader, home-c, news, reveal, share, subscribe-reveal,
│                         #    site-footer, theme-handover, velocity-lean, work, work-article,
│                         #    work-filter, work-landing, work-scroller, work-video ·
│                         #    prototypes: hero, hero-sphere, tagline)
├── css/main.css          # build output (committed — GitHub Pages serves it)
├── assets/               # images + video (work portfolio, brand, banners)
├── docs/                 # working conventions + Drupal handoff docs
├── index.html            # design-system index (sidebar, foundations, component demos)
├── scripts/verify.mjs    # the `npm run verify` gate (docs/css-architecture.md, rule 6)
├── scripts/theme-js.mjs  # wraps js/*.js into Drupal.behaviors for the theme (`npm run build:theme`)
├── drupal/               # the Drupal 11 + Canvas site (DDEV project `icon-drupal`, docroot web/)
│   ├── web/themes/custom/icon/   # THE theme: src/main.css imports ../../src/main.css, css/ + js/
│   │                             #   are build outputs, templates/ the chrome + pages, components/ the SDCs
│   ├── config/sync/              # the site's config (drush cex / cim) — content types, paragraphs, views, displays
│   └── scripts/, sample-content/ # drush php:script sample content (news-, work-, clients-sample-content.php, home-page.php) + media
└── server.js             # dev-only static preview server (port 4100)
```

## Develop / build

```sh
npm install
npm run dev      # Tailwind v4 CLI watch → css/main.css (UNMINIFIED)
npm run build    # one-shot, minified → css/main.css
npm run verify   # the gate: build sync · raw colours · media queries · inline styles · theme sync
npm run build:theme  # the Drupal theme: css/main.css (same tokens + the Twig/SDC sources) + js/ behaviours
```

Drupal: `cd drupal && ddev start`, then https://icon-drupal.ddev.site (admin / admin, `ddev drush uli`). The theme is `drupal/web/themes/custom/icon` — see `docs/drupal-handoff.md`, "The theme".

Local preview: `node server.js`, then open http://localhost:4100. `/` serves `index.html` — the design-system index, which links to every template and experiment.

## Theming

Three site themes plus one per-client scope, all class-driven on `<html>` (never `<body>` — see `LESSONS.md`): **light is the default** (no class) on `homeC` and the DS index; the news pages (`news`, `news-b`, the article templates), `work-landing` and `work-landing-b` **open dark** and hand over to light on scroll (`js/theme-handover.js`); the work articles open in the `.theme-work-article` chameleon (a client background + ink pair set inline on `<html>`) and hand over the same way. `.dark` and `.theme-blue` are full token re-themes. The older prototypes (`home`, `homeB`, `work`) still open in `.dark`. The dev-only theme toggle was removed (Sep 2026) — inspect `.dark` / `.theme-blue` by adding the class to `<html>` in devtools.

## Conventions

Read `AGENTS.md` first — it owns the operating rules and points into `docs/`. See `LESSONS.md` for mistakes worth not repeating.
