<?php

/**
 * @file
 * Moves the Testimonials (group) instances placed before onto the version with no Box.
 *
 * The group's Box pick and its Custom pair went on 23 Oct 2026 (user call:
 * "Testimonials define the colours … so why are there two other places to
 * manage colours never seen?"): the carousel always sits in a box and the
 * quote showing colours it. An instance placed before still carries `box`,
 * `bg` and `ink`; this drops them and points the instance at the current
 * version, in saved pages and Work articles. An open draft is reported,
 * not touched — save or discard it in the editor and run again. Idempotent.
 *
 * Run: ICON_SEED=1 drush php:script scripts/testimonial-box.php
 */

declare(strict_types=1);

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\Page;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

if (!getenv('ICON_SEED')) {
  throw new \RuntimeException('Refusing to run without ICON_SEED=1.');
}
\Drupal::service('account_switcher')->switchTo(User::load(1));
$id = 'sdc.icon.testimonials';
$version = (string) Component::load($id)->get('active_version');
$gone = ['box', 'bg', 'ink'];

$convert = static function (array $items) use ($id, $version, $gone): ?array {
  $changed = FALSE;
  foreach ($items as &$item) {
    if ($item['component_id'] !== $id) {
      continue;
    }
    $inputs = is_string($item['inputs']) ? (json_decode($item['inputs'], TRUE) ?: []) : $item['inputs'];
    $kept = array_diff_key($inputs, array_flip($gone));
    if ($kept !== $inputs || $item['component_version'] !== $version) {
      $item['inputs'] = json_encode($kept ?: new \stdClass(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      $item['component_version'] = $version;
      $changed = TRUE;
    }
  }
  return $changed ? $items : NULL;
};

$autosave = \Drupal::service(AutoSaveManager::class);
$done = 0;
$holders = [
  ['canvas_page', 'components', Page::loadMultiple()],
  ['node', 'field_work_canvas', Node::loadMultiple(\Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'work')->execute())],
];
foreach ($holders as [$type, $field, $entities]) {
  foreach ($entities as $entity) {
    if (!$entity->hasField($field)) {
      continue;
    }
    $items = $entity->get($field)->getValue();
    if (!array_filter($items, static fn(array $i): bool => $i['component_id'] === $id)) {
      continue;
    }
    if ($type === 'canvas_page' && !$autosave->getAutoSaveEntity($entity)->isEmpty()) {
      print "Draft open on {$type} {$entity->id()} \"{$entity->label()}\": save or discard it in the editor, then run again.\n";
      continue;
    }
    $new = $convert($items);
    if ($new === NULL) {
      continue;
    }
    $entity->set($field, $new);
    $entity->setNewRevision(TRUE);
    $entity->setRevisionLogMessage('The Testimonials box: colours from the quote (scripts/testimonial-box.php).');
    $entity->save();
    $done++;
    print "{$type} {$entity->id()} \"{$entity->label()}\": the group onto version $version.\n";
  }
}
\Drupal::service('account_switcher')->switchBack();
print "$done saved.\n";
