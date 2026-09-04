# LESSONS.md

Running log of corrections for the **ICON Agency** build. Each entry prevents a
future mistake. Read before starting work. Append (don't edit) when something
goes wrong.

> Fresh for this project. The previous contents were lessons from a different
> (GovCMS) build and no longer apply here.

---

- **Scroll parallax must run on the compositor, not the main thread.** Driving a
  parallax transform from a `scroll` event handler — even rAF-throttled — sets
  the element's transform a frame behind the page's real scroll position, so it
  visibly stutters / "swims", worst on trackpad + momentum scrolling. Fix: a CSS
  scroll-driven animation, which the browser samples on the compositor in
  lockstep with scroll:
  ```css
  @keyframes hero-video-parallax { to { transform: translate3d(0, 50vh, 0); } }
  @media (prefers-reduced-motion: no-preference) {
    @supports (animation-timeline: scroll()) {
      .hero__video {
        animation: hero-video-parallax linear both;
        animation-timeline: scroll(root);
        animation-range: 0 100vh;       /* over the hero's height */
      }
    }
  }
  ```
  This reproduces `translateY = scrollY * 0.5` exactly (verified: at scrollY 400,
  computed translateY = 200px) but is smooth. Keep a JS fallback only for
  browsers without scroll-driven animations (Firefox, today), and there run a
  continuous rAF **lerp** loop (`current += (target - current) * 0.18`) rather
  than reacting to scroll events — smoother than the naive handler. Gate it with
  `CSS.supports('animation-timeline: scroll()')` so the two never both run.
  (`hero.css` / `js/hero.js`.)

- **Tailwind v4's `--minify` (Lightning CSS) drops the FIRST of a
  vendor-prefixed/unprefixed pair** when both appear in one declaration block.
  Declare the `-webkit-` form FIRST so the standard property survives:
  ```css
  -webkit-backdrop-filter: blur(24px);
  backdrop-filter: blur(24px);
  ```
  Load-bearing for every frosted surface here (`.overlay-frosted-glass`,
  `.site-nav__pill`, `.icon-button`, `.menu-toggle`, `.site-nav__submenu`). For
  multi-function `filter` / `backdrop-filter` / `transform` values the minifier
  also strips the space separator (`blur(14px)saturate(140%)` → invalid syntax,
  declaration dropped); wrap those in a custom property, which it leaves intact.
  (Inherited gotcha — same Tailwind v4 / Lightning CSS engine.)

- **A JS-computed layout offset goes stale when the layout shifts.** The desktop
  nav aligns the SERVICES drawer under its trigger via a JS-set `--submenu-offset`
  (`trigger.left − drawer.left`). Computing it only on open + `resize` left it
  stale when the scroll-revealed home glyph expands and pushes SERVICES right —
  the items only re-aligned on the next hover. Fix: recompute on every geometry
  change with a **ResizeObserver on the row** (catches the glyph transition AND
  web-font reflow), plus a scroll re-sync *while open* for smooth tracking, all
  **rAF-coalesced** to one read+write per frame. Observe the element you REACT to
  (the row), never the one you MUTATE (the drawer) — writing the offset resizes
  the drawer, so observing that would loop. And guard the width: pin the drawer
  (`width: 0; min-width: 100%`) so a long label can't widen the pill and feed
  back into the offset. (`js/header.js` / `src/components/site-header.css`.)

- **A per-route theme override must rebind on `<html>`, not `<body>` — Tailwind
  v4 registers the `--color-*` aliases and resolves them at `:root`.** The
  `/work/article` case study flips to a light palette via a `.theme-work-article`
  scope that re-binds the brand vars (`--icon-black`, `--background`, …) to the
  `--work-article-*` values. Placed on `<body>` it re-themed the prose (which
  reads the RAW `var(--icon-black)`) but NOT anything reading the Tailwind alias
  `var(--color-icon-black)` or a generated utility — those stayed dark. Reason:
  `@theme inline` registers `--color-icon-black: var(--icon-black)`; a registered
  custom property computes its value at the element where it's declared (`:root`
  = `<html>`, where `.dark` set `--icon-black`), then inherits that *resolved*
  color down — a `<body>`-level rebind is too late. Fix: put the scope on the
  same `<html>` element as `.dark` (`class="dark theme-work-article"`); it wins
  by source order (imported last) and both the raw vars and the `--color-*`
  aliases/utilities re-theme. Corollary: hand-written component CSS that must
  follow such a scope can also just read the raw `var(--icon-black)` directly
  (as `prose.css` does). (`src/utilities/work-article.css` /
  `templates/work-article.html`.)

