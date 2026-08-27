# Content Rhythm

## Typography
- Use the fluid type scale defined in `src/theme/typography.css` (`--text-xs` through `--text-9xl`). It's wired to Tailwind's type utilities, so markup (`text-2xl`, `text-4xl`, …) and component CSS (`var(--text-2xl)`) agree on one scale. Note `--text-7xl`/`8xl`/`9xl` are fixed rem values, not clamps.
- For semantic headings and cards, reach for the display tokens: `--heading-1`/`--heading-2`/`--heading-3` and `--card-title`/`--card-subtitle`.
- Three **voice roles** (in `src/theme/typography.css`) carry most of the canonical pages' running text — reach for these before inventing a size:
  - `--voice-mid` — the site's one "sentence" size (hero client label, intro statement, expertise links, work tile titles, "More" links, footer blurb). Medium weight, `-0.01em` tracking, 1.2 line-height.
  - `--voice-meta` — the quiet meta line (card categories/dates, footer place names, filter labels).
  - `--voice-label` — chrome and wayfinding (nav pill links, mobile menu labels, menu toggle, arrow-links): bold, uppercase, page-independent — the nav used to ride `--text-xs`, which the homepage re-scopes larger, so the header changed size per page.
  - the **display voice** (`--voice-display`) — the big caps mastheads (WORK, NEWS & INSIGHTS, the /news `h1`, the footer's LET'S TALK) *and* the homepage intro statement, which share one curve: `clamp(2.4rem, 5.6vw, 9rem)`. `.intro` aliases the token rather than repeating the literal, so the statement and the mastheads are the same size by construction. Pair it with `--voice-display-lh` (0.82) for the stacked caps settings; the intro statement runs looser because it is sentence case. (This replaced a wordmark cap-height derivation — the reasoning is in `src/theme/typography.css`. `--logo-h` is now the wordmark's own fluid height — the stepped ladder is retired — and the sticky offsets derive from it.)
- Base `<h1>` is already wired in `src/base/typography.css` (weight `--font-weight-semibold`, size `--heading-1`, tight line-height). Other component headings are styled per component.
- Avoid oversized headings that dominate smaller screens — the clamp scale already handles graceful growth.
- Two type families: `--font-sans` (Kumbh Sans, via Google Fonts) for body and everything by default, `--font-serif` ("miller-text", via Typekit kit `bhv7yrj`) opted in per element. Two opt-ins, both bold (700 — the kit ships upright and italic at 400/700, no heavier cut): `.miller-text` for the italic display accents, and `.masthead-accent` for the accent line inside a caps masthead — upright, and text-transform reset so it stays as authored (the "& Insights" line of both news mastheads, which is why they read NEWS / & Insights rather than all caps).

## Spacing
- Use token-based spacing only. The named `--spacing-*` keys (`--spacing-3xs` through `--spacing-3xl`) generate utilities — reach for `gap-lg`, `p-md`, `m-xl` in markup. Tailwind's numeric scale (`p-4`, `gap-8`) is still available.
- For large responsive vertical rhythm between major sections, use `var(--space-section)` (or `var(--space-section-sm)`) in component CSS.
- The homepage's internal rhythm runs on one fluid clamp ramp, `--space-3xs … --space-2xl` (`src/theme/spacing.css`) — one curve, so retunes are one edit. Prefer it over bespoke clamps in page-layer CSS.
- The canonical homepage (`.page-home`) is **uncapped and fully fluid**: `.u-container`'s 90rem cap is lifted and `--container-pad` widened per-page (see `utilities/home-c.css`), so type and space keep growing on wide screens; the rest of the site keeps the 90rem default.
- Avoid one-off spacing overrides unless documented inline with a `/* Custom value: <why> */` comment.

## Readability
- Keep line length comfortable in text-heavy sections. Rich-text rhythm and measure are owned by the `.content-page` wrapper in `src/base/prose.css`.

## Text wrapping
- Headings (`h1`–`h4`) get `text-wrap: balance` globally in `src/base/typography.css`.
- Body copy (`p`, `li`, `dd`) gets `text-wrap: pretty` globally in `src/base/typography.css`.
- Both are progressive enhancements; no fallback required.
- **Do not re-apply per component.** The base rule covers every element. Component CSS that adds `text-wrap` is duplicative and a maintenance risk.

For rich-text body fields rendered through CKEditor, all prose rhythm is owned by the `.content-page` wrapper in `src/base/prose.css`. See `wysiwyg-output.md`.
