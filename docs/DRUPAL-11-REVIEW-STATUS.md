# Drupal 11 review — where things stand

Plain summary of what was done about the Drupal 11 review, 8 September 2026.
Detail and verification are in DRUPAL-11-REMEDIATION.md.

## Fixed — the critical items (P1)

- Uploaded SVG icons are now properly sanitised before they are shown on the site.
- Unpublished images, films and logos no longer appear anywhere on the site.
- Hidden paragraphs in a Work case study no longer show on the article.
- Empty homepage blocks now refresh correctly the moment the first item is added.
- The content import scripts refuse to run on production, and only run locally when asked for by name. They no longer delete existing content before the new content has saved.

## Fixed — the important items (P2)

- The homepage panel's pin, add, remove and reorder actions are locked to proper POST requests and every change is recorded as a revision.
- The Content editor role can now create and edit News, Work, Hero slides and Offices, manage media, edit pages and the footer, without needing admin rights.
- The list pages (Client logos, Icons, Offices) use a scoped permission instead of the broad "administer media".
- The two deprecated Drupal functions are replaced, so the site is ready for Drupal 13.
- The module declares everything it depends on.
- The admin-theme switch only applies to the panel's own dialog forms.
- The page template has the hook that custom 403 and 404 pages need.
- The panels work from the keyboard: arrow keys reorder rows, the project picker works with arrows and Enter, and screen readers hear each change.
- A "Skip to content" link now shows when a keyboard user tabs to it.
- The homepage hero now shows only the slides chosen for it. Slides can be taken off and kept under "Available" for later, and a new slide goes straight onto the reel. The login page's reel is a named setting instead of a lookup.

## Already resolved before the review was read

- Category filters on News and Work now filter on the server and both listings lazy-load, so results are always complete.
- The footer is managed in the CMS (offices, words, social links).

## Not applicable here

- Four findings describe the other codebase the design system was imported into: the old footer blocks, its custom error pages, its second client-logo media type. They only matter if the two sites are merged.

## Still open

- Deeper cache metadata for media helpers: a hardening item, not a correctness bug today.
- Front-end behaviours in the Canvas live preview: components added inside the editor preview do not get their scripts until the page reloads. Planned as its own piece of work.
