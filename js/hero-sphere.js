/* hero-sphere.js — Three.js orbiting photo-sphere hero for homeB.
 *
 * Twelve work cards (6 images ×2) sit on an INVISIBLE sphere via a Fibonacci
 * distribution, billboarding to always face the viewer, slowly orbiting a
 * centred "Make what matters" headline that lives at the sphere's centre depth
 * (so front cards occlude it and back cards are occluded by it). Drag to freely
 * orbit (horizontal → yaw, mouse-vertical → pitch, both unlimited), release for a
 * momentum ease-out where yaw blends back into the idle 50s/rev drift. Drag is
 * live only at scroll-top, before the scroll choreography engages.
 *
 * Scroll choreography (GSAP ScrollTrigger, homeB only): the hero pins and a
 * scrubbed sequence plays — the scroll spins the sphere up and lands the last
 * card (the looping website "hero video") dead-centre while the OTHER cards fall
 * + fade away under gravity; the centred video then zooms out to a full-viewport
 * plane BEHIND the still-pinned headline + nav (headline forced on top via
 * renderOrder), holds, then the headline reverse-rolls out as the pin releases
 * and the intro scrolls up. Reduced motion / no-WebGL / no-ScrollTrigger skip
 * it: static headline, normal flow.
 *
 * Architecture (hardened in design review):
 *  - Holders live at the SCENE ROOT, not parented to the rig. Each frame we
 *    compute a holder's world position by rotating its Fibonacci direction by
 *    the rig quaternion, then billboard via a plain camera-quaternion copy — no
 *    inverse-parent math, no pole flipping.
 *  - Depth interleave is resolved by the DEPTH BUFFER (cards + text are opaque
 *    with alphaTest + depthWrite once settled), not a fragile painter sort, so
 *    there's no equator popping. Cards are transparent only while blooming in.
 *  - One hand-rolled rAF loop owns idle yaw, per-card bob, drag, momentum and
 *    billboarding. GSAP drives only the one-shot entrance.
 *
 * Degrades gracefully: no Three.js / no WebGL / lost context → the DOM <h1>
 * fallback shows on the black backdrop; prefers-reduced-motion → one static
 * frame, no loop, no drag. The accessible <h1> is always in the DOM; the canvas
 * is decorative.
 *
 * Drupal: Drupal.behaviors.iconHeroSphere via `icon/hero-sphere` (dep: three;
 * gsap for the entrance). */
