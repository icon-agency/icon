<?php

/**
 * @file
 * Takes the Divider components out of the split mastheads' above and below
 * slots: the masthead draws its own two lines now (23 Sep 2026). Pages and
 * open drafts; drafts restamped (LESSONS.md). Idempotent.
 *
 * Run: ddev exec drush php:script scripts/masthead-dividers-off.php
 */

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\user\Entity\User;

\Drupal::service('account_switcher')->switchTo(User::load(1));
$etm = \Drupal::entityTypeManager();
$autosave = \Drupal::service(AutoSaveManager::class);
$strip = static function (array &$items): int {
  $mastheads = [];
  foreach ($items as $i) {
    if (($i['component_id'] ?? '') === 'sdc.icon.split-masthead') {
      $mastheads[$i['uuid']] = TRUE;
    }
  }
  $kept = [];
  $n = 0;
  foreach ($items as $i) {
    if (($i['component_id'] ?? '') === 'sdc.icon.divider' && isset($mastheads[$i['parent_uuid'] ?? '']) && in_array($i['slot'] ?? '', ['above', 'below'], TRUE)) {
      $n++;
      continue;
    }
    $kept[] = $i;
  }
  $items = $kept;
  return $n;
};
foreach ([['canvas_page', 'components'], ['node', 'field_work_canvas']] as [$type, $field]) {
  $storage = $etm->getStorage($type);
  foreach ($storage->loadMultiple() as $entity) {
    if (!$entity->hasField($field)) {
      continue;
    }
    $items = $entity->get($field)->getValue();
    if ($n = $strip($items)) {
      $entity->set($field, $items);
      if (method_exists($entity, 'setNewRevision')) {
        $entity->setNewRevision(TRUE);
      }
      $entity->save();
      print $entity->label() . ": $n divider(s) out of the masthead\n";
    }
    $draft = $autosave->getAutoSaveEntity($entity);
    if (!$draft->isEmpty()) {
      $d = $draft->entity;
      $items = $d->get($field)->getValue();
      if ($m = $strip($items)) {
        $d->set($field, $items);
        $autosave->saveEntity($d);
        print $entity->label() . " (draft): $m divider(s) out\n";
      }
      $fresh = $storage->loadUnchanged($entity->id());
      $draft = $autosave->getAutoSaveEntity($fresh);
      if (!$draft->isEmpty() && $draft->entity->getChangedTime() < $fresh->getChangedTime()) {
        $d = $draft->entity;
        $d->setChangedTime($fresh->getChangedTime());
        $autosave->saveEntity($d);
        if ($fresh instanceof \Drupal\canvas\Entity\Page && ($c = $autosave->getUnresolvedConflictForEntity($fresh))) {
          $autosave->resolveConflict($fresh, $c);
        }
        print $entity->label() . " (draft): restamped\n";
      }
    }
  }
}
print "Done.\n";
