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

A short, real component to show the **shape** of a per-component note. Source: `src/components/tagline.css`, `js/tagline.js`. (The tagline itself now lives only on the prototype pages — the canonical components carry richer notes of this same shape in the design-system index (`/index.html`), e.g. the Intro section's "Drupal (Canvas / SDC)" block, which lists props, behaviours and reduced-motion notes ready to lift into a `component.yml`.)

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
| `templates/*.html` | Rebuild as SDC `.twig` under `components/<name>/<name>.twig`, plus a `<name>.component.yml` schema. `homeC.html` (canonical), `news-b.html`, `work-landing-b.html` (the header-B winners; the A variants stay as alternates) the news-article templates and the work articles (`work-article-master.html` is the reference; the client folios are instances of it) are the pages being ported; `home`/`homeB`/`work` are prototypes — port their components only where the system has adopted them. |
| `js/*.js` | Move into Drupal libraries; attach via `Drupal.behaviors`. The full behaviour → library table (header, reveal, home, site-footer, news, work, work-filter, work-landing, theme-handover, share, subscribe-reveal, velocity-lean, work-article, work-scroller, work-video, plus the prototype-only files) is in `docs/animation.md`. `js/hero-loader.js`'s generated rows become a Twig loop inside the hero SDC. |

The CSS doesn't change shape across the port — only the markup gets re-templated as Twig + SDC schemas.

## The theme

`drupal/web/themes/custom/icon` (the DDEV project is `drupal/`, docroot `web/`; see the README). `base theme: false`, two regions (`highlighted` for messages, `content`), Claro stays the admin theme. Decisions, each one a rule:

- **One build, not a copy.** The theme's `src/main.css` `@import`s the repo-root `src/main.css` unchanged and adds `@source "../templates"` + `@source "../components"`, so the Twig and SDC markup feed the same utility scan. `npm run build:theme` writes `css/main.css` (minified, committed — Drupal serves the committed file). The root `@source` lines stay until every page is Twig, so the theme output is a superset of the root build meanwhile. The root entry fences the site off with `@source not "../drupal"` — Tailwind's automatic source detection would otherwise scan the theme into the STATIC build (LESSONS.md). Drupal-only pages the static system has no template for (the account pages, `templates/layout/page--user.html.twig`) get their CSS as the theme's own page layer, `src/auth.css`, imported after the root entry under the same rules; `src/admin-chrome.css` shifts the fixed chrome by Drupal's `--drupal-displace-offset-*` so an editor's admin top bar and sidebar stay clickable (the chrome's z-index sits above them).
- **Behaviours are generated, never forked.** `scripts/theme-js.mjs` wraps each root `js/*.js` IIFE verbatim in `Drupal.behaviors.<name>.attach()` (gated by `once()` on `<html>`, so an AJAX re-attach is a no-op) and writes the theme's `js/`. `velocity-lean.js` and `hero-loader.js` are copied as plain IIFEs (the first only defines `window.ICON.velocityLean` — consumers declare the `icon/velocity-lean` dependency; the second must run after the hero's markup and before the CDN scripts, so its library is footer-scoped with a negative weight — NOT `header: true`, which runs it before the body exists; LESSONS.md). Prototype-only behaviours (hero, tagline, hero-sphere) are not ported. `icon.libraries.yml` is the `docs/animation.md` table, one library per behaviour; `icon.info.yml` attaches the chrome set (global CSS + fonts, header, reveal, velocity-lean, site-footer) on every page.
- **Chrome is Twig, not regions.** `templates/includes/site-header.html.twig` and `site-footer.html.twig` are the static header/footer with the four home links at `path('<front>')`, labels through `|t`, and the footer films as theme assets (`assets/banner/`). The one asset the CSS itself references — `url(../assets/brand/logo-dark.svg)`, the footer's logo mask — resolves relative to `css/main.css`, so it is mirrored at `assets/brand/` in the theme; any new `url(../assets/…)` in `src/` needs the same mirror. `html.html.twig` adds the `js-animations` class inline before the stylesheet; `page.html.twig` is skip link → header → `<main id="main">` → footer, with `main_attributes` (from `icon_preprocess_page()`) as the hook for a page layer's shell class. The theme class (`dark`, `theme-work-article`) is added to `html_attributes` by page preprocess, never to `<body>`.
- **Still static, by design, until their slice lands:** the nav items and service lists (a menu-driven header is a later slice), `aria-current` on the active nav item, the footer newsletter inputs (the webform/newsletter block), and the header trigger reads EXPERTISE (the canonical `homeC.html` label; the other templates still say SERVICES — reconcile in the static templates).
- **Verify covers it.** `npm run verify` check 5 byte-compares the theme's `css/main.css` and every generated `js/` file against a fresh build.

