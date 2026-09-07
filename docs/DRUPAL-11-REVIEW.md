# Drupal 11 review of the design-system import

The findings below are the historical review. See
[the remediation record](DRUPAL-11-REMEDIATION.md) for fixes and verification.

Reviewed 2026-09-07. Source baseline: design-system branch commit `dd62930` plus
the integration changes in this working tree. This document records findings;
no application code or database content was changed during the review.

## Findings

P1 means a security, data-loss or significant functional issue to address before
release. P2 means a functional or architectural problem to resolve during the
Drupal integration. Modelling recommendations are identified separately from
Drupal API requirements.

### 1. P1: Uploaded SVG content is marked safe without adequate sanitization

`web/modules/custom/icon_site/src/Twig/IconSiteExtension.php:25,63-76`

The helper accepts file contents, removes a few patterns with regular expressions,
and declares the result safe HTML. It does not establish a safe SVG document.
A call to the actual helper with an in-memory fixture retained a JavaScript URL,
`foreignObject` content and an unquoted event handler. No attack code was executed.
An uploaded icon can therefore introduce executable markup into pages displaying
the fact-card component. Merely requiring an editor to upload it does not make it
trusted code.

Use an SVG-aware sanitizer and a controlled rendering path, with media/file access
and cache metadata. The installed `svg_image` formatter already uses
`enshrined\svgSanitize\Sanitizer`; assess reuse before introducing another helper.
Also restrict file resolution to the intended media source and public directory.

### 2. P1: Work body rendering bypasses unpublished Paragraph access checks

`web/themes/custom/icon/icon.theme:444-489`, especially line 461.

The field preprocessor re-reads every referenced Paragraph and constructs fresh
component render arrays instead of rendering the formatter's access-checked
children. It never checks Paragraph view access. In a local Drupal probe, an
unsaved unpublished quote returned FALSE for anonymous view access but was still
included in the generated groups. A hidden paragraph in a Work body can therefore
be exposed by this alternate rendering path.

Preserve the entity-reference-revisions formatter output while grouping the
layout, or explicitly carry each entity's access result and cacheability into
the replacement render arrays. Do not duplicate entity rendering in a field
preprocessor merely to remove wrappers.

### 3. P1: Raw media extraction bypasses media and field access

`web/themes/custom/icon/icon.theme:175-195,398-434`
and `web/modules/custom/icon_site/icon_site.module:70-102`.

The helpers read referenced entities and file URLs directly without checking
media view access or the source field's access. A local probe confirmed that an
unpublished image denied to anonymous users still produced image props through
both helpers. This affects article imagery, Work galleries, films and hero media.

Use media view modes/formatters where possible. If a component requires scalar
props, build them through a service that returns both the values and explicit
access/cache metadata. A public file URL is not equivalent to permission to show
the media entity in this context.

### 4. P1: The editable footer was replaced with static content

`web/themes/custom/icon/templates/layout/page.html.twig:19`
and `templates/includes/site-footer.html.twig:15-135` within the same theme.

The old page template passed `page.footer_top`, `footer_first` through
`footer_fourth`, and `footer_bottom` to the existing footer component. The new
template includes a different footer containing literal office details, CTA,
social links, acknowledgement and video paths. It never renders those regions.
The block placements and their editable content still exist in the local site.
A Twig render with a sentinel in a footer region confirmed that it is discarded.

Restore the existing block/region rendering and adapt its markup to the new
design. Keeping region declarations in `icon.info.yml` does not render them.
The import integration carried this regression across and needs correction.

Subscribe is a separate unfinished feature: both the previous fallback and the
imported footer contain an input and `type="button"`, with no submission form or
backend integration. The imported header Subscribe only reveals the input.

### 5. P1: Sample-content reruns delete Paragraph entities and their history

`scripts/news-sample-content.php:59-60`
and `scripts/work-sample-content.php:76-77`.

These scripts call `delete()` on the existing Paragraph entities before replacing
the body. They match nodes by URL alias, so an editor's existing case study or
story can be overwritten. Deleting a Paragraph entity removes revisions that
older node revisions may still reference. Validation failures after deletion
can leave the previous body damaged. The scripts also force sample field values
and publishing flags.

Use disposable fixture installation, or an explicit migration/update process
that creates new revisions and retains referenced historical Paragraphs.
Keep these scripts away from ordinary deployment and existing editorial content.

### 6. P2: Media and block cacheability is incomplete

`web/themes/custom/icon/icon.theme:175-195,405-434,461-475`
and `web/modules/custom/icon_site/src/Plugin/Block/{HeroBlock,ClientsMarqueeBlock,FeaturedWorkBlock}.php`.

