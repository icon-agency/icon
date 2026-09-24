<?php

/**
 * @file
 * The expertise pages' service rows into one Grid (24 Sep 2026; user ask:
 * "on wider screens, can these be 4 column?" — go). Each page's run of
 * three-column Columns rows — the Featured box and the services — becomes
 * one flow-grid: the rows' cells move in, in order (the box first), the
 * rows and the Spacers that stacked the cells on a phone come out (the
 * grid keeps its own gap). Pages and open drafts; drafts restamped
 * (LESSONS.md). Idempotent: a page with no three-column row is left alone.
 *
 * Run: ddev exec drush php:script scripts/services-grid.php
 */

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\user\Entity\User;

\Drupal::service('account_switcher')->switchTo(User::load(1));
$etm = \Drupal::entityTypeManager();
$autosave = \Drupal::service(AutoSaveManager::class);
$uuid = \Drupal::service('uuid');
$grid_version = (string) $etm->getStorage('component')->load('sdc.icon.flow-grid')->get('active_version');

$merge = static function (array &$items) use ($uuid, $grid_version): int {
  // The three-column rows at the page's top level, in order.
  $rows = [];
  foreach ($items as $k => $i) {
    if (($i['component_id'] ?? '') === 'sdc.icon.columns' && empty($i['parent_uuid'])) {
      $in = json_decode((string) $i['inputs'], TRUE) ?: [];
      if ((int) ($in['columns'] ?? 0) === 3) {
        $rows[$k] = $i['uuid'];
      }
    }
  }
  if (!$rows) {
    return 0;
  }
  $first = array_key_first($rows);
  $last = array_key_last($rows);
  $row_uuids = array_flip($rows);
  $grid_uuid = $uuid->generate();
  $grid = [
    'parent_uuid' => NULL,
    'slot' => NULL,
    'uuid' => $grid_uuid,
    'component_id' => 'sdc.icon.flow-grid',
    'component_version' => $grid_version,
    'inputs' => json_encode(['gap' => 'normal']),
    'label' => NULL,
  ];
  // The rows' cells, row by row, slot by slot, in their own order; the
  // stacking Spacers come out.
  $cells = [];
  $dropped = [];
  foreach ($rows as $row_uuid) {
    foreach (['column_1', 'column_2', 'column_3', 'column_4'] as $slot) {
      foreach ($items as $k => $i) {
        if (($i['parent_uuid'] ?? '') !== $row_uuid || ($i['slot'] ?? '') !== $slot) {
          continue;
        }
        if ($i['component_id'] === 'sdc.icon.spacer') {
          $dropped[$k] = TRUE;
          continue;
        }
        $i['parent_uuid'] = $grid_uuid;
        $i['slot'] = 'items';
        $cells[$k] = $i;
      }
    }
  }
  // Rebuild: the grid where the first row stood, its cells right after it;
  // the rows, their spacers, and any top-level Spacer between the rows go.
  $out = [];
  foreach ($items as $k => $i) {
    if (isset($row_uuids[$i['uuid']]) || isset($dropped[$k]) || isset($cells[$k])) {
      if ($k === $first) {
        $out[] = $grid;
        foreach ($cells as $c) {
          $out[] = $c;
        }
      }
      continue;
    }
    if ($k > $first && $k < $last && empty($i['parent_uuid']) && $i['component_id'] === 'sdc.icon.spacer') {
      continue;
    }
    $out[] = $i;
  }
  $items = $out;
  return count($rows);
};

$storage = $etm->getStorage('canvas_page');
$aliases = \Drupal::service('path_alias.repository');
foreach (['/digital', '/creative', '/communications', '/reputation', '/production'] as $alias) {
  $path = $aliases->lookupByAlias($alias, 'en');
  if (!$path || !preg_match('#^/page/(\d+)$#', $path['path'], $m)) {
    continue;
  }
  $page = $storage->load($m[1]);
  $items = $page->get('components')->getValue();
  if ($n = $merge($items)) {
    $page->set('components', $items);
    $page->setNewRevision(TRUE);
    $page->setRevisionLogMessage('The service rows into one Grid.');
    $page->save();
  }
  print "$alias: $n row(s) into a grid\n";
  $draft = $autosave->getAutoSaveEntity($page);
  if (!$draft->isEmpty()) {
    $d = $draft->entity;
    $items = $d->get('components')->getValue();
    if ($k = $merge($items)) {
      $d->set('components', $items);
      $autosave->saveEntity($d);
      print "$alias (draft): $k row(s) into a grid\n";
    }
    $fresh = $storage->loadUnchanged($page->id());
    $draft = $autosave->getAutoSaveEntity($fresh);
    if (!$draft->isEmpty() && $draft->entity->getChangedTime() < $fresh->getChangedTime()) {
      $d = $draft->entity;
      $d->setChangedTime($fresh->getChangedTime());
      $autosave->saveEntity($d);
      if ($c = $autosave->getUnresolvedConflictForEntity($fresh)) {
        $autosave->resolveConflict($fresh, $c);
      }
      print "$alias (draft): restamped\n";
    }
  }
}
print "Done.\n";