## News — the first content slice (4 Sep 2026)

What `templates/news-b.html` and `news-article.html` became, and the decisions worth knowing before the Work slice repeats the shape.

- **Content type `news`** (`drupal/config/sync/node.type.news.yml`): `field_news_image` (media image, required — the square tile), `field_news_banner` (media image, optional — the 2:1 banner whose presence picks the head variant, exactly the rule `news-article.css` prototypes: present → the stacked head with the wide hero, absent → the tile head; no variant field), `field_news_category` (`list_string`, keys = the `?category=` slugs the static chips already used — `agency`, `our-work`, `awards`, `insights`), `field_news_date` (date), and `field_news_content` — a Paragraphs field (`entity_reference_revisions`) holding the body. Field names follow `field-naming.md` with `news` as the shared block (card and article both read them).
- **The article is a node template; the body is Paragraphs, each rendered by an SDC.** `node--news--full.html.twig` is the fixed frame (back link, title, category / date row, tile photo or banner, both Share rails, the Next up rail) and `{{ content.field_news_content }}` the composable middle. Four paragraph types — `prose`, `news_article_figure`, `news_article_video`, `pull_quote`, fields BEM-prefixed (`field_prose_text`, `field_pull_quote_variant`, …) — go through `icon_preprocess_paragraph()` into `sdc_props` and a one-line `paragraph--<type>.html.twig` includes the SDC of the same name: the three-piece pattern of `drupal-mapping-pattern.md`, literally. `field--node--field-news-content.html.twig` prints the paragraphs bare so the flow's direct-child measure rule holds. Editors get the classic Paragraphs widget on the node form (modal add, closed summaries).
- **Why not Canvas for the body (4 Sep 2026).** Canvas 1.10.1's composer opens only its own Pages: `ComponentTreeLoader::getCanvasFieldName()` throws for every other entity type ("Other entity types and bundles must use content templates for now", issue 3498525, still open, no newer release). Content Templates DO work (`/canvas/template/node/news/full` composes the full-view layout once, binding SDC props to node fields) but per-story composition inside a template ("exposed slots") has no editor UI yet. So the tree field was retired and the body moved to Paragraphs; the four SDCs are unchanged and Canvas-eligible, so a later move is a data migration (paragraph → tree item), not a rebuild. Canvas keeps the homepage (a Page) and can own the article FRAME as a Content Template later.
- **Four body SDCs** (`components/`): `prose` (rich text → `.content-page`; from Paragraphs the text arrives as a `processed_text` render array in format `basic_html`), `news-article-figure` (Canvas image prop → `.news-article__figure`), `news-article-video` (an embed URL + title → the 16:9 film band), `pull-quote` (`variant` enum italic/upright, optional `cite`). The prose SDC cannot know which article it sits in, so `src/utilities/news-article.css` now also gives the measure to `.news-article__flow > .content-page` — the one design-system CSS change this slice needed.
- **The card is an SDC** (`news-card`: title, url, Canvas image, category, date, `heading_level` enum h2/h3). The node teaser (`node--news--teaser.html.twig`) is one include of it with props from preprocess; there is no `<article>` wrapper because `news-card.css` establishes its container query on the card's direct parent. The rail sets h3 through `#news_heading_level` on the row build **and** a `#cache` key (LESSONS.md).
- **The listing is one View, `news`**: `page_1` at `/news` (12 per page, sorted by `field_news_date`), with a contextual filter on the category whose default is the **query parameter** `category` (fallback `all` = the exception value) — so the chips are real links that filter server-side, exactly the no-JS baseline `filter-bar.css` describes, and `js/news.js` enhances them in place. On a `?category=` page the `data-news-list` hook is withheld, so the in-place filter only ever runs over the full set. `next_up` is a block display (3 latest, current node excluded via the `node` default argument) embedded by `icon_preprocess_node()`. The masthead, chips, list wrapper and pager are `templates/views/` + `templates/navigation/pager.html.twig`, fed by `icon_preprocess_views_view()`, `_views_view_unformatted()` and `_pager()` (the status pill's noun rides on the pager element as `#noun`).
- **Dark opening + behaviours**: `icon_preprocess_html()` adds `dark` to `<html>` on the listing and news nodes and attaches `icon/theme-handover`; the listing attaches `icon/news` + `icon/subscribe-reveal`, the article `icon/news` + `icon/share`.
- **Sample content**: `drupal/scripts/news-sample-content.php` (`ddev drush php:script …`) creates the twelve listing stories (the Butterfly one with the 2:1 banner, so both heads are on the site) and the PRCA article with its body as paragraphs — prose, the film, figures, pull quotes. Re-running replaces each story's paragraphs. Images are the repo assets copied to `drupal/sample-content/news/` (git dedupes identical blobs). (For the record, building a Canvas tree in code works too — a prop on the component's default static prop source is stored **collapsed**: `getDefaultStaticPropSource($prop)->withValue($v)->getValue()` under the prop name, JSON-encoded in `inputs` — it just has no editor for nodes yet.)
- **Still open**: Subscribe (both header disclosure and footer) stays inert until the newsletter slice; the local tasks block sits unstyled in `highlighted` so editors can reach Edit; the `basic_html` toolbar has not yet been trimmed to what `.content-page` styles (`wysiwyg-output.md`).