Raw media/Paragraph props lose the referenced entity, file, image-style and access
metadata that normal render arrays would carry. Updating an image, alt text or
style can leave cached article output stale. Hero and logo helpers carry some
entity tags, but not the complete dependencies. Hero, logos and featured-work
blocks also return a bare empty array when empty, losing the list cache tag needed
to invalidate that empty result when the first item is added.

Access-sensitive queries and checks must also preserve the resulting contexts
and max-age, not just selected entity tags. Use CacheableMetadata and cacheable
access results alongside view builders/formatters. Drupal's default renderer
contexts do not replace all entity-specific access dependencies.

### 7. P2: Hero slides have global node ownership instead of component ownership

`web/modules/custom/icon_site/icon_site.module:152-198`
and `src/Plugin/Block/HeroBlock.php:36-46,58-109` in the same module.

The hero queries every published `hero_slide` node. A block stores only their
order, not a selected collection. New published slides automatically appear in
every instance, subject to the eight-item cap. The login page also reads this
collection through the homepage Canvas tree. A slide's publishing operation can
therefore change several displays outside the page currently being edited.

The node model brings standalone routes, content-list entries, ownership and
publication semantics to an item whose apparent purpose is a hero's composition.
It also leads to custom modal forms, hidden settings and Canvas workarounds.
Drupal permits this model, but it is a poor fit for the stated requirements.

Recommended: a reusable Hero custom block containing ordered Hero slide Paragraphs,
each with a media reference, label and optional link. Render the block through SDCs
and place it in Canvas. For heroes that need no reuse, nested Canvas components
are another option. Reuse the intended Hero block explicitly for the login reel
if that requirement remains; avoid discovering it by parsing the front-page tree.

### 8. P2: Client logos now have two incompatible media models

`config/sync/media.type.client_logos.yml`, `config/sync/media.type.logo.yml`,
`web/modules/custom/icon_site/src/Plugin/Block/ClientsMarqueeBlock.php:100-117`.

The pre-existing `client_logos` type uses an Image source with a dedicated required
alt field and SVG/raster support. The imported `logo` type uses a generic File
source, accepts only SVG/PNG, and repurposes the media name as alt text. The new
marquee queries only `logo`, so existing `client_logos` media is excluded.

Consolidate on the existing media model unless a documented limitation requires
a replacement. Preserve asset names separately from accessibility text. Migrate
references deliberately if a replacement is chosen. A separate controlled SVG
icon type may be reasonable, but it still needs the sanitizer/access fixes above.

### 9. P2: Listing filters produce incomplete results and incorrect totals

`web/themes/custom/icon/assets/js/source/work-landing.js:23-31,63-104,108-124`,
`assets/js/source/news.js:32-40,81-127,131-147`,
`web/themes/custom/icon/icon.theme:258`,
`config/sync/views.view.work.yml:28-34` and `views.view.news.yml:29-35`.

Both Views paginate at 12 items. On an unfiltered page, JavaScript intercepts the
category links and filters only the rows already present. It cannot find matching
content on later pages, and rewrites the status to "Page 1 of 1". Reloading the
same category URL invokes the server filter and can show a different result set.
Work additionally supports multiple categories, but the row's `data-category`
contains only the first one; secondary-category matches are hidden by the JS.

Let Views own filtering and paging, using ordinary links or Views AJAX. Apply
animation to the returned results rather than reimplementing the content query
in a static-page script.

### 10. P2: Canvas panel actions save live content outside page publication

`web/modules/custom/icon_site/src/Controller/NewsActionController.php:22-43`,
`src/Controller/LogoActionController.php:23-51`,
`icon_site.routing.yml:15-28`, `js/panel-sortable.js:162-168,218-223,297-318`.

Pin/promote/remove and logo reordering save entities immediately. Discarding a
Canvas page draft cannot undo these changes. Requests use GET query parameters,
and the routes do not restrict methods to POST. The routes do have CSRF tokens
and permission checks; this is not a finding of entirely missing CSRF protection.
The controllers also do not request new revisions for these direct changes.

The frontend reloads the editor regardless of HTTP success, without checking for
failed writes or outstanding page autosaves. It also patches Drupal.Ajax globally
to work around dialog interactions with Canvas.

Prefer supported editor widgets and explicit content workflows. For genuinely
immediate actions, use POST with CSRF/access checks, validation, revisions where
required, and success/error handling. Define the publication boundary clearly.

### 11. P2: Generated behaviors are not suitable for dynamic component instances

`web/themes/custom/icon/scripts/theme-js.mjs:60-65`
and `assets/js/source/home-c.js:51` in the theme.

All generated behaviors are gated by `once(..., 'html', context)`. An AJAX fragment
does not contain the page's html element, and subsequent attachments are no-ops.
New or replaced components therefore do not get their listeners or observers.
Several sources also select only the first instance with document.querySelector.
There is no corresponding detach cleanup for listeners, observers and animation
loops when Canvas removes/replaces content.

