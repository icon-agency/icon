<?php

/**
 * @file
 * The cut-out inset onto the editor's 0–100 scale (16 Oct 2026; user call:
 * "0% means none and 100% leaves nothing, 50 is in the middle"). The props
 * (a figure's `pad`, the Work tile's Foreground inset) used to be the inset
 * from EACH edge, so 50% left nothing and 40% was the cap; now they are how
 * much of the frame the cut-out gives up, and the templates halve them for
 * the CSS. Every stored value is doubled, once, so nothing on the page
 * moves. Pages, work articles and open drafts; drafts restamped. Idempotent
 * only in that a second run would double again — it is marked in State.
 *
 * Run: ddev exec drush php:script scripts/inset-scale.php
 */

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\user\Entity\User;

if (\Drupal::state()->get('icon_site.inset_scale_doubled')) {
  print "Already on the 0–100 scale.\n";
  return;
}
\Drupal::service('account_switcher')->switchTo(User::load(1));
$etm = \Drupal::entityTypeManager();
$autosave = \Drupal::service(AutoSaveManager::class);
$double = static fn(string $v): string => preg_match('/^\s*(\d+(?:\.\d+)?)\s*%?\s*$/', $v, $m) ? rtrim(rtrim(number_format((float) $m[1] * 2, 1, '.', ''), '0'), '.') . '%' : $v;
$fix = static function (array &$items) use ($double): int {
  $n = 0;
  foreach ($items as &$i) {
    $in = json_decode((string) ($i['inputs'] ?? ''), TRUE);
    if (is_array($in) && isset($in['pad']) && $in['pad'] !== '' && $double($in['pad']) !== $in['pad']) {
      $in['pad'] = $double($in['pad']);
      $i['inputs'] = json_encode($in, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      $n++;
    }
  }
  return $n;
};
$total = 0;
foreach ([['canvas_page', 'components'], ['node', 'field_work_canvas']] as [$type, $field]) {
  $storage = $etm->getStorage($type);
  foreach ($storage->loadMultiple() as $entity) {
    if (!$entity->hasField($field)) {
      continue;
    }
    $changed = FALSE;
    $items = $entity->get($field)->getValue();
    if ($n = $fix($items)) {
      $entity->set($field, $items);
      $changed = TRUE;
      $total += $n;
    }
    if ($type === 'node' && $entity->hasField('field_work_tile_inset') && ($v = (string) $entity->get('field_work_tile_inset')->value) !== '' && $double($v) !== $v) {
      $entity->set('field_work_tile_inset', $double($v));
      $changed = TRUE;
      $total++;
    }
    if ($changed) {
      if (method_exists($entity, 'setNewRevision')) {
        $entity->setNewRevision(TRUE);
      }
      $entity->save();
      print $entity->label() . ": insets doubled\n";
    }
    $draft = $autosave->getAutoSaveEntity($entity);
    if (!$draft->isEmpty()) {
      $d = $draft->entity;
      $items = $d->get($field)->getValue();
      if ($fix($items)) {
        $d->set($field, $items);
        $autosave->saveEntity($d);
        print $entity->label() . " (draft): insets doubled\n";
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
      }
    }
  }
}
\Drupal::state()->set('icon_site.inset_scale_doubled', TRUE);
print "Done: $total value(s) doubled.\n";
