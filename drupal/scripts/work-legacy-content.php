<?php

/**
 * @file
 * The legacy folio card on "Celebrating movement in all its forms" (The
 * Athlete's Foot, /work/fit-for-every-run): the old site's five square
 * cut-out layers, back to front, from drupal/sample-content/work/legacy/
 * (the same files the static design system shows in work-landing-b.html).
 *
 * Run from drupal/:  ddev drush php:script scripts/work-legacy-content.php
 * Idempotent: media matched by name, the node by alias.
 */

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

$source_dir = DRUPAL_ROOT . '/../sample-content/work/legacy';
$fs = \Drupal::service('file_system');
$dest = 'public://work/legacy';
$fs->prepareDirectory($dest, $fs::CREATE_DIRECTORY | $fs::MODIFY_PERMISSIONS);

$image = function (string $file, string $alt) use ($source_dir, $dest, $fs): Media {
  $existing = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties(['bundle' => 'image', 'name' => $file]);
  if ($existing) {
    return reset($existing);
  }
  $uri = $fs->copy("$source_dir/$file", "$dest/$file", $fs::EXISTS_REPLACE);
  $f = File::create(['uri' => $uri, 'status' => 1]);
  $f->save();
  $m = Media::create(['bundle' => 'image', 'name' => $file, 'field_media_image' => ['target_id' => $f->id(), 'alt' => $alt]]);
  $m->save();
  return $m;
};

$path = \Drupal::service('path_alias.repository')->lookupByAlias('/work/fit-for-every-run', 'en')['path'] ?? NULL;
$node = $path ? Node::load((int) substr($path, 6)) : NULL;
if (!$node) {
  throw new \RuntimeException('No /work/fit-for-every-run — run work-sample-content.php first.');
}
$layers = [];
for ($i = 1; $i <= 5; $i++) {
  $layers[] = ['target_id' => $image("taf-layer-$i.png", "The Athlete’s Foot — Fit for every run campaign creative, layer $i")->id()];
}
$node->set('field_work_legacy_layers', $layers)->save();
echo "legacy layers set on node/{$node->id()}\n";
