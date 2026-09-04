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

  /* ---- The featured picker: a searchable dropdown ------------------------
   * The card's button opens a menu under the row: a search box over every
   * published Work item (from the card's data-options), filtered on project,
   * client or title as you type. Picking writes "Label (id)" into the row's
   * (hidden) entity autocomplete — the value transport Canvas accepts — and
   * updates the row's text itself, because Canvas does not re-render a block
   * form after a change. */
  var menu = null;

  var closeMenu = function () {
    if (menu) { menu.el.remove(); menu = null; }
  };

  var escapeHtml = function (s) {
    return String(s).replace(/[&<>"]/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;" }[c];
    });
  };

  var renderList = function (q) {
    var needle = q.trim().toLowerCase();
    var items = menu.options.filter(function (o) {
      return !needle || (o.project + " " + o.client).toLowerCase().indexOf(needle) !== -1;
    });
    menu.list.innerHTML = items.length
      ? items.map(function (o) {
          return '<li class="icon-panel__menu-item' + (o.id === menu.current ? " is-current" : "") + '" data-id="' + o.id + '" role="option">' +
            '<p class="icon-panel__name">' + escapeHtml(o.project) + "</p>" +
            (o.client ? '<p class="icon-panel__meta">' + escapeHtml(o.client) + "</p>" : "") + "</li>";
        }).join("")
      : '<li class="icon-panel__menu-empty">No matching project</li>';
  };

  var pick = function (id) {
    var o = menu.options.find(function (x) { return x.id === id; });
    if (!o) return;
    var row = menu.row;
    var input = row.querySelector("input.icon-panel__search");
    var label = o.client ? o.project + " \u2014 " + o.client : o.project;
    if (input) setValue(input, label + " (" + o.id + ")");
    var current = row.querySelector("input.icon-panel__current");
    if (current) current.value = String(o.id);
    var text = row.querySelector(".icon-panel__pickable .icon-panel__text");
    if (text) {
      text.innerHTML = '<p class="icon-panel__name">' + escapeHtml(o.project) + "</p>" +
        (o.client ? '<p class="icon-panel__meta">' + escapeHtml(o.client) + "</p>" : "");
    }
    closeMenu();
  };

  document.addEventListener("click", function (e) {
    var button = e.target.closest(".icon-panel__pickable");
    if (button) {
      e.preventDefault();
      var row = button.closest("tr");
      var card = row.closest(".icon-panel__card");
      if (menu && menu.row === row) { closeMenu(); return; }
      closeMenu();
      var options = [];
      try { options = JSON.parse(card.getAttribute("data-options") || "[]"); } catch (err) {}
      var el = document.createElement("div");
      el.className = "icon-panel__menu";
      el.innerHTML = '<input type="text" class="icon-panel__menu-search" placeholder="Search project or client\u2026" autocomplete="off">' +
        '<ul class="icon-panel__menu-list" role="listbox"></ul>';
      var rowRect = row.getBoundingClientRect();
      var cardRect = card.getBoundingClientRect();
      el.style.top = (rowRect.bottom - cardRect.top + 4) + "px";
      card.appendChild(el);
      var currentInput = row.querySelector("input.icon-panel__current");
      menu = { el: el, row: row, options: options, list: el.querySelector("ul"), current: currentInput ? parseInt(currentInput.value, 10) : 0 };
      renderList("");
      el.querySelector("input").focus();
      return;
    }
    if (!menu) return;
    var item = e.target.closest(".icon-panel__menu-item");
    if (item && menu.el.contains(item)) { pick(parseInt(item.getAttribute("data-id"), 10)); return; }
    if (!menu.el.contains(e.target)) closeMenu();
  });

  document.addEventListener("input", function (e) {
    if (menu && e.target === menu.el.querySelector("input")) renderList(e.target.value);
  });

  document.addEventListener("keydown", function (e) {
    if (!menu) return;
    if (e.key === "Escape") { closeMenu(); return; }
    if (e.key === "Enter" && e.target === menu.el.querySelector("input")) {
      e.preventDefault();
      var first = menu.list.querySelector(".icon-panel__menu-item");
      if (first) pick(parseInt(first.getAttribute("data-id"), 10));
    }
  });

  // The "latest" switch dims the list at once; the server does the same on
  // the next open.
  document.addEventListener("change", function (e) {
    if (!e.target.classList || !e.target.classList.contains("icon-panel__toggle")) return;
    var card = e.target.closest(".icon-panel") && e.target.closest(".icon-panel").querySelector(".icon-panel__card");
    if (card) card.classList.toggle("icon-panel__card--auto", e.target.checked);
  });

  // After a slide is saved in its dialog the list is stale; Canvas has
  // auto-saved the page, so a reload is safe and is the one reliable way to
  // re-render a block form that nothing in the model has changed.
  if (window.jQuery) {
    window.jQuery(document.body).on("icon-panel:saved", function () {
      window.location.reload();
    });
  }
})();
