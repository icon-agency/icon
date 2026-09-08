/* work-tile-variants.js — THEME-AUTHORED: a Work card that carries both a
 * square and a wide tile (work-card.twig, [data-tile-variants]) shows the one
 * its frame calls for through CSS (--tile-square / --tile-wide). Images cost
 * nothing hidden — they are lazy — but a hidden film would still download
 * and play, so every variant film starts with its src in data-src, and this
 * hands the src to whichever variant is showing: on attach, when the
 * breakpoints cross (below 768 every frame is square), and when the landing's
 * filter hides or shows items (the frames are nth-child rules, so a hidden
 * neighbour changes a card's shape). Lives in js-theme/ because js/ is
 * generated from the repo-root behaviours. */
((Drupal, once) => {
  function settle(figure) {
    figure.querySelectorAll("video[data-src]").forEach((video) => {
      const showing = getComputedStyle(video).display !== "none";
      if (showing && !video.getAttribute("src")) {
        video.setAttribute("src", video.dataset.src);
        const p = video.play();
        if (p && p.catch) p.catch(() => {});
      }
      else if (!showing && video.getAttribute("src")) {
        video.pause();
        video.removeAttribute("src");
        video.load();
      }
    });
  }

  Drupal.behaviors.iconWorkTileVariants = {
    attach(context) {
      const figures = once("work-tile-variants", "[data-tile-variants]", context);
      if (!figures.length) return;
      figures.forEach(settle);

      if (!once("work-tile-variants-global", "html").length) return;
      const settleAll = () => document.querySelectorAll("[data-tile-variants]").forEach(settle);
      let timer = 0;
      const later = () => { clearTimeout(timer); timer = setTimeout(settleAll, 120); };
      ["(min-width: 768px)", "(min-width: 900px)"].forEach((q) => window.matchMedia(q).addEventListener("change", later));
      // the landing's filter toggles `hidden` on items; lazy-loaded pages append
      new MutationObserver(later).observe(document.body, { attributes: true, attributeFilter: ["hidden"], subtree: true, childList: true });
    },
  };
})(Drupal, once);
