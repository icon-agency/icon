# Definition of Done

A page or component is done when:

## Code quality
- HTML is semantic and clean.
- CSS is placed in the correct layer (`src/theme/`, `src/base/`, `src/utilities/`, `src/components/`, page-specific).
- Naming follows project conventions (BEM blocks, `u-` prefix for primitive utilities).
- New tokens land in `src/theme/*.css`; no hex / rgb / hsl appears outside `src/theme/colors.css`.
- New component files are imported in `src/main.css` in the correct order.
- JS is minimal and justified. Throttled with `requestAnimationFrame` when scroll-driven.

## Design quality
- Layout is polished across the project breakpoints — Tailwind defaults `sm:640` / `md:768` / `lg:1024` / `xl:1280` / `2xl:1536`, plus custom `3xl:1920` and `4xl:2560` (`src/theme/breakpoints.css`). `lg` (1024) is the mobile-menu / desktop cutoff.
- Spacing feels intentional and consistent — token-driven, no one-off pixel values.
- Typography reads cleanly, scales smoothly through the fluid clamp scale, and wraps via `text-wrap: balance` / `pretty`.
- No obvious visual bugs or awkward wraps.

## Accessibility
- Keyboard flow works.
- Focus states are visible (`:focus-visible { outline: 2px solid var(--color-icon-blue); outline-offset: 2px; }` in `src/base/reset.css`).
- Landmarks and headings are correct.
- `prefers-reduced-motion` is respected at every motion surface — the GSAP component timelines (hero ScrambleText headline, tagline cursor-pop trail / fit-to-width) and the CSS scroll-driven hero-video parallax — not just the global scroll-reveal host.
- Automated accessibility scan shows no serious issues.

For the full a11y checklist, see `docs/accessibility-checklist.md`.

## Drupal readiness
- Component can be mapped to a Single Directory Component (`components/<name>/<name>.twig` + `<name>.component.yml`) or a paragraph type.
- Field assumptions are documented (paragraph fields, expected props).
- Twig handoff is straightforward — BEM classes applied directly, no CVA, no inline conditionals in `class=""` attributes (compute classes in preprocess if conditional).
- Preprocess requirements are noted.

## Verification
Run `npm run build` before marking work done. The build is fast and minified; warnings or unresolved utilities indicate a missing token or missing `@source` path.

For full verification:
1. `npm run build` — produces `css/main.css` without warnings.
2. Open each affected template in a browser; check at narrow / tablet / desktop / wide widths.
3. Toggle `prefers-reduced-motion` and confirm animations stop.
4. Tab through the page; confirm focus ring is visible.
5. Run an accessibility scan (pa11y or axe).

## Review assets
- Mobile screenshot
- Tablet screenshot
- Desktop screenshot
- Notes for any known compromises or follow-ups for the Drupal port
