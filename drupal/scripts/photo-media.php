<?php

/**
 * @file
 * Moves every placed Filmstrip photo from two fields to the one pick.
 *
 * The Photo item's `image` and `film` became `media`, a picture-or-film
 * pick from the media library (user call, Sep 2026). An instance placed
 * before still renders through the old fields; this rewrites it onto the
 * current version. Saved pages and articles only — an open draft keeps
 * what it holds and still renders. Idempotent.
 *
 * Run: ICON_SEED=1 drush php:script scripts/photo-media.php
 */

declare(strict_types=1);

use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\Page;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

if (!getenv('ICON_SEED')) {
  throw new \RuntimeException('Refusing to run without ICON_SEED=1.');
}
\Drupal::service('account_switcher')->switchTo(User::load(1));
$version = (string) Component::load('sdc.icon.intro-photo')->get('active_version');

$convert = static function (array $items) use ($version): ?array {
  $changed = FALSE;
  foreach ($items as &$item) {
    if ($item['component_id'] !== 'sdc.icon.intro-photo') {
      continue;
    }
    $inputs = is_string($item['inputs']) ? (json_decode($item['inputs'], TRUE) ?: []) : $item['inputs'];
    $id = $inputs['film']['target_id'] ?? $inputs['image']['target_id'] ?? NULL;
    if (isset($inputs['media']) || !$id) {
      continue;
    }
    $item['inputs'] = json_encode(['media' => ['target_id' => (int) $id]], JSON_UNESCAPED_SLASHES);
    $item['component_version'] = $version;
    $changed = TRUE;
  }
  unset($item);
  return $changed ? $items : NULL;
};

foreach (Page::loadMultiple() as $page) {
  if ($items = $convert($page->get('components')->getValue())) {
    $page->set('components', $items)->setNewRevision(TRUE);
    $page->setRevisionLogMessage('Filmstrip photo: one media pick (scripts/photo-media.php).');
    $page->save();
    print "Page {$page->id()} ({$page->label()}): photos moved to the one pick.\n";
  }
}
$nids = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'work')->execute();
foreach (Node::loadMultiple($nids) as $node) {
  if ($node->hasField('field_work_canvas') && ($items = $convert($node->get('field_work_canvas')->getValue()))) {
    $node->set('field_work_canvas', $items)->setNewRevision(TRUE);
    $node->save();
    print "Node {$node->id()}: photos moved to the one pick.\n";
  }
}
\Drupal::service('account_switcher')->switchBack();
