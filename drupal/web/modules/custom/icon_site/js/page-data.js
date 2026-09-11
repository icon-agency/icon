/* page-data.js — the Page data panel's colour fields: a swatch of the hex
   value in each (css/page-data.css lays the pair out side by side; nothing
   is moved — Canvas reads a moved field as an edit). Runs on the form as
   Canvas re-renders it. */
(function (Drupal, once) {
  "use strict";

  function dress(input) {
    var item = input.closest(".form-item");
    if (!item || item.querySelector(".icon-colour__swatch")) return;
    item.classList.add("icon-colour");
    var swatch = document.createElement("span");
    swatch.className = "icon-colour__swatch";
    (input.parentElement || item).appendChild(swatch);
    var paint = function () {
      var v = input.value.trim();
      swatch.style.background = /^#[0-9a-f]{3,8}$/i.test(v) ? v : "transparent";
    };
    input.addEventListener("input", paint);
    paint();
  }

  Drupal.behaviors.iconPageDataColours = {
    attach: function (context) {
      once("icon-page-data-colours", 'form[data-form-id="page_data_form"]', context).forEach(function (form) {
        form.querySelectorAll("#edit-field-work-bg-wrapper input[type='text'], #edit-field-work-ink-wrapper input[type='text']").forEach(dress);
      });
    }
  };
})(Drupal, once);
