# Content Rhythm

## Typography
- Use the fluid type scale defined in `src/theme/typography.css` (`--text-xs` through `--text-9xl`). It's wired to Tailwind's type utilities, so markup (`text-2xl`, `text-4xl`, …) and component CSS (`var(--text-2xl)`) agree on one scale. Note `--text-7xl`/`8xl`/`9xl` are fixed rem values, not clamps.
- For semantic headings and cards, reach for the display tokens: `--heading-1`/`--heading-2`/`--heading-3` and `--card-title`/`--card-subtitle`.
- Base `<h1>` is already wired in `src/base/typography.css` (weight `--font-weight-semibold`, size `--heading-1`, tight line-height). Other component headings are styled per component.
- Avoid oversized headings that dominate smaller screens — the clamp scale already handles graceful growth.
- Two type families: `--font-sans` (Kumbh Sans, via Google Fonts) for body and everything by default, `--font-serif` ("miller-text", via Typekit kit `bhv7yrj`) opted in via the `.miller-text` utility for italic display accents.

## Spacing
- Use token-based spacing only. The named `--spacing-*` keys (`--spacing-3xs` through `--spacing-3xl`) generate utilities — reach for `gap-lg`, `p-md`, `m-xl` in markup. Tailwind's numeric scale (`p-4`, `gap-8`) is still available.
- For large responsive vertical rhythm between major sections, use `var(--space-section)` (or `var(--space-section-sm)`) in component CSS.
- Avoid one-off spacing overrides unless documented inline with a `/* Custom value: <why> */` comment.

## Readability
- Keep line length comfortable in text-heavy sections. Rich-text rhythm and measure are owned by the `.content-page` wrapper in `src/base/prose.css`.

## Text wrapping
- Headings (`h1`–`h4`) get `text-wrap: balance` globally in `src/base/typography.css`.
- Body copy (`p`, `li`, `dd`) gets `text-wrap: pretty` globally in `src/base/typography.css`.
- Both are progressive enhancements; no fallback required.
- **Do not re-apply per component.** The base rule covers every element. Component CSS that adds `text-wrap` is duplicative and a maintenance risk.

For rich-text body fields rendered through CKEditor, all prose rhythm is owned by the `.content-page` wrapper in `src/base/prose.css`. See `wysiwyg-output.md`.
