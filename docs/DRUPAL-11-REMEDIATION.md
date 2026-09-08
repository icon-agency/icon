# Drupal 11 review — remediation record

What was done about [the review](DRUPAL-11-REVIEW.md), finding by finding, with
how each fix was verified. The review's baseline was commit `dd62930`; several
findings describe the OTHER codebase the design system was imported into
(`icon-agency/iconagency-drupal-ai`) rather than this repo — noted where so.

## P1 — fixed 2026-09-08

| # | Finding | Fix | Verified by |
| --- | --- | --- | --- |
| 1 | Uploaded SVG marked safe after regex stripping | `IconSiteExtension::inlineSvg()` now runs the file through `enshrined/svg-sanitize` (added to `composer.json`; remote references removed, output minified) and only then applies the caller's class. A media ID is resolved only when the media's own view access allows. | A fixture with `<script>`, `onload`, `onclick`, a `javascript:` link, `foreignObject`, a remote `<image>` and a remote `<use>` rendered nothing hostile; the site's real icons (fact cards, footer offices) still render with their paths. |
| 2 | Work body rebuilds paragraphs without access checks | `icon_preprocess_field()` skips any paragraph whose `access('view')` is not allowed. | An unpublished paragraph: anonymous view access FALSE → left out. |
| 3 | Media helpers bypass media access | `_icon_media_image()` (theme) and `icon_site_media_source()` (module) return nothing unless `$media->access('view')` allows. Every hero, tile, banner, figure, film and logo goes through one of them. | An unpublished image media as anonymous: both helpers empty; a published one: props. |
| 5 | Seed scripts delete paragraphs before saving | Every seed/mirror script requires `scripts/_guard.php`: refuses on a production platform environment, and otherwise unless `ICON_SEED=1` is set (`ddev exec "ICON_SEED=1 drush php:script scripts/<script>.php"`). The three scripts that replace bodies now delete the old paragraphs only AFTER the new node revision has validated and saved. | A bare `drush php:script` run refuses with the message; the gated run completes. |
| 4 | The editable footer replaced by static content | About the other codebase's footer regions. In this repo the footer's content is the CMS's since 2026-09-07 (Content → Footer, Content → Offices, the footer-social menu) — see the handoff. The page shell has no `content` Twig block for 403/404 templates to extend (review #13); add one when integrating with a site that has them. | — |

Also from the review, done in the same pass: the four Canvas blocks return a
cache-tagged empty result (hero: the reel's tags; featured work: `node_list:work`;
marquee: its logo tags; news feed: the View's config tag) instead of a bare `[]`
(finding 6, the "empty result never invalidates" part).

## P2 — first pass, 2026-09-08

| # | Finding | Fix | Verified by |
| --- | --- | --- | --- |
| 10 | Panel actions over GET, no revisions | The news and logo action routes are `methods: [POST]` (CSRF token and permission kept); the panel script's own calls POST; every change saves a new revision with the user and a log line ("Homepage feed: pin (Canvas panel)"). The Drupal-ajax links already POST. | GET → 405; anonymous POST without a token → 403; a pin from the Canvas panel saved sticky with a second revision and its log message. |
| 12 | The content editor role cannot use the new content | The role has create / edit any / delete any for News, Work, Hero slide and Office, the media permissions, the Canvas page permissions, the menu permission for the social links, and two scoped permissions of icon_site's: `manage icon lists` (Client logos, Icons, Offices and the marquee's order — replacing `administer media` on those routes) and `manage icon footer`. The list save handlers also check each entity's update access. | A probe user with the role: allowed on every list page, the Footer page, node add and the social menu. |
| 13 | No `content` block for 403 / 404 templates to extend | `page.html.twig` wraps its main content in `{% block content %}`. | A 404 renders through the block. |
| 15 | Dependencies, negotiator, deprecated embeds | `icon_site.info.yml` declares media, media_library, file, image, options, path_alias, menu_link_content, paragraphs and canvas; the panel theme negotiator applies only to node / media form routes and the media library, with `?panel=1`, for a user who may view the admin theme; the two `views_embed_view()` calls are `#type: view` render elements. | Next up rails render on a news and a work article; no PHP warnings. |

