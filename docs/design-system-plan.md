# ICON Design System — index.html plan

Plan for building the ICON design-system index page, modelled on GreenPowerDS
(`GreenPowerDS/index.html` — reference only, not in this repo) and adapted to
this build's stack and target. **Scope: `templates/homeC.html` is the canonical
template.** `home.html`, `homeB.html`, `work.html`, `work-article.html` are
prototypes and stay OUT of the system (linked only as reference).

---

## Status — August 2026

The plan below is kept as the working brief; this block records where it stands.

- **Phase 1 (shell + foundations): shipped.** Root `index.html` replaced the
  redirect. Sidebar (Template / Experiments / Prototypes / Pattern Library),
  mobile drawer, scrollspy (geometry recomputed on the pane's scroll,
  rAF-coalesced — one step past the GreenPowerDS IO lesson, see the inline
  comment), `src/utilities/design-system.css`, and all six Foundations
  sections. Light default with the dev theme toggle, per §4 — plus a third
  `.theme-blue` theme the plan didn't anticipate.
- **Phase 2 (component sections): COMPLETE (26 Aug 2026).** Ten component
  sections live, each with Canvas/SDC notes: Hero and News article (link-out,
  per §3.6), Intro, Site header, Work grid, Clients marquee, News cards &
  stack, Invitations & links, Reveal primitives, Global footer. The colour
  foundations were reconciled with the one-black/one-blue token consolidation
  the swatches themselves surfaced, and every hand-typed spacing / radius /
  easing / duration label converted to live measurement (the §3.5 principle,
  finished — a hand-typed swatch label is exactly how the icon-black hex
  drifted).
- **Phase 3 (configurators): not started.**
- **Phase 4 (gate): partial.** This docs pass is the `AGENTS.md`/`docs/`
  update; `scripts/verify.mjs` is **not built** — `npm run verify` still
  aliases the build.
- **§5 naming decision: RESOLVED — promoted.** The `homec-*` namespace became
  the site-canonical API (`.hero`, `.intro`, `.work`, `.clients`, `.news`,
  `.arrow-link`, `.media-reveal`, `.page-home`); the home-A prototype's hero
  was renamed `.video-hero` to free the name. Field prefixes and SDC folders
  inherit these names.
- **§2 marquee pause: still open.** The clients marquee runs without a pause
  control; the exception is recorded in `accessibility-checklist.md` until the
  DS clients section lands and makes the decision prose.
- **Blueprint drift:** the sidebar gained an **Experiments** group (linking
  `experiments/*` one-offs); `templates/news.html`, the news article and the
  Work landing (`templates/work-landing.html`, Aug 2026) joined the Template
  group alongside Home C. The **Prototypes group was removed**
  (26 Aug 2026, user call) — home A/B and the work pages are legacy, stay in
  the repo, and are no longer surfaced by the DS.

---

### Addendum — 3 September 2026 (docs review)

- **Dev theme toggle removed.** `js/theme-toggle.js` is gone; inspect `.dark`
  / `.theme-blue` by adding the class to `<html>` in devtools. §4's "include
  the dev theme toggle" no longer applies.
- **§2 marquee pause: RECORDED.** The DS clients section carries the WCAG
  2.2.2 exception as prose ("Accessibility — the recorded WCAG 2.2.2
  exception"); `accessibility-checklist.md` points at it.
- **The work article joined the system.** `templates/work-article-master.html`
  plus four client folios are in the Template group; the `work-article.html`
  prototype is gone. Its page layer holds four blocks with five consumers
  (`.work-gallery`, `.work-scroller`, `.work-video`, `.work-stats`) —
  **promoted to `src/components/` the same day**, declarations byte-for-byte
  (one specificity bump on the portrait modifier, documented in the file);
  the layered-media anatomy and the lean composition stay in the page layer.
- **Dead weight removed:** `image-grid.css`, `more-work.css`, the unused
  effects helpers, `--heading-2/3`, `--card-title/subtitle`, the fixed
  `--text-7xl…9xl` overrides and the overlay / video-filter knobs. `u-grid`
  and `u-container--wide` stay as documented primitives although nothing
  consumes them yet.
- **Phase 4 (gate): BUILT.** `scripts/verify.mjs` behind `npm run verify` —
  build sync, raw colours, media queries against the table (escape hatch:
  a `verify-allow` comment with the reason), literal inline styles. The
  review found both mechanical rules had drifted; the nine ad-hoc scrim and
  shadow alphas became five tokens (`--scrim-*`, `--shadow-*`) so the gate
  passes clean from day one.
- **Contrast:** the chameleon grey runs at 90% ink and the Nike pair was
  re-pitched to `#cc0000`, so every client pair clears AA for body copy.

## 1. Does "Drupal Canvas AI, not GovCMS" matter? Yes — three ways

1. **Document SDC props, not just paragraph fields.** GreenPowerDS annotated
   every demo with a paragraph type + `field_*` list for a GovCMS/Twig
   preprocess port. Canvas assembles pages from **Single Directory Components**
   with a machine-readable `*.component.yml` props/slots schema — and Canvas AI
   reads those schemas. So each DS section's "Drupal" note should state:
   SDC folder name (same root as the BEM block, per `docs/field-naming.md`),
   props (name, type, enum of variants), slots, and which props are editor
   content vs preprocess-computed. The DS index effectively becomes the
   human-readable twin of the future `component.yml` files.
2. **Variants become prop enums.** Our existing convention (variants = one
   `list_string` field, never separate paragraph types) maps 1:1 onto an SDC
   enum prop (e.g. `homec-work__row` variant: `split | split-tall-left | wide`).
   The DS page should name every variant explicitly so the enum can be lifted
   verbatim.
3. **GovCMS platform constraints don't apply.** No CDN blocking (Typekit +
   GSAP + Lenis CDNs are acceptable in the prototype; self-hosting becomes a
   performance choice at port time, not a compliance requirement), no GovCMS
   embed-proxy caveats, no Mercury sub-theme rules. Drop those GreenPowerDS
   annotations; keep the "known port risks" habit.

