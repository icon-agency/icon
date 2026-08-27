/* share.js — the article Share rail, live. Two actions and no more (user
 * call): COPY LINK and EMAIL.
 *
 * Copy writes the page URL through the async clipboard API, falling back to
 * a select-and-execCommand textarea where the API is missing or refused
 * (http:// previews, older engines). Feedback is a beat-long .is-copied
 * class (the CSS swaps the link glyph for a check) plus an sr-only live
 * region — a sighted reader sees the check, a screen reader hears
 * "Link copied".
 *
 * Email is a plain mailto: link, functional without any JS; this behaviour
 * only enriches it with the article's real title and URL (the static
 * markup can't know its own address).
 *
 * Drupal: Drupal.behaviors.iconShare via `icon/share`, attached on the
 * article node view. */
(function () {
  "use strict";

  var root = document.querySelector("[data-share]");
  if (!root) return;

  var status = root.querySelector("[data-share-status]");

  var email = root.querySelector("[data-share-email]");
  if (email) {
    email.setAttribute(
      "href",
      "mailto:?subject=" + encodeURIComponent(document.title) +
        "&body=" + encodeURIComponent(window.location.href)
    );
  }

  var copy = root.querySelector("[data-share-copy]");
  if (copy) {
    var timer = 0;
    var done = function () {
      copy.classList.add("is-copied");
      if (status) status.textContent = "Link copied";
      clearTimeout(timer);
      timer = setTimeout(function () {
        copy.classList.remove("is-copied");
        if (status) status.textContent = "";
      }, 1800);
    };
    var fallback = function () {
      var ta = document.createElement("textarea");
      ta.value = window.location.href;
      ta.setAttribute("readonly", "");
      // offscreen, not display:none — a hidden control can't be selected
      ta.style.position = "fixed";
      ta.style.opacity = "0";
      document.body.appendChild(ta);
      ta.select();
      try {
        if (document.execCommand("copy")) done();
      } catch (err) {}
      document.body.removeChild(ta);
    };
    copy.addEventListener("click", function () {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(window.location.href).then(done, fallback);
      } else {
        fallback();
      }
    });
  }
})();
