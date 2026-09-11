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

  /* The multi-value lists (Deliverables): core's tabledrag moves rows live
     under the pointer with no mark of where they will land, and its
     per-table instance dies on Canvas's re-renders. The panel sorter
     (js/panel-sortable.js — a ghost of the row, a blue line at the gap,
     keyboard moves) takes them instead: the table gets its classes, core's
     handle is replaced by the sorter's, and the drop writes the weight
     selects. Nothing is moved in the form, so Canvas reads no edit. */
  function sortable(table) {
    var wrapper = table.closest(".js-form-wrapper, .field--widget-string-textfield") || table.parentElement;
    wrapper.classList.add("icon-panel__card");
    table.classList.add("icon-panel__list", "icon-panel__list--weights");
    // core's tabledrag stays off this table: its once() key is claimed
    var claimed = (table.getAttribute("data-once") || "").split(" ").filter(Boolean);
    if (claimed.indexOf("tabledrag") === -1) claimed.push("tabledrag");
    table.setAttribute("data-once", claimed.join(" "));
    table.querySelectorAll("tr.draggable").forEach(function (row) {
      var cell = row.querySelector("td.field-multiple-drag");
      if (!cell || cell.querySelector(".icon-panel__handle")) return;
      cell.querySelectorAll("a.tabledrag-handle").forEach(function (a) { a.remove(); });
      var handle = document.createElement("button");
      handle.type = "button";
      handle.className = "icon-panel__handle";
      handle.setAttribute("aria-label", Drupal.t("Drag to reorder"));
      handle.innerHTML = '<svg viewBox="0 0 10 16" width="10" height="16" aria-hidden="true"><circle cx="2.5" cy="2.5" r="1.5"/><circle cx="7.5" cy="2.5" r="1.5"/><circle cx="2.5" cy="8" r="1.5"/><circle cx="7.5" cy="8" r="1.5"/><circle cx="2.5" cy="13.5" r="1.5"/><circle cx="7.5" cy="13.5" r="1.5"/></svg>';
      cell.appendChild(handle);
    });
    // the weight column and core's "Show row weights" toggle stay hidden
    table.querySelectorAll(".delta-order, .tabledrag-hide").forEach(function (el) { el.classList.add("icon-panel__weight"); });
    var toggle = wrapper.querySelector(".tabledrag-toggle-weight-wrapper");
    if (toggle) toggle.hidden = true;
  }

  Drupal.behaviors.iconPageDataColours = {
    attach: function (context) {
      once("icon-page-data-colours", '[data-form-id="page_data_form"]', context).forEach(function (form) {
        form.querySelectorAll("#edit-field-work-bg-wrapper input[type='text'], #edit-field-work-ink-wrapper input[type='text']").forEach(dress);
      });
      once("icon-page-data-sortable", '[data-form-id="page_data_form"] table.field-multiple-table', context).forEach(sortable);
    }
  };
})(Drupal, once);