## 2. Accessibility stance — "AA considered, not strict"

Keep (already built, or cheap, or Level A — below AA, so "relaxed AA" still
includes them):

- Reduced-motion guards on every animation (already universal in this build).
- Keyboard + visible focus (focus ring is defined once in `reset.css`).
- Skip link, landmarks, heading order, `sr-only` real-text for the hero lockup
  (already done).
- **Flag: the clients marquee currently has no pause control.** Auto-motion
  longer than 5s is WCAG 2.2.2 **Level A** (we removed hover-pause by request).
  Recommendation: a small pause affordance like GreenPowerDS's marquee button,
  or at minimum note it as a conscious exception in the DS section. Decision
  recorded in the DS prose either way.

Relax (document as non-goals rather than silently skipping):

- AAA measures (line-measure 1.4.8 etc.) — note-only.
- Formal conformance statements, per-component WCAG citations on everything —
  cite only where a real decision was made.
- Strict contrast on large display type over media (hero over video) — keep the
  scrim, don't chase ratios on decorative display text.
- Automated a11y scan stays a manual, occasional check (as GreenPowerDS), not CI.

## 3. What to copy from GreenPowerDS — and what not

**Copy (the patterns that make it work):**

1. Root `index.html` **is** the design system — hand-edited, no generator.
2. Demos wear **production CSS** (`css/main.css`) — no parallel docs styles to
   drift. The DS shell gets its own page-layer stylesheet only for the
   sidebar/app chrome (`src/utilities/design-system.css` here).
3. App shell: fixed sidebar + independently scrolling content pane
   (`overflow: clip` + `position: relative` guards — their LESSONS and ours
   agree). Sidebar: groups of **external page links** (with external icon) on
   top, then a native `<details open>` **Pattern Library** of in-page anchors.
   Category bands in the content mirror the sidebar groups; IntersectionObserver
   scrollspy highlights position.
4. Per-section pattern: title → short rationale prose (with `code` refs to
   token/CSS files) → groups → **live rendered demos** with a label naming the
   SDC/machine name + BEM classes. No code snippets; the live markup is the
   reference.
5. Foundations first: colour swatches (token name + value + role), **live
   JS-measured type specimens** (probe element reads computed sizes so the page
   can't drift from the real scale), spacing/radius/motion tables.
6. **Don't inline-demo the hero.** Scroll-driven choreography misrepresents at
   reduced scale — use a thumbnail card linking to `templates/homeC.html`, with
   a "why it is not demoed here" note. (Applies equally to the pinned news
   scroller — link out or demo the *card* alone, not the pin.)
7. Interactive configurators that add/remove **real DOM** (so `:has()`
   quantity-queries behave exactly as Drupal-rendered DOM) — for us: work-row
   variants, news-card meta on/off, clients row count.
8. Decision state as prose: "Settled: …", "Still open: …" — and curation by
   commenting sidebar entries in/out.
9. Sidebar footer utility links (GitHub repo; JIRA/backlog page if one exists).
10. `verify` as a real gate (see §6).

**Don't copy:**

- Anything Sass-specific (`!default` override layer, `@import` graph, scss/
  taxonomy). Our equivalent already exists: Tailwind v4 `@theme` token files +
  the 5-layer `src/main.css` import order.
- GovCMS/Mercury caveats and webform/`#states` mapping notes.
- Their atoms/organisms taxonomy — ICON's existing split (components /
  utilities / page layers, BEM-per-file) stays.
