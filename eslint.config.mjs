// ESLint for the authored JavaScript: the design system's behaviours (js/),
// the theme-authored behaviours and the panel script (Drupal), and the build
// scripts. The theme's js/ is GENERATED from js/ by scripts/theme-js.mjs and
// is not linted twice.
import js from "@eslint/js";
import globals from "globals";

const drupalGlobals = { Drupal: "readonly", drupalSettings: "readonly", once: "readonly", jQuery: "readonly" };
const libraryGlobals = { gsap: "readonly", ScrollTrigger: "readonly", SplitText: "readonly", DrawSVGPlugin: "readonly" };

export default [
  { ignores: ["node_modules/**", "css/**", "drupal/vendor/**", "drupal/web/core/**", "drupal/web/modules/contrib/**", "drupal/web/themes/contrib/**", "drupal/web/themes/custom/icon/js/**", "drupal/web/sites/**", "experiments/**"] },
  js.configs.recommended,
  {
    files: ["js/**/*.js", "drupal/web/themes/custom/icon/js-theme/**/*.js", "drupal/web/modules/custom/**/*.js"],
    languageOptions: { ecmaVersion: 2022, sourceType: "script", globals: { ...globals.browser, ...drupalGlobals, ...libraryGlobals } },
    rules: {
      "no-unused-vars": ["error", { args: "none", caughtErrors: "none" }],
      "no-empty": ["error", { allowEmptyCatch: true }],
    },
  },
  {
    // prototype-only behaviours (not ported to Drupal) keep their feature switches
    files: ["js/hero.js", "js/hero-sphere.js", "js/tagline.js"],
    rules: { "no-unused-vars": "warn" },
  },
  {
    files: ["scripts/**/*.mjs", "server.js", "*.config.mjs"],
    languageOptions: { ecmaVersion: 2022, sourceType: "module", globals: { ...globals.node } },
    rules: { "no-empty": ["error", { allowEmptyCatch: true }] },
  },
];
