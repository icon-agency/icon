<?php

/**
 * @file
 * The service headings on the expertise pages in Heading 5 — Caps.
 *
 * 24 Sep 2026 (user call: "change these and all the other services sections
 * in Expertise to H5 Caps"), a step down from the Heading 4 — Caps of the
 * day before (services-headings-caps.php). Every Content block that sits
 * directly in a three-column row — the services — has its h4s turned into
 * h5s with `is-caps`; the Featured box (a one-column box inside the row)
 * and the intro's columns are left as they are. Pages and open drafts;
 * drafts restamped. Idempotent.
 *
 * Run: ddev exec drush php:script scripts/services-headings-h5.php
 */

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\user\Entity\User;

\Drupal::service('account_switcher')->switchTo(User::load(1));
$etm = \Drupal::entityTypeManager();
$autosave = \Drupal::service(AutoSaveManager::class);

$caps = static function (array &$items): int {
  $rows = [];
  foreach ($items as $i) {
    if (($i['component_id'] ?? '') === 'sdc.icon.columns') {
      $in = json_decode((string) $i['inputs'], TRUE) ?: [];
      if ((int) ($in['columns'] ?? 0) === 3) {
        $rows[$i['uuid']] = TRUE;
      }
    }
  }
  $n = 0;
  foreach ($items as &$i) {
    if (($i['component_id'] ?? '') !== 'sdc.icon.prose' || !isset($rows[$i['parent_uuid'] ?? ''])) {
      continue;
    }
    $in = json_decode((string) $i['inputs'], TRUE) ?: [];
    $html = (string) ($in['text']['value'] ?? '');
    $new = preg_replace_callback('/<h4(?: class="([^"]*)")?>(.*?)<\/h4>/s', static function ($m) {
      $classes = array_filter(explode(' ', $m[1] ?? ''));
      if (!in_array('is-caps', $classes, TRUE)) {
        $classes[] = 'is-caps';
      }
      return '<h5 class="' . implode(' ', $classes) . '">' . $m[2] . '</h5>';
    }, $html);
    if ($new !== $html) {
      $in['text']['value'] = $new;
      $i['inputs'] = json_encode($in, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      $n++;
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
  if ($n = $caps($items)) {
    $page->set('components', $items);
    $page->setNewRevision(TRUE);
    $page->setRevisionLogMessage('The service headings in Heading 5 — Caps.');
    $page->save();
  }
  print "$alias: $n service block(s) capped\n";
  $draft = $autosave->getAutoSaveEntity($page);
  if (!$draft->isEmpty()) {
    $d = $draft->entity;
    $items = $d->get('components')->getValue();
    if ($k = $caps($items)) {
      $d->set('components', $items);
      $autosave->saveEntity($d);
      print "$alias (draft): $k capped\n";
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
