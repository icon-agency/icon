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

  // What a screen reader hears after a keyboard move or a pick.
  var say = function (text) {
    var live = document.querySelector(".icon-panel__live");
    if (!live) {
      live = document.createElement("p");
      live.className = "icon-panel__live";
      live.setAttribute("aria-live", "polite");
      document.body.appendChild(live);
    }
    live.textContent = "";
    setTimeout(function () { live.textContent = text; }, 30);
  };

  /* ---- Keyboard reorder --------------------------------------------------
   * The handle is a focusable control (role=button, named for its row):
   * ArrowUp / ArrowDown move the row one place, Home / End to the ends,
   * and the order is written the same way a drop writes it. Focus stays
   * on the handle, which travels with its row. */
  document.addEventListener("keydown", function (e) {
    var handle = e.target.closest && e.target.closest(".icon-panel__handle");
    if (!handle || drag) return;
    var keys = { ArrowUp: -1, ArrowDown: 1, Home: -Infinity, End: Infinity };
    if (!(e.key in keys)) return;
    e.preventDefault();
    var row = handle.closest("tr.draggable");
    var table = row && row.closest("table");
    if (!table) return;
    var rows = rowsOf(table);
    var from = rows.indexOf(row);
    var to = Math.max(0, Math.min(rows.length - 1, keys[e.key] === -Infinity ? 0 : keys[e.key] === Infinity ? rows.length - 1 : from + keys[e.key]));
    if (to === from) return;
    var tbody = row.parentNode;
    if (to > from) tbody.insertBefore(row, rows[to].nextSibling);
    else tbody.insertBefore(row, rows[to]);
    handle.focus();
    var name = (row.querySelector(".icon-panel__name") || {}).textContent || "";
    say((name ? name.trim() + " " : "") + "moved to position " + (to + 1) + " of " + rows.length);
    sync(table);
  });
  // the handle is a link only so the markup filter keeps it; a click is not a jump
  document.addEventListener("click", function (e) {
    if (e.target.closest && e.target.closest(".icon-panel__handle")) e.preventDefault();
  });

  /* ---- A reel and its spares (.icon-panel--reel: the hero, the filmstrip)
   * "Add to reel" moves a row from the Available table to the end of the
   * reel; "Remove" moves it back (oldest first is not kept — it lands at
   * the end of Available, which is fine for a holding list). Both write
   * the order the way a drop does. */
  var onReel = function (row) { return !!row.closest(".icon-panel__list--reel"); };
  var moveRow = function (row, add) {
    var panel = row.closest(".icon-panel--reel");
    var to = panel && panel.querySelector(add ? ".icon-panel__list--reel tbody" : ".icon-panel__list--available tbody");
    if (!to) return;
    to.appendChild(row);
    var name = (row.querySelector(".icon-panel__name") || {}).textContent || "";
    say(name.trim() + (add ? " added to the reel" : " removed from the reel"));
    var focus = row.querySelector(add ? ".icon-panel__handle" : ".icon-panel__reel-add");
    if (focus) focus.focus();
    sync(panel.querySelector(".icon-panel__list--reel"));
  };
  document.addEventListener("click", function (e) {
    var add = e.target.closest && e.target.closest(".icon-panel__reel-add");
    if (!add) return;
    e.preventDefault();
    moveRow(add.closest("tr"), true);
  });
  // The slide dialog's reel action (icon_site_form_node_form_alter): reads
  // as "Remove from reel" for a slide on the reel, "Add to reel" for one
  // under Available; the click moves the row and closes the dialog.
  var reelLink = function (link) {
    var row = document.querySelector('.icon-panel--reel tr[data-row="' + link.getAttribute("data-nid") + '"]');
    if (!row) { link.hidden = true; return null; }
    var on = onReel(row);
    link.textContent = on ? "Remove from reel" : "Add to reel";
    link.classList.toggle("button--danger", on);
    link.classList.toggle("button--primary", !on);
    return row;
  };
  document.addEventListener("click", function (e) {
    var link = e.target.closest && e.target.closest(".icon-panel__dialog-reel");
    if (!link) return;
    e.preventDefault();
    var row = reelLink(link);
    if (!row) return;
    var add = !onReel(row);
    if (window.jQuery && window.jQuery.fn.dialog) {
      try { window.jQuery("#icon-panel-dialog").dialog("close"); } catch (err) {}
    }
    moveRow(row, add);
  });
  if (window.jQuery) {
    window.jQuery(window).on("dialog:aftercreate", function () {
      document.querySelectorAll(".icon-panel__dialog-reel").forEach(reelLink);
    });
  }

  /* ---- The drag ----------------------------------------------------------
   * Pick a row up by its handle: a GHOST of it follows the pointer, the row
   * itself stays in the list, dimmed, and a blue line with a dot marks the
   * gap it will drop into. Nothing else moves until release — then the
   * ghost glides into the gap, the row moves there, and the order is
   * written. Escape cancels. */
  // The line is on the body, position: fixed, ABOVE the ghost: inside the
  // card it sat underneath the ghost that follows the pointer, which is
  // exactly where the gap is — so the editor never saw it.
  var lineFor = function (card) {
    var line = document.querySelector(".icon-panel__drop");
    if (!line) {
      line = document.createElement("div");
      line.className = "icon-panel__drop";
      document.body.appendChild(line);
    }
    var r = card.getBoundingClientRect();
    line.style.left = r.left + "px";
    line.style.width = r.width + "px";
    return line;
  };

  // Where the pointer would drop the row: an index into the row list, and
  // the viewport y of the gap before that index.
  var targetFor = function (y) {
    var rows = rowsOf(drag.table);
    var index = rows.length;
    var lineY = null;
    for (var i = 0; i < rows.length; i++) {
      var b = rows[i].getBoundingClientRect();
      if (y < b.top + b.height / 2) {
        index = i;
        var prev = rows[i - 1];
        lineY = prev ? (prev.getBoundingClientRect().bottom + b.top) / 2 : b.top - 4;
        break;
      }
    }
    if (lineY === null) {
      var last = rows[rows.length - 1].getBoundingClientRect();
      lineY = last.bottom + 4;
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
    closeMenu();
    var rect = row.getBoundingClientRect();
    var ghost = document.createElement("div");
    ghost.className = "icon-panel__ghost";
    var clone = document.createElement("table");
    clone.className = table.className;
    clone.innerHTML = "<tbody>" + row.outerHTML + "</tbody>";
    clone.querySelector("tr").classList.remove("is-dragging");
    ghost.appendChild(clone);
    ghost.style.left = rect.left + "px";
    ghost.style.top = rect.top + "px";
    ghost.style.width = rect.width + "px";
    document.body.appendChild(ghost);
    drag = { row: row, table: table, card: card, ghost: ghost, startY: e.clientY, top: rect.top, target: null, lineY: null };
    row.classList.add("is-dragging");
    document.body.classList.add("icon-panel-sorting");
  });

  document.addEventListener("pointermove", function (e) {
    if (!drag) return;
    drag.ghost.style.transform = "translateY(" + (e.clientY - drag.startY) + "px)";
    var t = targetFor(e.clientY);
    var from = rowsOf(drag.table).indexOf(drag.row);
    // The gap above or below the row itself is no move.
    var noop = t.index === from || t.index === from + 1;
    var line = lineFor(drag.card);
    line.style.top = t.y + "px";
    line.classList.toggle("is-on", !noop);
    drag.target = noop ? null : t.index;
    drag.lineY = t.y;
  });

  var settle = function (cancel) {
    if (!drag) return;
    var d = drag;
    drag = null;
    document.body.classList.remove("icon-panel-sorting");
    lineFor(d.card).classList.remove("is-on");
    var moved = !cancel && d.target !== null;
    // The ghost glides to where the row will be (its own place on cancel),
    // then hands back to the real row.
    var destTop;
    if (moved) {
      destTop = d.lineY - (d.target > rowsOf(d.table).indexOf(d.row) ? d.row.getBoundingClientRect().height : 0);
    } else {
      destTop = d.row.getBoundingClientRect().top;
    }
    d.ghost.style.transition = "transform 150ms cubic-bezier(0.2, 0, 0, 1), opacity 150ms";
    d.ghost.style.transform = "translateY(" + (destTop - d.top) + "px)";
    d.ghost.style.opacity = "0.85";
    setTimeout(function () {
      d.ghost.remove();
      if (moved) {
        var rows = rowsOf(d.table);
        var before = rows[d.target] || null; // past the end → append
        if (before !== d.row) d.row.parentNode.insertBefore(d.row, before);
        sync(d.table);
      }
      d.row.classList.remove("is-dragging");
    }, 150);
  };
  document.addEventListener("pointerup", function () { settle(false); });
  document.addEventListener("pointercancel", function () { settle(true); });
  document.addEventListener("keydown", function (e) { if (e.key === "Escape" && drag) settle(true); });

  // Write the list back into the settings' inputs: a reel panel's one
  // `order` field (row keys, comma-separated — the hero's slides, the
  // filmstrip's photos) or the featured grid's five project fields (the
  // i-th row's project into projects[i]). One block form is open at a time,
  // so the inputs are found from the document.
  var sync = function (table) {
    var rows = rowsOf(table);
    var order = document.querySelector("input.icon-panel__order");
    var projects = document.querySelectorAll("input.icon-panel__project");
    if (table.closest(".icon-panel--reel") && order) {
      // the reel is the selection: only its table is the order
      var reel = table.closest(".icon-panel--reel").querySelector(".icon-panel__list--reel") || table;
      var value = rowsOf(reel).map(function (r) { return r.getAttribute("data-row"); }).join(",");
      if (value !== order.value) setValue(order, value);
      var count = table.closest(".icon-panel--reel").querySelector(".icon-panel__title");
      if (count) count.textContent = count.textContent.replace(/\d+(?= of)/, String(rowsOf(reel).length));
      var note = table.closest(".icon-panel--reel").querySelector(".icon-panel__empty-note");
      if (note) note.classList.toggle("is-hidden", rowsOf(reel).length > 0);
      var group = table.closest(".icon-panel--reel").querySelector("[data-group=available]");
      if (group) group.hidden = rowsOf(group.querySelector("table")).length === 0;
    }
    // The marquee's order is content: post it, then reload.
    var orderUrl = table.closest(".icon-panel__card") && table.closest(".icon-panel__card").getAttribute("data-order-url");
    if (table.closest(".icon-panel--clients") && orderUrl) {
      var ids = rows.map(function (r) { return r.getAttribute("data-row"); }).join(",");
      fetch(orderUrl + "&ids=" + ids, { method: "POST", headers: { "X-Requested-With": "XMLHttpRequest" }, credentials: "same-origin" })
        .then(function () { window.location.reload(); });
      return;
    }
    if (table.closest(".icon-panel--featured") && projects.length) {
      projects.forEach(function (field, i) {
        var id = rows[i] ? (rows[i].getAttribute("data-id") || "") : "";
        if (field.value !== id) setValue(field, id);
      });
    }
  };


  /* ---- The featured picker: a searchable dropdown ------------------------
   * The card's button opens a menu under the row: a search box over every
   * published Work item (from the card's data-options), filtered on project,
   * client or title as you type. Picking writes "Label (id)" into the row's
   * (hidden) entity autocomplete — the value transport Canvas accepts — and
   * updates the row's text itself, because Canvas does not re-render a block
   * form after a change. */
  var menu = null;

  function closeMenu() {
    if (!menu) return;
    var opener = menu.row.querySelector(".icon-panel__pickable") || menu.row;
    opener.setAttribute("aria-expanded", "false");
    menu.el.remove();
    menu = null;
    // focus returns to the control that opened the list
    if (opener.focus) opener.focus();
  }

  // the highlighted option (arrow keys move it; Enter picks it)
  var setActive = function (index) {
    var items = Array.prototype.slice.call(menu.list.querySelectorAll(".icon-panel__menu-item"));
    if (!items.length) { menu.active = -1; return; }
    menu.active = Math.max(0, Math.min(items.length - 1, index));
    items.forEach(function (li, i) {
      li.classList.toggle("is-active", i === menu.active);
      li.setAttribute("aria-selected", i === menu.active ? "true" : "false");
    });
    var input = menu.el.querySelector("input");
    input.setAttribute("aria-activedescendant", items[menu.active].id);
    items[menu.active].scrollIntoView({ block: "nearest" });
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
      ? items.map(function (o, i) {
          return '<li class="icon-panel__menu-item' + (o.id === menu.current ? " is-current" : "") + '" data-id="' + o.id + '" id="icon-panel-option-' + o.id + '" role="option" aria-selected="false">' +
            '<p class="icon-panel__name' + (o.latest ? " icon-panel__name--empty" : "") + '">' + escapeHtml(o.project) + "</p>" +
            (o.client ? '<p class="icon-panel__meta">' + escapeHtml(o.client) + "</p>" : "") + "</li>";
        }).join("")
      : '<li class="icon-panel__menu-empty" role="presentation">No matching project</li>';
    // the first match is highlighted, ready for Enter
    setActive(0);
  };

  var pick = function (id) {
    var o = menu.options.find(function (x) { return x.id === id; });
    if (!o) return;
    var row = menu.row;
    // An action pick (the news feed's "Add a story"): call the action, then
    // reload — the story is content, not a page setting.
    var actionUrl = row.getAttribute("data-action-url");
    if (actionUrl) {
      closeMenu();
      fetch(actionUrl + "&nid=" + o.id, { method: "POST", headers: { "X-Requested-With": "XMLHttpRequest" }, credentials: "same-origin" })
        .then(function () { window.location.reload(); });
      return;
    }
    // "Show latest" is the empty pick: the slot takes the newest work item
    row.setAttribute("data-id", o.latest ? "" : String(o.id));
    var text = row.querySelector(".icon-panel__pickable .icon-panel__text");
    if (text) {
      text.innerHTML = '<p class="icon-panel__name' + (o.latest ? " icon-panel__name--empty" : "") + '">' + escapeHtml(o.project) + "</p>" +
        (o.client ? '<p class="icon-panel__meta">' + escapeHtml(o.client) + "</p>" : "");
    }
    closeMenu();
    say(o.project + (o.client ? ", " + o.client : "") + " chosen");
    sync(row.closest("table"));
  };

  document.addEventListener("click", function (e) {
    var button = e.target.closest(".icon-panel__pickable");
    if (button) {
      e.preventDefault();
      var row = button.closest("tr") || button;
      var card = button.closest(".icon-panel__card");
      if (menu && menu.row === row) { closeMenu(); return; }
      closeMenu();
      var options = [];
      try { options = JSON.parse(card.getAttribute("data-options") || "[]"); } catch (err) {}
      var el = document.createElement("div");
      el.className = "icon-panel__menu";
      var listId = "icon-panel-listbox-" + Date.now();
      el.innerHTML = '<input type="text" class="icon-panel__menu-search" placeholder="Search project or client\u2026" autocomplete="off" role="combobox" aria-expanded="true" aria-autocomplete="list" aria-controls="' + listId + '" aria-label="Search project or client">' +
        '<ul class="icon-panel__menu-list" role="listbox" id="' + listId + '"></ul>';
      button.setAttribute("aria-expanded", "true");
      var rowRect = row.getBoundingClientRect();
      var cardRect = card.getBoundingClientRect();
      el.style.top = (rowRect.bottom - cardRect.top + 4) + "px";
      card.appendChild(el);
      menu = { el: el, row: row, options: options, list: el.querySelector("ul"), current: parseInt(row.getAttribute("data-id") || "0", 10) || 0, active: -1 };
      renderList("");
      // after the click has fully bubbled: Canvas's own handlers refocus the
      // clicked control, so the search box takes focus on the next tick
      setTimeout(function () { var input = el.querySelector("input"); if (input) input.focus(); }, 0);
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
    if (e.key === "Escape") { e.preventDefault(); closeMenu(); return; }
    if (e.target !== menu.el.querySelector("input")) return;
    var items = menu.list.querySelectorAll(".icon-panel__menu-item");
    if (e.key === "ArrowDown") { e.preventDefault(); setActive(menu.active + 1); return; }
    if (e.key === "ArrowUp") { e.preventDefault(); setActive(menu.active - 1); return; }
    if (e.key === "Home") { e.preventDefault(); setActive(0); return; }
    if (e.key === "End") { e.preventDefault(); setActive(items.length - 1); return; }
    if (e.key === "Enter") {
      e.preventDefault();
      var chosen = items[menu.active] || items[0];
      if (chosen) pick(parseInt(chosen.getAttribute("data-id"), 10));
    }
  });

  // The "latest" switch dims the list at once; the server does the same on
  // the next open.
  document.addEventListener("change", function (e) {
    if (!e.target.classList || !e.target.classList.contains("icon-panel__toggle")) return;
    var card = document.querySelector(".icon-panel--featured .icon-panel__card");
    if (card) card.classList.toggle("icon-panel__card--auto", e.target.checked);
  });

  /* ---- Canvas swallows form build-id updates for forms it does not know --
   * Canvas overrides Drupal's `update_build_id` ajax command and RETURNS
   * EARLY for any form that is not one of its own (and re-installs that
   * override, so wrapping the command does not stick). A Drupal form in a
   * dialog over the editor therefore keeps posting its FIRST build id: every
   * ajax step (open the media library, insert the selection) rebuilds and
   * caches the form under a new id that is never used again, and the save
   * finds the media widget as it was on open — empty. So the build id is
   * updated a level up, where the response arrives: before Drupal.Ajax
   * hands the commands out, apply every update_build_id to the inputs in
   * our dialog ourselves. */
  if (window.Drupal && window.Drupal.Ajax && !window.Drupal.Ajax.prototype.__iconPanelBuildId) {
    var origSuccess = window.Drupal.Ajax.prototype.success;
    window.Drupal.Ajax.prototype.__iconPanelBuildId = true;
    window.Drupal.Ajax.prototype.success = function (response, status) {
      if (Array.isArray(response)) {
        response.forEach(function (c) {
          if (!c || c.command !== "update_build_id") return;
          document.querySelectorAll('#icon-panel-dialog input[name="form_build_id"]').forEach(function (input) {
            if (input.value === c.old) input.value = c.new;
          });
        });
      }
      return origSuccess.apply(this, arguments);
    };
  }

  // After a slide is saved in its dialog the list is stale; Canvas has
  // auto-saved the page, so a reload is safe and is the one reliable way to
  // re-render a block form that nothing in the model has changed.
  if (window.jQuery) {
    window.jQuery(document.body).on("icon-panel:saved", function (e, saved) {
      // an item created from a reel panel (a Hero slide, a filmstrip photo)
      // goes straight onto the reel: written into the order (Canvas
      // autosaves it) before the reload — the order field sits beside the
      // panel container, not inside it
      var order = document.querySelector(".icon-panel--reel") ? document.querySelector("input.icon-panel__order") : null;
      var nid = saved && saved[0];
      var isNew = saved && saved[1];
      if (order && isNew && nid && order.value.split(",").indexOf(String(nid)) === -1) {
        setValue(order, (order.value ? order.value + "," : "") + nid);
        setTimeout(function () { window.location.reload(); }, 900);
        return;
      }
      window.location.reload();
    });
  }
})();
