<?php

/**
 * @file
 * The expertise pages' Featured box, the same on every page (24 Sep 2026;
 * user call: "for consistency, can you make them all light grey (on all
 * Expertise pages we built). Make the 'Featured' a H4 on all"). The
 * one-column box that holds the Featured list opens on light grey, and its
 * heading is a plain Heading 4 — the caps class and the line break that
 * padded it on the older pages come off. Pages and open drafts; drafts
 * restamped (LESSONS.md). Idempotent.
 *
 * Run: ddev exec drush php:script scripts/featured-box.php
 */

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\user\Entity\User;

\Drupal::service('account_switcher')->switchTo(User::load(1));
$etm = \Drupal::entityTypeManager();
$autosave = \Drupal::service(AutoSaveManager::class);

$fix = static function (array &$items): int {
  $n = 0;
  $byUuid = [];
  foreach ($items as $k => $i) {
    $byUuid[$i['uuid']] = $k;
  }
  foreach ($items as &$i) {
    if (($i['component_id'] ?? '') !== 'sdc.icon.prose') {
      continue;
    }
    $in = json_decode((string) $i['inputs'], TRUE) ?: [];
    $html = (string) ($in['text']['value'] ?? '');
    if (!preg_match('/<h[1-6][^>]*>\s*Featured(?:<br>|&nbsp;|\s)*<\/h[1-6]>/', $html, $m)) {
      continue;
    }
    if ($m[0] !== '<h4>Featured</h4>') {
      $in['text']['value'] = str_replace($m[0], '<h4>Featured</h4>', $html);
      $i['inputs'] = json_encode($in, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      $n++;
    }
    $pk = $byUuid[$i['parent_uuid'] ?? ''] ?? NULL;
    if ($pk !== NULL && ($items[$pk]['component_id'] ?? '') === 'sdc.icon.columns') {
      $pin = json_decode((string) $items[$pk]['inputs'], TRUE) ?: [];
      if (($pin['box'] ?? '') !== 'light-grey') {
        $pin['box'] = 'light-grey';
        $items[$pk]['inputs'] = json_encode($pin, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $n++;
      }
    }
  }
  unset($i);
  return $n;
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
  if ($n = $fix($items)) {
    $page->set('components', $items);
    $page->setNewRevision(TRUE);
    $page->setRevisionLogMessage('The Featured box on light grey, its heading a plain Heading 4.');
    $page->save();
  }
  print "$alias: $n change(s)\n";
  $draft = $autosave->getAutoSaveEntity($page);
  if (!$draft->isEmpty()) {
    $d = $draft->entity;
    $items = $d->get('components')->getValue();
    if ($k = $fix($items)) {
      $d->set('components', $items);
      $autosave->saveEntity($d);
      print "$alias (draft): $k change(s)\n";
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
