<?php

/**
 * @file
 * The old pages' YouTube and Vimeo films into the folios already built
 * (user catch, 16 Oct 2026, on Democracy Cards: "can you add this video to
 * our democracy cards work"). The old site carried them as remote-video
 * media, which the first extraction missed; data/work-folios.json now lists
 * each as an `embed` block (18 across 17 old pages) and work-mirror.php
 * builds them on a run. For a folio that is already a node — edited or not
 * — this adds the Video embed where the data has it: after the text block
 * the data's preceding text block opens on (matched by its heading), else
 * at the end of the article. A node whose tree already carries the film is
 * left alone; an open draft gets the same. Idempotent.
 *
 * Usage: ddev exec drush php:script scripts/work-embeds.php
 */

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\user\Entity\User;

\Drupal::service('account_switcher')->switchTo(User::load(1));
$etm = \Drupal::entityTypeManager();
$uuid = \Drupal::service('uuid');
$version = (string) $etm->getStorage('component')->load('sdc.icon.news-article-video')->get('active_version');
$data = json_decode(file_get_contents(__DIR__ . '/data/work-folios.json'), TRUE);
$decode = static fn($inputs) => is_string($inputs) ? (json_decode($inputs, TRUE) ?: []) : (array) $inputs;

/**
 * Adds a folio's missing embeds to a tree; the number added.
 */
$fill = static function (array &$items, array $folio) use ($uuid, $version, $decode): int {
  $n = 0;
  $blocks = $folio['blocks'];
  foreach ($blocks as $i => $block) {
    if ($block['type'] !== 'embed') {
      continue;
    }
    foreach ($items as $item) {
      if (($item['component_id'] ?? '') === 'sdc.icon.news-article-video' && ($decode($item['inputs'])['embed_url'] ?? '') === $block['url']) {
        continue 2;
      }
    }
    // the anchor: the nearest text block before it in the data, by heading
    $heading = NULL;
    for ($j = $i - 1; $j >= 0; $j--) {
      if ($blocks[$j]['type'] === 'text' && preg_match('#<h[23]>\s*(.+?)\s*</h[23]>#is', $blocks[$j]['html'], $m)) {
        $heading = trim(html_entity_decode(strip_tags($m[1])));
        break;
      }
    }
    $at = NULL;
    if ($heading !== NULL) {
      foreach ($items as $k => $item) {
        if (($item['component_id'] ?? '') === 'sdc.icon.prose' && empty($item['parent_uuid'])) {
          $html = (string) ($decode($item['inputs'])['text']['value'] ?? '');
          if (preg_match('#<h[23]>\s*' . preg_quote($heading, '#') . '\s*</h[23]>#i', $html)) {
            $at = $k + 1;
          }
        }
      }
    }
    $new = [
      'parent_uuid' => NULL,
      'slot' => NULL,
      'uuid' => $uuid->generate(),
      'component_id' => 'sdc.icon.news-article-video',
      'component_version' => $version,
      'inputs' => json_encode(['embed_url' => $block['url'], 'title' => $block['title'] ?: $folio['project']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      'label' => NULL,
    ];
    if ($at === NULL) {
      $items[] = $new;
      print "    + " . $block['url'] . " (at the end)\n";
    }
    else {
      array_splice($items, $at, 0, [$new]);
      print "    + " . $block['url'] . " (after \"$heading\")\n";
    }
    $n++;
  }
  return $n;
};

$autosave = \Drupal::service(AutoSaveManager::class);
$storage = $etm->getStorage('node');
foreach ($data as $folio) {
  if (!array_filter($folio['blocks'], static fn($b) => $b['type'] === 'embed')) {
    continue;
  }
  $existing = $storage->loadByProperties(['type' => 'work', 'title' => $folio['project'], 'field_work_client' => $folio['client']]);
  if (!$existing) {
    continue;
  }
  $node = reset($existing);
  print $node->label() . " (node " . $node->id() . ")\n";
  $items = $node->get('field_work_canvas')->getValue();
  if ($n = $fill($items, $folio)) {
    $node->set('field_work_canvas', $items);
    $violations = $node->validate();
    if (count($violations)) {
      print "  not saved — " . strip_tags((string) $violations[0]->getMessage()) . "\n";
    }
    else {
      $node->setNewRevision(TRUE);
      $node->setRevisionLogMessage("Mirrored from iconagency.com.au{$folio['old_path']}; the old page's film added (scripts/work-embeds.php).");
      $node->save();
      print "  $n film(s) added\n";
    }
  }
  $draft = $autosave->getAutoSaveEntity($node);
  if (!$draft->isEmpty()) {
    $draft_entity = $draft->entity;
    $items = $draft_entity->get('field_work_canvas')->getValue();
    if ($n = $fill($items, $folio)) {
      $draft_entity->set('field_work_canvas', $items);
      $autosave->saveEntity($draft_entity);
      print "  (draft) $n film(s) added\n";
    }
    $fresh = $storage->loadUnchanged($node->id());
    $draft = $autosave->getAutoSaveEntity($fresh);
    if (!$draft->isEmpty() && $draft->entity->getChangedTime() < $fresh->getChangedTime()) {
      $draft_entity = $draft->entity;
      $draft_entity->setChangedTime($fresh->getChangedTime());
      $autosave->saveEntity($draft_entity);
      print "  (draft) restamped to the new revision\n";
    }
  }
}
print "Done.\n";
