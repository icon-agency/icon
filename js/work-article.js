/* work-article.js — page behaviour for the Work article (folio case study).
 *
 * One job: the scroll-velocity SKEW — the /work landing's card gesture
 * (user call, Sep 2026), via the SHARED engine (js/velocity-lean.js). One
 * --wa-skew on the article root per frame; the banner, the gallery figures
 * and the Next up cards consume it in work-article.css. Same 3deg cap as
 * the landing and the news rows, so every skew on the site moves alike.
 *
 * Reduced-motion guarding lives HERE, with the consumer — the engine never
 * decides whether motion exists (its own header's contract).
 *
 * Drupal: Drupal.behaviors.iconWorkArticle, depending on icon/velocity-lean.
 */
(function () {
  "use strict";

  var article = document.querySelector(".work-article");
  if (!article) return;
  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
  if (window.ICON && window.ICON.velocityLean) {
    window.ICON.velocityLean(article, article, "--wa-skew", 3);
  }
})();
