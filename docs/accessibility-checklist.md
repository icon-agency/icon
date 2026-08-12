# Accessibility Checklist

This is the canonical a11y home for the project. Other docs (e.g. definition-of-done) link here rather than restate any of it.

## Structure
- Page has one clear `<main>` landmark.
- Landmarks (`header`, `nav`, `main`, `aside`, `footer`) used appropriately.
- Heading order is logical — page owns `h1`, prose starts at `h2`.
- Lists are real lists when content is list-like.

## Keyboard
- All interactive elements are keyboard reachable.
- Focus order is logical.
- No keyboard traps (the mobile menu and SERVICES drawer must return focus and be Esc-dismissable).
- Skip link is present and visible on focus.

## Focus
- Focus indicators are clearly visible.
- The project focus ring is `outline: 2px solid var(--color-icon-blue)` with `outline-offset: 2px`, set on `:focus-visible` in `src/base/reset.css`. Override only when the default would be invisible on a particular background — then bump the offset or invert. (There is no `--color-focus` token.)
- Focus is never removed without replacement.
- Hover-only behaviour has a keyboard equivalent (pair a `:focus-visible` sibling with every `:hover` rule).

## Motion
- Respect `prefers-reduced-motion`. Current motion is two systems, each reduced-motion-guarded:
  - **GSAP timelines** attached per component in JS — the hero ScrambleText headline reveal and the tagline cursor-pop trail / fit-to-width.
  - **CSS scroll-driven parallax** on the hero video (with a rAF-lerp JS fallback).
  - The `[data-animate]` scroll-reveal host in `src/utilities/animations.css` is an available primitive (currently unused) and is also cancelled under reduced motion.
- The reduced-motion guards live in `src/base/reset.css` (`scroll-behavior: auto`) and `src/utilities/animations.css` (zeroes `[data-animate]` and `[data-parallax*]` transforms). Any new component-level transition needs its own guard.
- Avoid unnecessary animation; motion is never required to understand content.

## Content
- Link text is meaningful (no "click here").
- No two links on a page share an accessible name while pointing at different destinations. Repeated CTAs ("Read more", "View service details") get an `sr-only` suffix naming the target: `View service details<span class="sr-only"> for [service]</span>`. Entities sharing a title need a qualifier (e.g. suburb). Same rule for repeated buttons (`aria-label="Save [title]"`).
- Buttons describe the action.
- Images have an alt-text strategy. Decorative images / SVGs take `alt=""` or `aria-hidden="true"`.
- Contrast meets WCAG AA. The palette is oklch neutral semantic roles plus brand `--icon-black` / `--icon-grey` / `--icon-blue`, defined only in `src/theme/colors.css`. Dark mode is the **default** (`.dark` on `<html>`), so verify any new colour pairing against the `.dark` values — `--icon-black` inverts to `#f5f5f5` and `--icon-blue` to a translucent white in dark mode.

## Forms and interactive patterns
- Labels are present (visible or `sr-only` via Tailwind's built-in `sr-only` utility — the templates already use `sr-only`, e.g. the hero `<h1>`).
- Errors are understandable; instructions are clear and precede the field they describe.

Generic form a11y principles to carry into any multi-step / contact flow:
- **Validate on Continue.** On submit, render an error summary at the top that lists each problem, anchor-links each item to its field, and moves focus to the first invalid field.
- **Mark only optional fields** with "(optional)" — never asterisk the required ones.
- **One question (or tight group) per screen.** On each step change, move focus to the step `h1`.
- **Radio/checkbox groups use `fieldset` + `legend`** so the group has an accessible name.
- **Conditional reveals** are wired with `aria-expanded` on the control and `aria-live` on the revealed region, so the change is announced.
- **Sensitive data stays in-session only** — re-use answers across Back/Continue from in-memory state, no client-side draft storage (this also satisfies Redundant entry, below).

## WCAG 2.2 specifics
The project targets WCAG 2.2 AA. Criteria new in 2.2 that automated tools largely miss:
- **Target size minimum (2.5.8):** interactive targets ≥ 24×24 px — check the nav pill links, the `menu-toggle` hamburger, and the mobile `icon-button` home/contact glyphs.
- **Consistent help (3.2.6):** persistent help/contact entry points sit in the same place on every screen — the primary nav and contact button are consistent across pages.
- **Focus not obscured (2.4.11):** the fixed/sticky header and the SERVICES drawer must not cover the focused element when tabbing.
- **Redundant entry (3.3.7):** multi-step forms re-use previously entered data across Back/Continue (in-session state, no client-side draft storage).
- **4.1.1 Parsing was removed in 2.2** — disregard legacy audit findings about parsing errors.

## Testing
- Keyboard test (Tab through, Enter / Space on interactive elements, Esc on the mobile menu and SERVICES drawer).
- Reduced-motion check (toggle the OS setting, reload — GSAP and parallax should be inert).
- Zoom and reflow check (200% zoom, narrow viewport).
- Target-size check (≥ 24×24 px on the nav pill, menu-toggle, icon-buttons).
- Basic screen-reader sanity check (VoiceOver on macOS, NVDA on Windows).
- Automated accessibility scan (pa11y or axe via the browser extension).
- **"Accept when:" habit** — for each change, write down the condition that proves it works, then verify it by hand: keyboard, screen reader, 200% zoom, reduced motion, target size.
- **Automated scans are a floor, not a certificate** — Lighthouse/axe detect roughly a third of WCAG failures. The manual checks above are what validate AA.
