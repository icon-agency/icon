# Backend foundation — what to settle before content moves in

For Frank to validate, 8 September 2026. The site is a fresh build on this
repo. Nothing has launched and no editor has authored in it: the content was
imported by scripts that re-run in half an hour. So the foundation can still
change cheaply — until people start creating content in it, when every model
change becomes a migration.

Two lists. The first is what to add so the platform matches the engineering
in `icon-agency/iconagency-drupal-ai`; the second is the content-model
decisions that get expensive later. Both are ordered; effort is a rough
working-day estimate.

## 1. Platform: what to add, in order

| # | Add | Why | Effort | Notes |
| --- | --- | --- | --- | --- |
| 1 ✅ 8 Sep | **The quality gate** — PHPCS (Drupal + DrupalPractice), PHPStan with drupal rules, Rector deprecation checks, ESLint and Stylelint on the custom code; one `npm run check`; a GitHub workflow that runs it on every push | The review counted the lint errors; a gate stops them returning, and it is the check the AI tooling and any other developer will expect | 1 day, most of it cleaning the existing code to pass | Copy the workflow and the `scripts/check-*` runners from Frank's repo; the design system already has `npm run verify` for CSS — the two sit side by side |
| 2 ✅ 8 Sep | **Config split** — a `local` split for development-only modules (devel, stage_file_proxy) and settings, a `production` split for the rest | The module is in Frank's build but unused; this is what keeps dev modules and debug settings out of production without hand edits | Half a day | Splits live in `config/`, activated by the settings file per environment |
| 3 ✅ 8 Sep | **Settings layering** — a tracked `settings.platform.php` (hosting), a tracked `settings.ddev.php` (local), a gitignored `settings.local.php` last; the load order documented in the file | Ours is one file plus a local; explicit layers are easier to reason about and match the other project | Half a day | The hosting file is the one reverted earlier; it comes back with the platform config |
| 4 ✅ 8 Sep | **Redis** as the cache backend on the host (contrib `redis`, the settings lines, the service in the platform config) | Cheap, and it matters once editors and agents are busy | Half a day | DDEV's redis add-on gives local parity; the hosting lines come with the platform config |
| 5 ✅ 8 Sep | **The AI modules** — `ai`, `ai_provider_openai`, `ai_image_alt_text`, plus the forty-line custom module that hides the AI button on SVG uploads | The entry point for every AI use Frank listed: validations, improvements, agents, GEO/SEO, content | Half a day | The provider key is an environment variable on each environment, never config |
| 6 | **Remote video media** (core oEmbed) replacing the pasted embed link on news and work films | Editors paste a YouTube or Vimeo link and get a preview; agents can read it; it is the model Frank's build already has | 1 day including migrating the 25 existing embeds | See model decision 3 |
| 7 | **Metatag + schema.org** on nodes and Canvas pages | The SEO / GEO layer the AI tooling writes into | 1 day | Additive; decide where the fields live before content |
| 8 | **Content moderation** (core workflows) with a draft → published workflow on News, Work and pages | AI-drafted or AI-improved content needs a state a human approves; easiest before editors arrive | Half a day, plus the editor role's transitions | See model decision 7 |
| 9 | **Solr** — keep the service definition and config in the repo, enable the service when a search or AI-retrieval need exists | Nothing on the site searches today; a Solr service costs disk on every environment | Half a day when needed | Frank's `search_api_solr` config and Solr core config are reusable |
| 10 ✗ dropped | **Local tooling** — a Lando file beside the DDEV one | Frank confirmed DDEV is fine (8 Sep), so there is one local tool; versions (PHP, MariaDB, Redis) still change in hosting config and `.ddev/config.yaml` together | — | — |
| 12 ✅ 8 Sep | **Pathauto + Redirect** — automatic URLs from a pattern per type, and a 301 for every alias that changes | URLs are the first SEO/GEO surface; a slug from the project and client names says what the page is, and old links keep answering | Half a day | Work: `/work/{project}-{client}`; News keeps the mirrored old-site URLs (no pattern yet); Pathauto's stop words are off so names stay whole |
| 11 ▶ 8 Sep | **Hosting push / pull** — the code goes to icon-agency/iconagency-drupal-ai (Frank's call: the repo the Upsun project x77dkhrg45siw is wired to), `.platform.app.yaml` in `drupal/` (the app root; the design system is the repo root, the theme's built CSS/JS are committed so the host runs Composer only), `.platform/` routes and services (MariaDB 11.8, Redis 8; no Solr yet), `settings.platform.php` from the Platform.sh template via `platformsh/config-reader`; the database and files go up with the Upsun CLI (`upsun sql`, `upsun mount:upload`) | The first environment, for Frank to review the backend | — | Frank's 15 commits are tagged `frank-build-2026-09-08` on his repo and backed up locally with his database and files |
| 11 | **Hosting push / pull scripts** — the Platform.sh push and pull of database and files as project commands | Frank's `lando pull` / `lando push`; the DDEV equivalent is a pair of scripts around the CLI we already use | Half a day | After the project exists |

