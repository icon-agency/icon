# components/ — the Single Directory Components

Drupal Canvas composes pages from the SDCs in this folder, one per BEM block,
same root name as the CSS file under the repo's `src/components/` and the
`field_<block>_*` field prefix (`docs/field-naming.md`).

Each folder holds `<name>.component.yml` + `<name>.twig` — the thin template
is a near-copy of the static markup in `templates/*.html` with `{{ props }}`
in place of dummy content (`docs/drupal-mapping-pattern.md`). Styles come from
the theme's global stylesheet (the design-system build), so no per-component
CSS lives here.

Canvas eligibility (`/admin/appearance/component/status` explains a rejection):
every prop and slot needs a `title`; a required prop needs `examples` (the
first is the default); variants are `enum` props; images use
`$ref: json-schema-definitions://canvas.module/image`; rich text is a string
with `contentMediaType: text/html`.
