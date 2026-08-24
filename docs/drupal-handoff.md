# Drupal Handoff

The index for porting this prototype into a **vanilla Drupal 11+** custom theme with **Drupal Canvas** (Single Directory Components / SDC). This is a plain theme — not GovCMS, not Mercury, not a subtheme.

This page is the overview. The mechanics live in two siblings:
- `drupal-mapping-pattern.md` — the worked three-piece pattern (paragraph type → preprocess → thin Twig inside an SDC folder).
- `field-naming.md` — the BEM-prefixed field machine-name rule.

## Goal
Ensure all front-end work translates cleanly into a Drupal theme using SDC and paragraph-based content structures. Tailwind v4 is the build system; **BEM is the public class API**; **CVA / `html_cva()` is not used**.

## Template philosophy
- The BEM components under `src/components/` map 1:1 to SDC components in the theme. Same block name across the CSS file, the SDC folder, and the field prefix.
- Drupal outputs data into **thin Twig templates** inside SDC folders — variable output, loops, and presence `if`s only.
- No logic-heavy template assumptions. Conditional class lists are computed in **preprocess** and emitted as one `modifier_class` string, never built inline in `class="…"`.
- Inline `<script>` from the prototype becomes a Drupal library attached via `Drupal.behaviors` — the JS in `js/` is already authored as IIFEs that map cleanly onto behaviours.

## Preferred mapping pattern
For each UI pattern, document:
- Suggested paragraph type or content structure.
- Expected fields — BEM-prefixed machine names (`field_<block>_<element>`), editor-friendly labels.
- Optional fields.
- Variant flags — a single `list_string` field on the paragraph, not separate paragraph types.
- Any preprocess variables required, especially the computed `modifier_class`.

The full worked example (paragraph YAML → preprocess PHP → `.component.yml` → `.twig`) lives in **`drupal-mapping-pattern.md`** — don't re-derive it here.

## Example handoff note: tagline component

A short, real component to show the shape of a per-component note. Source: `src/components/tagline.css`, `js/tagline.js`.

Suggested Drupal structure:
- Paragraph: `paragraph: tagline` (page-section paragraph).
- Fields:
  - `field_tagline_heading` (text long — the fit-to-width statement)
  - `field_tagline_pops` (entity reference / media, multiple — the cursor-pop trail images)

Preprocess notes:
- Map the heading and the image list to clean scalar/array vars; null-check entity references with `?->`.
- Run `t()` on any static labels.
- If a variant is ever added, compute `modifier_class` here — there is no variant today, so the root class is just `tagline`.

Twig notes:
- Markup mirrors the `src/components/tagline.css` BEM structure: `.tagline` → `.tagline__heading` (with `.tagline__line` children) and the `.tagline__pops` / `.tagline__pop` trail container.
- Emit the root as `<section class="{{ modifier_class|default('tagline') }}">`. No inline conditionals, no `html_cva()`.
- The fit-to-width sizing and InertiaPlugin cursor-pop trail come from `js/tagline.js`, attached as the `icon/tagline` library via `Drupal.behaviors` — not from Twig.

## Field naming guidance
Machine names are BEM-prefixed; labels stay human. See **`field-naming.md`** for the full rule and the exceptions (core fields, media fields, entity references).

## Handoff note block
Every component note should include:
- Drupal structure suggestion (paragraph type or SDC).
- Field list with machine names + labels.
- Preprocess notes (including any `modifier_class` computation).
- Twig notes.
- Known implementation risks (e.g. animation that must move into a `Drupal.behaviors` library).

## What ports vs. what is rebuilt

| Layer | Action |
|---|---|
| `src/theme/*.css` (tokens) | Port as-is into the theme's `src/theme/` directory. |
| `src/base/*.css` | Port as-is. |
| `src/utilities/*.css` | Port as-is. |
| `src/components/*.css` | Port as-is. The BEM names match what the SDC Twig outputs. |
| `templates/*.html` | Rebuild as SDC `.twig` under `components/<name>/<name>.twig`, plus a `<name>.component.yml` schema. |
| `js/*.js` | Move into Drupal libraries (`icon/header`, `icon/hero`, `icon/tagline`); attach via `Drupal.behaviors`. |

The CSS doesn't change shape across the port — only the markup gets re-templated as Twig + SDC schemas.

## Related docs
- `drupal-mapping-pattern.md` — the three-piece SDC pattern with a worked example.
- `field-naming.md` — BEM-prefixed field machine names.
- `wysiwyg-output.md` — how CKEditor body fields are styled by the `.content-page` prose wrapper.
- `tailwind-conventions.md` — the utility-vs-BEM decision and why CVA is rejected for this theme.

## Prototype paths that must revert on port

The templates are browsable as a static site on GitHub Pages, where the project
is served from a subdirectory (`/icon/`), not a domain root. Two consequences,
both deliberate, both to be undone when the markup becomes Twig:

- **The four "home" controls point at the design-system index, not `/`.** The
  header wordmark (`.site-logo`), the nav pill's home glyph
  (`.site-nav__home`), the mobile home button (`.site-header__mobile-home
  .icon-button`) and the logo inside the open mobile menu
  (`.mobile-menu__logo`) all use `href="../index.html"`. On Pages an `href="/"`
  leaves the project entirely and lands on the org root. In Drupal all four are
  the site front page: `href="/"` (or `{{ path('<front>') }}`). Each template
  carries a comment saying so above the wordmark.

- **Content links keep their real Drupal paths and do NOT resolve statically.**
  `/work`, `/news`, `/news/article`, `/news?category=…`, `/contact` and the
  rest are the routes the built site will have, so they are written as such and
  simply 404 on the preview server. Do not "fix" these to relative paths — they
  are correct, and the filter chips in particular rely on the query string
  being honoured server-side by a Views exposed filter.
