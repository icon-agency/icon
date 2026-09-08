/* subscribe-reveal.js — the Subscribe bar's hint state (the pill that is the
 * header search face's twin; components/subscribe-bar.css). Three states
 * live in the one field: at rest its hint reads "Subscribe"; while the
 * pointer is over the bar or the field has focus the hint is "Your email";
 * typed, the address shows in the field's own face. The hints are two
 * overlaid spans in the markup (so Drupal's |t owns the words) and the CSS
 * crossfades them — this file only keeps .is-hinting true while the field
 * is focused or the bar is under the pointer. Esc empties the field and
 * lets it go.
 *
 * Submitting stays the shared inert [data-subscribe] hook — it ships with
 * the newsletter slice, same as the footer.
 *
 * Drupal: Drupal.behaviors.iconSubscribeReveal via `icon/subscribe-reveal`. */
(function () {
  "use strict";

  var bars = document.querySelectorAll("[data-subscribe-bar]");
  Array.prototype.forEach.call(bars, function (bar) {
    var input = bar.querySelector("[data-subscribe-input]");
    if (!input) return;
    var over = false;

    function sync() {
      bar.classList.toggle("is-hinting", over || document.activeElement === input);
    }

    bar.addEventListener("pointerenter", function () { over = true; sync(); });
    bar.addEventListener("pointerleave", function () { over = false; sync(); });
    input.addEventListener("focus", sync);
    input.addEventListener("blur", sync);
    input.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        input.value = "";
        input.blur();
      }
    });
  });
})();
