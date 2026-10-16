<?php

/**
 * @file
 * "Who made it" into the deliverables (user call, 16 Oct 2026, on the Space
 * Agency folio: "remove this and add to the deliverables at top"). The old
 * pages close on a list of the disciplines on the job under that heading;
 * on a mirrored Work article it stood as a Content block of its own. This
 * moves each such list's items onto the Work masthead's deliverables (the
 * node's field follows on save — icon_site_node_presave()) and takes the
 * heading and list out of the text, dropping the block when nothing is
 * left. An open draft gets the same, kept and restamped (columns-split.php's
 * recipe). Idempotent; work-mirror.php does the same for new runs.
 *
 * Usage: ddev exec drush php:script scripts/who-made-it.php
 */

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\user\Entity\User;

\Drupal::service('account_switcher')->switchTo(User::load(1));
$etm = \Drupal::entityTypeManager();

/**
 * Moves the lists in a tree; the number of blocks touched.
 */
$move = static function (array &$items): int {
  $n = 0;
  $decode = static fn($inputs) => is_string($inputs) ? (json_decode($inputs, TRUE) ?: []) : (array) $inputs;
  $masthead = NULL;
  foreach ($items as $i => $item) {
    if (($item['component_id'] ?? '') === 'sdc.icon.work-masthead') {
      $masthead = $i;
    }
  }
  if ($masthead === NULL) {
    return 0;
  }
  $deliverables = $decode($items[$masthead]['inputs'])['deliverables'] ?? [];
  foreach ($items as $i => $item) {
    if (($item['component_id'] ?? '') !== 'sdc.icon.prose') {
      continue;
    }
    $inputs = $decode($item['inputs']);
    $html = (string) ($inputs['text']['value'] ?? '');
    if (!preg_match('#<h[23]>\s*Who made it\s*</h[23]>\s*(?:<!--.*?-->\s*)*<ul>(.*?)</ul>#is', $html, $m)) {
      continue;
    }
    preg_match_all('#<li>(.*?)</li>#is', $m[1], $li);
    foreach ($li[1] as $x) {
      $x = rtrim(trim(html_entity_decode(strip_tags($x))), '.');
      if ($x !== '' && !in_array($x, $deliverables, TRUE)) {
        $deliverables[] = $x;
      }
    }
    $html = trim(str_replace($m[0], '', $html));
    if (trim(strip_tags($html)) === '') {
      unset($items[$i]);
    }
    else {
      $inputs['text']['value'] = $html;
      $items[$i]['inputs'] = is_string($item['inputs']) ? json_encode($inputs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $inputs;
    }
    $n++;
  }
  if ($n) {
    $inputs = $decode($items[$masthead]['inputs']);
    $inputs['deliverables'] = array_values($deliverables);
    $items[$masthead]['inputs'] = is_string($items[$masthead]['inputs']) ? json_encode($inputs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $inputs;
    $items = array_values($items);
  }
  return $n;
};

$autosave = \Drupal::service(AutoSaveManager::class);
$storage = $etm->getStorage('node');
$ids = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'work')->execute();
foreach ($storage->loadMultiple($ids) as $node) {
  $items = $node->get('field_work_canvas')->getValue();
  if ($n = $move($items)) {
    $node->set('field_work_canvas', $items);
    $violations = $node->validate();
    if (count($violations)) {
      print $node->label() . ": not saved — " . strip_tags((string) $violations[0]->getMessage()) . "\n";
    }
    else {
      $node->setNewRevision(TRUE);
      $node->setRevisionLogMessage('Mirrored from iconagency.com.au; "Who made it" moved into the deliverables (scripts/who-made-it.php).');
      $node->save();
      print $node->label() . ": $n block(s) moved into the deliverables\n";
    }
  }
  $draft = $autosave->getAutoSaveEntity($node);
  if (!$draft->isEmpty()) {
    $draft_entity = $draft->entity;
    $items = $draft_entity->get('field_work_canvas')->getValue();
    if ($n = $move($items)) {
      $draft_entity->set('field_work_canvas', $items);
      $autosave->saveEntity($draft_entity);
      print $node->label() . " (draft): $n block(s) moved into the deliverables\n";
    }
    $fresh = $storage->loadUnchanged($node->id());
    $draft = $autosave->getAutoSaveEntity($fresh);
    if (!$draft->isEmpty() && $draft->entity->getChangedTime() < $fresh->getChangedTime()) {
      $draft_entity = $draft->entity;
      $draft_entity->setChangedTime($fresh->getChangedTime());
      $autosave->saveEntity($draft_entity);
      print $node->label() . " (draft): restamped to the new revision\n";
    }
    // (the conflict check is Canvas Pages' only — it type-hints Page — so a
    // node with an open draft threw on the host, 16 Oct 2026)
    if ($fresh instanceof \Drupal\canvas\Entity\Page && ($conflict = $autosave->getUnresolvedConflictForEntity($fresh))) {
      $autosave->resolveConflict($fresh, $conflict);
    }
  }
}
print "Done.\n";
