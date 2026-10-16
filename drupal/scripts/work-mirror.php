<?php

/**
 * @file
 * The old site's case studies, rebuilt here from iconagency.com.au/work
 * (16 Oct 2026; user ask: "help build all the folio items … delete the
 * others and replace with the ones on iconagency.com.au").
 *
 * Reads scripts/data/work-folios.json — one entry per old case study, the
 * audit's reviewed project name, client, feature statement and categories,
 * the old page's header colours and hero, its card's tile layers, and its
 * body as ordered blocks (text, gallery, media, services) — and builds each
 * as a Work node the way the iCanQuit and Nike folios were built by hand:
 * the Work masthead first, then the master's blocks in the old page's own
 * order: a Content block per text section; a one-picture gallery as a
 * landscape figure, a pair as two square figures, three or more as the
 * scroller; the full-width media as a landscape figure; a portrait film or
 * picture as a cut-out on the brand's ground, two to a row. The old page's
 * Services list becomes the masthead's deliverables.
 *
 * THE CARD (user call, 16 Oct 2026: "cards wide could use the banner,
 * square the legacy"): the old card's cut-out layers become the Legacy
 * folio card's layers, back to front; a card with one image gets it as the
 * Square tile; the old page's hero (film or picture) is the Banner and the
 * Wide tile.
 *
 * MEDIA is fetched from the old site's originals and RESIZED ON THE WAY IN
 * (the originals total 941 MB of images: 2400px long edge for a photo,
 * 1600 for a tile or layer; a PNG with no transparency becomes a JPEG), so
 * the host's small files volume carries them. Films come down as they are.
 * Media are reused by file name across runs.
 *
 * Idempotent: a folio is found by the node its old path redirects to, else
 * by project name and client; the six placeholders the audit maps to old
 * pages are rebuilt in place (their IDs below). Every folio gets a 301 from
 * its old path. A folio an editor has saved since (its latest revision is
 * not the mirror's) is SKIPPED unless ICON_FORCE=1 — a re-run replaced
 * Matt's published edits once (16 Oct 2026).
 *
 * Run (newest ten):   ddev exec "ICON_SEED=1 ICON_LIMIT=10 drush php:script scripts/work-mirror.php"
 * One or more:        ddev exec "ICON_SEED=1 ICON_ONLY=/work/sextortion,/work/food-fight drush php:script scripts/work-mirror.php"
 * Everything:         ddev exec "ICON_SEED=1 drush php:script scripts/work-mirror.php"
 */

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\redirect\Entity\Redirect;

require_once __DIR__ . '/_guard.php';

$data = json_decode(file_get_contents(__DIR__ . '/data/work-folios.json'), TRUE);
$only = array_filter(array_map('trim', explode(',', (string) getenv('ICON_ONLY'))));
$limit = (int) getenv('ICON_LIMIT');
$todo = [];
foreach ($data as $folio) {
  if (str_starts_with($folio['action'], 'KEEP')) {
    continue;
  }
  if ($only && !in_array($folio['old_path'], $only, TRUE)) {
    continue;
  }
  $todo[] = $folio;
  if ($limit && count($todo) >= $limit) {
    break;
  }
}
// The placeholders the audit maps to old pages: rebuilt in place.
$rebuild = [
  '/work/our-work-nwmphn' => 14,
  '/work/dfat-permanent-protection-visa-communications' => 15,
  '/work/celebrating-movement' => 16,
  '/work/museum-australian-democracy-mis-and-disinformation-campaign' => 18,
  '/work/australian-space-agency-website' => 20,
  '/work/alcohol-and-drug-foundation' => 21,
];
// The old site's colour names.
$colours = ['var(--black)' => '#0d0d0d', 'var(--dark)' => '#0d0d0d', 'var(--white)' => '#ffffff', 'var(--light)' => '#f4f4f4', 'var(--grey)' => '#8a8a8a'];
$hex = static fn(string $c, string $fallback): string => preg_match('/^#[0-9a-f]{3,8}$/i', $c) ? strtolower($c) : ($colours[trim($c)] ?? $fallback);

