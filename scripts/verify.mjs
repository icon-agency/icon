#!/usr/bin/env node
/* verify.mjs — the gate behind `npm run verify` (design-system-plan.md §6).
 *
 * Four checks, all mechanical, all things the docs used to only DESCRIBE:
 *   1. build sync   — css/main.css is byte-identical to a fresh minified
 *                     build (GitHub Pages serves the committed file).
 *   2. raw colours  — no hex / rgb / hsl / oklch outside src/theme/colors.css
 *                     (comments and url() payloads ignored).
 *   3. media queries — every width in an @media prelude is a breakpoint-table
 *                     value (px or rem; max-width may sit 1px / 0.02px under
 *                     a step), unless a `verify-allow` comment sits within the
 *                     three lines above it and says why.
 *   4. inline styles — style="" in templates/ and index.html carries only
 *                     custom properties (JS-set values, the chameleon pair).
 *   5. theme sync   — the Drupal theme's css/main.css and generated js/ match
 *                     a fresh `npm run build:theme` (Drupal serves the
 *                     committed files; the js/ are wrapped copies of js/).
 *
 * Exit 1 on any failure. Run before marking work done (definition-of-done).
 */
import { execFileSync } from "node:child_process";
import { readFileSync, readdirSync, statSync, mkdtempSync, rmSync } from "node:fs";
import { join, relative } from "node:path";
import { tmpdir } from "node:os";
import { build as buildThemeJs } from "./theme-js.mjs";

const root = new URL("..", import.meta.url).pathname;
const failures = [];
const fail = (check, where, what) => failures.push(`${check}  ${where}  ${what}`);

const walk = (dir, ext) =>
  readdirSync(dir).flatMap((name) => {
    const p = join(dir, name);
    return statSync(p).isDirectory() ? walk(p, ext) : p.endsWith(ext) ? [p] : [];
  });
const rel = (p) => relative(root, p);
const stripComments = (css) => css.replace(/\/\*[\s\S]*?\*\//g, (m) => m.replace(/[^\n]/g, " "));

/* 1. build sync */
{
  const dir = mkdtempSync(join(tmpdir(), "icon-verify-"));
  const out = join(dir, "main.css");
  try {
    execFileSync("npm", ["exec", "--", "tailwindcss", "-i", join(root, "src/main.css"), "-o", out, "--minify"], { cwd: root, stdio: ["ignore", "ignore", "pipe"] });
    const fresh = readFileSync(out);
    const committed = readFileSync(join(root, "css/main.css"));
    if (!fresh.equals(committed)) fail("build-sync", "css/main.css", "differs from a fresh `npm run build` — rebuild and commit the output");
  } catch (e) {
    fail("build-sync", "tailwindcss", `build failed: ${String(e.stderr || e.message).trim()}`);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
}

/* 2. raw colours */
{
  const colourRe = /#[0-9a-f]{3,8}\b|\b(?:rgba?|hsla?|oklch|oklab|lab|lch|hwb)\(/gi;
  for (const file of walk(join(root, "src"), ".css")) {
    if (rel(file) === "src/theme/colors.css") continue;
    const lines = stripComments(readFileSync(file, "utf8")).replace(/url\([^)]*\)/g, "url()").split("\n");
    lines.forEach((line, i) => {
      const hit = line.match(colourRe);
      if (hit) fail("raw-colour", `${rel(file)}:${i + 1}`, `${hit[0]} — declare it in src/theme/colors.css and read the token`);
    });
  }
}

/* 3. media queries */
{
  const stepsPx = [640, 768, 900, 1024, 1280, 1536, 1920, 2560];
  const stepsRem = stepsPx.map((px) => px / 16);
  const near = (v, steps, isMax) => steps.some((s) => v === s || (isMax && (Math.abs(v - (s - 1)) < 1e-6 || Math.abs(v - (s - 0.02)) < 1e-6 || Math.abs(v - (s - 1 / 16)) < 1e-6 || Math.abs(v - (s - 0.02 / 16)) < 1e-6)));
  for (const file of walk(join(root, "src"), ".css")) {
    const raw = readFileSync(file, "utf8");
    const lines = raw.split("\n");
    lines.forEach((line, i) => {
      if (!/^\s*@media/.test(line)) return;
      const allowed = lines.slice(Math.max(0, i - 3), i).some((l) => l.includes("verify-allow"));
      if (allowed) return;
      for (const m of line.matchAll(/(min|max)-width\s*:\s*([\d.]+)(px|rem)/g)) {
        const v = parseFloat(m[2]);
        const ok = m[3] === "px" ? near(v, stepsPx, m[1] === "max") : near(v, stepsRem, m[1] === "max");
        if (!ok) fail("media-query", `${rel(file)}:${i + 1}`, `${m[0]} is not a breakpoint-table step (add it to the table or a verify-allow comment above with the reason)`);
      }
    });
  }
}

/* 4. inline styles */
{
  const files = [join(root, "index.html"), ...walk(join(root, "templates"), ".html")];
  for (const file of files) {
    const lines = readFileSync(file, "utf8").split("\n");
    lines.forEach((line, i) => {
      for (const m of line.matchAll(/\sstyle="([^"]*)"/g)) {
        const bad = m[1].split(";").map((d) => d.trim()).filter(Boolean).filter((d) => !d.startsWith("--"));
        if (bad.length) fail("inline-style", `${rel(file)}:${i + 1}`, `style="${m[1]}" — only custom properties may be set inline`);
      }
    });
  }
}

/* 5. theme sync */
{
  const theme = join(root, "drupal/web/themes/custom/icon");
  const dir = mkdtempSync(join(tmpdir(), "icon-verify-theme-"));
  const out = join(dir, "main.css");
  try {
    execFileSync("npm", ["exec", "--", "tailwindcss", "-i", join(theme, "src/main.css"), "-o", out, "--minify"], { cwd: root, stdio: ["ignore", "ignore", "pipe"] });
    if (!readFileSync(out).equals(readFileSync(join(theme, "css/main.css")))) fail("theme-sync", "drupal/web/themes/custom/icon/css/main.css", "differs from a fresh `npm run build:theme` — rebuild and commit the output");
  } catch (e) {
    fail("theme-sync", "tailwindcss (theme)", `build failed: ${String(e.stderr || e.message).trim()}`);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
  for (const [file, code] of Object.entries(buildThemeJs())) {
    let committed = null;
    try { committed = readFileSync(join(theme, "js", file), "utf8"); } catch {}
    if (committed !== code) fail("theme-sync", `drupal/web/themes/custom/icon/js/${file}`, "is not the generated wrap of js/" + file + " — run `npm run build:theme` (never edit the theme copy)");
  }
}

if (failures.length) {
  console.error(`verify: ${failures.length} problem${failures.length === 1 ? "" : "s"}\n`);
  for (const f of failures) console.error("  " + f);
  process.exit(1);
}
console.log("verify: build in sync · no raw colours outside colors.css · every media query on the table · no literal inline styles · theme css + js in sync");
