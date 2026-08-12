# Field Naming Convention

## The rule

**Prefix every Drupal field machine name with the BEM block it maps to** — which is also the name of the SDC component folder and the CSS file under `src/components/`.

```
field_card_title
field_card_excerpt
field_card_image
field_hero_eyebrow
field_hero_summary
field_hero_cta_text
field_navbar_brand
```

The field, the markup, the CSS file, the SDC folder, and the BEM block all share the same root name (`card`, `hero`, `navbar`). You can jump between them without thinking.

---

## Why

- A developer reading a Twig template can predict the CSS file (`field_card_*` → `src/components/card.css`).
- A developer reading the CSS can predict the field names (`.hero__eyebrow` → `field_hero_eyebrow`).
- A site-builder adding a new field to an existing paragraph type knows the naming instantly.
- Grepping is trivial: `field_card_` finds every field consumed by the card component.

Without the prefix, field names drift (`field_title`, `field_heading`, `field_name`) and you lose the ability to trace data from the database to the rendered pixel.

---

## Rules

1. **Use the BEM block, not the element.** `field_card_title`, not `field_card__title` or `field_cardtitle`. Drupal machine names can't contain `__`, and double underscores would clash with Drupal's own template-suggestion convention.
2. **Use the BEM block the editor thinks of, not the deepest nesting.** The card's title element is `.card__title`, but the editor thinks "the card's title". `field_card_title` is right. Avoid `field_card_body_title`.
3. **Keep field labels editor-friendly.** The *machine name* is BEM-prefixed; the *label* should read naturally ("Card title", "Hero eyebrow"). Editors never see machine names.
4. **Variants are a single field, not a naming suffix.** For a `card--resource` modifier, add `field_card_variant` as a `list_string` with allowed values (`default`, `resource`, `news`, `featured`, `external`), not a separate `field_card_resource_*`.
5. **Shared fields use the most generic block.** If both `card` and `tile` share an image field and render it identically, make it `field_card_image` (or generalise both to a shared paragraph).
6. **Don't prefix with the paragraph type twice.** If your paragraph is `paragraph: card_resource`, use `field_card_title`, not `field_card_resource_title`. The paragraph type is already the namespace.

---

## Editor-facing labels

Machine names stay BEM-prefixed; labels stay human:

| Machine name | Label | Help text |
|---|---|---|
| `field_card_title` | Card title | The card's main heading. Appears as a link. |
| `field_card_excerpt` | Summary | One or two sentences describing the resource. |
| `field_card_image` | Cover image | Recommended 2:3, min 800×1200. |
| `field_card_variant` | Card style | Select the visual style: Resource / News / Featured. |
| `field_hero_eyebrow` | Eyebrow label | Short category label above the hero heading. |

Editors care about labels. Developers care about machine names. Serve both.

---

## Anchoring to component names

Because the BEM block, the SDC folder, and the CSS file all share a name, the field prefix is effectively the **component name**:

```
src/components/card.css
                ↕
   components/card/card.twig        (in the Drupal theme)
                ↕
        field_card_*                (in the paragraph type)
```

If a component name changes, all three move together — and so do the field names.

---

## Exceptions

- **Core Drupal fields** (`title`, `body`, `field_image` on a node) already exist and are used across contrib modules. Don't rename them. Wrap them in a paragraph if you need stricter naming.
- **Media fields** often inherit from media type defaults (`field_media_image`, `field_media_oembed_video`). Leave those alone.
- **Entity reference fields** that point at a shared entity (taxonomy, user) should use the relationship name, not a BEM block (`field_topic`, `field_author`).
