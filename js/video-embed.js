/* video-embed.js — the embedded film (YouTube, Vimeo) as a click-to-play
 * cover with the site's play control, the way the old site did it (user ask,
 * 16 Oct 2026: "see how Vimeo is set up on the old site, cover image and
 * play icon, can we have the same approach?"). Nothing of the player loads
 * until the gesture: the figure carries the embed URL as data, the cover is
 * an art-directed still or, with none set, the player's own thumbnail
 * (YouTube's by its ID, Vimeo's by its oEmbed), and the click builds the
 * iframe with autoplay on and fades the cover — the same move as
 * work-video.js makes for a hosted film. Without JS a <noscript> iframe stands.
 * Drupal: Drupal.behaviors.iconVideoEmbed over [data-video-embed]. */
(function () {
  var roots = document.querySelectorAll("[data-video-embed]");
  roots.forEach(function (root) {
    if (root.hasAttribute("data-video-embed-ready")) return;
    root.setAttribute("data-video-embed-ready", "");
    var url = root.getAttribute("data-embed-url") || "";
    var title = root.getAttribute("data-embed-title") || "";
    var cover = root.querySelector(".video-embed__cover");
    if (cover && !cover.getAttribute("src")) {
      var yt = /(?:youtube(?:-nocookie)?\.com\/(?:embed\/|watch\?v=)|youtu\.be\/)([\w-]{6,})/.exec(url);
      var vm = /vimeo\.com\/(?:video\/)?(\d+)/.exec(url);
      if (yt) {
        cover.src = "https://img.youtube.com/vi/" + yt[1] + "/maxresdefault.jpg";
        cover.hidden = false;
      } else if (vm && window.fetch) {
        fetch("https://vimeo.com/api/oembed.json?url=" + encodeURIComponent("https://vimeo.com/" + vm[1]) + "&width=1280")
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (!d || !d.thumbnail_url) return;
            cover.src = d.thumbnail_url.replace(/-d_\d+x\d+/, "-d_1280x720");
            cover.hidden = false;
          })
          .catch(function () {});
      }
    }
    function start() {
      if (root.classList.contains("is-playing")) return;
      var f = document.createElement("iframe");
      f.src = url + (url.indexOf("?") > -1 ? "&" : "?") + "autoplay=1";
      f.title = title;
      f.setAttribute("allow", "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture");
      f.setAttribute("referrerpolicy", "strict-origin-when-cross-origin");
      f.setAttribute("allowfullscreen", "");
      root.classList.add("is-playing");
      root.appendChild(f);
      f.focus();
    }
    root.querySelectorAll("[data-video-embed-start]").forEach(function (el) {
      el.addEventListener("click", start);
    });
  });
})();
