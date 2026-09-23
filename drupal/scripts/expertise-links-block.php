<?php

/**
 * @file
 * The Expertise links block in place of the group and its typed items.
 *
 * Sep 2026 (user call: "this section is more of a one off … set it up as a
 * fixed component"). Wherever a page holds Expertise link items — in the
 * intro's expertise slot (Home) or in an Expertise links group (the
 * Expertise page) — they and the group are removed and one Expertise links
 * block takes their slot: in the intro with its gutter off, elsewhere on.
 * The block renders the same list from the main menu. Open drafts are
 * swapped and restamped too (LESSONS.md). Idempotent.
 *
 * Run: ddev exec drush php:script scripts/expertise-links-block.php
 */

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\user\Entity\User;

\Drupal::service('account_switcher')->switchTo(User::load(1));
$etm = \Drupal::entityTypeManager();
$version = (string) $etm->getStorage('component')->load('block.icon_expertise_links')->get('active_version');

$swap = static function (array &$items) use ($version): int {
  $n = 0;
  $groups = [];
  foreach ($items as $item) {
    if (($item['component_id'] ?? '') === 'sdc.icon.expertise-links') {
      $groups[$item['uuid']] = $item;
    }
  }
  // Where a block goes: the intro slot an item sat in, or the group's own slot.
  $targets = [];
  foreach ($items as $item) {
    $id = $item['component_id'] ?? '';
    if ($id === 'sdc.icon.intro-expertise' && !isset($groups[$item['parent_uuid'] ?? ''])) {
      $targets[($item['parent_uuid'] ?? '') . '|' . ($item['slot'] ?? '')] = ['parent' => $item['parent_uuid'] ?? '', 'slot' => $item['slot'] ?? '', 'gutter' => FALSE];
    }
  }
  foreach ($groups as $g) {
    $targets[($g['parent_uuid'] ?? '') . '|' . ($g['slot'] ?? '')] = ['parent' => $g['parent_uuid'] ?? '', 'slot' => $g['slot'] ?? '', 'gutter' => TRUE, 'label' => json_decode((string) $g['inputs'], TRUE)['label'] ?? 'Our expertise'];
  }
  if (!$targets) {
    return 0;
  }
  $kept = [];
  foreach ($items as $item) {
    $id = $item['component_id'] ?? '';
    if ($id === 'sdc.icon.intro-expertise' || $id === 'sdc.icon.expertise-links') {
      $n++;
      continue;
    }
    $kept[] = $item;
  }
  foreach ($targets as $t) {
    $kept[] = [
      'uuid' => \Drupal::service('uuid')->generate(),
      'component_id' => 'block.icon_expertise_links',
      'component_version' => $version,
      'parent_uuid' => $t['parent'] ?: NULL,
      'slot' => $t['slot'] ?: NULL,
      'inputs' => json_encode(['label' => 'Expertise links', 'label_display' => '0', 'heading' => $t['label'] ?? 'Our expertise', 'gutter' => $t['gutter']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ];
  }
  $items = $kept;
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
    if ($n = $swap($items)) {
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
          $entity->setRevisionLogMessage('Expertise links: the block from the main menu in place of the typed list.');
        }
        $entity->save();
        print $entity->label() . ": $n typed pieces replaced by the block\n";
      }
    }
    $draft = $autosave->getAutoSaveEntity($entity);
    if (!$draft->isEmpty()) {
      $draft_entity = $draft->entity;
      $items = $draft_entity->get($field)->getValue();
      if ($n = $swap($items)) {
        $draft_entity->set($field, $items);
        $autosave->saveEntity($draft_entity);
        print $entity->label() . " (draft): $n typed pieces replaced by the block\n";
      }
      $fresh = $storage->loadUnchanged($entity->id());
      $draft = $autosave->getAutoSaveEntity($fresh);
      if (!$draft->isEmpty() && $draft->entity->getChangedTime() < $fresh->getChangedTime()) {
        $draft_entity = $draft->entity;
        $draft_entity->setChangedTime($fresh->getChangedTime());
        $autosave->saveEntity($draft_entity);
        if ($fresh instanceof \Drupal\canvas\Entity\Page && ($conflict = $autosave->getUnresolvedConflictForEntity($fresh))) {
          $autosave->resolveConflict($fresh, $conflict);
        }
        print $entity->label() . " (draft): restamped\n";
      }
    }
  }
}
print "Done.\n";
