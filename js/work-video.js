/* work-video.js — click-to-play film (born on The Athlete's Foot example,
 * Sep 2026). The cover art and the play control both start the film: reveal
 * the native controls, play, fade the art frame. No autoplay on purpose —
 * this is the SOUND-ON cut, so it waits for the gesture (which also
 * satisfies every mobile autoplay policy for free).
 * Drupal: Drupal.behaviors.iconWorkVideo over [data-work-video]. */
(function () {
  var roots = document.querySelectorAll("[data-work-video]");
  roots.forEach(function (root) {
    var video = root.querySelector(".work-video__media");
    if (!video) return;
    var start = function () {
      root.classList.add("is-playing");
      video.setAttribute("controls", "");
      var p = video.play();
      if (p && p.catch) p.catch(function () {});
    };
    root.querySelectorAll("[data-work-video-start]").forEach(function (el) {
      el.addEventListener("click", start, { once: true });
    });
  });
})();
