// Stylelint for the authored CSS: the design system's src/ (Tailwind v4,
// CSS-first), the theme's own page layers, and the panel stylesheet. Built
// outputs (css/, the theme's css/) are not linted.
export default {
  extends: ["stylelint-config-standard"],
  ignoreFiles: ["css/**", "drupal/web/themes/custom/icon/css/**", "node_modules/**", "drupal/vendor/**", "drupal/web/core/**"],
  rules: {
    // Tailwind v4's at-rules and functions
    "at-rule-no-unknown": [true, { ignoreAtRules: ["import", "source", "theme", "utility", "variant", "custom-variant", "layer", "apply", "config", "plugin", "reference", "property"] }],
    "function-no-unknown": [true, { ignoreFunctions: ["theme", "--alpha", "--spacing"] }],
    "import-notation": null,
    // BEM: block__element--modifier, and the design system's own names
    "selector-class-pattern": null,
    "custom-property-pattern": null,
    "keyframes-name-pattern": null,
    // the design system writes hex and rgb() where the token file says so
    "color-function-notation": null,
    "alpha-value-notation": null,
    "color-hex-length": null,
    // prose comments and grouped declarations are deliberate
    "comment-empty-line-before": null,
    "declaration-empty-line-before": null,
    "rule-empty-line-before": null,
    "custom-property-empty-line-before": null,
    "at-rule-empty-line-before": null,
    "no-descending-specificity": null,
    "media-feature-range-notation": null,
    "value-keyword-case": null,
    "selector-not-notation": null,
    "shorthand-property-no-redundant-values": null,
    "declaration-block-no-redundant-longhand-properties": null,
    "property-no-vendor-prefix": null,
    "font-family-name-quotes": null,
    "number-max-precision": null,
    // the token file writes hsl() as the palette was authored; declarations are grouped on a line on purpose
    "lightness-notation": null,
    "hue-degree-notation": null,
    "color-function-alias-notation": null,
    "declaration-block-single-line-max-declarations": null,
    // Tailwind v4 layers: @import after @layer / @source is how the entry is written
    "no-invalid-position-at-import-rule": null,
    // sections repeat a selector to keep a concern's rules together
    "no-duplicate-selectors": null,
    // `clip` is the sr-only recipe every screen reader guide still uses
    "property-no-deprecated": [true, { ignoreProperties: ["clip"] }],
    // user-drag has no standard spelling yet; the prefixed one is the real one, the bare one is future-proofing
    "property-no-unknown": [true, { ignoreProperties: ["user-drag"] }],
  },
};
