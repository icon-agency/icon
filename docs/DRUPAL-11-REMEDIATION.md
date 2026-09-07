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

## P2 — open

Findings 6 (the rest of the cacheability work), 7 (hero slides as nodes — a
deliberate trade-off, to discuss), 8 (two logo media types — an integration
concern), 10 (panel actions over GET, no revisions), 11 (behaviours gated on
`<html>`), 12 (the content editor role's permissions), 13, 14 (keyboard access
on the panels, a visible skip link) and 15 (module dependencies, the theme
negotiator's scope, deprecated `views_embed_view()`) are as the review states.
Finding 9 (listing filters over one page) was overtaken on 2026-09-07: the chips
navigate to the server-side filter and both listings lazy-load from the View's
pager.