Keep page-level setup separate from component behaviors. Initialize each component
root through once() within context, and provide cleanup where removal requires it.
The existing local element-scoped behaviors are useful references. Test initial
load, fragment insertion, replacement and two instances of the same SDC.

### 12. P2: The Content editor role cannot use the new content models

`config/sync/user.role.content_editor.yml:24-40`
and `web/modules/custom/icon_site/icon_site.routing.yml:1-28`.

Both the exported role and the active local role lack create permissions for
News, Work and Hero slide, and lack the broad media permission demanded by the
new list routes. An administrator demonstration does not prove the intended
editorial workflow works.

Define and test the editor permission matrix. Prefer scoped permissions for
managing the relevant lists over granting `administer media` to every editor.
The new media list save handler should also check each entity's update access
rather than relying solely on its route permission.

### 13. P2: Existing error-page overrides no longer render their content

`web/themes/custom/icon/templates/layout/page.html.twig:15-18`,
`page--403.html.twig:3` and `page--404.html.twig:3` in the same directory.

The old page template exposed a Twig `content` block. Both error templates extend
it and override that block. The imported parent removed the block. Rendering the
404 template with the active Twig service confirmed that its custom heading is
missing and the parent's content is rendered instead.

Restore the extension point and verify both error routes after updating the shell.

### 14. P2: Panel controls lack a complete keyboard interaction

`web/modules/custom/icon_site/src/Plugin/Block/HeroBlock.php:28-29,62-68`
and `js/panel-sortable.js:77-101,198-209,268-275`.

Reorder handles are aria-hidden spans and only pointer events start dragging.
The backing fields are hidden and removed from tab order. The custom listbox
provides no arrow-key option navigation or active-descendant state; Enter selects
the first search result. Editors cannot perform the same ordering/selection
workflow with a keyboard.

Use accessible supported widgets or implement explicit move controls, keyboard
selection, focus management and announcements. Also restore a visible-on-focus
skip link: the imported page shell uses `sr-only` without a focus-visible variant.

### 15. P2: Dependency and API contracts need cleanup

`web/modules/custom/icon_site/icon_site.info.yml:7-9`,
`src/Theme/PanelDialogThemeNegotiator.php:34-35`,
`web/themes/custom/icon/icon.theme:164,385`.

The custom module declares only Node and Views while providing media forms and
Canvas-specific integration. Its compatibility/install contract should express
the actual prerequisites. The theme calls module functions and assumes the new
fields/Views exist, so document/enforce the relationship or handle missing setup.

The theme negotiator switches to the admin theme on any request with `?panel=1`,
without restricting it to the intended forms/routes or checking whether that
theme is appropriate for the current user. Limit its scope and account for cache
contexts when request state affects presentation.

Replace the two deprecated `views_embed_view()` calls with View render elements.
Inject services into classes; procedural hooks may still use Drupal's service
locator where appropriate. Do not interpret the lint output as a requirement to
replace every procedural hook or every Paragraph type.

## Field and Content Model Recommendations

These are recommendations for this site, not universal Drupal prohibitions.

| Current choice | Problem or tradeoff | Recommended direction |
| --- | --- | --- |
| Hero slide node | Independent global content for a hero-owned item | Hero slide Paragraphs inside a reusable Hero block; SDC rendering in Canvas |
| News and Work nodes | Appropriate independently addressable editorial content | Keep these content types |
| News/Work categories as `list_string` | Adding/renaming categories requires configuration changes; little room for category metadata | Taxonomy references if editors own categories; fixed lists remain valid if developer-controlled by design |
| Client as a string | Repeated names and no relationship to client branding/content | Reference a Client entity or vocabulary if clients are reused; keep a string only if deliberately just a byline |
| Quote as `string(255)` | Arbitrary short limit for editorial quotations | `string_long` textarea for plain text, or restricted `text_long` if formatting is required |
| Remote video as an external Link | Editors must supply player embed URLs; bypasses existing media/oEmbed workflow | Reference the existing `remote_video` media type and render an approved view mode |
| Page/ink/ground colors as strings | No form-level color validation; page regex even accepts invalid five/seven-digit hex values | Controlled theme/contrast presets or a validated color field/widget |
| Gallery inset as a string | Accepts arbitrary CSS and extra declarations inside the style attribute | Spacing preset, or constrained numeric value with a fixed unit |
| Gallery `ground` and scroller `ground` | Passed directly into inline CSS; HTML escaping does not validate CSS | Validate values against a bounded token/color set before rendering |
| Logo as File media with name used for alt | Duplicates existing Client logos and conflates asset label with alt text | Reuse/consolidate the existing Image-source media model |
| Figure Paragraph with image only | No place for context-specific caption, attribution or credit | Add these only where editorial requirements call for them; keep reusable media metadata separate |
| Work video | No caption-track/transcript fields in this model or `<track>` output | Define an accessible film model, reusing core media/formatters where practical |
| Stats value as a string | Can legitimately contain display values such as "4.5m" or "1 in 3" | Keep string for display-only figures; use numeric value plus units only if calculation/animation needs it |
| Work title and Project name | Two similar labels with different uses in pickers and public output | Clarify labels/help and fallback rules before removing either |
| News/Work Paragraph bodies | Valid Drupal composition approach, but template comments incorrectly describe a Canvas component-tree field | Decide and document Canvas for page composition and Paragraphs for article bodies, or explicitly plan a different model |

