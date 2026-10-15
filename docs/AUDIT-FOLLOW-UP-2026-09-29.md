# Audit follow-up — the seven items neither document covers

**Date:** 29 September 2026
**Relates to:** `BACKEND-AUDIT-2026-09-24.md`, `TWO-STAGE-DRUPAL-PROGRAMME-2026-09-28.md`
**State:** built and verified locally on `claude/design-system-drupal-canvas-95ee74`; nothing committed, pushed or run on the host. Host readings below were read-only.

| # | Item | Outcome | Needs |
|---|---|---|---|
| 1 | Footer edits reset on deploy | Fixed | Deploy |
| 2 | Failing JS lint | Fixed | Deploy |
| 3 | Host disk | Measured; a hosting decision | Decision |
| 4 | PHP sizing | Measured worse than assumed; settings file added | Deploy, then verify |
| 5 | Version-lifting scripts | Reviewed; clean | Nothing |
| 6 | Old-site redirects | Mapped and scripted | Deploy, run script on host, review 16 rows |
| 7 | Accessibility and front-end performance | Audited; three fixes made, the rest listed | Deploy; decisions on the rest |

## 1. Footer edits reset on deploy (audit M8, and C2 on the same form)

**Cause.** The footer form saved into the `icon_site.footer` config object. That object is exported, and `drush deploy` imports the exported copy on every release. The exported copy also lacked the eyebrow, B Corp link, ABN and the two legal links, so those would have been removed outright.

**Change.** The editor's values are saved to State, which is neither exported nor imported. `icon_site_footer_setting()` reads the editor's value, then the config object as the deployed default, then the code default. A custom cache tag `icon_site_footer` refreshes pages on save. `icon_site_post_update_footer_to_state()` carries each site's current footer into State; `drush deploy` runs database updates before it imports config, so an editor's existing values are kept ahead of the import.

**Links.** The four link fields are checked on save and again on read: a path starting with one slash, or an http, https, mailto or tel URL. Fourteen cases tested, including `javascript:` in mixed case, with a leading space, with an embedded tab, `data:`, `//host` and `/\host`.

**Proof.** Saved a test listing URL through the form, ran a full `drush deploy`, and the page still printed it. The form refused `javascript:alert(1)` and stored nothing. Local footer restored afterwards.

**Interim.** State has no revisions or translation. The programme's S2.4 (revisionable footer content) and S2.7 (contextual editing) still stand.

## 2. Failing JS lint (audit H4)

`npm run check:js` now passes with no errors and no warnings. Removed the unused `closeMobileNav` from `js/header.js` (left by the phone menu work of 23 September), the useless first assignment of `framed` in `js/page-transition.js`, and two unused variables in `js/hero-sphere.js`. The CI workflow still does not run on pushes to `icon-build`; that is a repository setting and is unchanged.

## 3. Host disk

| | Size |
|---|---|
| Configured in `.platform.app.yaml` | 3072 MB |
| Actual files volume on `icon-build` | 974 MB, 651 MB used |
| Database service | 1024 MB |
| Project plan storage | 5120 MB, development plan |
| Local files in all | 2078 MB |
| Local films, 41 | 1571 MB |
| Films over 20 MB, 14 | 1398 MB |

The environment never received the configured 3 GB, so the audit's capacity figure (M4) is three times too generous. The site's media does not fit the host today, and responsive image derivatives (H2) will add to it.

**Options, in order of preference.**
1. Have the volume grown to the configured size, or move to a plan whose production environment carries it. This is a Platform.sh account action.
2. Re-encode the fourteen large films. They average 100 MB; web delivery at 1080p is normally a fifth of that. `ffmpeg` is not installed on this machine, so no test encode was made.
3. Host film externally. This is the audit's M11 direction and removes film from the disk budget.

No host file was moved or deleted.

## 4. PHP sizing (audit C6)

Read from the host's web PHP configuration, not the command line:

| Setting | Host now | Restored file |
|---|---|---|
| `opcache.max_accelerated_files` | 4000 | 30000 |
| `opcache.memory_consumption` | 64 MB | 256 MB |
| `opcache.revalidate_freq` | 0 | 60 |
| `display_errors` | On | Off |
| `session.use_strict_mode` | not set | On |
| `session.gc_probability` | default | 0 |
| PHP files in the codebase | 19,198 | |