- **`overflow: hidden` silently freezes CSS scroll-driven parallax by promoting an
  ancestor to a scroll container.** The card parallax (`.project-card__image`,
  `.image-grid__media`, `.case-study__featured-media`) used
  `animation-timeline: view()`. `view()` resolves its scrollport to the subject's
  NEAREST ancestor scroll container — and each image sits inside a frame with
  `overflow: hidden` (which IS a scroll container), so `view()` measured the image
  against its own non-scrolling frame and pinned the animation at 50% (zero
  movement, on every template). Compounding it: `body { overflow-x: hidden }` (the
  reset.css horizontal-overflow guard) forces `overflow-y: auto`, making `<body>`
  a scroll container too — so even a named `view-timeline` on an outer wrapper
  anchored to `<body>`, which never scrolls (the document root does), and stayed
  frozen. Fix needs BOTH parts: (1) `body { overflow-x: clip }` — `clip` clips the
  same overflow WITHOUT becoming a scroll container (it doesn't force the other
  axis to `auto`); (2) declare `view-timeline-name: --card-parallax` on the outer
  `.reveal` (whose nearest scroll container is now the document root) and point
  each media at it with `animation-timeline: --card-parallax` instead of bare
  `view()`. Verified: images now translate ±~5–10% as the card scrolls through the
  viewport. The hero video was never affected because it uses `scroll(root)`
  explicitly. Measure scroll-driven transforms after a double `requestAnimationFrame`
  — `getComputedStyle` read immediately after `scrollTo` lags one frame behind the
  compositor and looks frozen even when it isn't. (`src/base/reset.css`,
  `src/components/reveal.css`, `project-card.css`, `image-grid.css`,
  `src/utilities/work-article.css`.)

- **A `clip-path` that hides an element deadlocks its own IntersectionObserver
  reveal.** Home C's image reveal started each frame at `clip-path: inset(100%)`
  (zero visible area) and observed THAT frame, revealing it once it crossed an 18%
  intersection threshold. It never revealed: the clip counts against the
  intersection geometry, so the observed element's `intersectionRatio` is pinned
  at 0 (verified: clipped frame ratio 0 while an identical unclipped one reported
  1) and never reaches the threshold — and it can't un-clip until it does. A
  near-identical `[data-animate]` observer with no clip on its targets (reveal.js)
  fired fine, which is what isolated the cause. Fix: never clip the OBSERVED
  element to nothing — observe an unclipped wrapper and put the
  `clip-path`/`transform` reveal on a CHILD (here the `<img>`/`<video>` inside
  `.homec-reveal`), so the frame keeps its full box for the observer.
  (`src/utilities/home-c.css`, `js/home-c.js`.)

- **Lightning CSS drops one of `translate` / `transform` when a rule sets
  both.** The footer logo's entrance was authored as `translate: 0 110%` for the
  rise plus `transform: perspective(1400px) rotateX(-52deg)` for the swivel —
  separate properties precisely so they compose rather than overwrite. The
  source was correct and the un-minified behaviour was correct; the BUILT
  `css/main.css` carried only the rotate. The rise vanished, silently, with no
  warning and no build error, so the logo unfolded on the spot instead of
  swivelling up. Rules that set `translate` alone are untouched (the footer's
  block fade-ups still work) — it is specifically the pairing the optimiser
  tries to merge. Fix: when an element needs both, write ONE `transform` with
  the functions in order (`perspective() translateY() rotateX()`), and reserve
  the separate properties for cases where two different agents own them (JS
  writing `rotate` while CSS animates `transform`, as the news cards do).
  Verify by grepping the BUILT file, not the source — `grep -o
  '\.selector{[^}]*}' css/main.css` — because this class of bug only exists
  after minification. (Same engine as the `--minify` shorthand hazard above.)
  (`src/components/site-footer.css`.)

