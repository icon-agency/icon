<?php

/**
 * @file
 * Turns the split masthead's Reduced padding on (with both lines) across
 * the expertise pages (user call, 23 Sep 2026: "Turn on for the Expertise
 * pages please"). Pages and open drafts; drafts restamped (LESSONS.md).
 * Idempotent.
 *
 * Run: ddev exec drush php:script scripts/masthead-compact.php
 */

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\user\Entity\User;

\Drupal::service('account_switcher')->switchTo(User::load(1));
$storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
$autosave = \Drupal::service(AutoSaveManager::class);
$paths = ['/digital', '/creative', '/communications', '/reputation', '/production'];
// A placed instance keeps the component version it was placed on, and its
// inputs are checked against THAT version's schema — the pages built before
// the toggles existed refuse them ("the `compact` prop is not defined") until
// the instance moves to the active version, which every save via the editor
// does on its own.
$active = \Drupal::entityTypeManager()->getStorage('component')->load('sdc.icon.split-masthead')->getActiveVersion();
$set = static function (array &$items) use ($active): int {
  $n = 0;
  foreach ($items as &$i) {
    if (($i['component_id'] ?? '') !== 'sdc.icon.split-masthead') {
      continue;
    }
    $in = json_decode($i['inputs'] ?? '{}', TRUE) ?: [];
    $want = ['line_above' => TRUE, 'line_below' => TRUE, 'compact' => TRUE];
    if (array_intersect_key($in, $want) === $want && ($i['component_version'] ?? '') === $active) {
      continue;
    }
    $i['inputs'] = json_encode($want + $in);
    $i['component_version'] = $active;
    $n++;
  }
  return $n;
};
foreach ($storage->loadMultiple() as $page) {
  if (!in_array($page->get('path')->alias, $paths, TRUE)) {
    continue;
  }
  $items = $page->get('components')->getValue();
  if ($set($items)) {
    $page->set('components', $items);
    $page->setNewRevision(TRUE);
    $page->save();
    print $page->label() . ": reduced padding on\n";
  }
  $draft = $autosave->getAutoSaveEntity($page);
  if (!$draft->isEmpty()) {
    $d = $draft->entity;
    $items = $d->get('components')->getValue();
    if ($set($items)) {
      $d->set('components', $items);
      $autosave->saveEntity($d);
      print $page->label() . " (draft): reduced padding on\n";
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
      print $page->label() . " (draft): restamped\n";
    }
  }
}
print "Done.\n";
