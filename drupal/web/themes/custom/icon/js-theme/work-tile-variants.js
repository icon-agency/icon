/* work-tile-variants.js — THEME-AUTHORED: hands a Work card's film its src,
 * and only when it will actually be seen.
 *
 * Two gates, both required (work-card.twig, [data-tile-film]):
 *   VARIANT — a card that carries both a square and a wide tile
 *     ([data-tile-variants]) shows the one its frame calls for through CSS
 *     (--tile-square / --tile-wide). The hidden one must not download.
 *   VIEWPORT — a film off screen must not download either. Until Sep 2026 a
 *     single-tile card wrote its src in the markup with preload="auto", so a
 *     /work listing fetched every campaign film at once, tens of megabytes,
 *     before the visitor had scrolled (found in review). Now every film waits
 *     in data-src and an IntersectionObserver decides, the way the footer's
 *     films already worked (js/site-footer.js).
 *
 * Re-checked on attach, when the breakpoints cross (below 768 every frame is
 * square), and when the landing's filter hides or shows items (the frames are
 * nth-child rules, so a hidden neighbour changes a card's shape).
 * Reduced motion: the film is a still — src, first frame, no play.
 * Lives in js-theme/ because js/ is generated from the repo-root behaviours. */
((Drupal, once) => {
  const NEAR = "200px";
  const still = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function settle(figure) {
    const near = figure.dataset.tileNear === "1";
    figure.querySelectorAll("video[data-src]").forEach((video) => {
      const showing = getComputedStyle(video).display !== "none";
      if (showing && near) {
        if (!video.getAttribute("src")) video.setAttribute("src", video.dataset.src);
        if (!still) {
          const p = video.play();
          if (p && p.catch) p.catch(() => {});
        }
      }
      else if (video.getAttribute("src")) {
        video.pause();
        // Dropping the src frees the decoded frames and stops the download;
        // load() is what makes the element let go of the old resource.
        video.removeAttribute("src");
        video.load();
      }
    });
  }

  Drupal.behaviors.iconWorkTileVariants = {
    attach(context) {
      const figures = once("work-tile-variants", "[data-tile-film]", context);

      if (figures.length) {
        if ("IntersectionObserver" in window) {
          const io = new IntersectionObserver((entries) => {
            entries.forEach((e) => {
              e.target.dataset.tileNear = e.isIntersecting ? "1" : "0";
              settle(e.target);
            });
          }, { rootMargin: `${NEAR} 0px` });
          figures.forEach((f) => io.observe(f));
        }
        else {
          // No observer: every film is "near", which is the old behaviour.
          figures.forEach((f) => { f.dataset.tileNear = "1"; settle(f); });
        }
      }

      if (!once("work-tile-variants-global", "html").length) return;
      const settleAll = () => document.querySelectorAll("[data-tile-film]").forEach(settle);
      let timer = 0;
      const later = () => { clearTimeout(timer); timer = setTimeout(settleAll, 120); };
      ["(min-width: 768px)", "(min-width: 900px)"].forEach((q) => window.matchMedia(q).addEventListener("change", later));
      // the landing's filter toggles `hidden` on items; lazy-loaded pages append
      new MutationObserver(later).observe(document.body, { attributes: true, attributeFilter: ["hidden"], subtree: true, childList: true });
    },
  };
})(Drupal, once);
