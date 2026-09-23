<?php

/**
 * @file
 * Moves every placed Work: latest rail to the block's active component
 * version (23 Sep 2026; user catch: the Line above tick "isn't working").
 *
 * A placed block instance keeps the component version it was placed on,
 * and when its settings form is saved Canvas keeps only the keys THAT
 * version's default_settings name (BlockComponent::clientModelToInput) —
 * so on a rail placed before the line settings existed, a tick on Line
 * above was dropped on the way to the page. The editor does not move an
 * instance up on its own. Pages, work articles and open drafts; drafts
 * restamped (LESSONS.md). Idempotent.
 *
 * Run: ddev exec drush php:script scripts/rail-versions.php
 */

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\user\Entity\User;

\Drupal::service('account_switcher')->switchTo(User::load(1));
$etm = \Drupal::entityTypeManager();
$autosave = \Drupal::service(AutoSaveManager::class);
$component = $etm->getStorage('component')->load('block.icon_work_latest');
$active = $component->getActiveVersion();
$defaults = $component->getSettings($active)['default_settings'];
$lift = static function (array &$items) use ($active, $defaults): int {
  $n = 0;
  foreach ($items as &$i) {
    if (($i['component_id'] ?? '') !== 'block.icon_work_latest' || ($i['component_version'] ?? '') === $active) {
      continue;
    }
    $in = json_decode($i['inputs'] ?? '{}', TRUE) ?: [];
    unset($in['rules']);
    // Every key the active version names, the instance's own value first.
    $in = array_intersect_key($in + $defaults, $defaults);
    unset($in['id'], $in['provider']);
    $i['inputs'] = json_encode($in);
    $i['component_version'] = $active;
    $n++;
  }
  return $n;
};
foreach ([['canvas_page', 'components'], ['node', 'field_work_canvas']] as [$type, $field]) {
  $storage = $etm->getStorage($type);
  foreach ($storage->loadMultiple() as $entity) {
    if (!$entity->hasField($field)) {
      continue;
    }
    $items = $entity->get($field)->getValue();
    if ($lift($items)) {
      $entity->set($field, $items);
      if (method_exists($entity, 'setNewRevision')) {
        $entity->setNewRevision(TRUE);
      }
      $entity->save();
      print $entity->label() . ": rail on the active version\n";
    }
    $draft = $autosave->getAutoSaveEntity($entity);
    if (!$draft->isEmpty()) {
      $d = $draft->entity;
      $items = $d->get($field)->getValue();
      if ($lift($items)) {
        $d->set($field, $items);
        $autosave->saveEntity($d);
        print $entity->label() . " (draft): rail on the active version\n";
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
