# CKEditor / WYSIWYG Output

Every Drupal site ends up with a rich-text body field. Editors fill it with an unpredictable mix of `<h2>`, `<h3>`, paragraphs, lists, blockquotes, tables, figures, and links — often all on the same page.

This doc covers how prose is styled in this project without letting CKEditor output leak across the rest of the design system.

---

## The rule

**All prose styling lives inside the `.content-page` wrapper.** The wrapper is defined in `src/base/prose.css`. Render any CKEditor / body field inside it and it styles correctly with no extra CSS per component:

```twig
<div class="content-page">
  {{ content.body }}
</div>
```

`.content-page` is for body-copy pages and article bodies — not the homepage. The base `src/base/typography.css` rules apply at the element level too, but the prose wrapper owns the contextual treatment in the places where editors are producing arbitrary HTML.

---

## Why a wrapper, not global element styles only

The base layer (`src/base/typography.css`) gives bare elements their defaults: `h1`–`h4` get `text-wrap: balance`, `p`/`li`/`dd` get `text-wrap: pretty`, and `h1` gets its font-size, weight, and line-height. That covers unstyled HTML correctly. But editors produce *contextual* prose with vertical rhythm, list indentation, link decoration, table borders, and so on — applying all of that at the element level would clash with every component that uses `<h2>` or `<ul>` (cards, heroes, nav).

So the system splits cleanly:

- **Bare element defaults** live in `src/base/typography.css` (heading text-wrap, the `h1` size/weight, body text-wrap).
- **Prose rhythm and contextual treatment** live in `src/base/prose.css` scoped under `.content-page`.

This gives editors a safe sandbox while keeping component styles predictable.

---

## What the wrapper styles

The wrapper has opinionated styles for the elements CKEditor can emit. From `src/base/prose.css`, every selector scoped under `.content-page`:

- `h1`–`h6` — fluid `clamp()` sizes with descending weight and `margin-top` / `margin-bottom` rhythm; `text-wrap: balance` on `h1`, `pretty` below; colour `--icon-black`. `h2` is uppercase and heavy (weight 800).
- `.intro-text` — an oversized lead paragraph for the top of an article.
- `p` — fluid size, `line-height: 1.75`, `margin-bottom: 2rem`, colour `--icon-grey`. A `:has(+ h*)` rule adds extra space before a following heading.
- `ul`, `ol`, `li` — `disc` / `decimal` markers, `1.5rem` indent, `0.5rem` between items, colour `--icon-grey`.
- `blockquote` — centred, max-width 720px; the quote `<p>` is set in `--font-serif` (Miller Text); attribution is a `<footer>` in `--icon-grey`.
- `a` — animated underline-grow: colour `--icon-blue` with an `--icon-blue` border-bottom, hovering to `--icon-black` as the `::after` underline scales in.
- `strong`, `em` — semantic emphasis; `strong` recolours to `--icon-black`.
- `code`, `pre` — monospace on `--secondary` background with `0.25rem` corners.
- `table`, `th`, `td` — `--border` bottom borders, `0.75rem` padding, `--secondary` header background, left-aligned bold headers.
- `img` — `2rem` vertical margin, `--radius-icon` corners.
- `hr` — `--border` rule with `3rem` vertical margin.

Colours and structural values reference brand tokens (`--icon-black` / `--icon-grey` / `--icon-blue`, `--secondary`, `--border`, `--font-serif`, `--radius-icon`); the design's `clamp()` sizing values are kept verbatim from the v0 reference.

---

## CKEditor configuration should match

Whatever elements the wrapper styles, the CKEditor toolbar should allow — no more. Otherwise editors will insert elements that don't have a style, or produce inline markup (colours, font sizes) that breaks the design system.

- Strip inline styles via the text format filter (`filter_html` or equivalent).
- Disable the "Font size" and "Font colour" plugins.
- Allow heading levels the wrapper styles (typically `h2`–`h4`, not `h1` — the page owns `h1`).
- Allow `<table>` only if the wrapper styles tables (it does).

---

## Accessibility

- Heading levels inside prose start at `h2` (the page owns `h1`).
- Use `scope` on table headers when tables are data-bearing.
- Don't rely on colour alone for link styling — links carry a permanent border-bottom as well as the colour change on hover.
- Blockquotes use `<blockquote>` with attribution in a `<footer>`, not just italic paragraphs.
- Reduced-motion: link transitions are removed inside `@media (prefers-reduced-motion: reduce)` (the block is already in `prose.css`).

---

## Drupal port

When this lands in the Drupal SDC theme:

- The wrapper is rendered by the body-field Twig template (or a `paragraph--text` SDC component, depending on the content model).
- `src/base/prose.css` is loaded as part of the global stylesheet — no per-component prose styles needed.
- Editor toolbars are configured per text format in Drupal admin; mirror the toolbar to the wrapper's supported elements.

---

## Checklist

- [ ] `.content-page` wraps all CKEditor body field output
- [ ] Wrapper uses design tokens for colours/borders/radius — no stray raw colours
- [ ] Every element CKEditor can emit has a style
- [ ] CKEditor toolbar matches what the wrapper supports
- [ ] Heading levels start at `h2` inside prose
- [ ] Table headers use `scope`
- [ ] Links are distinguishable without colour alone
