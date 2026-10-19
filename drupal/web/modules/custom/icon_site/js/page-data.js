/* page-data.js — the colour fields' swatch and picker: on the Page data
   panel's pair (Page colour, Ink colour) and on every colour prop in a
   component's settings panel — a Picture's Ground colour, a box's or
   masthead's pair, the scroller's ground (user ask, 16 Oct 2026: "can I
   have a colour picker on this?"). The swatch shows the hex typed; the
   browser's own colour input sits over it, so a click opens the picker
   and a pick writes the hex back into the field and tells Canvas (an
   input event, as if typed). Nothing is moved — Canvas reads a moved
   field as an edit. Runs on the forms as Canvas re-renders them. */
(function (Drupal, once) {
  "use strict";

  var HEX = /^#[0-9a-f]{3,8}$/i;

  // the picker wants six digits: #abc -> #aabbcc, #rrggbbaa -> #rrggbb
  function six(v) {
    var h = v.slice(1);
    if (h.length === 3 || h.length === 4) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
    return "#" + h.slice(0, 6).toLowerCase();
  }

  // A colour field, by its name — bg, ink, ground, colour, color — or by
  // what it holds, a hex (user ask, 19 Oct 2026: "any time we add a colour,
  // a colour picker"). Canvas's panels and Claro's forms alike.
  // bg / ink as a whole word between _ [ ] or the ends: field_testimonial_bg[0][value],
  // canvas_component_props[…][bg][0][value] (an empty node field had slipped
  // through — "_bg[" was not matched; user catch, 19 Oct 2026)
  var NAME = /(colou?r|ground|(^|[_[])bg([_[\]]|$)|(^|[_[])ink([_[\]]|$))/i;
  function isColour(input) {
    return NAME.test(input.name || "") || HEX.test(input.value.trim());
  }

  function dress(input) {
    var item = input.closest(".form-item");
    if (!item || item.querySelector(".icon-colour__swatch")) return;
    item.classList.add("icon-colour");
    var host = input.parentElement || item;
    // Canvas wraps its inputs in a positioned element; Claro does not, so
    // the swatch had nothing to sit in there (user catch, 19 Oct 2026) — a
    // wrap of our own round the field, inline so the layout holds
    if (!/_wrap_/.test(host.className || "")) {
      var wrap = document.createElement("span");
      wrap.className = "icon-colour__wrap";
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);
      host = wrap;
    }
    var swatch = document.createElement("span");
    swatch.className = "icon-colour__swatch";
    var pick = document.createElement("input");
    pick.type = "color";
    pick.className = "icon-colour__pick";
    pick.tabIndex = -1;
    pick.setAttribute("aria-label", Drupal.t("Pick a colour"));
    host.appendChild(swatch);
    host.appendChild(pick);
    var paint = function () {
      var v = input.value.trim();
      var ok = HEX.test(v);
      swatch.style.background = ok ? v : "transparent";
      if (ok) pick.value = six(v);
    };
    input.addEventListener("input", paint);
    pick.addEventListener("input", function () {
      // As if typed. The panel's fields are React-controlled: a value set
      // through the element's own property is swallowed by React's value
      // tracker and no change reaches Canvas (caught 16 Oct 2026 — the
      // preview kept the old ground); the prototype's setter, then the
      // event, is what React treats as the user typing.
      input.focus();
      input.select();
      var typed = false;
      try {
        // the editing command writes through the browser as typing does
        typed = document.execCommand("insertText", false, pick.value);
      }
      catch (err) {}
      if (!typed || input.value !== pick.value) {
        var set = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value").set;
        set.call(input, pick.value);
        input.dispatchEvent(new Event("input", { bubbles: true }));
      }
      input.dispatchEvent(new Event("change", { bubbles: true }));
      paint();
    });
    paint();
  }

  /* A media pick's Remove: Canvas clears the prop in its model on the press
     and rebuilds the widget by AJAX, but an edit made while that reply is in
     flight — a colour typed or picked — is read with the picture still in
     the form's own state and brings the deleted picture back (user catch,
     16 Oct 2026: "when I delete, then pick a colour ground, the image
     reappears, even though it says no media items"). So the other fields
     hold still until the rebuild has landed. Read-only, not disabled: a
     disabled field is dropped from the form Canvas reads. Nothing in the
     item itself is touched — Canvas's own handler reads the item's hidden
     input to know which prop to clear. */
  function guardRemove(form) {
    form.addEventListener("mousedown", function (e) {
      if (!e.target.closest("[data-canvas-media-remove-button]")) return;
      var held = form.querySelectorAll('input[type="text"]:not([readonly]), textarea:not([readonly]), .icon-colour__pick');
      held.forEach(function (f) { f.readOnly = true; f.setAttribute("data-icon-held", ""); });
      var release = function () {
        form.querySelectorAll("[data-icon-held]").forEach(function (f) { f.readOnly = false; f.removeAttribute("data-icon-held"); });
      };
      var started = Date.now();
      var tick = function () {
        var busy = ((Drupal.ajax && Drupal.ajax.instances) || []).some(function (i) { return i && i.ajaxing; });
        if ((!busy && Date.now() - started > 1500) || Date.now() - started > 10000) release();
        else setTimeout(tick, 150);
      };
      setTimeout(tick, 300);
    }, true);
  }

  Drupal.behaviors.iconPageDataColours = {
    attach: function (context) {
      once("icon-page-data-colours", 'form[data-form-id="page_data_form"]', context).forEach(function (form) {
        form.querySelectorAll("#edit-field-work-bg-wrapper input[type='text'], #edit-field-work-ink-wrapper input[type='text']").forEach(dress);
      });
      // every colour field on the page — a component's props, a node's
      // fields, the footer's settings — by name or by the hex it holds
      once("icon-colour-fields", 'input[type="text"]', context).filter(isColour).forEach(dress);
      once("icon-media-remove-guard", 'form[data-form-id="component_instance_form"]', context).forEach(guardRemove);
    }
  };
})(Drupal, once);
