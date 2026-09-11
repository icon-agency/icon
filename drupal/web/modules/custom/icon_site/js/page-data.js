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

  /* ---- The frame's edit targets → the Page data panel ----------------
     Canvas's overlay takes every pointer event over the preview frame
     (the iframe has pointer-events: none), so the frame cannot listen for
     itself: the editor page hit-tests the pointer into the frame's
     document, outlines the masthead part under it (a class the theme's
     CSS draws) and, on a click, shows that part's field — the Page data
     tab if another is showing, scrolled to, lit for a moment, focused. */
  var frameOf = function () {
    return document.querySelector("iframe[data-canvas-preview]");
  };
  var hit = function (e) {
    var frame = frameOf();
    if (!frame) return null;
    var box = frame.getBoundingClientRect();
    if (e.clientX < box.left || e.clientX > box.right || e.clientY < box.top || e.clientY > box.bottom) return null;
    var doc;
    try { doc = frame.contentDocument; } catch (err) { return null; }
    if (!doc || !doc.documentElement.hasAttribute("data-icon-editing")) return null;
    // the frame may be scaled to its zoom level: map viewport px to its own
    var scale = box.width / (frame.contentWindow.innerWidth || box.width);
    var el = doc.elementFromPoint((e.clientX - box.left) / scale, (e.clientY - box.top) / scale);
    return el && el.closest ? el.closest("[data-edit-field]") : null;
  };
  var hovered = null;
  var hover = function (target) {
    if (target === hovered) return;
    if (hovered) hovered.classList.remove("is-edit-hover");
    hovered = target;
    if (hovered) hovered.classList.add("is-edit-hover");
  };
  document.addEventListener("pointermove", function (e) { hover(hit(e)); }, true);
  document.addEventListener("pointerleave", function () { hover(null); }, true);

  var showField = function (name) {
    var tab = document.querySelector("[id$='-trigger-pageData']");
    if (tab && tab.getAttribute("data-state") !== "active") tab.click();
    var find = function () {
      return document.querySelector('[data-form-id="page_data_form"] #edit-' + name.replace(/_/g, "-") + "-wrapper");
    };
    var tries = 0;
    (function look() {
      var wrapper = find();
      if (!wrapper) {
        if (tries++ < 20) setTimeout(look, 100);
        return;
      }
      wrapper.scrollIntoView({ block: "center", behavior: "smooth" });
      wrapper.classList.add("is-targeted");
      setTimeout(function () { wrapper.classList.remove("is-targeted"); }, 1600);
      var field = wrapper.querySelector("input[type='text'], textarea, input[type='checkbox'], button, a.button");
      if (field) setTimeout(function () { field.focus({ preventScroll: true }); }, 250);
    })();
  };
  document.addEventListener("click", function (e) {
    var target = hit(e);
    if (!target) return;
    e.preventDefault();
    e.stopPropagation();
    showField(target.getAttribute("data-edit-field"));
  }, true);

  Drupal.behaviors.iconPageDataColours = {
    attach: function (context) {
      once("icon-page-data-colours", '[data-form-id="page_data_form"]', context).forEach(function (form) {
        form.querySelectorAll("#edit-field-work-bg-wrapper input[type='text'], #edit-field-work-ink-wrapper input[type='text']").forEach(dress);
      });
      once("icon-page-data-sortable", '[data-form-id="page_data_form"] table.field-multiple-table', context).forEach(sortable);
    }
  };
})(Drupal, once);
