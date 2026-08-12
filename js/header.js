/* header.js — desktop nav scroll state + mobile menu toggle.
 * Port of the scroll/menu logic in v40-1-icon/components/navigation-bar.tsx.
 * The SERVICES submenu is CSS-driven (hover/focus-within); only the scroll
 * state and the mobile menu need JS.
 *
 * Drupal: becomes Drupal.behaviors.iconHeader attached via `icon/header`. */
(function () {
  "use strict";

  var nav = document.querySelector("[data-nav]");
  var logo = document.querySelector(".site-logo");
  var mobileHome = document.querySelector("[data-mobile-home]");
  var toggle = document.querySelector("[data-menu-toggle]");
  var menu = document.querySelector("[data-mobile-menu]");

  // ---- Scroll state: frosted pill (>10px), then the hero-exit swap at >100px —
  //      the wordmark fades out as the home glyph (pill + mobile button) fades in.
  function onScroll() {
    var y = window.scrollY;
    if (nav) {
      nav.classList.toggle("is-scrolled", y > 10);
      nav.classList.toggle("show-home", y > 100);
    }
    if (mobileHome) mobileHome.classList.toggle("is-visible", y > 100);
    // Logo fade is a one-shot CSS transition triggered HERE (not scroll-scrubbed),
    // at the same point the glyph appears, so the two swap together and the fade
    // always runs to completion. Also drops pointer events once hidden.
    if (logo) logo.classList.toggle("is-faded", y > 100);
  }
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  // ---- Mobile menu ----
  function isOpen() {
    return menu && menu.classList.contains("is-open");
  }

  function openMenu() {
    if (!menu) return;
    menu.hidden = false;
    window.requestAnimationFrame(function () {
      menu.classList.add("is-open");
    });
    if (toggle) {
      toggle.classList.add("is-open");
      toggle.setAttribute("aria-expanded", "true");
      toggle.setAttribute("aria-label", "Close menu");
    }
    document.body.classList.add("is-menu-open");
  }

  function closeMenu() {
    if (!menu) return;
    menu.classList.remove("is-open");
    if (toggle) {
      toggle.classList.remove("is-open");
      toggle.setAttribute("aria-expanded", "false");
      toggle.setAttribute("aria-label", "Open menu");
    }
    document.body.classList.remove("is-menu-open");
    window.setTimeout(function () {
      if (!isOpen()) menu.hidden = true;
    }, 400);
  }

  if (toggle) {
    toggle.addEventListener("click", function () {
      if (isOpen()) closeMenu();
      else openMenu();
    });
  }

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && isOpen()) closeMenu();
  });

  // Close when a menu link is followed.
  if (menu) {
    menu.addEventListener("click", function (e) {
      if (e.target.closest("a")) closeMenu();
    });
  }

  // ---- SERVICES disclosure (expanding drawer) ----
  // CSS handles the hover-open baseline (:has); JS adds the hover-intent close
  // delay, aria sync, keyboard, touch-open, Esc, outside-click, and aligns the
  // drawer items under SERVICES.
  var smItem = document.querySelector("[data-submenu-item]");
  var smTrigger = document.querySelector("[data-submenu-trigger]");
  var smDrawer = document.querySelector("[data-submenu-drawer]");

  if (nav && smItem && smTrigger && smDrawer) {
    var smTimer = null;
    var smSuppress = false;
    var smCoarse = window.matchMedia("(pointer: coarse)").matches;

    function smOffset() {
      var t = smTrigger.getBoundingClientRect();
      var d = smDrawer.getBoundingClientRect();
      // Add the trigger's left padding so items align with the SERVICES *text*,
      // not its (padded) box edge.
      var pad = parseFloat(getComputedStyle(smTrigger).paddingLeft) || 0;
      smDrawer.style.setProperty("--submenu-offset", Math.max(0, Math.round(t.left - d.left + pad)) + "px");
    }
    function smIsOpen() { return nav.classList.contains("is-submenu-open"); }
    function smOpen() {
      if (smSuppress) return;
      if (smTimer) { clearTimeout(smTimer); smTimer = null; }
      smOffset();
      nav.classList.add("is-submenu-open");
      smTrigger.setAttribute("aria-expanded", "true");
    }
    function smClose() {
      nav.classList.remove("is-submenu-open");
      smTrigger.setAttribute("aria-expanded", "false");
    }
    function smScheduleClose() {
      if (smTimer) clearTimeout(smTimer);
      smTimer = window.setTimeout(smClose, 150);
    }

    // Hover (intent delay on close)
    smItem.addEventListener("mouseenter", smOpen);
    smItem.addEventListener("mouseleave", smScheduleClose);
    smDrawer.addEventListener("mouseenter", smOpen);
    smDrawer.addEventListener("mouseleave", smScheduleClose);

    // Keyboard focus in/out of the item or drawer
    smItem.addEventListener("focusin", smOpen);
    smDrawer.addEventListener("focusin", smOpen);
    function smFocusOut(e) {
      var to = e.relatedTarget;
      if (!smItem.contains(to) && !smDrawer.contains(to)) smClose();
    }
    smItem.addEventListener("focusout", smFocusOut);
    smDrawer.addEventListener("focusout", smFocusOut);

    // ArrowDown drops focus into the drawer
    smTrigger.addEventListener("keydown", function (e) {
      if (e.key === "ArrowDown") {
        e.preventDefault();
        smOpen();
        var first = smDrawer.querySelector("a");
        if (first) first.focus();
      }
    });

    // Touch: first tap opens instead of navigating
    smTrigger.addEventListener("click", function (e) {
      if (smCoarse && !smIsOpen()) {
        e.preventDefault();
        smOpen();
      }
    });

    // Esc closes and returns focus to the trigger (without reopening)
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && smIsOpen()) {
        smClose();
        smSuppress = true;
        smTrigger.focus();
        window.setTimeout(function () { smSuppress = false; }, 120);
      }
    });

    // Click outside closes
    document.addEventListener("click", function (e) {
      if (smIsOpen() && !smItem.contains(e.target) && !smDrawer.contains(e.target)) smClose();
    });

    // Keep the drawer aligned under SERVICES whenever the row geometry changes.
    // The scroll-revealed home glyph (.show-home) expands BEFORE the links over
    // ~300ms, shifting SERVICES right; smOffset is otherwise only run on open.
    // A ResizeObserver on the ROW catches the glyph expand/collapse and web-font
    // reflow; a scroll re-sync (only while open) tracks the 300ms transition
    // smoothly. One rAF coalesces both into a single read + write per frame.
    var smRow = smTrigger.closest(".site-nav__row");
    var smRaf = 0;
    function smSyncOffset() {
      if (smRaf) return;
      smRaf = window.requestAnimationFrame(function () {
        smRaf = 0;
        smOffset();
      });
    }
    if (smRow && "ResizeObserver" in window) {
      // Observe ONLY the row: writing --submenu-offset resizes the drawer, not
      // the row, so this can never re-trigger the observer (no RO loop).
      var smRO = new ResizeObserver(function () { smSyncOffset(); });
      smRO.observe(smRow);
    }
    window.addEventListener("scroll", function () { if (smIsOpen()) smSyncOffset(); }, { passive: true });
    window.addEventListener("resize", smSyncOffset, { passive: true });
    smSyncOffset(); // warm --submenu-offset before the first interaction
  }
})();