$etm = \Drupal::entityTypeManager();
$fs = \Drupal::service('file_system');
$uuid = \Drupal::service('uuid');
$http = \Drupal::httpClient();
$version = static function (string $id): string {
  $c = \Drupal::entityTypeManager()->getStorage('component')->load($id);
  if (!$c) {
    throw new \RuntimeException("Component $id is not registered.");
  }
  return (string) $c->get('active_version');
};
$slug = static fn(string $s): string => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(\Drupal::transliteration()->transliterate($s))), '-');

/**
 * Downloads a URL to a temp file; NULL when it cannot.
 */
$fetch = static function (string $url) use ($http, $fs): ?string {
  $tmp = $fs->tempnam('temporary://', 'mirror-');
  try {
    $http->get($url, ['sink' => $fs->realpath($tmp), 'timeout' => 300, 'headers' => ['User-Agent' => 'Mozilla/5.0 (ICON mirror)']]);
  }
  catch (\Throwable $e) {
    print "    ! could not fetch $url: " . $e->getMessage() . "\n";
    return NULL;
  }
  return $tmp;
};

/**
 * An image media from an old-site original, resized on the way in.
 *
 * @param string $role
 *   photo (2400 long edge, JPEG unless transparent) or tile (1600, PNG kept).
 */
$image = static function (string $url, string $alt, string $role, string $dir) use ($etm, $fs, $fetch): ?Media {
  $name = rawurldecode(basename(parse_url($url, PHP_URL_PATH)));
  $base = preg_replace('/\.(png|jpe?g|webp|gif)$/i', '', $name);
  $existing = $etm->getStorage('media')->loadByProperties(['bundle' => 'image', 'name' => $name]);
  if ($existing) {
    return reset($existing);
  }
  // A file ALREADY IN PLACE is used as it is: the host has no Imagick, so a
  // folio is built locally and its resized files travel up before the run
  // there (16 Oct 2026, user ask: "push our new folio items … to the upsun
  // drupal version"); the file is the local run's, resized and renamed.
  $slug = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base);
  foreach (['jpg', 'png'] as $ext) {
    if (($path = $fs->realpath("$dir/$slug.$ext")) && file_exists($path)) {
      $f = File::create(['uri' => "$dir/$slug.$ext", 'status' => 1]);
      $f->save();
      $m = Media::create(['bundle' => 'image', 'name' => $name, 'field_media_image' => ['target_id' => $f->id(), 'alt' => $alt ?: $base]]);
      $m->save();
      return $m;
    }
  }
  $tmp = $fetch($url);
  if (!$tmp) {
    return NULL;
  }
  $max = $role === 'tile' ? 1600 : 2400;
  try {
    $im = new \Imagick($fs->realpath($tmp));
    $im->setFirstIterator();
    $alpha = $im->getImageAlphaChannel() && (float) $im->getImageChannelMean(\Imagick::CHANNEL_ALPHA)['mean'] < 65535 * 0.999;
    $w = $im->getImageWidth();
    $h = $im->getImageHeight();
    if (max($w, $h) > $max) {
      $im->resizeImage($w >= $h ? $max : 0, $h > $w ? $max : 0, \Imagick::FILTER_LANCZOS, 1);
    }
    $im->stripImage();
    if ($alpha || $role === 'tile' && preg_match('/\.png$/i', $name)) {
      $im->setImageFormat('png');
      $ext = 'png';
    }
    else {
      $im->setImageFormat('jpeg');
      $im->setImageCompressionQuality(85);
      $ext = 'jpg';
    }
    $fs->prepareDirectory($dir, $fs::CREATE_DIRECTORY | $fs::MODIFY_PERMISSIONS);
    $uri = "$dir/" . preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) . ".$ext";
    $im->writeImage($fs->realpath($uri) ?: $uri);
    $im->destroy();
  }
  catch (\Throwable $e) {
    print "    ! could not process $name: " . $e->getMessage() . "\n";
    return NULL;
  }
  finally {
    @$fs->delete($tmp);
  }
  $f = File::create(['uri' => $uri, 'status' => 1]);
  $f->save();
  $m = Media::create(['bundle' => 'image', 'name' => $name, 'field_media_image' => ['target_id' => $f->id(), 'alt' => $alt ?: $base]]);
  $m->save();
  return $m;
};

/**
 * A video media from an old-site film, as it is.
 */
