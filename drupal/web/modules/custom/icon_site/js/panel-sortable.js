/* panel-sortable.js — drag-to-reorder for the Canvas side panel's lists.
 *
 * WHY NOT CORE TABLEDRAG. Canvas renders Drupal forms through React and
 * re-renders them on every change, so tabledrag's per-table instance ends
 * up bound to a table that is no longer in the document: the handles show,
 * nothing moves. This is delegated on the document instead — pointerdown on
 * a handle, wherever the row lives now — so a re-render costs nothing.
 *
 * On drop, every row's hidden weight select is set to its new position and a
 * change event is dispatched through React's own setter, which is what makes
 * Canvas auto-save the new order (the same path a typed change takes).
 */
(function () {
  "use strict";

  var drag = null;

  // Write a value the way a keypress would, so React (and so Canvas) sees it:
  // the prototype's setter, then input + change, then blur.
  var setValue = function (field, value) {
    var proto = field.tagName === "SELECT" ? HTMLSelectElement.prototype : HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(proto, "value").set.call(field, String(value));
    field.dispatchEvent(new Event("input", { bubbles: true }));
    field.dispatchEvent(new Event("change", { bubbles: true }));
    field.dispatchEvent(new FocusEvent("blur"));
    field.dispatchEvent(new FocusEvent("focusout", { bubbles: true }));
  };

  var rowsOf = function (table) {
    return Array.prototype.slice.call(table.querySelectorAll("tr.draggable"));
  };

  // The drop line: one per card, positioned in the gap the row would land in.
  var lineFor = function (card) {
    var line = card.querySelector(".icon-panel__drop");
    if (!line) {
      line = document.createElement("div");
      line.className = "icon-panel__drop";
      card.appendChild(line);
    }
    return line;
  };

  // Where the pointer would drop the row: an index into the row list, and
  // the card-relative y of the gap before that index.
  var targetFor = function (y) {
    var rows = rowsOf(drag.table);
    var cardTop = drag.card.getBoundingClientRect().top;
    var index = rows.length;
    var lineY = null;
    for (var i = 0; i < rows.length; i++) {
      var b = rows[i].getBoundingClientRect();
      if (y < b.top + b.height / 2) {
        index = i;
        var prev = rows[i - 1];
        lineY = prev ? (prev.getBoundingClientRect().bottom + b.top) / 2 - cardTop : b.top - 4 - cardTop;
        break;
      }
    }
    if (lineY === null) {
      var last = rows[rows.length - 1].getBoundingClientRect();
      lineY = last.bottom + 4 - cardTop;
    }
    return { index: index, y: lineY };
  };

  document.addEventListener("pointerdown", function (e) {
    var handle = e.target.closest(".icon-panel__handle");
    if (!handle || e.button !== 0) return;
    var row = handle.closest("tr");
    var table = row && row.closest("table");
    var card = table && table.closest(".icon-panel__card");
    if (!row || !table || !card) return;
    e.preventDefault();
    drag = { row: row, table: table, card: card, target: null };
    row.classList.add("is-dragging");
    document.body.classList.add("icon-panel-sorting");
  });

  // The row STAYS PUT while dragging; only the line moves. Moving rows under
  // the pointer is what made the list jump and rows change height.
  document.addEventListener("pointermove", function (e) {
    if (!drag) return;
    var t = targetFor(e.clientY);
    var from = rowsOf(drag.table).indexOf(drag.row);
    // The gap above or below the row itself is no move.
    var noop = t.index === from || t.index === from + 1;
    var line = lineFor(drag.card);
    line.style.top = t.y + "px";
    line.classList.toggle("is-on", !noop);
    drag.target = noop ? null : t.index;
  });

  var end = function () {
    if (!drag) return;
    var d = drag;
    drag = null;
    d.row.classList.remove("is-dragging");
    document.body.classList.remove("icon-panel-sorting");
    lineFor(d.card).classList.remove("is-on");
    if (d.target === null) return;
    var rows = rowsOf(d.table);
    var before = rows[d.target] || null; // past the end → append
    if (before === d.row) return;
    d.row.parentNode.insertBefore(d.row, before);
    var panel = d.table.closest(".icon-panel");
    var field = panel && panel.querySelector("input.icon-panel__order");
    if (!field) return;
    var value = rowsOf(d.table).map(function (r) { return r.getAttribute("data-row"); }).join(",");
    if (value !== field.value) setValue(field, value);
  };
  document.addEventListener("pointerup", end);
  document.addEventListener("pointercancel", end);

  // After a slide is saved in its dialog the list is stale; Canvas has
  // auto-saved the page, so a reload is safe and is the one reliable way to
  // re-render a block form that nothing in the model has changed.
  if (window.jQuery) {
    window.jQuery(document.body).on("icon-panel:saved", function () {
      window.location.reload();
    });
  }
})();
