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

  document.addEventListener("pointerdown", function (e) {
    var handle = e.target.closest(".icon-panel__handle");
    if (!handle || e.button !== 0) return;
    var row = handle.closest("tr");
    var table = row && row.closest("table");
    if (!row || !table) return;
    e.preventDefault();
    drag = { row: row, table: table, pointerId: e.pointerId };
    row.classList.add("is-dragging");
    document.body.classList.add("icon-panel-sorting");
  });

  document.addEventListener("pointermove", function (e) {
    if (!drag) return;
    var y = e.clientY;
    // One step per pass; repeat until the row sits where the pointer is, so
    // a fast drag (few move events) still lands in the right place.
    for (var pass = 0; pass < 10; pass++) {
      var moved = false;
      var rows = rowsOf(drag.table);
      for (var i = 0; i < rows.length; i++) {
        var r = rows[i];
        if (r === drag.row) continue;
        var b = r.getBoundingClientRect();
        var mid = b.top + b.height / 2;
        var after = r.compareDocumentPosition(drag.row) & Node.DOCUMENT_POSITION_FOLLOWING; // drag.row is after r
        if (after && y < mid) { r.before(drag.row); moved = true; break; }
        if (!after && y > mid) { r.after(drag.row); moved = true; break; }
      }
      if (!moved) break;
    }
  });

  var end = function () {
    if (!drag) return;
    var rows = rowsOf(drag.table);
    var panel = drag.table.closest(".icon-panel");
    var field = panel && panel.querySelector("input.icon-panel__order");
    drag.row.classList.remove("is-dragging");
    document.body.classList.remove("icon-panel-sorting");
    drag = null;
    if (!field) return;
    var value = rows.map(function (r) { return r.getAttribute("data-row"); }).join(",");
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
