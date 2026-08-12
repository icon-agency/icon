# The Three-Piece Mapping Pattern (SDC)

Every reusable component follows the same three-piece pattern when it moves from this static prototype into the Drupal theme:

1. **Paragraph type** — owns the data shape (in the Drupal admin).
2. **Preprocess function** — owns the Drupal-specific field lookups (in `THEMENAME.theme`).
3. **Thin Twig template inside an SDC** — owns the markup (a near-copy of the static HTML).

The Twig template lives in `components/<name>/<name>.twig` alongside a `<name>.component.yml` schema. **CVA is not used** in this project — BEM classes are applied directly. Conditional classes are computed in preprocess.

Keeping the three pieces separated is the single biggest thing you can do to stop the Drupal theme from rotting.

---

## 1. Define the paragraph type

Create a paragraph (or block / node) with one field per data prop the component needs. **Prefix every field with the BEM block it maps to** — see `field-naming.md`.

```yaml
paragraph: card_resource
fields:
  field_card_image      (image)
  field_card_badge      (text)
  field_card_title      (text + link)
  field_card_excerpt    (text long)
  field_card_date       (date)
  field_card_variant    (list_string: default | featured | external)
```

Notes:
- One field = one prop the template consumes. If the markup doesn't use it, don't add it.
- Variants (e.g. `card--resource`, `card--featured`) are a single `list_string` field, not separate paragraph types.
- Keep field labels editor-friendly ("Card image") even though machine names are BEM-prefixed.

## 2. Preprocess: extract field values + compute modifier classes

In the theme's `THEMENAME.theme` file, map raw Drupal field arrays to simple scalar variables. **Compute the modifier class here**, not in Twig:

```php
function THEMENAME_preprocess_paragraph__card_resource(&$variables) {
  $p = $variables['paragraph'];

  $variables['image']   = $p->get('field_card_image')->entity?->getFileUri();
  $variables['badge']   = $p->get('field_card_badge')->value;
  $variables['title']   = $p->get('field_card_title')->value;
  $variables['url']     = $p->get('field_card_title')->uri;
  $variables['excerpt'] = $p->get('field_card_excerpt')->value;
  $variables['date']    = $p->get('field_card_date')->value;

  $variant = $p->get('field_card_variant')->value ?: 'default';
  $classes = ['card', 'card--resource'];
  if ($variant === 'featured') {
    $classes[] = 'card--featured';
  }
  if ($variant === 'external') {
    $classes[] = 'card--external';
  }
  $variables['modifier_class'] = implode(' ', $classes);
}
```

Notes:
- Always null-check entity references (`?->`).
- The `modifier_class` variable is a single space-joined string the Twig template drops into the root element's `class` attribute.
- Run `t()` on any static strings the component outputs (labels like "Published", "Read more", "Go to external resource").

## 3. Thin Twig template inside an SDC

Folder layout:

```
themes/custom/THEMENAME/components/card/
├── card.component.yml
├── card.twig
└── card.css  (or imported via global CSS — see below)
```

`card.component.yml` declares the props schema:

```yaml
$schema: https://git.drupalcode.org/project/drupal/-/raw/HEAD/core/assets/schemas/v1/metadata.schema.json
name: Card
group: Content
props:
  type: object
  properties:
    image:
      type: string
    badge:
      type: string
    title:
      type: string
    url:
      type: string
    excerpt:
      type: string
    date:
      type: string
    modifier_class:
      type: string
      default: 'card card--resource'
```

`card.twig` is the thin markup template:

```twig
{# card.twig #}
<article class="{{ modifier_class }}">
  {% if image %}
    <div class="card__media">
      <img class="card__image" src="{{ image }}" alt="">
    </div>
  {% endif %}

  <div class="card__body">
    {% if badge %}
      <span class="card__badge">{{ badge }}</span>
    {% endif %}

    <h2 class="card__title">
      <a class="card__title-link" href="{{ url }}">{{ title }}</a>
    </h2>

    {% if excerpt %}
      <p class="card__excerpt">{{ excerpt }}</p>
    {% endif %}

    {% if date %}
      <p class="card__date">{{ 'Published'|t }} {{ date|date('j F Y') }}</p>
    {% endif %}
  </div>
</article>
```

Rules:
- **Don't restructure the DOM.** The CSS depends on the nesting. If the design needs to change, change it in `src/components/card.css`, don't patch it in Twig.
- **No business logic in Twig.** If the template contains more than variable output, loops, and `if` checks on presence, the logic belongs in preprocess.
- **No CVA. No inline conditionals in `class="…"`.** Compute all classes in preprocess and emit them via `modifier_class`.
- **Diff against the static template.** The Twig template should read like `templates/<page>.html` with `{{ vars }}` in place of dummy content — nothing more.

---

## Component CSS

Component CSS comes from `src/components/<name>.css`. There are two integration patterns:

1. **Global stylesheet** (simplest): the entire `src/main.css` build output is loaded as the theme's global library. Every component's CSS is available everywhere. This is how the prototype works.
2. **Per-component CSS**: each SDC folder owns a `<name>.css` (or `<name>.tailwind.css`) that's `@import`ed into the theme's `src/main.css`. Same final output; finer-grained authoring.

Either way, the BEM class names in the Twig template must match the class names in the CSS.

---

## Why three pieces

- **Paragraph type** owns the data shape. Editors see it. Content model decisions live here.
- **Preprocess** owns the Drupal-specific lookups and class-list computation. All the `get('field_x')->value` noise (and all conditional class logic) is contained in one file.
- **Twig template** stays thin and readable. You can always compare it to the static reference template.

Merge any two of these and the seams blur: business logic leaks into templates, content-model decisions leak into PHP, and the next developer has to read all three files to change one thing.

---

## Checklist per component

- [ ] Paragraph type defined with BEM-prefixed fields (`field_<block>_<element>`)
- [ ] Preprocess function maps fields to clean vars
- [ ] Preprocess computes `modifier_class` for variant classes
- [ ] SDC folder created: `components/<name>/<name>.twig` + `<name>.component.yml`
- [ ] Twig template mirrors the static HTML with `{{ vars }}`
- [ ] No `.0.value`, class-building logic, or CVA in Twig
- [ ] No inline conditionals inside `class="…"` attributes
- [ ] Variant classes emitted via `modifier_class` from preprocess
- [ ] Static HTML reference (`templates/*.html`) is kept in sync if the component changes
- [ ] BEM names in Twig match `src/components/<name>.css`