## Work — the landing and the case study (4 Sep 2026)

What `templates/work-landing-b.html` and `work-article-master.html` (+ the client folios) became. The News shape, repeated — the differences are the ones the design system itself names.

- **Content type `work`**: `field_work_client`, `field_work_category` (`list_string`, multiple — the six landing slugs; the FIRST value is the card's filter key, all are listed in the facts row), `field_work_deliverables` (multiple strings), `field_work_statement` (the serif statement under the banner), `field_work_tile` (media image OR video — the card), `field_work_banner` (media image or video, optional, falls back to the tile), `field_work_bg` + `field_work_ink` (the chameleon pair as hex strings, defaulting to the master's blue / white), and `field_work_content` (Paragraphs).
- **The chameleon is preprocess, as the CSS asked.** `icon_preprocess_html()` puts `.theme-work-article` and the two custom properties inline on `<html>` for a work node (hex-validated), and attaches the handover; the banner in `node--work--full.html.twig` carries `data-theme-handover="gone"` + `data-theme-handover-class`, so the page hands back to the site theme as the banner leaves — exactly the static templates' hooks.
- **The body: seven block types.** The shared `prose` (Content), `pull_quote`, `news_article_video` (Video) plus `work_gallery_row` (Gallery row: 1–2 media, style default / portrait / layered, optional ground colour + float inset), `work_video` (Film: video media + cover image + title), `work_scroller` (Scroller: images + ground colour) and `work_stats` (Results → nested `work_stat` Result: value / unit / label). Each renders through the SDC of the same name (`work-gallery`, `work-video`, `work-scroller`, `work-stats`); `_icon_work_paragraph_props()` owns the lookups.
- **Runs and galleries are composed in the field template.** `icon_preprocess_field()` turns the paragraphs into `groups`: consecutive Content / Film / Video / Results blocks become ONE `.work-article__chunk` with one Share rail (the master's "run"); consecutive Gallery row blocks become ONE `.work-gallery`; a Scroller or Pull quote stands alone in the flow. Editors just order blocks; the rails and galleries fall out of the sequence. `src/utilities/work-article.css` gives a bare `.content-page` in a chunk the prose measure, as the news layer does.
- **The card is an SDC** (`work-card`: title, url, client, media {type, src, alt, width, height}, heading level, caption delay) whose root is the homepage's `.work__item`. Media helper `_icon_media_source()` returns an image (through the `work_media` style) or a video file, so a tile or banner can be a looping film.
- **The landing is the `work` View**: `/work` with the same query-parameter category filter as `/news`, 12 per page, sorted by authored date; `next_up` (2 latest, current excluded) is the rail. The masthead, chips, uncapped grid (`views-view-unformatted--work.html.twig`, `data-work-list` withheld when server-filtered) and pager ("projects") are theme templates; `icon/work-landing` + `icon/subscribe-reveal` attach on the listing, `icon/share` + `icon/velocity-lean` + `icon/work-article` + `icon/work-video` + `icon/work-scroller` on the case study.
- **Sample content**: `drupal/scripts/work-sample-content.php` — the twelve landing projects; iCanQuit, The Athlete's Foot, Melbourne Marathon (Nike) and MoAD carry bodies after their folios and pairs; media in `drupal/sample-content/work/` (renamed kebab-case; git dedupes the repo's copies).
- **Still open**: the layered banner variant (`.work-article__banner--layered`) and the `list-slash` prose option aren't reachable from the editor yet; `work_gallery` SDCs with array-of-object props are not Canvas-eligible (Canvas has no shape for them), which is fine — they are Paragraphs-fed.

