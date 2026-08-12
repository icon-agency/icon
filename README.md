# ICON Agency — Tailwind v4

Front-end build for the ICON Agency marketing site: semantic HTML + Tailwind v4 (token system, no `tailwind.config.js`) + minimal vanilla JS + GSAP. Built for a future vanilla Drupal 11+ theme with Drupal Canvas (SDC) handoff. Dark mode is the default.

## Layout

```
├── src/                  # CSS source
│   ├── main.css          # entry — imports theme → base → utilities → components
│   ├── theme/            # @theme tokens (colors, typography, spacing, radius, breakpoints, motion)
│   ├── base/             # reset, typography defaults, .content-page prose wrapper
│   ├── utilities/        # container, effects, animations, home-page overrides
│   └── components/       # BEM components, one block per file (site-header, hero, logo-loop, tagline)
├── templates/home.html   # home page markup
├── js/                   # one IIFE per behaviour (header.js, hero.js, tagline.js)
├── css/main.css          # build output
├── assets/               # images (work portfolio)
├── docs/                 # working conventions + Drupal handoff docs
├── index.html            # menu / orientation page
├── server.js             # dev-only static preview server (port 4100)
└── v40-1-icon/           # v0/Next.js export — read-only design reference, not built
```

## Develop / build

```sh
npm install
npm run dev      # Tailwind v4 CLI watch → css/main.css (UNMINIFIED)
npm run build    # one-shot, minified → css/main.css
npm run verify   # alias for build
```

Local preview: `node server.js`, then open http://localhost:4100. `/` serves `index.html` — a template index linking to every page under `templates/`.

## Conventions

Read `AGENTS.md` first — it owns the operating rules and points into `docs/`. See `LESSONS.md` for mistakes worth not repeating.