- The 16× duplicated inline scripts across templates (they logged it as a
  lesson; we already keep JS in one file per behaviour).

## 4. The ICON DS index — blueprint

**File:** root `index.html` (replacing the current meta-refresh redirect; Home C
stays one click away, first link in the sidebar — same as GreenPowerDS).
`templates-index.html` is superseded and can be deleted or kept as a stub.

**Shell styling:** new `src/utilities/design-system.css` (page layer, imported
last with the other page layers). Light theme default to match Home C; include
the dev theme toggle so both palettes are inspectable (labelled dev-only).

**Sidebar IA:**

- **Template** (external): Home C (`templates/homeC.html`) — the canonical page.
- **Prototypes (reference, not in system)** (external, visually de-emphasised):
  home, homeB, work, work-article.
- **Pattern Library** (`<details open>`, in-page anchors):
  - *Foundations:* Colours (light + dark) · Typography (Kumbh/Miller, fluid
    `--text-*` scale, Home-C wide-screen growth + logo-matched lockup sizing) ·
    Spacing & layout (12-col `u-grid`, fluid `--container-pad`, the uncapped
    Home-C container vs the 90rem site default — a settled decision worth its
    own note) · Radius · Motion (easings, durations, scroll-driven-animation
    policy: compositor timelines, never scroll handlers) · Breakpoints.
  - *Header:* site header (pill nav, scroll states, over-hero inversion,
    mobile menu) — demo static states inline, link out for scroll behaviour.
  - *Hero:* thumbnail link-out + prose (lockup construction, word cascade
    in/out, PiP video) — not inline-demoed.
  - *Components:* Intro (lede + expertise list + statement, baseline
    alignment) · Work item (media reveal from bottom-left, parallax, video
    support, title/meta) · Work row variants (split, split--tall-left, wide)
    with a configurator · Clients marquee (3 rows, direction, italic
    alternation + the even-count rule, pause decision) · News card (square
    media, split-axis parallax, category+date meta) · News pinned scroller
    (link-out demo) · More-link.
  - *Primitives:* reveal-fx (`data-animate`, `.homec-reveal`, SplitText
    line-mask) · `u-container`/`u-grid` · effects utilities (`.miller-text`,
    frosted glass, grain).
- **Footer links:** GitHub repo (icon-agency/icon).

**Per-section metadata (every component):** BEM block · SDC folder name ·
props/slots sketch (Canvas-ready) · variants enum · JS behaviour file
(→ `Drupal.behaviors` name) · a11y notes where a decision exists · "Settled /
Still open" state.

## 5. Naming/terminology decision (open)

Home C blocks are currently `homec-*` — a prototype namespace. Before the DS
formalises them, decide: keep `homec-*` (honest about origin) or promote to
site-canonical names (`.hero`, `.intro`, `.work`, `.clients`, `.news`) since
Home C **is** the site now. Recommendation: promote at DS-build time — the DS
index is exactly the moment names become API. One rename pass, verify, then
freeze. (Field prefixes and SDC folders inherit whatever we choose.)

## 6. Verify script (adapted, lighter than GreenPowerDS)

Extend the existing `npm run verify` (currently an alias of build) with a small
`scripts/verify.mjs`:

- Build sync: rebuild Tailwind, byte-compare `css/main.css` (Pages serves the
  committed file — same reasoning as GreenPowerDS).
- No raw colours outside `src/theme/colors.css` (already an AGENTS rule; make
  it mechanical).
- No inline `style=` in templates/index (allow `--animate-delay`, `--w` and
  other JS-set custom props).
- Structural tag balance across `index.html` + `templates/*.html`.
- WARN-only: `<img>` without dimensions/lazy, `href="#"`.

## 7. Build phases

1. **Shell** — `index.html` app shell + sidebar + scrollspy +
   `design-system.css`; Foundations sections (colours, type with live
   measurement, spacing, motion). Replace root redirect.
2. **Component sections** — intro, work item/rows, clients, news card,
   more-link, primitives; link-out cards for hero + pinned scroller; per-section
   SDC/props notes.
3. **Configurators** — work-row variant switcher, news-card meta toggle,
   clients row/pause demo; polish (mobile drawer, reduced-motion pass).
4. **Gate** — `verify.mjs`, update `AGENTS.md`/`docs/` (DS maintenance rules:
   hand-edited, curation via commented nav entries, definition-of-done for new
   sections), commit + Pages.

Phase 1+2 are the meaningful bulk; 3 and 4 are polish and governance.
