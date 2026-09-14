<?php

/**
 * @file
 * Moves every placed Video hosted from a film path to a film media pick.
 *
 * The component's `video_src` (a file path) became `film`, a Video from
 * the media library, so its panel offers the same picker every other film
 * on the site does (user call, Sep 2026). An instance placed before still
 * renders through the old path; this rewrites it — the Video media whose
 * file is that path — onto the current component version, and drops the
 * article's open draft so the editor opens on the rewritten tree (unlike
 * scripts/photo-media.php, which leaves a draft as it is: a draft here
 * would still hold the retired path). Idempotent.
 *
 * Run: ICON_SEED=1 drush php:script scripts/work-video-film.php
 */

declare(strict_types=1);

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\Component;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

if (!getenv('ICON_SEED')) {
  throw new \RuntimeException('Refusing to run without ICON_SEED=1.');
}
\Drupal::service('account_switcher')->switchTo(User::load(1));
$version = (string) Component::load('sdc.icon.work-video')->get('active_version');
$files = \Drupal::entityTypeManager()->getStorage('file');
$media = \Drupal::entityTypeManager()->getStorage('media');
$autosave = \Drupal::service(AutoSaveManager::class);

$film_for = static function (string $path) use ($files, $media): ?int {
  $uri = 'public://' . ltrim(preg_replace('#^.*?/sites/default/files/#', '', $path), '/');
  foreach ($files->loadByProperties(['uri' => $uri]) as $file) {
    foreach ($media->loadByProperties(['bundle' => 'video', 'field_media_video_file' => $file->id()]) as $m) {
      return (int) $m->id();
    }
  }
  return NULL;
};

$nids = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'work')->execute();
foreach (Node::loadMultiple($nids) as $node) {
  $items = $node->get('field_work_canvas')->getValue();
  $changed = FALSE;
  foreach ($items as &$item) {
    if ($item['component_id'] !== 'sdc.icon.work-video') {
      continue;
    }
    $inputs = is_string($item['inputs']) ? (json_decode($item['inputs'], TRUE) ?: []) : $item['inputs'];
    if (!isset($inputs['video_src'])) {
      continue;
    }
    $path = is_array($inputs['video_src']) ? (string) ($inputs['video_src']['uri'] ?? '') : (string) $inputs['video_src'];
    $mid = $film_for(str_replace('internal:', '', $path));
    if (!$mid) {
      print "Node {$node->id()}: no Video media for $path — left as is.\n";
      continue;
    }
    unset($inputs['video_src']);
    $inputs = ['film' => ['target_id' => $mid]] + $inputs;
    $item['inputs'] = json_encode($inputs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $item['component_version'] = $version;
    $changed = TRUE;
    print "Node {$node->id()}: film -> media $mid\n";
  }
  unset($item);
  if ($changed) {
    $node->set('field_work_canvas', $items);
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Video hosted: film path -> media pick (scripts/work-video-film.php).');
    $node->save();
    $autosave->delete($node);
  }
}
\Drupal::service('account_switcher')->switchBack();
