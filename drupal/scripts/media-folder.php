<?php

/**
 * @file
 * The mirrored media into the library's Work folder (user check, 16 Oct
 * 2026: "when you add the work images and videos, you're adding them to
 * the work folder"). The mirror had filed nothing: every image and film
 * whose file sits under files/work/ and has no folder yet is set to Work
 * (field_media_category). Idempotent; mirror-media.inc.php files new media
 * on creation now.
 *
 * Usage: ddev exec drush php:script scripts/media-folder.php
 */

use Drupal\user\Entity\User;

\Drupal::service('account_switcher')->switchTo(User::load(1));
$storage = \Drupal::entityTypeManager()->getStorage('media');
$n = 0;
foreach ($storage->loadMultiple($storage->getQuery()->accessCheck(FALSE)->condition('bundle', ['image', 'video'], 'IN')->execute()) as $media) {
  if (!$media->get('field_media_category')->isEmpty()) {
    continue;
  }
  $source = $media->getSource()->getSourceFieldDefinition($media->bundle->entity)->getName();
  $file = $media->get($source)->entity;
  if (!$file || !str_starts_with((string) $file->getFileUri(), 'public://work/')) {
    continue;
  }
  $media->set('field_media_category', 'work')->save();
  $n++;
}
print "$n media filed under Work.\n";
