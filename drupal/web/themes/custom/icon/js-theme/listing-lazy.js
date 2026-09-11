/* listing-lazy.js — THEME-AUTHORED (not generated): the /news and /work
 * listings load their next page of rows as the reader nears the end,
 * instead of the pager. The View still pages server-side (12 a page) —
 * that is the no-JS and search-engine path, and it is what this fetches:
 * the pager's "next" link, from which the new rows are lifted into the
 * list. A list may cap how many pages arrive on their own (data-lazy-auto:
 * /news sets 1, so the first 20 stories come unasked and a "Load more"
 * button brings each 10 after that — user call, Sep 2026); without the
 * attribute every page arrives as the reader nears the end. Each arrival gets the entrance the first page had (the fade-up,
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
        // how many pages arrive unasked; then the button takes over
        var autoPages = parseInt(list.getAttribute("data-lazy-auto"), 10);
        if (isNaN(autoPages)) autoPages = Infinity;
        var autoLoaded = 0;
        var button = document.createElement("button");
        button.type = "button";
        button.className = "listing-lazy__button";
        button.textContent = Drupal.t("Load more");
        button.hidden = true;
        foot.appendChild(button);
        button.addEventListener("click", function () { load(true); });

        function count() { return list.querySelectorAll(itemSelector).length; }
        function say() {
          var n = count();
          if (nextHref) {
            statusEl.textContent = isNaN(total)
              ? n + " " + noun
              : Drupal.t("@shown of @total @noun", { "@shown": n, "@total": total, "@noun": noun });
          }
          else {
            // A complete list says nothing (user call, Sep 2026: the "That
            // is all 10 stories" pill went): the pill is for a list still
            // loading, and the sr-only status line announces the end.
            statusEl.textContent = "";
            statusEl.hidden = true;
            moreEl.hidden = true;
            button.hidden = true;
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

        function load(asked) {
          if (loading || !nextHref) return;
          if (!asked && autoLoaded >= autoPages) {
            // the unasked pages are spent: the reader asks for the rest
            button.hidden = false;
            return;
          }
          loading = true;
          // BUSY, not hidden. Hiding the button while it holds focus drops
          // the keyboard reader to <body> and loses their place (found in
          // review, Sep 2026); a disabled button keeps focus, and the live
          // region already announces the new count.
          var hadFocus = document.activeElement === button;
          if (asked) {
            button.disabled = true;
            button.setAttribute("aria-busy", "true");
          }
          else {
            button.hidden = true;
          }
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
              if (!asked) autoLoaded += 1;
              say();
              // more to come: either keep going while the foot is in reach
              // (a short page, a fast scroll) or, the unasked pages spent,
              // offer the button
              if (nextHref) {
                if (autoLoaded >= autoPages) button.hidden = false;
                else near();
              }
              else if (asked) {
                // Nothing left to ask for: the button goes, so send the
                // reader who pressed it to the first story that arrived
                // rather than to the top of the document.
                var first = list.children[base] && list.children[base].querySelector("a");
                if (hadFocus && first) {
                  first.setAttribute("tabindex", "-1");
                  first.focus({ preventScroll: true });
                }
              }
            })
            .catch(function () {
              // leave the pager reachable: the reader can still click Next
              section.classList.remove("is-lazy");
              foot.hidden = true;
              nextHref = null;
              io.disconnect();
            })
            .then(function () {
              loading = false;
              button.disabled = false;
              button.removeAttribute("aria-busy");
              foot.classList.remove("is-loading");
            });
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