(function () {
  "use strict";

  var section = document.querySelector("[data-hero-sphere]");
  if (!section || section.dataset.heroSphereInit) return;

  var canvas = section.querySelector("[data-hero-sphere-canvas]");
  var THREE = window.THREE;
  var gsap = window.gsap;
  var ScrollTrigger = window.ScrollTrigger;
  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function hasWebGL() {
    try {
      var c = document.createElement("canvas");
      return !!(
        window.WebGLRenderingContext &&
        (c.getContext("webgl2") || c.getContext("webgl"))
      );
    } catch (e) {
      return false;
    }
  }

  // No engine / no GPU → leave the DOM headline visible on the black backdrop.
  if (!THREE || !canvas || !hasWebGL()) {
    section.classList.add("no-webgl");
    return;
  }
  section.dataset.heroSphereInit = "1";

  /* ------------------------------------------------------------------ config */
  // Hero assets — 10 images + 4 videos, all cover-cropped to 16:9 landscape.
  // The first three videos are interleaved (indices 3, 7, 11) so the Fibonacci
  // layout spreads them; the LAST card (index 13) is the website-loop "hero
  // video" the scroll choreography spins to centre and zooms full-screen.
  var ASSETS = [
    { url: "../assets/hero/Header_image_1.png" },
    { url: "../assets/hero/Breakout_image_01_5.png" },
    { url: "../assets/hero/NDIS-hero3_0.png" },
    { url: "../assets/hero/Banner-Fit For Every Run.mp4", video: true },
    { url: "../assets/hero/MOAD-Creative-01.jpg" },
    { url: "../assets/hero/iStock-1322801149.jpg" },
    { url: "../assets/hero/PPV-Header-v1.jpg" },
    { url: "../assets/hero/banner-vid-nike-mara.mp4", video: true },
    { url: "../assets/hero/iCQ Banner 4.jpg" },
    { url: "../assets/hero/Image_02_0.png" },
    { url: "../assets/hero/IMG_6053 1 (2).png" },
    { url: "../assets/hero/02f8d6fd-380b-4873-9a0e-43fe1d24a7bd.mp4", video: true },
    { url: "../assets/hero/Woman writing her goal 3.png" },
    { url: "../assets/hero/website_V14_SHORT LOOP_1 (1).mp4", video: true }
  ];
  var CARD_COUNT = ASSETS.length; // 14 (last = the takeover hero video)
  var R = 1.6; // sphere world radius
  var FOV = 40; // vertical fov (deg)
  var RADIUS_FRACTION = 0.4; // short-axis orbit radius ≈ 40% of min(vw,vh)
  var OVAL_MAX = 2.2; // long-axis stretch cap; ~2.2:1 covers common phones,
  //                     past it (ultra-wide/very-tall) the oval holds ~2.2:1
  var CARD_ASPECT = 16 / 9; // landscape card aspect (width / height)
  // Card size scales with the screen via a clamp: on-screen width =
  // clamp(110px, 22% of the smaller viewport dimension, 220px); height follows
  // the 16:9 aspect. Smaller on phones, larger on desktops, fluid on resize.
  var CARD_W_MIN = 110, CARD_W_MAX = 220, CARD_W_FRAC = 0.22;
  // Depth falloff: cards on the back half of the sphere shrink + darken toward
  // these factors (1 = front/unchanged), so the background recedes.
  var DEPTH_SCALE_MIN = 0.55; // back cards → 55% size
  var DEPTH_DIM_MIN = 0.4; // back cards → 40% brightness
  var TEX_MAX_EDGE = 1024; // downscale source images (max ≈300px wide on screen)
  var GA = Math.PI * (3 - Math.sqrt(5)); // golden angle ≈ 2.39996

  var W_IDLE = (2 * Math.PI) / 50; // idle yaw: 1 rev / 50s
  var W_BOB = (2 * Math.PI) / 4; // bob period 4s
  var BOB_PX = 6;

  var DRAG_YAW = 0.005; // rad / px (horizontal → yaw, free)
  var DRAG_PITCH = 0.004; // rad / px (vertical drag → pitch; mouse/pen only)
  var FRICTION = 0.97; // per-frame @60fps momentum decay

  /* Scroll-choreography tuning (progress 0..1 across the pinned stage). The
     sphere spins up and lands the hero video dead-centre, which then zooms to
     full-screen behind the headline; meanwhile the other cards fall away. */
  var FALL_STAGGER = 0.45; // spread of per-card fall starts (the gravity wave)
  var DROP_END = 0.5; // the non-hero cards have fallen away by here
  var SPIN_END = 0.55; // the spin lands the hero video dead-centre by here
  var SPIN_REVS = 2; // extra whole revolutions whipped through before it settles
  var ZOOM_END = 0.78; // the hero video has zoomed to full-screen by here
  var HEAD_OUT_START = 0.84; // headline begins its reverse-roll out here

  var DPR_CAP = 2;
  var ENABLE_HOVER = false; // hover-to-advance deferred to a follow-up

  /* Scroll-choreography state (driven by ScrollTrigger.onUpdate → choreo()). */
  var HERO_INDEX = CARD_COUNT - 1; // the website-loop video card (spins + zooms)
  var dropProgress = 0; // 0 = sphere intact, 1 = the non-hero cards have fallen
  var dropBegan = false; // cards flipped transparent for the fall-fade
  var headlineReveal = 1, lastHeadline = 1; // 1 = shown, 0 = reversed out
  var spinProgress = 0; // 0 = free orbit, 1 = hero video landed dead-centre
  var zoomProgress = 0; // 0 = hero card on the sphere, 1 = full-screen
  var choreoEngaged = false; // scroll owns rigQuat while true (idle/momentum off)
  var rigStart = new THREE.Quaternion(); // rig snapshot when the scroll engages
  var rigTarget = new THREE.Quaternion(); // maps the hero card's dir → centre (+Z)
  var _qSlerp = new THREE.Quaternion();
  var _axisZ = new THREE.Vector3(0, 0, 1);
  var st = null; // the ScrollTrigger instance

  function clamp01(x) { return x < 0 ? 0 : x > 1 ? 1 : x; }
  function lerp(a, b, t) { return a + (b - a) * t; }
  function easeInOut(t) { return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2; }

  /* ------------------------------------------------------------------ scene */
  var renderer = new THREE.WebGLRenderer({
    canvas: canvas,
    alpha: true,
    antialias: true,
    powerPreference: "high-performance"
  });
  renderer.setClearColor(0x000000, 0); // transparent — CSS paints the backdrop
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, DPR_CAP));
  if ("outputColorSpace" in renderer) renderer.outputColorSpace = THREE.SRGBColorSpace;
  var maxAniso = renderer.capabilities.getMaxAnisotropy
    ? renderer.capabilities.getMaxAnisotropy()
    : 1;

  var scene = new THREE.Scene();
  var camera = new THREE.PerspectiveCamera(FOV, 1, 0.1, 100);
  // Camera looks down -Z from +Z at the origin → quaternion is identity, so a
  // billboard is just `holder.quaternion.copy(camera.quaternion)`.

  var fovY = (FOV * Math.PI) / 180;
  var tanHalf = Math.tan(fovY / 2);
  var pxPerWorld = 1; // px per world unit at centre depth (set in fit())
  var cardWWorld = 1, cardHWorld = 1;
  var ellipseH = 1, ellipseV = 1; // per-axis orbit stretch (set in fit())

  function fit() {
    var vw = section.clientWidth || window.innerWidth;
    var vh = section.clientHeight || window.innerHeight;

    // Reshape the orbit envelope to the browser ratio — a circle when square, a
    // wide oval in landscape, a tall oval in portrait. The SHORT axis sets a
    // base radius (≈40% of the smaller dimension); the LONG axis is stretched by
    // the viewport's aspect, capped by OVAL_MAX so ultra-wide/tall screens don't
    // distort too far. Re-runs on every resize, so it tracks the window fluidly.
    var shortPx = RADIUS_FRACTION * Math.min(vw, vh);
    var stretch = Math.min(Math.max(vw, vh) / Math.min(vw, vh), OVAL_MAX);
    ellipseH = vw >= vh ? stretch : 1; // landscape stretches horizontally
    ellipseV = vw >= vh ? 1 : stretch; // portrait stretches vertically

    // Card size is anchored to the short-axis radius (stable across ratios):
    // project R to shortPx on the vertical axis, then size cards from that.
    var camDist = (R * (vh / 2)) / (shortPx * tanHalf);
    camera.position.set(0, 0, camDist);
    camera.aspect = vw / vh;
    camera.updateProjectionMatrix();
    camera.updateMatrixWorld();

    pxPerWorld = shortPx / R;
    // Clamp the on-screen card width to the viewport, then derive world units.
    var cardWPx = Math.max(CARD_W_MIN, Math.min(CARD_W_FRAC * Math.min(vw, vh), CARD_W_MAX));
    cardWWorld = cardWPx / pxPerWorld;
    cardHWorld = cardWWorld / CARD_ASPECT;

    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, DPR_CAP));
    renderer.setSize(vw, vh, false);

    if (textPlane) layoutText(vw, vh);
  }

  /* -------------------------------------------------------- fibonacci sphere */
  var cards = [];
  function fibonacciDir(i, n) {
    var y = 1 - ((i + 0.5) * 2) / n; // (i+0.5) centring → no card on a pole
    var r = Math.sqrt(Math.max(0, 1 - y * y));
    var th = i * GA;
    return new THREE.Vector3(Math.cos(th) * r, y, Math.sin(th) * r);
  }

  /* ------------------------------------------------------------ soft shadow */
  function makeShadowTexture() {
    var w = 320, h = 180; // 16:9 to match the landscape cards
    var c = document.createElement("canvas");
    c.width = w;
    c.height = h;
    var x = c.getContext("2d");
    x.clearRect(0, 0, w, h);
    x.fillStyle = "#000";
    x.shadowColor = "rgba(0,0,0,1)";
    x.shadowBlur = 36;
    var inset = 42;
    roundRect(x, inset, inset, w - inset * 2, h - inset * 2, 12);
    x.fill();
    var tex = new THREE.CanvasTexture(c);
    if ("colorSpace" in tex) tex.colorSpace = THREE.SRGBColorSpace;
    return tex;
  }
  function roundRect(x, left, top, w, h, r) {
    x.beginPath();
    x.moveTo(left + r, top);
    x.arcTo(left + w, top, left + w, top + h, r);
    x.arcTo(left + w, top + h, left, top + h, r);
    x.arcTo(left, top + h, left, top, r);
    x.arcTo(left, top, left + w, top, r);
    x.closePath();
  }

  /* ------------------------------------------------------- texture loading */
  var videos = []; // <video> sources kept alive for play/pause/dispose

  // Cover-crop a texture to the card's landscape aspect (no letterboxing).
  function coverCrop(tex, ia) {
    var ta = CARD_ASPECT; // ≈1.78
    tex.center.set(0.5, 0.5);
    if (ia > ta) tex.repeat.set(ta / ia, 1); // source wider → trim sides
    else tex.repeat.set(1, ia / ta); // source taller → trim top/bottom
  }

  // Transparent 1×1 placeholder: with alphaTest:0.5 every texel is discarded,
  // so a failed load leaves an invisible gap — never a blank quad popping in.
  function placeholderTexture() {
    var c = document.createElement("canvas");
    c.width = c.height = 1;
    return new THREE.CanvasTexture(c);
  }

  function loadImageAsset(url) {
    return new Promise(function (resolve) {
      var img = new Image();
      img.onload = function () {
        var scale = Math.min(1, TEX_MAX_EDGE / Math.max(img.width, img.height));
        var cw = Math.max(1, Math.round(img.width * scale));
        var ch = Math.max(1, Math.round(img.height * scale));
        var c = document.createElement("canvas");
        c.width = cw;
        c.height = ch;
        c.getContext("2d").drawImage(img, 0, 0, cw, ch);

        var tex = new THREE.CanvasTexture(c);
        if ("colorSpace" in tex) tex.colorSpace = THREE.SRGBColorSpace;
        tex.anisotropy = maxAniso;
        tex.generateMipmaps = true;
        tex.minFilter = THREE.LinearMipmapLinearFilter;
        tex.magFilter = THREE.LinearFilter;
        coverCrop(tex, cw / ch);
        tex.needsUpdate = true;
        resolve(tex);
      };
      img.onerror = function () { resolve(placeholderTexture()); };
      img.src = encodeURI(url);
    });
  }

  function loadVideoAsset(url) {
    return new Promise(function (resolve) {
      var v = document.createElement("video");
      v.muted = true;
      v.loop = true;
      v.playsInline = true;
      v.setAttribute("muted", "");
      v.setAttribute("playsinline", "");
      v.preload = "auto";
      v.className = "hero-sphere__source"; // off-screen, kept renderable
      section.appendChild(v);
      videos.push(v);

      var done = false;
      function ready() {
        if (done) return;
        done = true;
        var tex = new THREE.VideoTexture(v);
        if ("colorSpace" in tex) tex.colorSpace = THREE.SRGBColorSpace;
        tex.minFilter = THREE.LinearFilter; // video frames: no mipmaps
        tex.magFilter = THREE.LinearFilter;
        tex.generateMipmaps = false;
        var ia = v.videoWidth && v.videoHeight
          ? v.videoWidth / v.videoHeight
          : CARD_ASPECT;
        coverCrop(tex, ia);
        resolve(tex);
      }
      v.addEventListener("loadeddata", ready);
      v.addEventListener("canplay", ready);
      v.addEventListener("error", function () {
        if (!done) { done = true; resolve(placeholderTexture()); }
      });
      v.src = encodeURI(url);
      // Respect reduced motion: load the first frame but don't autoplay.
      if (reduce) v.load();
      else { var p = v.play && v.play(); if (p && p.catch) p.catch(function () {}); }
      window.setTimeout(ready, 5000); // safety: proceed even if events lag
    });
  }

  function loadAsset(asset) {
    return asset.video ? loadVideoAsset(asset.url) : loadImageAsset(asset.url);
  }

  /* --------------------------------------------------------- centre headline */
  // Three lines mirroring the brand hero — "Make" italic, all centred, white,
  // Miller-Text serif — drawn to a canvas texture so it can be occluded by
  // cards (depth) and scrambled in on entrance.
  // Match the original hero headline: "Make" italic-left, "what" right,
  // "matters" left — staggered within a centred block.
  var LINES = [
    { text: "Make", italic: true, align: "left" },
    { text: "what", italic: false, align: "right" },
    { text: "matters", italic: false, align: "left" }
  ];
  var textCanvas = document.createElement("canvas");
  var textCtx = textCanvas.getContext("2d");
  var textTex = new THREE.CanvasTexture(textCanvas);
  if ("colorSpace" in textTex) textTex.colorSpace = THREE.SRGBColorSpace;
  // Smooth, ~1:1 sampling — no mipmap softening, no alphaTest hard edges.
  textTex.generateMipmaps = false;
  textTex.minFilter = THREE.LinearFilter;
  textTex.magFilter = THREE.LinearFilter;
  var textPlane = null;
  var textFontPx = 120;
  var textLineH = 110;

  function textFontFor(vw) {
    // clamp(32px, 8vw, 136px) — single fluid scale, ~midway between the original
    // (~180px) and the 50%-reduced (88px) headline.
    // Matches `clamp(2rem, 8vw, 8.5rem)` in hero-sphere.css (16px root).
    return Math.max(32, Math.min(0.08 * vw, 136));
  }

  function layoutText(vw, vh) {
    if (!textPlane) return;
    textFontPx = textFontFor(vw);
    textLineH = textFontPx * 0.9;
    // Supersample the glyph canvas (≥2×) so the headline stays crisp.
    var dpr = Math.max(2, Math.min(window.devicePixelRatio || 1, DPR_CAP));

    // Measure widest line to size the canvas.
    var pad = textFontPx * 0.3;
    setFont(textCtx, textFontPx, false);
    var maxW = 0;
    for (var i = 0; i < LINES.length; i++) {
      setFont(textCtx, textFontPx, LINES[i].italic);
      maxW = Math.max(maxW, textCtx.measureText(LINES[i].text).width);
    }
    var cssW = Math.ceil(maxW + pad * 2);
    var cssH = Math.ceil(textLineH * LINES.length + pad * 2);

    textCanvas.width = Math.round(cssW * dpr);
    textCanvas.height = Math.round(cssH * dpr);
    textCanvas._cssW = cssW;
    textCanvas._cssH = cssH;
    textCanvas._dpr = dpr;

    // Size the plane so the canvas projects ≈ 1:1 px at centre depth.
    textPlane.scale.set(cssW / pxPerWorld, cssH / pxPerWorld, 1);
    drawText(1); // resolved
  }

  function setFont(ctx, px, italic) {
    ctx.font = (italic ? "italic " : "") + "700 " + px + "px 'miller-text', Georgia, serif";
  }

  // Per-character "rolling" reveal (GSAP "Rolling text" style): each glyph flips
  // in around its horizontal axis — a vertical unfold from edge-on to flat with a
  // back.out overshoot — staggered across the headline so a roll waves across.
  // progress 0..1 drives elapsed seconds.
  var REVEAL_STAGGER = 0.07; // sec between characters (the roll wave)
  var REVEAL_CHAR_DUR = 0.6; // sec per character flip
  var TOTAL_CHARS = 0;
  for (var _li = 0; _li < LINES.length; _li++) TOTAL_CHARS += LINES[_li].text.length;
  var REVEAL_TOTAL = (TOTAL_CHARS - 1) * REVEAL_STAGGER + REVEAL_CHAR_DUR;

  // back.out overshoot ease (≈ GSAP back.out(1.8)).
  function backOut(p) {
    var s = 1.8;
    p -= 1;
    return p * p * ((s + 1) * p + s) + 1;
  }

  function drawText(progress) {
    var dpr = textCanvas._dpr || 1;
    var cssW = textCanvas._cssW || textCanvas.width;
    var cssH = textCanvas._cssH || textCanvas.height;
    textCtx.setTransform(dpr, 0, 0, dpr, 0, 0);
    textCtx.clearRect(0, 0, cssW, cssH);
    textCtx.textBaseline = "middle";
    textCtx.textAlign = "left";
    textCtx.fillStyle = "#ffffff";

    var te = progress * REVEAL_TOTAL;
    var pad = textFontPx * 0.3;
    var charIndex = 0;

    for (var li = 0; li < LINES.length; li++) {
      var line = LINES[li];
      setFont(textCtx, textFontPx, line.italic);
      var y = pad + textLineH * (li + 0.5);
      var src = line.text;

      // Measure glyph advances to lay characters out left→right (the resting
      // layout matches the original; each glyph then animates in place).
      var widths = [], totalW = 0, ci;
      for (ci = 0; ci < src.length; ci++) {
        var w = textCtx.measureText(src[ci]).width;
        widths.push(w);
        totalW += w;
      }
      var x = line.align === "right" ? cssW - pad - totalW : pad;

      for (ci = 0; ci < src.length; ci++) {
        var lp = (te - charIndex * REVEAL_STAGGER) / REVEAL_CHAR_DUR;
        lp = lp < 0 ? 0 : lp > 1 ? 1 : lp;
        var e = backOut(lp);
        var scaleY = e; // 0 (edge-on) → 1 (flat) with overshoot = the flip
        var alpha = lp * 2.2;
        alpha = alpha < 0 ? 0 : alpha > 1 ? 1 : alpha;

        textCtx.save();
        textCtx.translate(x + widths[ci] / 2, y);
        textCtx.scale(1, scaleY); // vertical unfold ≈ rotationX flip
        textCtx.globalAlpha = alpha;
        textCtx.fillText(src[ci], -widths[ci] / 2, 0);
        textCtx.restore();

        x += widths[ci];
        charIndex++;
      }
    }
    textCtx.globalAlpha = 1;
    textTex.needsUpdate = true;
  }

  /* ----------------------------------------------------------- build scene */
  var sharedGeo = new THREE.PlaneGeometry(1, 1);
  var shadowTex = makeShadowTexture();

  function buildCards(textures) {
    for (var i = 0; i < CARD_COUNT; i++) {
      var tex = textures[i]; // one hero asset per card
      var holder = new THREE.Object3D();

      var shadowMat = new THREE.MeshBasicMaterial({
        map: shadowTex,
        color: 0x000000,
        transparent: true,
        opacity: 0,
        depthWrite: false,
        depthTest: true,
        toneMapped: false
      });
      var shadow = new THREE.Mesh(sharedGeo, shadowMat);
      shadow.scale.set(1.18, 1.18, 1);
      shadow.position.z = -0.012;
      shadow.renderOrder = -1;
      holder.add(shadow);

      var photoMat = new THREE.MeshBasicMaterial({
        map: tex,
        transparent: true, // entrance fade; flips to opaque on bloom complete
        opacity: 0,
        alphaTest: 0.5,
        depthWrite: false,
        depthTest: true,
        toneMapped: false
      });
      var photo = new THREE.Mesh(sharedGeo, photoMat);
      photo.renderOrder = 0;
      holder.add(photo);

      scene.add(holder);
      cards.push({
        dir: fibonacciDir(i, CARD_COUNT),
        holder: holder,
        photoMat: photoMat,
        shadowMat: shadowMat,
        phase: i * GA,
        depthT: 0, // updated each frame (1 = front-most)
        // entrance state
        opacity: 0,
        scaleMul: 0.7,
        spawn: 0.85,
        settled: false
      });
    }
  }

  function buildText() {
    // Alpha-blended (no alphaTest) for smooth glyph edges. The opaque cards
    // write depth, so the text still interleaves correctly: depthTest hides it
    // behind front cards while it draws over the back ones.
    var mat = new THREE.MeshBasicMaterial({
      map: textTex,
      transparent: true,
      depthWrite: false,
      depthTest: true,
      toneMapped: false
    });
    textPlane = new THREE.Mesh(sharedGeo, mat);
    textPlane.position.set(0, 0, 0);
    textPlane.renderOrder = 0;
    scene.add(textPlane);
  }

  function setCardSteady(c) {
    c.settled = true;
    c.photoMat.transparent = false;
    c.photoMat.depthWrite = true;
    c.photoMat.opacity = 1;
    c.photoMat.needsUpdate = true;
  }

  function setCardFull(c) {
    c.opacity = 1;
    c.scaleMul = 1;
    c.spawn = 1;
    c.photoMat.opacity = 1;
    c.shadowMat.opacity = 0.35;
    setCardSteady(c);
  }

  /* --------------------------------------------------- per-frame transforms */
  var rigQuat = new THREE.Quaternion(); // accumulated free-orbit orientation
  var tmp = new THREE.Vector3();
  var _axisY = new THREE.Vector3(0, 1, 0);
  var _axisX = new THREE.Vector3(1, 0, 0);
  var _dq = new THREE.Quaternion();

  var yawVel = W_IDLE; // ang. velocity about world Y (idle spin + yaw fling)
  var pitchVel = 0; // ang. velocity about world X (pitch fling)
  var dragging = false;
  var tSec = 0;

  // Rotate the rig in WORLD space (premultiply) so left/right always spins the
  // screen-vertical axis and up/down the screen-horizontal axis at any angle —
  // a free orbit with no limits (quaternion, so no gimbal lock at vertical).
  function rotateRig(yawDelta, pitchDelta) {
    if (yawDelta) { _dq.setFromAxisAngle(_axisY, yawDelta); rigQuat.premultiply(_dq); }
    if (pitchDelta) { _dq.setFromAxisAngle(_axisX, pitchDelta); rigQuat.premultiply(_dq); }
    rigQuat.normalize();
  }

  function applyTransforms() {
    var vw = section.clientWidth || window.innerWidth;
    var vh = section.clientHeight || window.innerHeight;
    var vhWorld = vh / pxPerWorld; // world-space viewport height (fall distance)
    var n = cards.length;
    for (var i = 0; i < cards.length; i++) {
      var c = cards[i];
      var isHero = i === HERO_INDEX;

      // Hero video zoom: lift it out of the sphere to a centred, full-viewport
      // plane (cover-cropped to the screen aspect). It keeps depthWrite off, so
      // the headline (renderOrder 999) always draws over it — "behind" the title.
      if (isHero && zoomProgress > 0) {
        var ez = easeInOut(zoomProgress);
        c.holder.position.set(0, 0, lerp(R, 0, ez)); // glide to the centre plane
        c.holder.quaternion.copy(camera.quaternion);
        c.holder.scale.set(
          lerp(cardWWorld, vw / pxPerWorld, ez),
          lerp(cardHWorld, vh / pxPerWorld, ez),
          1
        );
        c.photoMat.color.setScalar(1);
        c.photoMat.opacity = 1;
        c.shadowMat.opacity = 0;
        coverCropViewport(c.photoMat.map, vw / vh, ez);
        continue;
      }

      tmp.copy(c.dir).applyQuaternion(rigQuat); // unit direction (rotated)
      var depthT = (tmp.z + 1) * 0.5; // 0 = back of sphere, 1 = front
      c.depthT = depthT;
      tmp.multiplyScalar(R * c.spawn);
      // Reshape to the browser ratio: stretch the on-screen x/y (camera is
      // axis-aligned, so world x/y == screen x/y), leaving depth (z) intact so
      // the perspective + depth interleave are unchanged.
      tmp.x *= ellipseH;
      tmp.y *= ellipseV;
      tmp.y += Math.sin(tSec * W_BOB + c.phase) * (BOB_PX / pxPerWorld);

      // Scroll choreography: the NON-hero cards fall straight down under gravity
      // and fade, staggered so the sphere "releases" as a wave. The hero card is
      // exempt — it's being spun to centre and will zoom instead.
      var fadeMul = 1;
      if (dropProgress > 0 && !isHero) {
        var sStart = n > 1 ? (i / (n - 1)) * FALL_STAGGER : 0;
        var lp = clamp01((dropProgress - sStart) / (1 - FALL_STAGGER));
        tmp.y -= lp * lp * (vhWorld * 1.15); // quadratic = gravity, off-screen
        fadeMul = 1 - lp;
      }

      c.holder.position.copy(tmp);
      c.holder.quaternion.copy(camera.quaternion);
      // Depth falloff: background (far) cards shrink + darken so they recede.
      var s = c.scaleMul * (DEPTH_SCALE_MIN + (1 - DEPTH_SCALE_MIN) * depthT);
      c.holder.scale.set(cardWWorld * s, cardHWorld * s, 1);
      c.photoMat.color.setScalar(DEPTH_DIM_MIN + (1 - DEPTH_DIM_MIN) * depthT);
      c.photoMat.opacity = c.opacity * fadeMul;
      c.shadowMat.opacity = 0.35 * c.opacity * fadeMul * (0.35 + 0.65 * depthT);
    }
    if (textPlane) textPlane.quaternion.copy(camera.quaternion);
  }

  /* ----------------------------------------------------- drag interaction */
  // Free-orbit drag is live only at scroll-top — once the choreography starts
  // dropping the cards (dropProgress > 0), pointer-down is ignored.
  var lastX = 0, lastY = 0, lastT = 0;

  function endDrag(e) {
    dragging = false;
    section.classList.remove("is-dragging");
    if (canvas.releasePointerCapture && e && e.pointerId != null) {
      try { canvas.releasePointerCapture(e.pointerId); } catch (err) {}
    }
  }

  function onPointerDown(e) {
    if (reduce || dropProgress > 0.02) return;
    if (e.target.closest && e.target.closest(".site-header, [data-nav]")) return;
    dragging = true;
    section.classList.add("is-dragging");
    lastX = e.clientX;
    lastY = e.clientY;
    lastT = (window.performance && performance.now()) || 0;
    yawVel = 0;
    pitchVel = 0;
    if (canvas.setPointerCapture && e.pointerId != null) {
      try { canvas.setPointerCapture(e.pointerId); } catch (err) {}
    }
  }

  function onPointerMove(e) {
    if (!dragging) return;

    var now = (window.performance && performance.now()) || lastT + 16;
    var dt = Math.max(0.008, (now - lastT) / 1000);
    var dx = e.clientX - lastX;
    var dy = e.clientY - lastY;

    var yawDelta = dx * DRAG_YAW;
    // Yaw on ANY pointer — left/right always spins the sphere. Pitch on mouse/pen
    // in EITHER vertical direction (up and down both rotate now); touch keeps
    // vertical for page scroll (pan-y) so the scroll choreography still works.
    var pitchDelta = e.pointerType !== "touch" ? dy * DRAG_PITCH : 0;
    rotateRig(yawDelta, pitchDelta);
    // Low-passed throw velocities for the release fling.
    yawVel = yawVel * 0.4 + (yawDelta / dt) * 0.6;
    if (pitchDelta) pitchVel = pitchVel * 0.4 + (pitchDelta / dt) * 0.6;

    lastX = e.clientX;
    lastY = e.clientY;
    lastT = now;
  }

  function onPointerUp(e) {
    if (!dragging) return;
    endDrag(e);
  }

  /* ----------------------------------------------------------- main loop */
  var clock = new THREE.Clock();
  var rafId = 0;
  var ready = false;
  var contextLost = false;

  function frame() {
    var dt = Math.min(clock.getDelta(), 0.05); // clamp tab-blur spikes
    tSec += dt;

    if (!dragging && !choreoEngaged) {
      // Momentum: yaw decays toward the idle spin (never stops), pitch decays to
      // zero and holds wherever it was left — both axes free, no clamp, no snap.
      // While the scroll choreography is engaged it owns the rig, so this is off.
      var decay = Math.pow(FRICTION, dt * 60);
      yawVel = W_IDLE + (yawVel - W_IDLE) * decay;
      pitchVel *= decay;
      rotateRig(yawVel * dt, pitchVel * dt);
    }

    applyTransforms();
    renderer.render(scene, camera);

    if (!ready) {
      ready = true;
      canvas.classList.add("is-ready");
    }
    rafId = window.requestAnimationFrame(frame);
  }

  /* --------------------------------------------------------- entrance */
  var entranceTl = null;
  function runEntrance() {
    if (!gsap) {
      cards.forEach(setCardFull);
      drawText(1);
      return;
    }
    entranceTl = gsap.timeline();

    // 1) Reveal the headline character-by-character, redrawing the canvas texture.
    var sp = { p: 0 };
    entranceTl.to(sp, {
      p: 1,
      duration: REVEAL_TOTAL,
      ease: "none",
      onUpdate: function () { drawText(sp.p); },
      onComplete: function () { drawText(1); }
    }, 0);

    // 2) Cards bloom in spiral order (~1.5s in, overlapping the scramble tail).
    cards.forEach(function (c, i) {
      entranceTl.to(c, {
        opacity: 1,
        scaleMul: 1,
        spawn: 1,
        duration: 0.6,
        ease: "back.out(1.4)",
        onComplete: function () { setCardSteady(c); }
      }, 1.5 + i * 0.12);
    });
  }

  /* ------------------------------------------------- scroll choreography */
  // Cards must be transparent (and stop writing depth) to fade as they fall; the
  // headline jumps above them so it stays crisp and unoccluded through the drop.
  function beginDrop() {
    dropBegan = true;
    if (textPlane) textPlane.renderOrder = 999;
    for (var i = 0; i < cards.length; i++) {
      var m = cards[i].photoMat;
      m.transparent = true;
      m.depthWrite = false;
      m.needsUpdate = true;
    }
  }
  // Scrubbed back to the top → restore the pristine, depth-interleaved sphere.
  function endDrop() {
    dropBegan = false;
    if (textPlane) textPlane.renderOrder = 0;
    for (var i = 0; i < cards.length; i++) {
      if (cards[i].settled) setCardSteady(cards[i]);
    }
  }

  // Land the hero video dead-centre by mapping its sphere direction onto +Z, then
  // whip the rig through a couple of decaying revolutions into that pose — so a
  // scroll spins the sphere up and the website video resolves to the centre.
  function setRigSpin(sp) {
    var land = easeInOut(sp);
    var spin = SPIN_REVS * 2 * Math.PI * (1 - sp); // unwinds to 0 as it settles
    _qSlerp.slerpQuaternions(rigStart, rigTarget, land);
    _dq.setFromAxisAngle(_axisY, spin);
    rigQuat.multiplyQuaternions(_dq, _qSlerp); // world-Y spin ∘ homing
    rigQuat.normalize();
  }

  // Snapshot the rig when the scroll first engages so the spin homes from wherever
  // the user left the free orbit; the scroll owns the rig from here.
  function engageChoreo() {
    choreoEngaged = true;
    rigStart.copy(rigQuat);
    if (textPlane) textPlane.renderOrder = 999; // headline stays above everything
  }
  // Scrubbed back to the top → hand the rig back to the idle/momentum loop and
  // restore the hero video's native crop.
  function disengageChoreo() {
    choreoEngaged = false;
    if (textPlane) textPlane.renderOrder = 0;
    if (cards[HERO_INDEX]) coverCropViewport(cards[HERO_INDEX].photoMat.map, 1, 0);
  }

  // Cross-fade the hero video's crop from its native 16:9 toward a viewport cover
  // as it zooms (t 0→1), so it fills any screen aspect without distortion.
  function coverCropViewport(tex, va, t) {
    if (!tex) return;
    var ia = CARD_ASPECT; // the hero video is 16:9
    var rx = 1, ry = 1;
    if (ia > va) rx = va / ia; else ry = ia / va;
    tex.center.set(0.5, 0.5);
    tex.repeat.set(lerp(1, rx, t), lerp(1, ry, t));
  }

  // One scrubbed tick: spin the sphere + land the hero video, fall the rest away,
  // zoom the hero video full-screen, then reverse-roll the headline out.
  function choreo(p) {
    if (p > 0.0008 && !choreoEngaged) engageChoreo();
    else if (p <= 0.0008 && choreoEngaged) disengageChoreo();

    // Non-hero cards fall + fade away during the spin.
    dropProgress = clamp01(p / DROP_END);
    if (dropProgress > 0.001 && !dropBegan) beginDrop();
    else if (dropProgress <= 0.001 && dropBegan) endDrop();

    // Spin the sphere up, landing the hero video dead-centre.
    spinProgress = clamp01(p / SPIN_END);
    if (choreoEngaged) setRigSpin(spinProgress);

    // Zoom the centred hero video out to full-screen (behind the headline).
    zoomProgress = clamp01((p - SPIN_END) / (ZOOM_END - SPIN_END));

    // Headline reverse-rolls out at the tail.
    headlineReveal = p <= HEAD_OUT_START
      ? 1
      : 1 - clamp01((p - HEAD_OUT_START) / (1 - HEAD_OUT_START));
    if (Math.abs(headlineReveal - lastHeadline) > 0.0015) {
      drawText(headlineReveal); // reuse the rolling reveal, played in reverse
      lastHeadline = headlineReveal;
    }
  }

  function setupScroll() {
    var ST = window.ScrollTrigger;
    if (reduce || !gsap || !ST) return;
    gsap.registerPlugin(ST);
    // Don't re-pin on the mobile URL-bar show/hide resize — the stage length is
    // viewport-relative, so otherwise it would jump mid-scroll on phones.
    if (ST.config) ST.config({ ignoreMobileResize: true });
    // The rig pose that puts the hero card's direction at screen centre (+Z).
    if (cards[HERO_INDEX]) rigTarget.setFromUnitVectors(cards[HERO_INDEX].dir, _axisZ);
    st = ST.create({
      trigger: section,
      start: "top top",
      end: function () {
        return "+=" + Math.round((section.clientHeight || window.innerHeight) * 3);
      },
      pin: true,
      pinSpacing: true,
      scrub: true,
      anticipatePin: 1,
      onUpdate: function (self) { choreo(self.progress); }
    });
    choreo(0);

    // The intro logo-loop video (height:auto) loads its metadata late and shifts
    // the layout below the pin; refresh once so the stage is measured against the
    // settled page and a fast early scroll can't catch a mid-flight reflow.
    var introVid = document.querySelector(".logo-loop__video");
    if (introVid) {
      if (introVid.readyState >= 1) ST.refresh();
      else introVid.addEventListener("loadedmetadata", function () { ST.refresh(); }, { once: true });
    }
  }

  /* --------------------------------------------------------- resize / vis */
  var resizePending = false;
  function onResize() {
    if (resizePending) return;
    resizePending = true;
    window.requestAnimationFrame(function () {
      resizePending = false;
      fit();
      if (reduce) renderStatic();
    });
  }

  function playVideos() {
    if (reduce) return;
    videos.forEach(function (v) {
      var p = v.play && v.play();
      if (p && p.catch) p.catch(function () {});
    });
  }

  function onVisibility() {
    if (document.hidden) {
      if (rafId) { window.cancelAnimationFrame(rafId); rafId = 0; }
    } else if (!reduce && !rafId && !contextLost) {
      clock.getDelta(); // drop the gap
      playVideos(); // background tabs pause muted video — resume on return
      rafId = window.requestAnimationFrame(frame);
    }
  }

  /* --------------------------------------------------------- static frame */
  function renderStatic() {
    rigQuat.setFromAxisAngle(_axisY, -0.3);
    tSec = 0;
    applyTransforms();
    renderer.render(scene, camera);
    canvas.classList.add("is-ready");
  }

  /* ----------------------------------------------------------- teardown */
  var disposed = false;
  function dispose() {
    if (disposed) return;
    disposed = true;
    if (rafId) { window.cancelAnimationFrame(rafId); rafId = 0; }
    if (entranceTl) { entranceTl.kill(); entranceTl = null; }
    window.removeEventListener("resize", onResize);
    document.removeEventListener("visibilitychange", onVisibility);
    canvas.removeEventListener("pointerdown", onPointerDown);
    window.removeEventListener("pointermove", onPointerMove);
    window.removeEventListener("pointerup", onPointerUp);
    window.removeEventListener("pointercancel", onPointerUp);
    if (st) { st.kill(); st = null; }
    sharedGeo.dispose();
    shadowTex.dispose();
    textTex.dispose();
    cards.forEach(function (c) {
      c.photoMat.map && c.photoMat.map.dispose();
      c.photoMat.dispose();
      c.shadowMat.dispose();
    });
    videos.forEach(function (v) {
      try { v.pause(); v.removeAttribute("src"); v.load(); } catch (e) {}
      if (v.parentNode) v.parentNode.removeChild(v);
    });
    if (textPlane) textPlane.material.dispose();
    renderer.dispose();
  }

  // Context loss is a terminal downgrade for this static build: let the browser
  // tear the context down (no preventDefault → no restore promised), stop the
  // loop, free GL resources, and reveal the DOM headline fallback for good.
  canvas.addEventListener("webglcontextlost", function () {
    contextLost = true;
    section.classList.add("no-webgl");
    dispose();
  });

  /* --------------------------------------------------------------- boot */
  buildText();

  // Gate the headline raster on the Typekit serif so it isn't drawn in a
  // fallback face, then size + build everything and start.
  function fontsReady() {
    if (document.fonts && document.fonts.ready) {
      var loads = [document.fonts.ready];
      if (document.fonts.load) {
        try {
          loads.push(document.fonts.load("italic 700 1em 'miller-text'"));
          loads.push(document.fonts.load("700 1em 'miller-text'"));
        } catch (e) {}
      }
      return Promise.all(loads).catch(function () {});
    }
    return Promise.resolve();
  }

  Promise.all(ASSETS.map(loadAsset)).then(function (textures) {
    buildCards(textures);
    return fontsReady();
  }).then(function () {
    fit();

    window.addEventListener("resize", onResize);
    document.addEventListener("visibilitychange", onVisibility);
    window.addEventListener("pagehide", dispose, { once: true });

    if (reduce) {
      cards.forEach(setCardFull);
      drawText(1);
      renderStatic();
      return; // no loop, no drag
    }

    canvas.addEventListener("pointerdown", onPointerDown);
    window.addEventListener("pointermove", onPointerMove);
    window.addEventListener("pointerup", onPointerUp);
    window.addEventListener("pointercancel", onPointerUp);

    drawText(0);
    clock.getDelta();
    frame(); // paint one frame synchronously, then self-schedule via rAF
    runEntrance();
    setupScroll(); // pin + scrubbed takeover choreography
  });
})();