## Homepage — a Canvas Page (4 Sep 2026)

`templates/homeC.html` as a Canvas **Page** (`/home`, the site front page), composed from five components a site builder can reorder, remove or re-word in the Canvas editor (`/canvas/editor/canvas_page/1`). `scripts/home-page.php` builds it.

- **The news feed is a Views block, which Canvas accepts as a component.** `news.latest` (→ the news rail: promoted stories, pinned first, newest next) is placed as `block.views_block.news-latest`. The marquee is placed as `block.icon_clients_marquee` (icon_site), which queries every published **Logo** media in Order and renders the `clients` SDC itself (no View); its one setting is the section's accessible label, and its Canvas panel links to the Client logos list below. (A Canvas 1.10 gotcha: a block with NO settings gets no client-side model, and opening its panel throws `reading 'source'` — give a block at least one real setting.)
- **The hero and Featured work are blocks whose Canvas panel is a draggable list.** The `Homepage hero` block (`block.icon_hero`, icon_site) renders the `icon:hero` SDC with an `icon:hero-slide` component per **Hero slide** content item (node type `hero_slide`: title = client name, `field_slide_media` = Video or Image media via the media library widget, `field_slide_link`). Its one setting is `order`, and its Canvas panel is a tabledrag list of the slides by client name with Edit links (new tab) and an "+ Add new slide" link at the top — drag rows to reorder, the page publishes the order; a slide not yet in the order is appended so a new one shows straight away. Up to eight (`HeroBlock::MAX`). Films must be 6 seconds, muted, loop — on the slide form and the panel. The two SDCs are hidden from the Canvas library (component status off) so nobody places a second hero; they are rendered only by the block. Canvas 1.10 cannot lock a component in place, so the hero block can still be dragged in the tree — nothing needs to be dragged, but a locked hero would mean placing the block in a theme region with a front-page visibility condition, outside the Canvas tree and without its panel. `Featured work` (`block.icon_featured_work`) is the FIXED five-tile grid: its one setting is `projects` (five node IDs in order) and its panel is a tabledrag table of five rows — a tile label, a select of every published Work item by title, an Edit link to the article (new tab), plus an "All work articles" link; empty picks fall back to the latest. It renders the `icon:featured-work` SDC (hidden from the library) by filling its three row slots — `pair_top`, `feature`, `pair_bottom` — with the picks' teasers, the third marked `#work_wide`. The feature row's tile is also wide by the ROW's modifier (`.work__row--feature`) in the design system. Both panels are card lists reordered by `icon_site`'s own `js/panel-sortable.js` (core tabledrag dies on Canvas's React re-renders, and Canvas ignores weight selects, so the order travels in one hidden text field the sorter writes); Add / Edit open the Hero slide form in a Drupal dialog over the editor (`?panel=1&use_admin_theme=1`, Save closes it and reloads); the featured rows show project name over client and open a searchable dropdown on click (every published Work item, filtered client-side on project or client; the pick is written into a hidden entity autocomplete over the `icon_work` selection handler, `WorkSelection.php`, which is also what validates it) — and a "Show the latest five automatically" switch at the top (`latest`) that makes the grid the newest five and the list a dimmed preview. Work items carry a short **Project name** (`field_work_project`) for lists; the title stays the card's headline. Styled by `icon_site/panel_lists` (`css/panel-lists.css`, attached to the Canvas editor page by `hook_page_attachments()` — a library attached by the block form itself never reaches the panel), and `blockSubmit()` reads the table by its nested parents path because Canvas posts the form with `#tree` semantics. Canvas 1.10's layers panel shows the component name for every instance (the per-instance label is stored and sent — the UI ignores it).
- **The homepage news feed is core's two node flags, relabelled.** `news.latest` shows PROMOTED stories only (`promote = 1`), pinned ones first (`sticky` DESC), then by date. The News form shows them as **Promote to homepage** and **Pin on homepage** (`icon_site_form_node_form_alter()` relabels core's "Promoted to front page" / "Sticky at top of lists"); new stories are promoted by default, so un-ticking removes one from the feed and pinning keeps it at the top whatever its date.
- **Client logos are a `logo` media type** (file source, `svg png`, the name is the alt, an Order field), managed as ONE LIST at `/admin/content/client-logos` (icon_site — also a tab under Content and a menu item): a draggable table of every logo — drag to reorder (writes `field_logo_weight`, the View's sort), rename the alt text inline, Edit (replace the file or rename), Delete, and an "Add logo" action at the top. The reusable piece is the `clients` SDC (`logos` array + heading), which any block or preprocess can feed.
- **The intro is props + SLOTS.** `intro` takes the words as props (masthead accent + caps, the intro text, the About link and label, the expertise heading) and exposes two slots: **Filmstrip cards** (`strip`), filled with `intro-photo` (a media-library image; alt from the media item) and `intro-fact` (icon from an enum of the four brand icons in `templates/includes/fact-icon.html.twig`, heading, label) components in whatever order they should drift; and **Expertise links** (`expertise`), filled with `intro-expertise` (label + link) components. Repeatable content in Canvas is child components in a slot, not array props — that is the pattern for every list an editor owns. `clients` (its accessible label) is an SDC over the theme's logos; a `client` media type (per `assets/client-logos/README.md`) is the later slice that makes the marquee editable. The fact card's `icon` prop is `type: integer` with `x-icon-media: true`, which icon_site's `hook_canvas_storable_prop_shape_alter()` maps to a reference to **Icon** media (`media.type.icon`: file source, `svg`, an Order field) edited as a SELECT — so the dropdown is the Fact icons list at `/admin/content/fact-icons` (add, drag to reorder, rename, replace, delete; the same list page class as Client logos, `MediaListFormBase`). The prop evaluates to the media ID — Canvas's widgets display a prop's RESOLVED value, and a select only preselects when that value is an option key — and `icon_inline_svg()` loads the media's file and inlines it (scripts and handlers stripped, class + aria-hidden added) so `fill="currentColor"` takes the card's white. Canvas has no SVG media shape of its own — the Image shape only matches Image-source media, and Drupal's image field refuses SVG — hence the mapping. Replacing an icon's file may need a cache rebuild to show on already-rendered pages.
- **The hero is an SDC in the library, and the reel is built from the pile** — `hero.component.yml` declares `libraryOverrides.dependencies` on `icon/hero-loader` (footer, weighted ahead of the externals) and `icon/home-c` (GSAP, SplitText, DrawSVG, Lenis), so the whole homepage system attaches whenever the hero renders. The template writes every piece twice (a pile card and a reel slide, in different containers); a slot child renders once, so the SDC renders the slides into `.hero__stage` as cards and `js/home-c.js` clones the reel from them when it finds `.hero__show-inner` empty — the template's own markup never takes that branch. Cards carry `data-client` / `data-client-url`, and the reel's client name renders as `<a class="hero__client-link">` when a URL is present. `work-gallery` / `work-stats` / `work-scroller` stay out of the library (arrays of objects; Paragraphs-fed).
- **The shell**: `icon_preprocess_page()` adds `.page-home` to `<main>` on the front page; the html stays light. Canvas renders the page's tree into `page.content`, so the theme's header, footer and `page.html.twig` wrap it unchanged.
- **Children in code**: a slot child is a tree item with `parent_uuid` = the parent's uuid and `slot` = the slot name (`scripts/home-page.php` builds the strip and the expertise list that way).
- **Block inputs in code**: a Views block instance must store `label`, `label_display` (`'0'`, a string) plus the plugin's defaults (`views_label`, `items_per_page`) or validation fails on `label_display`.
- **Theme assets inside an SDC**: an SDC renders with no page context (no `base_path`, no `directory`), so a component that references theme assets resolves its own path with `file_url(active_theme_path())` — `intro.twig` and `clients.twig` do; a page template keeps `base_path ~ directory`.

## Asset conventions

Client logo SVGs follow one upload rule — the component owns colour and
sizing, the file owns clean geometry — documented in
`assets/client-logos/README.md` beside the files themselves. The same rule
becomes the `client` media type's editor guidance at port time.

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
