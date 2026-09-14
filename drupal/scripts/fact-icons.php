<?php

/**
 * @file
 * Adds the live site's icons to the Icon media list (Content → Fact icons).
 *
 * Every unique SVG harvested from iconagency.com.au, minus the chrome and
 * the icons already seeded: ICON's own line icons (the masthead marks on the
 * practice and landing pages) and the Font Awesome Pro service-card icons.
 * Each is recoloured to currentColor in sample-content/icons. Idempotent:
 * an icon whose name already exists is left alone, and new ones take the
 * weights after the last existing icon. The Flame that was seeded from the
 * heavier 52-grid asset has its file replaced by the live site's lighter
 * drawing, in place, so cards that already use it keep their reference.
 *
 * Run: ICON_SEED=1 drush php:script scripts/fact-icons.php
 */

declare(strict_types=1);

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

if (!getenv('ICON_SEED')) {
  throw new \RuntimeException('Refusing to run without ICON_SEED=1.');
}

$icons = [
  'aperture.svg' => 'Aperture',
  'cube.svg' => 'Cube',
  'easel.svg' => 'Easel',
  'flag.svg' => 'Flag',
  'megaphone.svg' => 'Megaphone',
  'paper-plane.svg' => 'Paper plane',
  'robot.svg' => 'Robot',
  'smiley.svg' => 'Smiley',
  'speech-bubble.svg' => 'Speech bubble',
  'spinning-top.svg' => 'Spinning top',
  'star-burst.svg' => 'Star burst',
  'binoculars.svg' => 'Binoculars',
  'bolt.svg' => 'Bolt',
  'book.svg' => 'Book',
  'brain.svg' => 'Brain',
  'bullseye-arrow.svg' => 'Bullseye arrow',
  'bullseye-pointer.svg' => 'Bullseye pointer',
  'chess-knight.svg' => 'Chess knight',
  'city.svg' => 'City',
  'eye.svg' => 'Eye',
  'file-search.svg' => 'File search',
  'film.svg' => 'Film',
  'fist.svg' => 'Fist',
  'grin.svg' => 'Grin',
  'hands-holding-heart.svg' => 'Hands holding heart',
  'laptop-code.svg' => 'Laptop code',
  'lightbulb.svg' => 'Lightbulb',
  'messages-dollar.svg' => 'Messages dollar',
  'microphone.svg' => 'Microphone',
  'network.svg' => 'Network',
  'newspaper.svg' => 'Newspaper',
  'object-group.svg' => 'Object group',
  'palette.svg' => 'Palette',
  'pen-and-ruler.svg' => 'Pen and ruler',
  'pencil.svg' => 'Pencil',
  'photo-and-film.svg' => 'Photo and film',
  'pie-chart.svg' => 'Pie chart',
  'podium.svg' => 'Podium',
  'shield-check.svg' => 'Shield check',
  'shopping-cart.svg' => 'Shopping cart',
  'stars.svg' => 'Stars',
  'thumbs-up.svg' => 'Thumbs up',
  'user-chart.svg' => 'User chart',
  'user-group.svg' => 'User group',
  'users.svg' => 'Users',
  'video-camera.svg' => 'Video camera',
  'warning.svg' => 'Warning',
];

$storage = \Drupal::entityTypeManager()->getStorage('media');
$fs = \Drupal::service('file_system');
$dest = 'public://icons';
$fs->prepareDirectory($dest, $fs::CREATE_DIRECTORY | $fs::MODIFY_PERMISSIONS);

$weight = 0;
foreach ($storage->loadByProperties(['bundle' => 'icon']) as $existing) {
  $weight = max($weight, (int) $existing->get('field_logo_weight')->value + 1);
}

// Refresh the files of icons whose drawing changed since they were seeded.
foreach (['flame.svg' => 'Flame'] as $file => $name) {
  foreach ($storage->loadByProperties(['bundle' => 'icon', 'name' => $name]) as $media) {
    $current = $media->get('field_media_file')->entity;
    if ($current && $current->getFileUri() === "$dest/$file") {
      $fs->copy(DRUPAL_ROOT . "/../sample-content/icons/$file", "$dest/$file", $fs::EXISTS_REPLACE);
      print "Refreshed the file behind $name.\n";
    }
  }
}

$added = 0;
foreach ($icons as $file => $name) {
  if ($storage->loadByProperties(['bundle' => 'icon', 'name' => $name])) {
    continue;
  }
  $uri = $fs->copy(DRUPAL_ROOT . "/../sample-content/icons/$file", "$dest/$file", $fs::EXISTS_REPLACE);
  $f = File::create(['uri' => $uri, 'status' => 1]);
  $f->save();
  Media::create([
    'bundle' => 'icon',
    'name' => $name,
    'field_media_file' => ['target_id' => $f->id()],
    'field_logo_weight' => $weight++,
  ])->save();
  $added++;
}
print "Added $added icons (" . count($icons) . " in the set).\n";
