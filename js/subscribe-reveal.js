/* subscribe-reveal.js — the Subscribe bar's hint state (the pill that is the
 * header search face's twin; components/subscribe-bar.css). Three states
 * live in the one field: at rest its hint reads "Subscribe"; while the
 * pointer is over the bar or the field has focus the hint is "Your email";
 * typed, the address shows in the field's own face. The hints are two
 * overlaid spans in the markup (so Drupal's |t owns the words) and the CSS
 * crossfades them — this file keeps .is-hinting true while the field is
 * focused or the bar is under the pointer, and .is-open while it is focused
 * or holds a value (the masthead's compact pill grows on it). A click
 * anywhere on the pill focuses the field. Esc empties the field and lets
 * it go.
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
    var field = bar.querySelector(".subscribe-bar__field");
    var hint = bar.querySelector(".subscribe-bar__hint");

    // Where the hint rests: centred in the closed field, as a pixel offset
    // from its left edge (subscribe-bar.css --hint-rest-x). Measured closed,
    // and again when the layout changes; a percentage would ride the field's
    // width while it animates.
    function place() {
      if (!field || !hint || bar.classList.contains("is-open")) return;
      var x = Math.max(0, (field.clientWidth - hint.offsetWidth) / 2);
      bar.style.setProperty("--hint-rest-x", Math.round(x) + "px");
    }

    function sync() {
      var focused = document.activeElement === input;
      bar.classList.toggle("is-hinting", over || focused);
      // open while the field is in use or holds something — the masthead's
      // compact pill grows on this (page-header.css)
      bar.classList.toggle("is-open", focused || input.value !== "");
    }

    bar.addEventListener("pointerenter", function () { over = true; sync(); });
    bar.addEventListener("pointerleave", function () { over = false; sync(); });
    input.addEventListener("focus", sync);
    input.addEventListener("blur", sync);
    input.addEventListener("input", sync);
    // a click anywhere on the pill is a click into the field
    bar.addEventListener("click", function (e) {
      if (e.target.closest("button")) return;
      input.focus();
      sync(); // activeElement is already the field; the focus event may lag (or never fire while the window is unfocused)
    });
    input.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        input.value = "";
        input.blur();
      }
    });

    place();
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(place);
    window.addEventListener("resize", place, { passive: true });
    bar.addEventListener("transitionend", function (e) {
      if (e.propertyName === "width" && !bar.classList.contains("is-open")) place();
    });
  });
})();
