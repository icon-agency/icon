/* news-lazy.js — THEME-AUTHORED (not generated): the /news listing loads
 * the next page of stories as the reader nears the end, instead of the
 * pager. The View still pages server-side (12 a page) — that is the no-JS
 * and search-engine path, and it is what this fetches: the pager's "next"
 * link, from which the new .news-list__item rows are lifted into the list.
 * Each arrival fades up (the listing's own [data-animate] stagger, applied
 * here since js/reveal.js observed only the first page) and gets the
 * behaviours the first page had (js/news.js runs once per page, so the
 * card tilt is re-applied here). The pager is hidden and a status line
 * ("12 of 282 stories") with a loading mark takes its place.
 *
 * The category chips navigate — a server-side filter is the only honest
 * one over an archive this size — so js/news.js's in-place filter is
 * stepped around (a capture-phase listener lets the chip's own href run).
 * Lives in js-theme/ because js/ is generated from the repo-root
 * behaviours. */
((Drupal, once) => {
  Drupal.behaviors.iconNewsLazy = {
    attach(context) {
      once("news-lazy", ".news-list", context).forEach((list) => {
        var section = list.closest("section");
        var pager = section && section.querySelector(".pager");
        var next = pager && pager.querySelector("a.pager__next");
        var status = pager && pager.querySelector(".pager__status");
        var total = status ? parseInt((status.textContent.match(/\((\d+)/) || [])[1], 10) : NaN;
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
        foot.className = "news-lazy";
        foot.innerHTML =
          '<p class="news-lazy__status" aria-live="polite"></p>' +
          '<p class="news-lazy__more" aria-hidden="true"><span class="news-lazy__dot"></span><span class="news-lazy__dot"></span><span class="news-lazy__dot"></span></p>';
        pager.insertAdjacentElement("afterend", foot);
        var statusEl = foot.querySelector(".news-lazy__status");
        var moreEl = foot.querySelector(".news-lazy__more");
        var nextHref = next ? next.getAttribute("href") : null;
        var loading = false;

        function count() { return list.querySelectorAll(".news-list__item").length; }
        function say() {
          var n = count();
          if (nextHref) {
            statusEl.textContent = isNaN(total)
              ? Drupal.formatPlural(n, "1 story", "@count stories")
              : Drupal.t("@shown of @total stories", { "@shown": n, "@total": total });
          }
          else {
            statusEl.textContent = Drupal.formatPlural(n, "That is the one story.", "That is all @count stories.");
            moreEl.hidden = true;
          }
        }

        function reveal(li, i) {
          // the listing's own entrance, staggered in sixes like the first page
          li.setAttribute("data-animate", "");
          li.style.setProperty("--animate-delay", reduce ? "0ms" : ((i % 6) * 60) + "ms");
          requestAnimationFrame(function () {
            requestAnimationFrame(function () {
              li.classList.add("is-visible");
              var fig = li.querySelector("[data-reveal-img]");
              if (fig) fig.classList.add("is-revealed");
            });
          });
        }

        function tilt(card) {
          // js/news.js section 2, for the cards that arrive after it ran
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
          fetch(nextHref, { credentials: "same-origin", headers: { "X-Requested-With": "news-lazy" } })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
            .then(function (html) {
              var doc = new DOMParser().parseFromString(html, "text/html");
              var items = Array.prototype.slice.call(doc.querySelectorAll(".news-list .news-list__item"));
              var base = count();
              items.forEach(function (li, i) {
                li.removeAttribute("data-theme-handover");
                li.classList.remove("is-visible");
                var node = document.importNode(li, true);
                list.appendChild(node);
                reveal(node, base + i);
                var card = node.querySelector(".news-card");
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
