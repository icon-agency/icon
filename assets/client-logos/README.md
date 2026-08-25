# Client logos — the upload rule

One rule, so a new client is one upload with **zero per-logo tuning**, here
and in Drupal: **the component treats every file identically, so the file
must carry nothing the component already owns.**

The CSS contract (`src/utilities/home-c.css`, `.client__logo`) absorbs:

- **Colour** — every logo is forced to mono ink with `filter: brightness(0)`
  (inverted to light under the dark and blue themes). Brand colours, black,
  white: all flatten the same. Never recolour a file to "match".
- **Size and aspect ratio** — logos are bounded by `max-width`/`max-height`
  inside the card and centred. Wide wordmarks, square roundels and portrait
  marks all self-place. Never resize artwork to match its neighbours.

What the FILE must get right (the component cannot fix these):

1. **SVG, with a `viewBox` cropped tight to the artwork.** No baked-in
   padding — whitespace inside the viewBox renders as a smaller-looking
   logo, and the card already provides the breathing room.
2. **True transparency for counterforms.** Detail inside a solid shape must
   be cut out (mask / compound path), not painted white on top — the mono
   filter flattens paint, and white-on-top becomes a silhouette blob.
   (`san-churro.svg` arrived like that and was reworked; see the comment in
   the file.)
3. **Paths only.** Text outlined; no embedded bitmaps (`<image>`); no
   external references. The file must be self-contained.
4. **Kebab-case filename** (`beyond-blue.svg`), and the client's proper name
   as the `alt` text in the markup.

Current set complies (audited 25 Aug 2026). Pending SVGs — Equip Super,
DFAT, Home Affairs, Melbourne Marathon, Brandon Capital, DITRDCA, Greater
Western Water, Alcohol and Drug Foundation, Cancer Institute NSW, Cancer
Council Victoria — join the marquee as one `<li>` each in
`templates/homeC.html` when their files land here.

## Drupal

The marquee maps to SDC `clients` fed by a `client` media type: one SVG file
field + one name field (used as `alt`); the template loop emits the card and
the aria-hidden duplicate track. An editor uploads a file meeting the four
points above and types the name — nothing else. Validate point 1 cheaply at
upload (reject an SVG with no `viewBox`); points 2–3 are export discipline,
which is why they live in this README rather than in code.
