/* listing-lazy.js — THEME-AUTHORED (not generated): the /news and /work
 * listings load their next page of rows as the reader nears the end,
 * instead of the pager. The View still pages server-side (12 a page) —
 * that is the no-JS and search-engine path, and it is what this fetches:
 * the pager's "next" link, from which the new rows are lifted into the
 * list. Each arrival gets the entrance the first page had (the fade-up,
 * the media reveal, the drawn hairline — js/reveal.js observed only the
 * first page) and the card tilt (js/news.js and js/work-landing.js ran
 * once). The pager is hidden and a status line ("12 of 282 stories",
 * "12 of 40 projects" — the noun is the pager's own) with a loading mark
 * takes its place.
 *
 * The category chips navigate — a server-side filter is the only honest
 * one over a paged archive — so the in-place filters (js/news.js,
 * js/work-filter.js, js/work-landing.js) are stepped around by a
 * capture-phase listener that lets the chip's own href run. Lives in
 * js-theme/ because js/ is generated from the repo-root behaviours. */
((Drupal, once) => {
  Drupal.behaviors.iconListingLazy = {
    attach(context) {
      once("listing-lazy", ".news-list, .work-landing__list", context).forEach((list) => {
        // the row's class from the list's: news-list → news-list__item,
        // work-landing__list → work-landing__item
        var itemSelector = "." + list.classList[0].replace(/__list$/, "") + "__item";
        var section = list.closest("section");
        var pager = section && section.querySelector(".pager");
        var next = pager && pager.querySelector("a.pager__next");
        var status = pager && pager.querySelector(".pager__status");
        var counted = status ? status.textContent.match(/\((\d+)\s+([^)]+)\)/) : null;
        var total = counted ? parseInt(counted[1], 10) : NaN;
        var noun = counted ? counted[2].trim() : Drupal.t("items");
        if (!section || !pager || !("IntersectionObserver" in window)) return;
        var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

        // Chips navigate: step around js/news.js's in-place filter, which
        // only ever sees the stories loaded so far.
        document.addEventListener("click", function (e) {
          var chip = e.target.closest && e.target.closest("[data-filter]");
          if (!chip || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
          e.stopImmediatePropagation();
          e.stopPropagation();
        }, true);

        section.classList.add("is-lazy");
        var foot = document.createElement("div");
        foot.className = "listing-lazy";
        foot.innerHTML =
          '<p class="listing-lazy__status" aria-live="polite"></p>' +
          '<p class="listing-lazy__more" aria-hidden="true"><span class="listing-lazy__dot"></span><span class="listing-lazy__dot"></span><span class="listing-lazy__dot"></span></p>';
        pager.insertAdjacentElement("afterend", foot);
        var statusEl = foot.querySelector(".listing-lazy__status");
        var moreEl = foot.querySelector(".listing-lazy__more");
        var nextHref = next ? next.getAttribute("href") : null;
        var loading = false;

        function count() { return list.querySelectorAll(itemSelector).length; }
        function say() {
          var n = count();
          if (nextHref) {
            statusEl.textContent = isNaN(total)
              ? n + " " + noun
              : Drupal.t("@shown of @total @noun", { "@shown": n, "@total": total, "@noun": noun });
          }
          else {
            statusEl.textContent = Drupal.t("That is all @count @noun.", { "@count": n, "@noun": noun });
            moreEl.hidden = true;
          }
        }

        function reveal(li, i) {
          // the listing's own entrance: the news row fades up as a whole
          // (staggered in sixes like its first page); the work row's caption
          // and figure carry their own hooks. js/reveal.js observed only the
          // first page, so every hook here is flipped by hand.
          if (list.classList.contains("news-list")) {
            li.setAttribute("data-animate", "");
            li.style.setProperty("--animate-delay", reduce ? "0ms" : ((i % 6) * 60) + "ms");
          }
          requestAnimationFrame(function () {
            requestAnimationFrame(function () {
              if (li.hasAttribute("data-animate")) li.classList.add("is-visible");
              li.querySelectorAll("[data-animate]").forEach(function (el) { el.classList.add("is-visible"); });
              if (li.hasAttribute("data-rule")) li.classList.add("is-drawn");
              li.querySelectorAll("[data-rule]").forEach(function (el) { el.classList.add("is-drawn"); });
              li.querySelectorAll("[data-reveal-img]").forEach(function (fig) { fig.classList.add("is-revealed"); });
            });
          });
        }

        function tilt(card) {
          // js/news.js section 2 and js/work-landing.js section 2 (the same maths), for the cards that arrive after they ran
          if (reduce || !window.matchMedia("(hover: hover)").matches) return;
          var raf = 0, mx = 0, my = 0;
          var write = function () { raf = 0; card.style.setProperty("--mx", mx.toFixed(3)); card.style.setProperty("--my", my.toFixed(3)); };
          card.addEventListener("pointermove", function (e) {
            var r = card.getBoundingClientRect();
            if (!r.width || !r.height) return;
            mx = ((e.clientX - r.left) / r.width) * 2 - 1;
            my = ((e.clientY - r.top) / r.height) * 2 - 1;
            if (!raf) raf = requestAnimationFrame(write);
          }, { passive: true });
          card.addEventListener("pointerleave", function () { if (raf) { cancelAnimationFrame(raf); raf = 0; } mx = my = 0; write(); });
        }

        function load() {
          if (loading || !nextHref) return;
          loading = true;
          foot.classList.add("is-loading");
          fetch(nextHref, { credentials: "same-origin", headers: { "X-Requested-With": "listing-lazy" } })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
            .then(function (html) {
              var doc = new DOMParser().parseFromString(html, "text/html");
              var items = Array.prototype.slice.call(doc.querySelectorAll("." + list.classList[0] + " " + itemSelector));
              var base = count();
              items.forEach(function (li, i) {
                li.removeAttribute("data-theme-handover");
                li.classList.remove("is-visible");
                var node = document.importNode(li, true);
                list.appendChild(node);
                reveal(node, base + i);
                var card = node.querySelector(".news-card, .work__item");
                if (card) tilt(card);
                Drupal.attachBehaviors(node);
              });
              var n = doc.querySelector("a.pager__next");
              nextHref = n ? n.getAttribute("href") : null;
              say();
              // more to come and the foot still in reach (a short page, a
              // fast scroll): keep going without waiting for a new crossing
              if (nextHref) near();
            })
            .catch(function () {
              // leave the pager reachable: the reader can still click Next
              section.classList.remove("is-lazy");
              foot.hidden = true;
              nextHref = null;
              io.disconnect();
            })
            .then(function () { loading = false; foot.classList.remove("is-loading"); });
        }

        // The observer fires on a crossing; a jump that lands PAST the foot
        // (a long wheel flick, a keyboard End) never crosses it, so a
        // scroll listener also asks the plain question: is the foot within
        // reach of the bottom of the viewport?
        function near() {
          if (foot.getBoundingClientRect().top < window.innerHeight * 1.6) load();
        }
        var io = new IntersectionObserver(function (entries) {
          if (entries.some(function (e) { return e.isIntersecting; })) load();
        }, { rootMargin: "0px 0px 60% 0px" });
        io.observe(foot);
        var ticking = false;
        window.addEventListener("scroll", function () {
          if (ticking || !nextHref) return;
          ticking = true;
          requestAnimationFrame(function () { ticking = false; near(); });
        }, { passive: true });
        say();
      });
    },
  };
})(Drupal, once);
