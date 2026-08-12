/* work-filter.js — category filtering for the /work listing. Progressive
 * enhancement over the no-JS baseline: the chips are real <a> links (which a
 * Drupal Views exposed filter honours server-side); here we intercept them and
 * filter the grid in place, keeping the URL shareable via history.pushState.
 *
 * On filter we RE-FLOW the column-span rhythm (8,4,6,6,4,8,6,6) across only the
 * visible cards so the 12-col masonry stays tight, and force the revealed state
 * on shown cards (their entrance swipe belongs to initial scroll-in, not to a
 * re-filter). The initial unfiltered "all" view is left untouched so js/work.js
 * still drives the first-load swipe.
 *
 * No motion of its own → nothing to guard for reduced motion.
 * Drupal: Drupal.behaviors.iconWorkFilter. */
(function () {
  "use strict";

  var grid = document.querySelector("[data-work-grid]");
  if (!grid) return;

  var chips = Array.prototype.slice.call(document.querySelectorAll("[data-filter]"));
  var cols = Array.prototype.slice.call(grid.querySelectorAll(".work-grid__col"));
  if (!chips.length || !cols.length) return;

  var PATTERN = [8, 4, 6, 6, 4, 8, 6, 6];
  var SPANS = ["work-grid__col--4", "work-grid__col--6", "work-grid__col--8"];

  function setActiveChip(slug) {
    chips.forEach(function (chip) {
      var on = chip.getAttribute("data-filter") === slug;
      chip.classList.toggle("is-active", on);
      if (on) chip.setAttribute("aria-current", "true");
      else chip.removeAttribute("aria-current");
    });
  }

  function reflow(visible) {
    visible.forEach(function (col, i) {
      SPANS.forEach(function (c) { col.classList.remove(c); });
      col.classList.add("work-grid__col--" + PATTERN[i % PATTERN.length]);
    });
  }

  function apply(slug) {
    var visible = [];
    cols.forEach(function (col) {
      var show = slug === "all" || col.getAttribute("data-category") === slug;
      col.hidden = !show;
      if (show) {
        visible.push(col);
        // Filtering is a re-layout, not an entrance — show content immediately.
        var reveal = col.querySelector(".reveal");
        if (reveal) {
          reveal.classList.remove("is-revealing");
          reveal.classList.add("is-revealed");
        }
      }
    });
    reflow(visible);
    setActiveChip(slug);
  }

  chips.forEach(function (chip) {
    chip.addEventListener("click", function (e) {
      e.preventDefault();
      var slug = chip.getAttribute("data-filter");
      apply(slug);
      var url = slug === "all" ? "/work" : "/work?category=" + slug;
      try { window.history.pushState({ category: slug }, "", url); } catch (err) {}
    });
  });

  window.addEventListener("popstate", function () {
    var params = new URLSearchParams(window.location.search);
    apply(params.get("category") || "all");
  });

  // On load, honour an incoming ?category=… deep link. Leave the default "all"
  // view alone so js/work.js owns the first-load entrance swipe.
  var initial = new URLSearchParams(window.location.search).get("category");
  if (initial && initial !== "all") apply(initial);
})();
