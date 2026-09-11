/* opening.js — a Canvas page's opening colour, INSIDE the editor only. On
 * the page itself the theme puts the opening class on <html> in the
 * markup (icon_preprocess_html()); in the editor it hands the class over
 * as data-icon-opening instead, and this decides: the editor's own frame
 * (edit mode — the frame carries data-canvas-preview) stays on the plain
 * theme, so the blocks are read against white while they are worked on,
 * and Preview mode (a plain page frame) wears the colour, which is where
 * it is judged (user call, Sep 2026). Runs in <head>, before the first
 * paint, so neither mode flashes the other. In edit mode it also marks
 * the root so js/theme-handover.js leaves the class alone. */
(function () {
  "use strict";

  var root = document.documentElement;
  var opening = root.getAttribute("data-icon-opening");
  if (!opening) return;
  var editing = false;
  try {
    var frame = window.frameElement;
    editing = !!(frame && frame.hasAttribute("data-canvas-preview"));
  } catch (err) {}
  if (editing) root.setAttribute("data-icon-opening-off", "");
  else root.classList.add(opening);
})();