Not carried over: Frank's theme and components (the design system here is far ahead), media bulk upload and the svg_image formatter (no need yet; our sanitiser is the same library), the footer as block content (ours is an Office type, a settings form and a menu — either is sound).

## 2. Content model: decisions that get expensive later

Each with what we have, the recommendation, what it costs to change now versus after editors are in, and whether it matters for the AI tooling.

| # | Decision | Now | Recommendation | Change now | Change later | AI |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | News and Work categories | Fixed lists in config (`list_string`) | **Taxonomy vocabularies**, one each, if editors will ever add or rename a category | Half a day + re-run the news mirror | Field-type migration on populated storage | Agents classify and create terms; they cannot with a fixed list |
| 2 | Client on a Work item | A text field — and, since 8 Sep, the second half of the item's URL and page title | A **Client** vocabulary if clients will ever be listed, filtered or joined to their logo; keep text only if it is deliberately a byline | Half a day | Migration + relinking | An agent can fill or check a reference; not a string |
| 3 | Remote films in articles | A pasted embed URL | **Remote video media** (platform item 6) | 1 day incl. migrating 25 embeds | Same migration, plus editor retraining | Readable by agents; previewable |
| 4 | Hero slides | Content items with an explicit selection per hero and a named login hero (settled 8 Sep) | Keep; documented in the handoff and the remediation record | — | — | Neutral |
| 5 | Paragraph names | `news_article_figure` / `news_article_video` also used by Work | Rename to `media_figure` / `remote_video` **before content**, or accept permanently | Half a day + re-run imports | A paragraph-type migration | Neutral |
| 6 | Colour and spacing on work paragraphs | Free text that reaches inline CSS | **Presets** (a select of named grounds / insets) or a validated colour field | Half a day | Same, plus fixing existing values | An agent can set a preset safely; not free CSS |
| 7 | Editorial workflow | Publish on save | **Content moderation** (platform item 8) | Half a day | Same, but existing content needs a state | Required for AI drafts |
| 8 | Media model | Image, Video, Logo (file), Icon (file) with a Type field | Keep; add Remote video. If the sites' media ever needs to match Frank's, his logo type is Image-based — decide then | — | — | Alt-text generation works on Image media as is |
| 9 | Page composition | Canvas for pages, Paragraphs for article bodies | Keep; document as the rule | — | — | Neutral |
| 10 | Multilingual | None | Not needed; nothing to do now, and the model does not block it | — | — | — |

## 3. What is scripted versus hand-made

Everything an import can rebuild is safe to change under. Everything hand-made in the CMS is what a model change must carry.

- **Scripted** (`drupal/scripts/`): the sample Work items and their bodies, the news archive from iconagency.com.au (270 stories, images, films), the client logos, the fact icons, the offices, the footer words and social links, the homepage's Canvas tree with its blocks. All gated by `_guard.php`.
- **Hand-made so far**: nothing that would be lost — the homepage layout is seeded, then adjusted in the editor; those adjustments are in the database backup, not the scripts, so a rebuild would need them redone or the backup restored.

## 4. Suggested order

1. Frank marks the decisions in §2.
2. Platform items 1–5 land (about three days), with the model changes he asked for.
3. Content is re-imported once, against the settled model.
4. The review environment goes up on the hosting project, with items 6–8 following there.
5. Solr (9), Lando (10) and push/pull (11) as the need and the team decide.