- **A `readyState` gate on a paused video deadlocks on iOS — Safari ignores
  `preload="auto"` and parks paused video at `HAVE_METADATA`, so a gate
  waiting for `readyState >= 3` never settles on a phone.** The hero boot
  gated each card (and the reel's hand-off slide) this way: on mobile the
  two video gates sat until the 5.5s `MAX_WAIT` cap on every load — the pile
  stalled, and the (real) load counter froze at (40) because only the two
  image gates could ever settle, which read as "the loading is fake". Worse,
  the reduced-motion path had the same gate with NO cap: a permanent blank
  hero on reduced-motion iOS. Fix: **prime the video** — a `muted playsinline`
  video may be `play()`ed without a gesture, and playback is the one thing
  that reliably makes Safari buffer; pause it the moment its gate settles
  (invisible pre-pop, so nothing shows). Low Power Mode rejects the `play()`
  — caught, and the cap still fails open. And when a fail-open cap fires,
  **resolve the counter with it** (the loading phase is over by policy) —
  a real counter that can freeze below 100 is worse than an honest cap.
  (`js/home-c.js` hero boot; found on a phone, Aug 2026.)

- **`var()` is not valid inside a media query, and three docs recommended it
  for a month.** `frontend-rules.md`, `tailwind-conventions.md` and the
  comment in `src/theme/breakpoints.css` all told component authors to write
  `@media (min-width: var(--breakpoint-3xl))`. A media query's prelude is
  evaluated before the cascade exists, so a custom property there is a parse
  error and the whole rule silently never matches — which is why no file ever
  used the form and the docs never got caught. What works: the table's pixel
  value (`@media (min-width: 1920px)`), or Tailwind v4's `@variant 3xl { … }`
  nested in the rule, which resolves the step from the token. Two habits
  follow. Docs that prescribe a technique should point at a file that USES it
  (none did here), and rules with a mechanical shape belong in a gate —
  `scripts/verify.mjs` now checks every media width against the table.
  (Found in the Sep 2026 docs review.)

- **A shared behaviour file must guard each concern on its own markup — never
  `return` early on the first one's absence.** `js/reveal.js` grew from a
  `[data-animate]` fade-up into the home of three concerns (fade-ups, media
  reveals, the hairline draw), but kept its original prologue:
  `if (!nodes.length) return;`. Every static template carries a `data-animate`
  somewhere, so the early exit never fired and the later sections always ran.
  The first page that didn't — the bare Drupal front page, footer only — drew
  no hairlines at all, because the rule observer three sections down was never
  reached. Nothing in the static build could have caught it; the port did,
  which is one more argument for the theme rendering the REAL chrome early.
  Fix: each section is wrapped in its own `if (els.length)` block and none of
  them returns from the IIFE. Corollary for the generated Drupal behaviours:
  the wrapper runs the source once per page whatever the page holds, so a
  source file must be safe on ANY page, not just the templates it was written
  for. (`js/reveal.js`; found 4 Sep 2026 on the Drupal front page.)

- **A YAML `examples:` line with a bare colon is a mapping, not a string.**
  `- How ICON transformed with AI: at the nexus…` parsed as
  `{ "How ICON transformed with AI": "at the nexus…" }`, and Canvas rejected
  the whole `pull-quote` SDC ("Prop text has invalid example value: []
  Array value found"). The error names the prop, not the colon, so it reads
  like a schema bug. Quote every example that contains `: ` — titles, quotes
  and ledes routinely do. (`components/pull-quote/pull-quote.component.yml`,
  4 Sep 2026.)

- **A custom `#` key on a render-cached entity build is invisible to the
  cache.** The news teaser is rendered as h2 on /news and as h3 in the
  article's "Next up" rail; the rail set `#news_heading_level` on the row's
  entity build and the preprocess read it — and still rendered h2, because
  `EntityViewBuilder::view()` cache-keys the teaser by entity + view mode
  only, so the rail got the listing's cached copy. Anything that changes
  the output of a render-cached entity build must ALSO join
  `#cache['keys']` (here `heading-h3`). Symptom to recognise: a variant that
  works on first render in isolation and "randomly" reverts on pages that
  also render the plain one. (`icon.theme`,
  `icon_preprocess_views_view_unformatted()`, 4 Sep 2026.)

- **Tailwind v4 scans the whole repo by default — `@source` lines ADD to that,
  they don't replace it.** `src/main.css` names `../templates` and
  `../index.html`, and everyone read that as the complete source list. It
  never was: automatic source detection also walks every non-ignored file
  under the working directory, so the static `css/main.css` has always
  carried utilities harvested from prose in `docs/*.md` (`md:grid-cols-2`,
  `rounded-icon`, `text-destructive`, `bg-icon-blue/40` …) — harmless bloat,
  and invisible while the repo held only the design system. The moment the
  Drupal theme arrived (`drupal/`), its Twig and its own CSS layer joined
  the static build's scan too: `npm run verify` began failing on
  "build-sync" after theme-only edits, and a `var(--color-destructive)` in
  the theme's login stylesheet surfaced as a new theme variable in the
  STATIC file. Fix: `@source not "../drupal";` in the root entry — the
  static build is byte-identical to its pre-theme baseline again, while the
  theme build (which imports the root file and adds its own positive
  `@source` lines) still sees everything it needs. The alternative,
  `@import "tailwindcss" source(none)`, would also drop the doc-harvested
  utilities and change the shipped static CSS — a design-system call, not a
  port one, so it was left alone. Rule: when a directory beside the design
  system gains markup or CSS of its own, fence it off explicitly.
  (`src/main.css`, 4 Sep 2026.)

- **Canvas 1.10 composes only its own Pages; nodes go through Content
  Templates, and per-node slots have no editor yet.** The News slice put
  the article body in Canvas's `component_tree` field and I reported it
  editable at `/canvas/editor/node/N` because that URL returned 200 — but
  only the React shell had loaded; its first API call threw "For now Canvas
  only works if the entity is a canvas_page" (`ComponentTreeLoader::
  getCanvasFieldName()`, issue 3498525, open). A "Canvas" tab I then added
  made it worse: evaluating the editor route's access for VISITORS hit the
  same exception and every story became a 401. Two rules. A 200 on a
  single-page app's shell proves nothing — verify the API it calls, or
  click through in the UI. And before choosing a storage model on a
  contrib module's promise, find the guard that decides which entity types
  it serves. Resolution: the body moved to Paragraphs, each type rendered
  by the same SDC (`docs/drupal-handoff.md`, "Why not Canvas for the body").
  (4 Sep 2026.)

## A `header: true` library runs before the body exists

- **Symptom:** On the Drupal homepage the hero never booted — a static blue
  cover, the card pile popping over dark-ink header and counter, no
  `.is-covered`, the counter jumping (0)→(100). The static template was fine.
- **Cause:** `hero-loader.js` was attached with `header: true` because its
  header says "load it early, before the CDN scripts". In Drupal that puts it
  in `<head>`, where `document.querySelector("[data-text-box]")` runs before
  the body is parsed, finds nothing, and the IIFE returns silently. Nothing
  logs; the CSS shows the box in its un-lit state, which happens to be a
  full-viewport blue rectangle, so it even looked half-intended.
- **Fix:** Footer scope with `weight: -20` on the file (Drupal only allows
  negative weights in a library), which puts it after the markup and ahead
  of the external scripts — the template's order. The loader also grew a
  guard: no box while the document is still parsing → retry on
  DOMContentLoaded, so a head placement degrades to a late boot instead of
  none.
- **Rule:** "early" for a DOM-querying script means *right after its markup*,
  not `<head>`. Anything in a `header: true` library must be DOM-free or
  defer itself. Traced with a class-timeline poll (hero / header / box
  classes every 100ms) — the first line showed the box never got `.is-lit`
  and `window.__heroLoaderT0` was undefined.

## A slot child renders once; a template that writes a piece twice needs the second copy built

- **Context:** The hero writes every piece twice — a pile card in one container
  and a reel slide in another. As a Drupal block with an array setting that was
  a loop in Twig; the editor UX was a settings form with four fixed slots. The
  ask (add, drag to reorder, delete, media library or upload) is exactly what
  Canvas does for slot children, but a child component renders in ONE place.
- **Fix:** The hero SDC took a `slides` slot rendered into the stage; the Hero
  slide component is the card with the client name and link as data
  attributes; and `js/home-c.js` builds the reel from the pile when the reel
  is empty. The static template keeps both lists and never takes the branch.
- **Rule:** When a design-system piece appears in two DOM places, keep the
  CMS's authored copy to one and derive the other in the behaviour, guarded so
  the static markup is untouched. Don't reach for a block form with AJAX
  add-more/tabledrag inside Canvas's panel — the slot IS the repeater.

## A repeater in Canvas is a slot; a per-row shape is a slot per row

- **Context:** Featured work is five tiles in a fixed rhythm — a split pair,
  a wide feature, a tall-left pair. As a block it was five autocomplete
  pickers; the ask was drag-and-drop reordering and a searchable list.
- **Fix:** One SDC with THREE slots, one per row; a tile component with a
  single link prop (Canvas's title autocomplete → `entity:node/N`), rendered
  through a Twig function that resolves the link to the Work item's teaser.
  The wide tile is wide by its row's modifier, added to the design system
  next to the tile's own modifier, because a child cannot know its row.
- **Rule:** When the layout has a fixed rhythm, model each row as a slot and
  let CSS on the row shape the child; keep the child dumb. And in Canvas
  1.10, `content-entity-reference` props are for code components only — a
  link prop with the title autocomplete is the SDC's way to reference content.

## Not everything belongs in the Canvas panel

- **Context:** The client marquee is a Views block; its Canvas settings
  (items per block, override title) were useless to the editor, who wanted a
  list with drag-and-drop order, add / edit / delete, and the alt text as the
  label.
- **Fix:** A plain Drupal admin form — a tabledrag list of Logo media at
  /admin/content/client-logos with inline alt text, Edit / Delete operations
  and an "Add logo" local action — plus a settings-free block whose panel is
  a link to that list.
- **Rule:** Content that is a list of entities gets a Drupal list UI; the
  Canvas panel should say where the list is, not pretend to be it.

## A Canvas block with no settings has no model, and its panel throws

- **Symptom:** Selecting the settings-free marquee block in Canvas 1.10 gave
  "An unexpected error has occurred while rendering the component's form —
  Cannot read properties of undefined (reading 'source')".
- **Cause:** The layout API only builds a client-side model for components
  whose source `requiresExplicitInput()`; for blocks that is "has default
  configuration". A block whose only inputs are label / label_display gets no
  model entry, and the panel reads `model[uuid].source` unguarded. (A
  prop-less SDC selects without error — it just shows nothing.)
- **Fix:** Give the block one real setting — here the section's accessible
  label — so it has a model. Traced by walking the layout API's `layout`
  against its `model` and listing the instances with no entry.

## The editor wanted the list in the panel — a block form can carry tabledrag in Canvas

- **Context:** The hero slides as slot children put "Hero slide" ×4 in the
  layers panel with no names (Canvas 1.10 ignores instance labels) and made
  the hero draggable on the stage, which the editor read as wrong: the hero
  is the homepage's locked masthead; what they wanted was a list on the
  right — client names, drag to reorder, add, edit.
- **Fix:** Slides are content (a Hero slide node type: media library widget
  for film or image, client name, link) and the hero is a block again whose
  ONE setting is the order. Its `blockForm()` is a `#tabledrag` table plus
  links; Canvas hyperscriptifies the form and attaches Drupal behaviours, so
  the handles work inside the panel and every change auto-saves. Edit / Add
  open the node forms in a new tab.
- **Rule:** Content that the editor thinks of as a LIST is a list — entities
  with their own forms, an ordered list to manage them, and the Canvas panel
  showing that list. Slots are for composing a page, not for repeating rows.
  Canvas 1.10 has no per-instance lock: a block in the tree can always be
  dragged; a truly locked piece has to live outside the tree (a theme region
  block with a visibility condition) and gives up the panel.

## A content-managed dropdown for an SDC prop is a prop-shape alter

- **Context:** The fact card's icon was a four-value enum baked into the
  component; the editor asked how to add, edit and delete icons.
- **Fix:** The prop became `type: integer` with an `x-icon-media` marker, and
  `hook_canvas_storable_prop_shape_alter()` stores it as an entity reference
  to an Icon media type (File source, svg, ordered) with the `options_select`
  widget. The dropdown is now the Fact icons list page; the value the
  component gets is the media ID, and a Twig function inlines its file so
  `currentColor` still works.
- **Trap:** Canvas's widgets display the prop's RESOLVED value, not the stored
  one. With the prop resolving to the file URI, the select (and the entity
  autocomplete) showed nothing on load even though the value round-tripped —
  traced by reading the form endpoint's response: every option
  `selected:false`. Resolve to the ID the widget shows, and derive the rest in
  Twig.
- **Rule:** Core has no `hook_component_info_alter` (it is commented out in
  `ComponentPluginManager`), so an SDC enum cannot be made dynamic — change
  the prop's SHAPE and let the storable-prop-shape alter decide how it is
  stored and edited. SVG can't ride the Image shape (image fields refuse
  SVG), so a File-source media type is the vehicle.

## Canvas submits a block form with #tree semantics — read nested values

- **Symptom:** After the hero and featured lists were wrapped in a styled
  container, every pick vanished the moment the panel opened: the selects
  read "- Choose a project -" and the front page fell back to the latest
  work.
- **Cause:** Canvas posts the whole block form under
  `canvas_component_props[uuid][…]`, so a table inside a container arrives as
  `getValue(['panel', 'card', 'projects'])`, not `getValue('projects')`.
  `blockSubmit()` read the flat key, got nothing, and saved an empty list —
  and the panel auto-submits on open, so the wipe was immediate.
- **Rule:** In a Canvas block form, read values by their full parents path
  (with the flat key as a fallback for the ordinary block UI), and keep an
  eye on the stored inputs after opening the panel — the panel's first
  auto-save is the test.

## Reordering inside the Canvas panel: not tabledrag, not weight selects

- **Symptom:** The hero and featured lists showed drag handles but nothing
  moved; when a small custom sorter did move rows and wrote the hidden
  weight selects, Canvas never marked the page changed.
- **Cause, twice:** Canvas renders block forms through React and re-renders
  them on every change, so core tabledrag's instance was bound to a table no
  longer in the document. And Canvas's form store ignores changes to `#type
  weight` selects entirely — even calling the React onChange directly left
  the model untouched — while a select or text field it treats as a value.
- **Fix:** `js/panel-sortable.js`, a delegated pointer sorter on the document
  (survives re-renders). The row stays put and dims while dragging and a
  drop line marks the gap it will land in — moving rows under the pointer
  made the list jump and rows change height. On drop it writes ONE hidden
  text field (`order`, comma-separated row keys) through the input's
  prototype setter plus input / change / blur; `blockSubmit()` parses it.
  The handle is Canvas's own component glyph, so the panel matches the
  layers tree beside it. Verified: a real
  mouse drag re-posts the form and the auto-save carries the new order.
- **Rule:** In a Canvas block form, carry state in plain text fields and
  selects; drive any richer UI with delegated JS; and test with a real drag,
  reading the auto-save entity afterwards — the panel's "Changed" badge is
  the only honest signal.

## A Drupal dialog over the Canvas editor needs the admin theme

- **Symptom:** Edit links opened the slide form in a dialog, but Save did
  nothing and the form arrived as `<drupal-canvas-form>` custom elements.
- **Cause:** Ajax requests from the editor page carry
  `ajaxPageState.theme=canvas_stark`, so core's AjaxBasePageNegotiator
  rendered the node form in Canvas's panel theme. Canvas's own negotiator
  (priority 1001) switches to the admin theme when the request carries
  `use_admin_theme=1` — the same switch its media library uses.
- **Fix:** `?panel=1&use_admin_theme=1` on the Add / Edit links
  (`data-dialog-type="dialog"` with its own `target`, so the media library's
  `#drupal-modal` can open on top), an `#ajax` Save that closes the dialog
  and reloads the editor (Canvas has auto-saved), and an `#after_build` that
  trims meta / menu / path / revision — those are added by other modules
  AFTER a plain form alter runs. Note for tests: Drupal ajax buttons fire on
  `mousedown`, so a scripted `.click()` does nothing.

## A Canvas block's settings ARE its form's values

- **Symptom:** The featured picks kept vanishing — first when the rows were
  wrapped in containers for styling, then again when only the "latest"
  switch was toggled — and the empty grid was published before anyone
  noticed. Reading the existing configuration in `blockSubmit()` and
  applying only what was posted did not help.
- **Cause (traced by logging the form's config and the submit's values):**
  Canvas builds the block form from the stored settings, takes the inputs'
  DEFAULT values as its model, and on every change posts that model and
  rebuilds the settings from it with a plugin that has only default
  configuration. Anything that is not an input with a default is simply not
  in the model, so the next round trip drops it. `#type hidden` and
  `entity_autocomplete` elements do not travel either; text fields,
  checkboxes and selects do (not weight selects).
- **Fix:** Every setting is a top-level input keyed as the setting, with
  its default the current value — the hero's `order` text field, the
  featured `latest` checkbox and five `projects[i]` text fields — hidden by
  CSS where they are not for typing; the list the editor sees is markup
  only, and `js/panel-sortable.js` writes the inputs from it (on drop, on
  pick). `blockSubmit()` reads values only. Also learned on the way:
  `#markup` goes through the admin XSS filter, which drops `<button>`, and
  the panel never re-renders a block form after a change, so whatever the
  row should show right away is updated client-side.

## A media library inside a dialog over the Canvas editor: two more traps

- **Symptom:** Creating a hero slide from the panel's dialog: the film was
  picked, the thumbnail showed, and Save answered "This value should not be
  null" for the media field.
- **Cause, twice:** (1) Canvas's theme negotiator switches the media
  library's "Insert selected" post back to canvas_stark on purpose, so the
  widget came back as React custom elements with no real inputs. (2) Canvas
  overrides Drupal's `update_build_id` ajax command and returns early for
  forms it does not know, so the dialog kept posting its first build id;
  every ajax step cached the form under a new id nobody used, and Save
  rebuilt the widget as it was on open — empty. Traced by logging build ids
  and the media field's raw input per request.
- **Fix:** A theme negotiator (priority 1002) that keeps every `?panel=1`
  request in the admin theme, and a wrapper on `Drupal.Ajax.prototype.success`
  that applies `update_build_id` commands to the dialog's inputs before
  Canvas's command handler drops them. Also: a dialog has no message region,
  so validation errors are rendered inside the form.

## The drop line that was never there: check computed styles, and turn aggregation off locally

- **Symptom:** "Not seeing the line on drag." The sorter added the line's
  class and set its top; every check I ran read those and passed. The rule
  that draws it had been deleted by an earlier CSS dedupe, and for a while
  the container also held a stale copy of the file behind aggregated
  bundles the browser kept serving.
- **Fix:** The line, ghost and dimming rules are back (the line and the
  ghost are `position: fixed` on the body, the line above the ghost that
  follows the pointer). Locally, CSS/JS aggregation is off in
  `settings.local.php` (now included from settings.php) so edits reach the
  browser on `drush cr`.
- **Rule:** A UI check reads `getComputedStyle`, a rect and
  `elementFromPoint` — never just a class name. And after editing a module
  or theme file, confirm the served copy contains the change before
  testing anything built on it.

## Links and buttons fire Drupal ajax on different events; a script that throws at load takes everything after it down

- **Symptom:** The news panel's pin did nothing from a scripted mousedown,
  and the "Add a story" dropdown never opened.
- **Cause:** Drupal binds `use-ajax` LINKS on `click` and submit BUTTONS on
  `mousedown` (with click prevented) — a test has to send the right one.
  And a leftover `addEventListener("pointerup", end)` from an earlier
  version of the sorter threw `ReferenceError` at load, so every listener
  after that line (the dropdown, the dialog's reload hook) was never
  registered, while everything before it kept working — which is why the
  drag still worked and hid the fault.
- **Rule:** After editing a script, read the console for the file's name
  before trusting any behaviour; and the smoke test in the deploy step
  (`node -e` loading the file against a stub document) catches load-time
  errors before the browser does.

