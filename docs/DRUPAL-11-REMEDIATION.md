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

## P2 — open

Findings 6 (the rest of the cacheability work), 7 (hero slides as nodes — a
deliberate trade-off, to discuss), 8 (two logo media types — an integration
concern), 11 (behaviours gated on `<html>`) and 14 (keyboard access on the
panels, a visible skip link) are as the review states.
Finding 9 (listing filters over one page) was overtaken on 2026-09-07: the chips
navigate to the server-side filter and both listings lazy-load from the View's
pager.