The audit assumed platform defaults of about 10,000 files and 128 MB. The real figures are lower, and `display_errors` is on for web requests. `drupal/php.ini` restores `main`'s settings, with the upload limits stated rather than inherited. Platform.sh reads it from the app root on deploy.

**Verify after deploy:** the status report's PHP section, and `opcache_get_status()` cached scripts against the limit from a web request.

## 5. Version-lifting scripts (audit C7)

`masthead-compact.php` and `rail-versions.php` moved placed components to the active version on the five expertise pages. All ten instances are on the active version and the five pages validate with no violations.

**The larger finding.** The 674 of 779 figure is mostly an artefact. `canvas:doctor --checks=content` on the local copy reports 311 of 325 default revisions invalid. Of those, 303 carry only "You do not have access to transition from Published to Published", and the other 8 are team bios whose text format the validating user may not use. Both are permission failures of the anonymous account the check runs as. Validated as an administrator, all 25 entities holding a component tree pass with no violations.

C7 should be re-scoped from "674 invalid items" to "the health check runs without the permissions it needs". Past revisions were not checked here and still deserve the classification the programme asks for.

## 6. Old-site redirects

The old site has no sitemap, so its own listings were crawled: 29 news pages, 3 work pages and the navigation.

| Old paths | Count | Outcome |
|---|---|---|
| News stories | 282 | 270 keep the same path on the new site; 12 never imported, to `/news` |
| Case studies | 80 | 7 to the same project on the new site; 1 likely match; 72 to `/work` |
| Top-level pages | 14 | 8 unchanged; 3 renamed; 3 with no equivalent |

`drupal/scripts/data/old-site-redirects.csv` holds the 98 rows with the basis for each. `drupal/scripts/redirects.php` creates them as 301s. It is add-only and idempotent: a second run created none. Locally it made 97; one already existed.

**Sixteen rows are marked `review`:** the twelve stories never imported, `/behaviour-change`, `/people-culture` and `/subscribe`, which have no page on the new site, and `/work/our-work-nwmphn`, mapped to Raise It as the likely same campaign.

**Not covered:** query-string URLs, file URLs under the old `/files/`, and any path the old site's listings do not link to. Search Console's indexed pages would close that gap.

## 7. Accessibility and front-end performance

axe-core 4.10 against WCAG 2.2 A and AA plus best practice, on nine page types at 1400 wide and three at 375. Page weight from the browser's resource timings.

**Fixed**

| Finding | Change |
|---|---|
| 5.2 MB of footer posters on every page | The reel's videos carried `poster`, which a browser fetches whatever `preload` says. Now `data-poster`, handed over when a slide's turn comes. Posters fetched at load: 6 to 0; 1 when the footer is reached. |
| Legal pages skipped a heading level | Section headings are `h2` in the sentence style, not `h3` under the `h1`. |
| JS lint | Item 2. |

**Open, for decision**

| Finding | Where | Note |
|---|---|---|
| Heading levels skipped | Expertise pages: `h2` then `h5` services and `h4` Featured. About: `h2` then `h5` | The Heading 5 Caps look was asked for on 24 September. Keeping the look on an `h3` needs an editor style that separates level from appearance. Moderate, best practice. |
| Home page weighs 23 MB | 17 MB film, six hero films `preload="auto"` | Load the first film only and the rest on their turn. |
| No responsive images | Every image | Audit H2. Team photos are 900 wide shown at 238. |
| PNG posters | `work_media` style | 0.4 to 1.5 MB each. The large styles do not convert to WebP. Audit H2. |
| Images not lazy, no dimensions | Home: 14 images none lazy; 36 without width and height | Layout shift and eager loading. |
| Third-party hosts | Typekit, Google Fonts, jsDelivr, cdnjs | Audit H3. |

**Checked and passing:** no colour-contrast failures apart from the home hero's load counter, which is a transient state over film; no horizontal scroll at 375; phone menu targets are 21 px tall and pass WCAG 2.5.8 by spacing; images carry alt text; contact, news, work landing and the work article returned no violations.

**Limits of this pass.** Automated checks find roughly a third of accessibility issues. No screen reader or keyboard walk-through was done, and listing images load lazily so their weight was not captured. Core Web Vitals need a foreground browser or field data.

## To put this live

1. Commit and push. `drush deploy` on the host then runs the footer post-update before its config import.
2. On the host: `drush php:script scripts/redirects.php`, then `drush php:script scripts/legal-pages.php` for the heading change.
3. Verify the PHP settings from a web request.
4. Decide the disk option and the open rows in item 7.
