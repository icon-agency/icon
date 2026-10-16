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
require __DIR__ . '/mirror-media.inc.php';
$uuid = \Drupal::service('uuid');
$version = static fn(string $id): string => (string) $etm->getStorage('component')->load($id)->get('active_version');
$data = json_decode(file_get_contents(__DIR__ . '/data/work-folios.json'), TRUE);
$decode = static fn($inputs) => is_string($inputs) ? (json_decode($inputs, TRUE) ?: []) : (array) $inputs;

/**
 * Adds a folio's missing embeds to a tree; the number added.
 */
$fill = static function (array &$items, array $folio) use ($uuid, $version, $decode, $media_for, $image, $slug): int {
  $n = 0;
  $blocks = $folio['blocks'];
  $dir = 'public://work/' . $slug($folio['project']);
  foreach ($blocks as $i => $block) {
    // a film with a poster (a Video hosted) travels the same way as an embed
    $film = $block['type'] === 'media' && count($block['items']) === 1 && ($block['items'][0]['kind'] ?? '') === 'video' && !empty($block['items'][0]['poster']);
    if ($block['type'] !== 'embed' && !$film) {
      continue;
    }
    $component = $film ? 'sdc.icon.work-video' : 'sdc.icon.news-article-video';
    if ($film) {
      $media = $media_for($block['items'][0], 'photo', $dir);
      $cover = $media ? $image($block['items'][0]['poster']['url'], $block['items'][0]['poster']['alt'] ?? '', 'photo', $dir) : NULL;
      if (!$media || !$cover) {
        print "    ! the film or its poster could not be fetched\n";
        continue;
      }
      $inputs = ['film' => ['target_id' => (int) $media->id()], 'cover' => ['target_id' => (int) $cover->id()], 'title' => $block['items'][0]['title'] ?? $folio['project']];
      $key = 'film';
      // (a save from the editor stores the pick as a field item — the id under target_id either way)
      $same = static fn(array $in) => (int) ($in['film']['target_id'] ?? $in['film']['value']['target_id'] ?? 0) === (int) $media->id();
    }
    else {
      $inputs = ['embed_url' => $block['url'], 'title' => $block['title'] ?: $folio['project']];
      // (a save from the editor stores the address as a link item — {uri, options};
      // the plain string is the mirror's; a run read only the string and added
      // the Democracy Cards film twice, 16 Oct 2026)
      $same = static fn(array $in) => (is_array($in['embed_url'] ?? NULL) ? ($in['embed_url']['uri'] ?? '') : ($in['embed_url'] ?? '')) === $block['url'];
    }
    foreach ($items as $item) {
      if (($item['component_id'] ?? '') === $component && $same($decode($item['inputs']))) {
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
    // …and the text block after it, which wins when it is found (a block
    // between — a gallery — would otherwise be skipped over)
    $next = NULL;
    for ($j = $i + 1; $j < count($blocks); $j++) {
      if ($blocks[$j]['type'] === 'text' && preg_match('#<h[23]>(.+?)</h[23]>#is', $blocks[$j]['html'], $m)) {
        $next = trim(html_entity_decode(strip_tags($m[1])));
        break;
      }
    }
    $find = static function (?string $text) use ($items, $decode): ?int {
      if ($text === NULL) {
        return NULL;
      }
      $at = NULL;
      foreach ($items as $k => $item) {
        if (($item['component_id'] ?? '') === 'sdc.icon.prose' && empty($item['parent_uuid'])) {
          $html = (string) ($decode($item['inputs'])['text']['value'] ?? '');
          if (preg_match('#<h[23]>(.+?)</h[23]>#is', $html, $m) && strcasecmp(trim(html_entity_decode(strip_tags($m[1]))), $text) === 0) {
            $at = $k;
          }
        }
      }
      return $at;
    };
    $where = '';
    if (($k = $find($next)) !== NULL) {
      $at = $k;
      $where = "before \"$next\"";
    }
    elseif (($k = $find($heading)) !== NULL) {
      $at = $k + 1;
      $where = "after \"$heading\"";
    }
    else {
      $at = NULL;
      $where = 'at the end';
    }
    $new = [
      'parent_uuid' => NULL,
      'slot' => NULL,
      'uuid' => $uuid->generate(),
      'component_id' => $component,
      'component_version' => $version($component),
      'inputs' => json_encode($inputs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      'label' => NULL,
    ];
    if ($at === NULL) {
      $items[] = $new;
    }
    else {
      array_splice($items, $at, 0, [$new]);
    }
    print "    + " . ($film ? $block['items'][0]['file'] : $block['url']) . " ($where)\n";
    $n++;
  }
  return $n;
};

$autosave = \Drupal::service(AutoSaveManager::class);
$storage = $etm->getStorage('node');
foreach ($data as $folio) {
  if (!array_filter($folio['blocks'], static fn($b) => $b['type'] === 'embed' || ($b['type'] === 'media' && count($b['items']) === 1 && ($b['items'][0]['kind'] ?? '') === 'video' && !empty($b['items'][0]['poster'])))) {
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