$video = static function (string $url, string $name, string $dir) use ($etm, $fs, $fetch): ?Media {
  $existing = $etm->getStorage('media')->loadByProperties(['bundle' => 'video', 'name' => $name]);
  if ($existing) {
    return reset($existing);
  }
  // a film already in place (see the image's note) is used as it is
  $uri = "$dir/" . preg_replace('/[^A-Za-z0-9._-]+/', '-', $name);
  if (!(($path = $fs->realpath($uri)) && file_exists($path))) {
    $tmp = $fetch($url);
    if (!$tmp) {
      return NULL;
    }
    $fs->prepareDirectory($dir, $fs::CREATE_DIRECTORY | $fs::MODIFY_PERMISSIONS);
    $uri = $fs->move($tmp, $uri, $fs::EXISTS_REPLACE);
  }
  $f = File::create(['uri' => $uri, 'status' => 1]);
  $f->save();
  $m = Media::create(['bundle' => 'video', 'name' => $name, 'field_media_video_file' => ['target_id' => $f->id()]]);
  $m->save();
  return $m;
};

$media_for = static function (array $item, string $role, string $dir) use ($image, $video): ?Media {
  if ($item['kind'] === 'video') {
    return !empty($item['url']) ? $video($item['url'], $item['file'], $dir) : NULL;
  }
  return $image($item['url'], $item['alt'] ?? '', $role, $dir);
};

/**
 * A media's pixel size: an image from its file, a film from its MP4 header.
 *
 * The MP4's `tkhd` box carries the video track's width and height as 16.16
 * fixed-point numbers, and a rotation matrix that a phone's film may carry
 * (then width and height swap). No ffmpeg in the container, so read it here.
 */
$dims = static function (Media $m) use ($fs): array {
  $field = $m->bundle() === 'video' ? 'field_media_video_file' : 'field_media_image';
  $file = $m->get($field)->entity;
  if (!$file) {
    return [0, 0];
  }
  $path = $fs->realpath($file->getFileUri());
  if ($m->bundle() === 'image') {
    $i = @getimagesize($path);
    return $i ? [(int) $i[0], (int) $i[1]] : [0, 0];
  }
  $h = fopen($path, 'rb');
  $data = fread($h, min(filesize($path), 8 * 1024 * 1024));
  fclose($h);
  $pos = 0;
  while (($pos = strpos($data, 'tkhd', $pos)) !== FALSE) {
    $v = ord($data[$pos + 4]);
    // The box body follows the type: version and flags (4), the times, track
    // id and duration (20, or 32 in version 1), reserved (8), layer, group,
    // volume and reserved (8), then the matrix (36), then width and height.
    $matrix = $pos + 4 + 4 + ($v === 1 ? 32 : 20) + 8 + 8;
    $w = unpack('N', substr($data, $matrix + 36, 4))[1] / 65536;
    $hh = unpack('N', substr($data, $matrix + 40, 4))[1] / 65536;
    if ($w > 0 && $hh > 0) {
      $a = unpack('N', substr($data, $matrix, 4))[1];
      $d = unpack('N', substr($data, $matrix + 16, 4))[1];
      // a == 0 and d == 0: the track is rotated a quarter turn.
      return ($a === 0 && $d === 0) ? [(int) $hh, (int) $w] : [(int) $w, (int) $hh];
    }
    $pos += 4;
  }
  return [0, 0];
};

