<?php

/**
 * @file
 * The old site's films on the new expertise pages.
 *
 * 23 Sep 2026 (user check: "you downloaded the videos from the individual
 * pages of the old site for these?"). Each of Communications, Reputation
 * and Production gets its old page's hero loop on the split masthead's
 * aside and its films in the filmstrip's photo cards (the fact cards stay),
 * in place of the Digital pictures expertise-pages.php borrowed as
 * placeholders. Films are fetched once, with their posters, as video media
 * found by name; Production's website loop is the film Creative already
 * has (media "ICON Creative loop"). Idempotent; drafts are left alone.
 *
 * Run: ddev exec "ICON_SEED=1 drush php:script scripts/expertise-films.php"
 */

require_once __DIR__ . '/_guard.php';

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\user\Entity\User;

\Drupal::service('account_switcher')->switchTo(User::load(1));
$etm = \Drupal::entityTypeManager();
$media = $etm->getStorage('media');
$base = 'https://drupal.iconagency.com.au/files/agency/';

$fetch = static function (string $url, string $destination): File {
  $fs = \Drupal::service('file_system');
  $dir = dirname($destination);
  $fs->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  $data = (string) \Drupal::httpClient()->get($url, ['timeout' => 300])->getBody();
  $uri = $fs->saveData($data, $destination, FileExists::Replace);
  $file = File::create(['uri' => $uri, 'uid' => 1, 'status' => 1]);
  $file->save();
  return $file;
};
$film = static function (string $name, string $path, string $slug, string $alt) use ($media, $fetch, $base): int {
  $found = $media->loadByProperties(['bundle' => 'video', 'name' => $name]);
  if ($found) {
    return (int) reset($found)->id();
  }
  print "Fetching {$name}…\n"; // braces: PHP reads the ellipsis as part of a bare $name
  $video = $fetch($base . $path, 'public://' . date('Y-m') . '/' . $slug . '.mp4');
  $poster = $fetch($base . 'styles/xlarge/public/' . $path . '.png', 'public://posters/' . $slug . '.png');
  $m = Media::create([
    'bundle' => 'video',
    'name' => $name,
    'uid' => 1,
    'status' => 1,
    'field_media_video_file' => ['target_id' => $video->id()],
    'field_media_poster' => ['target_id' => $poster->id(), 'alt' => $alt],
    'field_media_category' => 'content',
  ]);
  $m->save();
  print "  media {$m->id()}\n";
  return (int) $m->id();
};

$creative_loop = $media->loadByProperties(['bundle' => 'video', 'name' => 'ICON Creative loop']);
$creative_loop = $creative_loop ? (int) reset($creative_loop)->id() : NULL;

$pages = [
  '/communications' => [
    'hero' => $film('Communications loop', '2024-07/Communications%2001_1_1.mp4', 'communications-loop', 'ICON communications team at work'),
    'strip' => [
      $film('Communications coffee B-roll', '2024-06/Coffee%20square%20B-roll-Melb%2001_1.mp4', 'communications-coffee-b-roll', 'Coffee in the Melbourne office'),
      $film('International Women’s Day', '2024-06/IWD-wide_2.mp4', 'communications-iwd', 'International Women’s Day at ICON'),
    ],
  ],
  '/reputation' => [
    'hero' => $film('Mark Forbes', '2024-06/Mark%20Forbes%2001_2.mp4', 'reputation-mark-forbes', 'Mark Forbes, ICON'),
    'strip' => [],
  ],
  '/production' => [
    'hero' => $creative_loop ?: $film('ICON Creative loop', '2024-07/website_V14_SHORT%20LOOP_1.mp4', 'icon-creative-loop', 'ICON showreel'),
    'strip' => [$film('Production crop', '2024-06/Crop%2001_2.mp4', 'production-crop', 'On set with ICON Production')],
  ],
];

$storage = $etm->getStorage('canvas_page');
$aliases = \Drupal::service('path_alias.repository');
foreach ($pages as $alias => $films) {
  $path = $aliases->lookupByAlias($alias, 'en');
  if (!$path || !preg_match('#^/page/(\d+)$#', $path['path'], $m)) {
    print "$alias: no page.\n";
    continue;
  }
  $page = $storage->load($m[1]);
  $items = $page->get('components')->getValue();
  $masthead = $strip = NULL;
  foreach ($items as $i) {
    if ($i['component_id'] === 'sdc.icon.split-masthead' && empty($i['parent_uuid'])) {
      $masthead = $i['uuid'];
    }
    if ($i['component_id'] === 'sdc.icon.filmstrip' && empty($i['parent_uuid'])) {
      $strip = $i['uuid'];
    }
  }
  $n = 0;
  $pool = $films['strip'];
  $k = 0;
  foreach ($items as &$i) {
    $in = json_decode($i['inputs'], TRUE) ?: [];
    // the masthead's picture: the hero loop
    if ($masthead && ($i['parent_uuid'] ?? '') === $masthead && ($i['slot'] ?? '') === 'aside' && $i['component_id'] === 'sdc.icon.news-article-figure') {
      $in['picture'] = ['target_id' => $films['hero']];
      $i['inputs'] = json_encode($in, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      $n++;
    }
    // the strip's photo cards: the page's films first, then the hero loop
    // again, then the placeholders that were there
    if ($strip && $pool && ($i['parent_uuid'] ?? '') === $strip && $i['component_id'] === 'sdc.icon.intro-photo') {
      if ($k < count($pool)) {
        $in['media'] = ['target_id' => $pool[$k]];
        $i['inputs'] = json_encode($in, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $n++;
      }
      $k++;
    }
  }
  unset($i);
  if ($n) {
    $page->set('components', $items);
    $page->setNewRevision(TRUE);
    $page->setRevisionLogMessage("The old site's films on the page.");
    $page->save();
  }
  print "$alias: $n pieces now carry the page's films\n";
}
print "Done.\n";