Reusable paragraph names such as `media_figure` and `remote_video` would describe
their domain more clearly than `news_article_*` when they also appear in Work.
This is naming cleanup, not a priority over access, rendering and migration fixes.
Field type changes on populated storage need data migrations, not just edited YAML.

## Additional Follow-up

- Header navigation/services remain hardcoded despite configured menus. Move them
  to Drupal menu rendering with access, active trail, translation and URL handling.
  This was also incomplete before the import, unlike the editable-footer regression.
- The account page template handles every `/user/*` screen with one compact auth
  layout; verify profile/edit/reset workflows rather than testing login alone.
- `status-messages.html.twig:16` mutates the same Attribute object on each loop,
  accumulating type classes. JS-created Drupal.Message items do not automatically
  gain the custom `data-toast` markup expected by the toast behavior.
- Several public text strings and JS result announcements bypass translation;
  hardcoded root URLs also assume no language/base-path prefix.
- Use responsive image/media formatters for article/card media. Current templates
  largely emit one src URL. Review video payloads, playback controls, reduced
  motion, captions and CDN/font failure behavior as a separate browser QA pass.
- Keep generated files distinct from authored behavior sources and remove unused
  prototype files after the rendering decisions are settled. Lint autofixes should
  operate on source and regenerate output, not create divergent generated code.

## Evidence and Limits

The local site bootstrapped successfully on Drupal 11.4.5. `icon_site` and
Paragraphs are enabled, and Hero slide, News and Work types are active. Existing
footer block placements remain present. The homepage HTTP response contains the
static imported footer. This is the state observed during this review, after the
earlier import-only task.

Executed probes, with no saved test entities or existing content changes:

- Unpublished Paragraph: anonymous view access FALSE, replacement groups count 1.
- Unpublished Media: anonymous view access FALSE, both raw image helpers returned props.
- SVG helper: dangerous URI, foreignObject and unquoted handler survived its filtering.
- Active Twig: custom 404 heading absent, parent content present, footer-region sentinel absent.
- Active Content editor role: create News/Work/Hero slide permissions all FALSE.

The import's existing check results remain relevant: theme build and syntax
validation passed; PHPCS reported 95 errors/12 warnings, PHPStan 13 errors,
ESLint 3,406 errors/51 warnings, and Stylelint 871 errors. They were recorded during
the import and were not all rerun for this read-only review. Many lint entries
are formatting issues and generated/source duplicates, not distinct runtime bugs.

The review covers custom modules, theme PHP, SDC/Twig contracts, imported behavior
patterns, content/field/display/Views/role configuration and sample scripts.
It is not an audit of Drupal core or every contributed dependency. No full
browser interaction, cross-user cache warm-up or destructive sample-script test
was run; the report distinguishes direct probes from code-path findings.

## Drupal References

- [Render-array cacheability](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays)
  explains preserving entity and access dependencies as content is rendered.
- [Access checks and cacheability](https://www.drupal.org/docs/8/api/cache-api/access-checkers-cacheability)
  describes cacheable access results.
- [JavaScript API](https://www.drupal.org/docs/drupal-apis/javascript-api/javascript-api-overview)
  documents behavior attachment on initial and AJAX loads and scoped once().
- [Taxonomy](https://www.drupal.org/docs/user_guide/en/structure-taxonomy.html)
  documents editor-managed content classification.
- [Core oEmbed support](https://www.drupal.org/node/2966029)
  documents the built-in media approach for YouTube/Vimeo.
- [Secure Drupal code](https://www.drupal.org/docs/administering-a-drupal-site/security-in-drupal/writing-secure-code-for-drupal)
  describes safe output and routing protections.
- [Route structure](https://www.drupal.org/docs/drupal-apis/routing-system/structure-of-routes)
  documents route methods and access requirements.
- [SDC props and slots](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/what-are-props-and-slots-in-drupal-sdc-theming)
  describes the presentation contract; it does not prescribe node types for components.
