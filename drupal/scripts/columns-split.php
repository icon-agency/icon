<?php

/**
 * @file
 * Fills the Columns component's new required Split into every Columns that
 * was placed before the pick existed (Sep 2026), so nothing refuses to
 * publish: Canvas validates a placed component against the component's
 * CURRENT schema whatever version it sits on, and a required prop that is
 * missing is a violation. Every Canvas page (published, and any open draft),
 * every Work article's Canvas body (field_work_canvas) and every pattern.
 * Idempotent; run it on each environment after the deploy that brings the
 * prop, before anyone publishes.
 *
 * Run: ddev exec "ICON_SEED=1 drush php:script scripts/columns-split.php"
 */

require_once __DIR__ . '/_guard.php';

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\user\Entity\User;

\Drupal::service('account_switcher')->switchTo(User::load(1));
$etm = \Drupal::entityTypeManager();
$version = (string) $etm->getStorage('component')->load('sdc.icon.columns')->get('active_version');

/**
 * Fills the prop in a tree; TRUE when anything changed.
 */
$fill = static function (array &$items) use ($version): int {
  $n = 0;
  // A child of a Columns in a slot that is not one of its columns — a pull
  // quote the gallery migration left in the gallery's "figures" slot (found
  // on one folio, Sep 2026) — goes into the first column; the tree would
  // not validate with it where it is.
  $columns = [];
  foreach ($items as $item) {
    if (($item['component_id'] ?? '') === 'sdc.icon.columns') {
      $columns[$item['uuid']] = TRUE;
    }
  }
  foreach ($items as &$item) {
    if (!empty($item['parent_uuid']) && isset($columns[$item['parent_uuid']]) && !preg_match('/^column_[1-4]$/', (string) $item['slot'])) {
      print "  moved a " . $item['component_id'] . " from slot '" . $item['slot'] . "' to column_1\n";
      $item['slot'] = 'column_1';
      $n++;
    }
  }
  unset($item);
  foreach ($items as &$item) {
    if (($item['component_id'] ?? '') !== 'sdc.icon.columns') {
      continue;
    }
    $inputs = is_string($item['inputs']) ? (json_decode($item['inputs'], TRUE) ?: []) : (array) $item['inputs'];
    if (isset($inputs['split'])) {
      continue;
    }
    $inputs['split'] = 'even';
    $item['inputs'] = is_string($item['inputs']) ? json_encode($inputs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $inputs;
    $item['component_version'] = $version;
    $n++;
  }
  unset($item);
  return $n;
};

$autosave = \Drupal::service(AutoSaveManager::class);
foreach ([['canvas_page', 'components'], ['node', 'field_work_canvas']] as [$type, $field]) {
  $storage = $etm->getStorage($type);
  $query = $storage->getQuery()->accessCheck(FALSE);
  if ($type === 'node') {
    $query->condition('type', 'work');
  }
  foreach ($storage->loadMultiple($query->execute()) as $entity) {
    if (!$entity->hasField($field)) {
      continue;
    }
    $items = $entity->get($field)->getValue();
    if ($n = $fill($items)) {
      $entity->set($field, $items);
      $violations = $entity->validate();
      if (count($violations)) {
        print $entity->label() . ": not saved — " . strip_tags((string) $violations[0]->getMessage()) . "\n";
      }
      else {
        if (method_exists($entity, 'setNewRevision')) {
          $entity->setNewRevision(TRUE);
        }
        if (method_exists($entity, 'setRevisionLogMessage')) {
          $entity->setRevisionLogMessage('Columns: the Split pick filled in as Even for columns placed before it existed.');
        }
        $entity->save();
        print $entity->label() . ": $n Columns filled\n";
      }
    }
    // An open draft holds its own tree.
    $draft = $autosave->getAutoSaveEntity($entity);
    if (!$draft->isEmpty()) {
      $draft_entity = $draft->entity;
      $items = $draft_entity->get($field)->getValue();
      if ($n = $fill($items)) {
        $draft_entity->set($field, $items);
        $autosave->saveEntity($draft_entity);
        print $entity->label() . " (draft): $n Columns filled\n";
      }
      // The save above stamped the page newer than the draft: Drupal's
      // changed-time check would refuse to publish it ("modified by
      // another user"). Restamp the draft with the page's time and advance
      // Canvas's own base too, keeping the draft (LESSONS.md).
      $fresh = $storage->loadUnchanged($entity->id());
      $draft = $autosave->getAutoSaveEntity($fresh);
      if (!$draft->isEmpty() && $draft->entity->getChangedTime() < $fresh->getChangedTime()) {
        $draft_entity = $draft->entity;
        $draft_entity->setChangedTime($fresh->getChangedTime());
        $autosave->saveEntity($draft_entity);
        print $entity->label() . " (draft): restamped to the new revision\n";
      }
      if ($conflict = $autosave->getUnresolvedConflictForEntity($fresh)) {
        $autosave->resolveConflict($fresh, $conflict);
      }
    }
  }
}
// Patterns keep a tree in config.
foreach (\Drupal::configFactory()->listAll('canvas.pattern.') as $name) {
  $config = \Drupal::configFactory()->getEditable($name);
  $items = (array) $config->get('component_tree');
  if ($n = $fill($items)) {
    $config->set('component_tree', $items)->save();
    print "$name: $n Columns filled\n";
  }
}
print "Done.\n";