## P2 — keyboard access, 2026-09-08

| # | Finding | Fix | Verified by |
| --- | --- | --- | --- |
| 14 | Pointer-only reorder, a picker with no keyboard, no visible skip link | Each row's handle is a focusable `role=button` named for its row ("Reorder Nike Melbourne Marathon Festival — arrow keys move it up or down"): ArrowUp / ArrowDown move the row a place, Home / End to the ends, focus travels with it, and a live region announces the new position; the order is written the same way a drop writes it. The picker's search box is a `combobox` over a `listbox`: the arrow keys, Home and End move the highlighted option (`aria-activedescendant`), Enter picks it, Escape closes and returns focus to the control that opened it, which carries `aria-expanded`. Focus rings on the handle and the pickable. The skip link is `.skip-link`, shown on `:focus` as a pill at the top-left (design system `src/base/reset.css`, outside the base layer so the sr-only utility cannot beat it) on every shell. | In the Canvas editor: ArrowDown on the first hero handle rewrote the order field (26,27,… → 27,26,…), focus stayed on the handle, the announcement read "PHN North Western Melbourne moved to position 2 of 5"; the picker's arrows moved the highlight with `aria-activedescendant` following and Escape returned focus; the skip link renders as a 160×45 pill when focused. |

## P2 — the hero model, 2026-09-08

| # | Finding | Fix | Verified by |
| --- | --- | --- | --- |
| 7 | Every published slide appeared in every hero; the login page found its hero by walking the front page's Canvas tree | Slides stay nodes (the dialogs, revisions and content list are theirs), but the hero block's `order` is now an explicit SELECTION: only chosen slides show. The panel gains an "Available — not on this reel" group with Add to reel, and Remove on reel rows; a slide created from the panel goes straight onto the reel. The login page names its hero: Content → Footer → Login page → "Hero reel to play" (a Canvas page, the front page by default), with a fallback to the front page when the named page is gone. The front page's selection was migrated to what it already showed. | In the editor: Take off wrote the order without the slide and showed it under Available (handle hidden, Add shown, "Cancer Council Victoria taken off the reel" announced); Add to reel restored the order; the save event for a new slide appended its id. The login page plays five slides with the setting on the front page, on page 1, and on a missing page. |

## P2 — cacheability and the preview, 2026-09-08

| # | Finding | Fix | Verified by |
| --- | --- | --- | --- |
| 6 | Media props lose access and file/style dependencies; contexts and max-age not carried | Both media helpers take a `CacheableMetadata` collector and record the media, its access result (with its contexts), the file and the image style; every caller — the news and work node preprocess, the paragraph preprocess, the Work body's field rebuild (each paragraph's access result too), the hero reel and the hero, featured-work and marquee blocks — merges it into what it renders (`_icon_apply_cache()` for preprocess variables, `applyTo()` for blocks). The access-checked queries add `user.permissions`. | Rendered as anonymous: a work article bubbles `user.permissions`, `user.node_grants:view` and 17 media / file / image-style tags; a news article 13; teasers 2–3; the hero block `user.permissions` + 8 tags, featured work 6, the marquee 37; max-age permanent throughout; the empty hero keeps `node_list:hero_slide`. Every page type renders, no PHP warnings. |
| 11 | Behaviours gated on `<html>` would miss AJAX-inserted components | Checked before changing anything: Canvas renders every edit as a NEW document into its second preview iframe and swaps frames (a marker set on the preview's window was gone after a change; the new document had its reveals applied). Behaviours therefore attach fresh on each change and the gap never shows in the editor; visitor pages are full loads. Left as is, recorded here. | The marker test in the editor, described. |

## P2 — open

Finding 8 (two logo media types) is an integration concern for the other
codebase. Everything else in the review is closed above.
Finding 9 (listing filters over one page) was overtaken on 2026-09-07: the chips
navigate to the server-side filter and both listings lazy-load from the View's
pager.
