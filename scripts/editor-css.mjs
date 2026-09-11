/* editor-css.mjs — re-scopes the built theme editor stylesheet from the
 * page's prose wrapper (.content-page) to CKEditor's (.ck-content), so the
 * same rules dress the editing area and the Styles menu's previews. Runs
 * after the tailwind build of src/editor.css (package.json build:theme). */
import { readFileSync, writeFileSync } from "node:fs";
const file = new URL("../drupal/web/themes/custom/icon/css/editor.css", import.meta.url);
const css = readFileSync(file, "utf8");
const out = css.replaceAll(".content-page", ".ck-content");
writeFileSync(file, out);
console.log(`editor-css: ${(css.match(/\.content-page/g) || []).length} prose rules re-scoped to .ck-content`);