$built = 0;
foreach ($todo as $folio) {
  $path = $folio['old_path'];
  $dir = 'public://work/' . $slug($folio['project'] ?: basename($path));
  print "{$folio['project']} — {$folio['client']} ($path)\n";

  // ---- the node --------------------------------------------------------------
  $node = NULL;
  if (isset($rebuild[$path])) {
    $node = Node::load($rebuild[$path]);
  }
  if (!$node) {
    $existing = $etm->getStorage('redirect')->loadByProperties(['redirect_source__path' => ltrim($path, '/')]);
    if ($existing) {
      $uri = reset($existing)->getRedirectUrl()->toString();
      if (preg_match('#^/node/(\d+)$#', $uri, $m) || ($alias = \Drupal::service('path_alias.manager')->getPathByAlias($uri)) && preg_match('#^/node/(\d+)$#', $alias, $m)) {
        $node = Node::load((int) $m[1]);
      }
    }
  }
  if (!$node) {
    $existing = $etm->getStorage('node')->loadByProperties(['type' => 'work', 'title' => $folio['project'], 'field_work_client' => $folio['client']]);
    $node = $existing ? reset($existing) : NULL;
  }
  if (!$node) {
    $node = Node::create(['type' => 'work', 'title' => $folio['project'], 'uid' => 1]);
    print "  new node\n";
  }
  else {
    // AN EDITED FOLIO IS LEFT ALONE: a node whose latest revision is not the
    // mirror's own carries an editor's work, and a re-run replaced Matt's
    // published Sextortion edits (16 Oct 2026). ICON_FORCE=1 overrides.
    $log = (string) $node->getRevisionLogMessage();
    if (!str_starts_with($log, 'Mirrored from') && getenv('ICON_FORCE') !== '1') {
      print "  SKIPPED node {$node->id()}: its latest revision is not the mirror's (\"" . mb_substr($log, 0, 60) . "\"); ICON_FORCE=1 to replace it\n";
      continue;
    }
    print "  rebuilding node {$node->id()}\n";
  }

  // ---- media ----------------------------------------------------------------
  $hero = $folio['hero'] ? $media_for($folio['hero'], 'photo', $dir) : NULL;
  $layers = [];
  foreach ($folio['tiles'] as $i => $t) {
    if ($m = $media_for($t, 'tile', $dir)) {
      $layers[] = $m;
    }
  }
  // No hero on the old page (or a film that is not where its name says):
  // the card's base layer stands in as the banner, so the masthead has a
  // picture.
  if (!$hero && $layers) {
    $hero = $layers[0];
    print "  no hero film found; the first tile stands in as the banner\n";
  }
  $bg = $hex($folio['bg'], '#0d0d0d');
  $ink = $hex($folio['ink'], '#ffffff');
  // The brand's own colour for a cut-out's ground: the old page's accent (its
  // text colour) unless that is just black or white, then its background.
  $plain = ['#000000', '#0d0d0d', '#ffffff', '#f4f4f4'];
  $accent = !in_array($ink, $plain, TRUE) ? $ink : (!in_array($bg, $plain, TRUE) ? $bg : '#2e2e2e');

  // ---- the tree ---------------------------------------------------------------
  $tree = [];
  $add = static function (string $component, array $inputs, ?string $parent = NULL, ?string $slot = NULL) use (&$tree, $uuid, $version): string {
    $id = $uuid->generate();
    $tree[] = [
      'parent_uuid' => $parent,
      'slot' => $slot,
      'uuid' => $id,
      'component_id' => $component,
      'component_version' => $version($component),
      'inputs' => json_encode($inputs ?: new \stdClass(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      'label' => NULL,
    ];
    return $id;
  };
  $prose = static fn(string $html): array => ['text' => ['value' => $html, 'format' => 'canvas_html_block']];
  $figure = static function (Media $m, string $shape, string $ground = '') {
    $in = ['shape' => $shape, 'picture' => ['target_id' => (int) $m->id()]];
    if ($ground) {
      $in['ground'] = $ground;
    }
    return $in;
  };
  // THE DELIVERABLES ARE THE OLD PAGE'S SERVICES LIST (user call, 16 Oct
  // 2026: the Services block at the foot "can be deleted and the items used
  // to populate the deliverables"); a page with no such list keeps the
  // disciplines of its service line.
  $services = [];
  $who = [];
  foreach ($folio['blocks'] as &$block) {
    if ($block['type'] === 'services') {
      $services = array_merge($services, $block['items']);
    }
    // A "What we delivered" (or Services, Deliverables, What we did) heading
    // with a list straight under it, written in the body on the old page,
    // is the deliverables too (user call, 16 Oct 2026, on Raise It: "these
    // could be the deliverables"): the items move to the masthead and the
    // heading and list come out of the text.
    if ($block['type'] === 'text' && preg_match('#<h[23]>\s*(What we delivered|What we did|Our services|Services|Deliverables)\s*</h[23]>\s*(?:<!--.*?-->\s*)*<ul>(.*?)</ul>#is', $block['html'], $m)) {
      preg_match_all('#<li>(.*?)</li>#is', $m[2], $li);
      $items = array_values(array_filter(array_map(static fn($x) => trim(html_entity_decode(strip_tags($x))), $li[1])));
      $services = array_merge($services, $items);
      // In the text the list becomes ONE SENTENCE under the same heading
      // (user call, 16 Oct 2026: "summarise the deliverables into a sentence
      // that reads well … it's now at the top as a list, more general
      // here"): "ICON's work spanned strategy, creative ideation … and web
      // design and development." A word keeps its capitals only when it has
      // two or more (an acronym, GovCMS); Title Case items go to lower case;
      // an item's own full stop goes.
      $words = array_map(static fn(string $x) => implode(' ', array_map(static fn(string $w) => preg_match_all('/\p{Lu}/u', $w) >= 2 ? $w : mb_strtolower($w), explode(' ', rtrim(trim($x), '.;:,')))), $items);
      $last = array_pop($words);
      $oxford = array_filter($items, static fn($x) => str_contains(" $x ", ' and ')) ? ',' : '';
      $sentence = $words ? implode(', ', $words) . "$oxford and $last" : $last;
      // A folio may carry a written line instead (`summary` in the data).
      $summary = $folio['summary'] ?? "ICON’s work spanned $sentence.";
      $block['html'] = trim(str_replace($m[0], '<h2>' . $m[1] . '</h2><p>' . htmlspecialchars($summary, ENT_QUOTES) . '</p>', $block['html']));
    }
    // "Who made it" — the old page's closing list of the disciplines on the
    // job (13 folios carry one) — is the deliverables as well (user call,
    // 16 Oct 2026, on the Space Agency folio: "remove this and add to the
    // deliverables at top"): the items join the masthead's list and the
    // heading and list come out of the text altogether; a block left empty
    // is dropped below.
    if ($block['type'] === 'text' && preg_match('#<h[23]>\s*Who made it\s*</h[23]>\s*(?:<!--.*?-->\s*)*<ul>(.*?)</ul>#is', $block['html'], $m)) {
      preg_match_all('#<li>(.*?)</li>#is', $m[1], $li);
      $items = array_values(array_filter(array_map(static fn($x) => rtrim(trim(html_entity_decode(strip_tags($x))), '.'), $li[1])));
      $who = array_merge($who, $items);
      $block['html'] = trim(str_replace($m[0], '', $block['html']));
    }
  }
  unset($block);
  $folio['blocks'] = array_values(array_filter($folio['blocks'], static fn($b) => $b['type'] !== 'text' || trim(strip_tags($b['html'])) !== ''));
  // The service line's disciplines stand where the old page has no services
  // list, and "Who made it" ADDS to either — it had replaced the service
  // line on a page with no services list (caught on the host's Space
  // Agency, 16 Oct 2026: "Website design and development" gone).
  $deliverables = array_values(array_unique(array_merge($services ?: $folio['deliverables'], $who)));
  $masthead = [
    'title' => $folio['project'],
    'client' => $folio['client'],
    'statement' => $folio['statement'],
    'categories' => $folio['categories'],
    'deliverables' => $deliverables,
    'bg' => $bg,
    'ink' => $ink,
  ];
  if ($hero) {
    $masthead['banner'] = ['target_id' => (int) $hero->id()];
  }
  $add('sdc.icon.work-masthead', $masthead);
  foreach ($folio['blocks'] as $block) {
    switch ($block['type']) {
      case 'text':
        $add('sdc.icon.prose', $prose($block['html']));
        break;

      case 'services':
        // In the masthead's deliverables, not a block of its own.
        break;

      case 'media':
      case 'gallery':
        $medias = array_values(array_filter(array_map(static fn($it) => $media_for($it, 'photo', $dir), $block['items'])));
        $ground = $hex($block['ground'] ?? '', '');
        if (!$medias) {
          break;
        }
        // A PORTRAIT film or picture (9:16 and the like) stands as the cut-out
        // of a square figure on the brand's ground, two to a row (user call,
        // 16 Oct 2026: "place the 9:16 in a two column … add the video to the
        // overlay cut-out and make the ground colour their brand orange");
        // the rest: one as a landscape figure, a pair as two squares, three or
        // more as the scroller.
        $portrait = static function (Media $m) use ($dims): bool {
          [$w, $h] = $dims($m);
          return $w > 0 && $h > $w * 1.2;
        };
        $tall = static fn(Media $m): array => ['shape' => 'square', 'float' => ['target_id' => (int) $m->id()], 'ground' => $accent, 'pad' => '12%'];
        $rest = [];
        $flush = static function () use (&$rest, $add, $figure, $ground) {
          if (!$rest) {
            return;
          }
          if (count($rest) === 1) {
            $col = $add('sdc.icon.columns', ['columns' => 1, 'gap' => 'tight', 'box' => 'none', 'split' => 'even']);
            $add('sdc.icon.news-article-figure', $figure($rest[0], 'landscape', $ground), $col, 'column_1');
          }
          elseif (count($rest) === 2) {
            $col = $add('sdc.icon.columns', ['columns' => 2, 'gap' => 'tight', 'box' => 'none', 'split' => 'even']);
            $add('sdc.icon.news-article-figure', $figure($rest[0], 'square', $ground), $col, 'column_1');
            $add('sdc.icon.news-article-figure', $figure($rest[1], 'square', $ground), $col, 'column_2');
          }
          else {
            $strip = $add('sdc.icon.work-scroller', $ground ? ['ground' => $ground] : []);
            foreach ($rest as $m) {
              $add('sdc.icon.news-article-figure', $figure($m, 'natural'), $strip, 'pictures');
            }
          }
          $rest = [];
        };
        $talls = array_values(array_filter($medias, $portrait));
        $others = array_values(array_filter($medias, static fn($m) => !$portrait($m)));
        // Pairs of portraits; a lone one shares its row with the first of the rest.
        while ($talls) {
          $a = array_shift($talls);
          $col = $add('sdc.icon.columns', ['columns' => 2, 'gap' => 'tight', 'box' => 'none', 'split' => 'even']);
          $add('sdc.icon.news-article-figure', $tall($a), $col, 'column_1');
          if ($talls) {
            $add('sdc.icon.news-article-figure', $tall(array_shift($talls)), $col, 'column_2');
          }
          elseif ($others) {
            $add('sdc.icon.news-article-figure', $figure(array_shift($others), 'square', $ground), $col, 'column_2');
          }
        }
        $rest = $others;
        $flush();
        break;
    }
  }

  // ---- fields ----------------------------------------------------------------
  $node->set('title', $folio['project']);
  $node->set('field_work_client', $folio['client']);
  $node->set('field_work_statement', $folio['statement']);
  $node->set('field_work_category', $folio['categories']);
  $node->set('field_work_deliverables', $deliverables);
  $node->set('field_work_bg', $bg);
  $node->set('field_work_ink', $ink);
  $node->set('field_work_banner', $hero ? ['target_id' => $hero->id()] : NULL);
  $node->set('field_work_tile_wide', $hero ? ['target_id' => $hero->id()] : NULL);
  if (count($layers) >= 2) {
    $node->set('field_work_legacy_layers', array_map(static fn($m) => ['target_id' => $m->id()], $layers));
    $node->set('field_work_tile', NULL);
  }
  else {
    $node->set('field_work_legacy_layers', []);
    $node->set('field_work_tile', $layers ? ['target_id' => $layers[0]->id()] : ($hero && $hero->bundle() === 'image' ? ['target_id' => $hero->id()] : NULL));
  }
  $node->set('field_work_tile_fg', NULL);
  $node->set('field_work_canvas', $tree);
  $node->set('path', ['pathauto' => 1]);
  $node->setPublished(TRUE);
  if ($node->hasField('moderation_state')) {
    $node->set('moderation_state', 'published');
  }
  if (!empty($folio['date'])) {
    $node->setCreatedTime(strtotime($folio['date']));
  }
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage("Mirrored from iconagency.com.au$path.");
  $node->save();
  \Drupal::service(\Drupal\canvas\AutoSave\AutoSaveManager::class)->delete($node);

  // ---- the 301 from the old path ----------------------------------------------
  $source = ltrim($path, '/');
  $redirects = $etm->getStorage('redirect')->loadByProperties(['redirect_source__path' => $source]);
  $r = $redirects ? reset($redirects) : Redirect::create(['redirect_source' => ['path' => $source, 'query' => []], 'status_code' => 301, 'language' => 'und']);
  $r->set('redirect_redirect', ['uri' => 'internal:/node/' . $node->id()]);
  $r->save();

  $built++;
  printf("  node %d at %s — %d block(s), %d layer(s), %s, %s\n", $node->id(), $node->toUrl()->toString(), count($tree) - 1, count($layers), $hero ? 'hero ' . $hero->bundle() : 'no hero', "$bg on $ink");
}
print "Done: $built folio(s).\n";
