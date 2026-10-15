# Work folio audit — iconagency.com.au/work

**Date:** 16 October 2026
**Spreadsheet:** `WORK-FOLIO-AUDIT-2026-10-16.xlsx` (sheets: Folios, Redirects, Same client, Media and disk); the same rows as CSV.
**Source:** the old site's three listing pages and all 81 case-study pages, read on the day. Nothing on either site was changed.

## The numbers

| | |
|---|---|
| Old case studies | 81 |
| Keep as built here | 2 (iCanQuit, Nike Melbourne Marathon Festival) |
| Rebuild from the old page | 6 (ADF, Space Agency, Permanent Protection Visa, Democracy Cards, Fit for Every Run, Raise It) |
| New | 73 |
| Current nodes with no old-site page | 4 (Care Closer to Home, FOGO for Whittlesea, Running Wings, Is This Legit?) |
| Old card with layered cut-outs | 53 (two to five layers) |
| Old card with one image | 28 (need a square tile) |
| Old pages with film | 29 (34 films) |

Categories on the old site: Creative 36, Digital 22, Communications 19, Reputation 4. Mapped to ours by the old "Led by" discipline, with the old service line adding Behaviour change, Brand and Websites where it lists them.

## How the old page gives up its three lines

- **Client** is the end of the service line ("Website design and development for iCanQuit"). Fourteen pages leave it blank; the heading stands in and the row is flagged.
- **Project name** is the old h2, except where that h2 is a description ("Our work for NWMPHN", "Website and copywriting") or half a sentence that the h1 completes ("i Can Quit is more / than breaking a habit"). Twenty-nine needed a name proposed; each is flagged with the reasoning.
- **Feature statement** is the old card's line, which is usually the h1. Nine are labels ("Waste education campaign", "Placie Donovan", an all-caps line) and have a proposed line in the statement column, with the old page's own description in the next column as an alternative.

Every row was read; the flags column says what is uncertain. The flags fall into: client not stated (14), project name proposed (29), statement rewritten (9), single card image (28), thin page under 300 words (7), heavy films (4), duplicates (2).

## Issues to decide

- **Duplicates.** Airwallex has two folios; JANA has two (brand refresh and public relations). Merge or keep both.
- **Same client, several folios.** Yarra Trams 4, City of Whittlesea 3, Alcohol and Drug Foundation 3, North Western Melbourne PHN 4, Sustainability Victoria 2, NDIS 2. Fine as folios, but the client-logo and "more from" rails will cluster.
- **Four current nodes have no old-site page.** Care Closer to Home, FOGO (Whittlesea), Running Wings and Is This Legit? go with the placeholders unless you want them kept.
- **Old FOGO is Inner West Council**, not Whittlesea: a different project from the current FOGO node.
- **Permanent Protection Visa** names Home Affairs on the page but DFAT in the old path.
- Every client is now named (16 Oct): Schneider Electric and Equip Super are each the client of a "Reputation management" folio; Australia India Council's project is "Australia & India. A dynamic mix." Health Hub is credited to the Connecting Care EMR Program (Peter Mac, Melbourne Health and The Royal Women's, named in the body). Confirmed 16 Oct: Still Six Lives is Red Nose and Stillbirth Foundation Australia; Sprout Send is MyGuestlist; SIDRA Solutions is the client as named (the trading name of Akcelik & Associates).

## Media and disk

The old originals total 941 MB of images (880 files, average 1 MB, the largest 42 MB) and 773 MB of film. The host's files volume has 307 MB free. Two things follow:

1. The mirror must downscale on import: originals re-saved at the largest size a component shows (2400 px long edge for galleries, 1600 square for tiles), which should bring images to roughly a tenth. Films need re-encoding or external hosting; two FOGO films alone are 238 MB.
2. Even then the host needs its configured disk before the full set goes up. This is the same decision as the audit follow-up's item 3.

## The card

Per your note: the **square** card uses the old layered cut-outs where the old card has them (53), and the **wide** card uses the page's banner. The 28 single-image cards need a square tile cut from the one image or chosen by hand.

## 301 redirect plan

The Redirects sheet lists every old path with its destination. The rules:

- Old case study → its rebuilt folio, at the alias pathauto makes from project name and client (projected in the sheet; final after the build).
- The two kept folios → their current aliases.
- The ten deleted placeholders' current aliases → the rebuilt folio for the six that have one, `/work` for the four that do not, so any link already shared keeps working.
- News stories keep their paths (270 of 282); the 12 never imported go to `/news`.
- `/icon-creative`, `/pr-communications`, `/services` → their renamed pages; `/behaviour-change`, `/people-culture`, `/subscribe` → the nearest section, for review.

`scripts/redirects.php` and its CSV from 29 September already cover the news and top-level rows and the current work mapping; the work rows are regenerated after the build, and the 97 redirects it created locally are superseded then.

## Anything else

- **Old page structure is uniform**: service line, "Led by", h2 + h1, then Overview / Background / What we made / Why it mattered / Services, galleries, films, an embedded "related work" view. That maps onto the master's block order (masthead, prose, columns, scroller, video, stats, pull quote) without hand work, so one mirror script can build all 73.
- **Results and quotes are rare** on the old pages: no stat blocks or blockquotes were found, so the work-stats and testimonial components will mostly stay empty unless you add them.
- **Awards** are mentioned in text only (You Haven't Been Drinking Alone, Nike, Democracy Cards); nothing structured to carry over.
- **The old site's listing order is by date**, newest first (October 2026 to May 2024). The sheet keeps that order.

## Proposed sequence

1. You correct names, clients, statements and the decisions above in the sheet.
2. Mirror script built and run locally on a first batch of ten (newest first) for review in the editor.
3. The remaining 63, then the ten placeholders deleted and redirects regenerated.
4. Disk decision, then the host run.

## Build log

**16 October 2026 — the newest ten, locally.** Built by `drupal/scripts/work-mirror.php` from `drupal/scripts/data/work-folios.json`: Sextortion, Raise It (rebuilt node 14), World Expo 2025 Osaka, Permanent Protection Visa (15), ADF parent campaign (21), Democracy Cards (18), Food fight, Transforming lives, World Vet Day, Fit for Every Run (16). All ten validate; 222 MB of media after resizing. Not on the host. The ADF teaser film could not be found on the old site, so its card's base layer stands in as the banner. The remaining 63, the deletion of the four placeholders with no old page, and the host run wait on review.

**16 October 2026 — the four placeholders with no old page deleted, locally:** Care Closer to Home (19), FOGO for Whittlesea (22), Running Wings (23), Is This Legit? (24). Their aliases and the redirects that pointed at them now 301 to `/work`. Thirteen Work nodes remain: the two kept, the ten built, and Space Agency website (20), still the placeholder until its old page is mirrored with the rest.
