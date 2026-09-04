/* toast.js — THEME-AUTHORED (not generated): dismissal for the message
 * toasts (templates/misc/status-messages.html.twig). The × removes a toast;
 * a plain status also leaves on its own after a beat (6s), paused while it
 * is hovered or holds focus, so a reader can finish it. Warnings and errors
 * never auto-dismiss. Messages Drupal adds later through Drupal.Message land
 * in the same [data-drupal-messages] container and are picked up on the
 * next attach. */
((Drupal, once) => {
  var AUTO_MS = 6000;
  var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function leave(item) {
    if (!item.isConnected) return;
    var done = function () { item.remove(); };
    if (reduce) return done();
    item.classList.add("is-leaving");
    item.addEventListener("animationend", done, { once: true });
    setTimeout(done, 400); // if the animation never fires (display: none ancestors)
  }

  Drupal.behaviors.iconToast = {
    attach(context) {
      once("toast", "[data-toast]", context).forEach((item) => {
        var close = item.querySelector("[data-toast-close]");
        if (close) close.addEventListener("click", function () { leave(item); });
        if (item.getAttribute("data-toast") !== "status") return;
        var timer;
        var arm = function () { clearTimeout(timer); timer = setTimeout(function () { leave(item); }, AUTO_MS); };
        var hold = function () { clearTimeout(timer); };
        item.addEventListener("mouseenter", hold);
        item.addEventListener("mouseleave", arm);
        item.addEventListener("focusin", hold);
        item.addEventListener("focusout", arm);
        arm();
      });
    },
  };
})(Drupal, once);
