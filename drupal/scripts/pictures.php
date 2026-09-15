<?php

/**
 * @file
 * Moves every placed Gallery row and Picture scroller onto the one Picture.
 *
 * A Gallery row (sdc.icon.work-gallery) becomes a Columns and boxes on the
 * Tight gap with a column per figure, each figure a Picture
 * (sdc.icon.news-article-figure): plain → natural, portrait → portrait,
 * layered → landscape, layered_square → square; a layered figure's only
 * pick was its cut-out riding the colour ground, so it moves to `float`.
 * A Picture scroller keeps its uuid and ground and takes its pictures as
 * Picture children in its slot; its `films` list only ever held the
 * schema's example and is dropped. Saved pages and articles; with
 * ICON_DRAFTS=1 the open editor drafts too — a draft that still held the
 * old rows would have put them back on publish (user question, Sep 2026:
 * "what do I need to update in the CMS?"). Idempotent.
 *
 * Run: ICON_SEED=1 drush php:script scripts/pictures.php
 *      ICON_SEED=1 ICON_DRAFTS=1 drush php:script scripts/pictures.php
 */

declare(strict_types=1);

use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\Page;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

if (!getenv('ICON_SEED')) {
  throw new \RuntimeException('Refusing to run without ICON_SEED=1.');
}
\Drupal::service('account_switcher')->switchTo(User::load(1));
$version = fn(string $id): string => (string) Component::load($id)->get('active_version');
$v = ['columns' => $version('sdc.icon.columns'), 'picture' => $version('sdc.icon.news-article-figure'), 'scroller' => $version('sdc.icon.work-scroller')];
$uuid = fn(): string => \Drupal::service('uuid')->generate();
$json = fn(array $a): string => json_encode($a ?: new \stdClass(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$decode = fn($in): array => is_string($in) ? (json_decode($in, TRUE) ?: []) : ($in ?: []);
$id = static fn($x): ?int => is_array($x) && isset($x['target_id']) ? (int) $x['target_id'] : NULL;

/** A gallery figure's inputs as a Picture's. */
$picture = static function (array $in) use ($id): array {
  $style = (string) ($in['style'] ?? 'plain');
  $ground = $id($in['picture'] ?? NULL);
  $float = $id($in['float'] ?? NULL);
  $shape = ['plain' => 'natural', 'portrait' => 'portrait', 'layered' => 'landscape', 'layered_square' => 'square'][$style] ?? 'natural';
  if (str_starts_with($style, 'layered') && $ground && !$float) {
    // the only pick was the cut-out, riding the colour ground
    [$ground, $float] = [NULL, $ground];
  }
  $out = ['shape' => $shape];
  if ($ground) {
    $out['picture'] = ['target_id' => $ground];
  }
  if ($float) {
    $out['float'] = ['target_id' => $float];
  }
  foreach (['ground', 'pad'] as $k) {
    if (!empty($in[$k])) {
      $out[$k] = $in[$k];
    }
  }
  return $out;
};

$convert = static function (array $items) use ($v, $uuid, $json, $decode, $id, $picture): ?array {
  $changed = FALSE;
  $out = [];
  $rows = [];
  foreach ($items as $item) {
    if ($item['component_id'] === 'sdc.icon.work-gallery') {
      $rows[$item['uuid']] = [];
    }
  }
  foreach ($items as $item) {
    if ($item['component_id'] === 'sdc.icon.work-gallery-figure' && isset($rows[$item['parent_uuid']])) {
      $rows[$item['parent_uuid']][] = $item;
    }
  }
  foreach ($items as $item) {
    $cid = $item['component_id'];
    if ($cid === 'sdc.icon.work-gallery') {
      $figures = $rows[$item['uuid']];
      $n = max(1, min(4, count($figures)));
      $item['component_id'] = 'sdc.icon.columns';
      $item['component_version'] = $v['columns'];
      $item['inputs'] = $json(['columns' => $n, 'gap' => 'tight', 'box' => 'none']);
      $out[] = $item;
      foreach (array_values($figures) as $i => $figure) {
        $figure['component_id'] = 'sdc.icon.news-article-figure';
        $figure['component_version'] = $v['picture'];
        $figure['slot'] = 'column_' . min($i + 1, $n);
        $figure['inputs'] = $json($picture($decode($figure['inputs'])));
        $out[] = $figure;
      }
      $changed = TRUE;
      continue;
    }
    if ($cid === 'sdc.icon.work-gallery-figure' && isset($rows[$item['parent_uuid']])) {
      continue;
    }
    if ($cid === 'sdc.icon.work-scroller') {
      $in = $decode($item['inputs']);
      if (!isset($in['items'])) {
        $out[] = $item;
        continue;
      }
      $ids = array_values(array_filter(array_map($id, $in['items'])));
      $item['component_version'] = $v['scroller'];
      $item['inputs'] = $json(array_filter(['ground' => $in['ground'] ?? '']));
      $out[] = $item;
      foreach ($ids as $mid) {
        $out[] = [
          'uuid' => $uuid(),
          'parent_uuid' => $item['uuid'],
          'slot' => 'pictures',
          'component_id' => 'sdc.icon.news-article-figure',
          'component_version' => $v['picture'],
          'inputs' => $json(['shape' => 'natural', 'picture' => ['target_id' => $mid]]),
          'label' => NULL,
        ];
      }
      $changed = TRUE;
      continue;
    }
    $out[] = $item;
  }
  return $changed ? $out : NULL;
};

foreach (Page::loadMultiple() as $page) {
  if ($items = $convert($page->get('components')->getValue())) {
    $page->set('components', $items);
    $page->setNewRevision(TRUE);
    $page->setRevisionLogMessage('Pictures: one component (scripts/pictures.php).');
    $page->save();
    print "Page {$page->id()} ({$page->label()}): rows and scrollers moved to Pictures.\n";
  }
}
$nids = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'work')->execute();
foreach (Node::loadMultiple($nids) as $node) {
  if ($node->hasField('field_work_canvas') && ($items = $convert($node->get('field_work_canvas')->getValue()))) {
    $node->set('field_work_canvas', $items);
    $node->setNewRevision(TRUE);
    $node->save();
    print "Node {$node->id()} ({$node->label()}): rows and scrollers moved to Pictures.\n";
  }
}
if (getenv('ICON_DRAFTS')) {
  $store = \Drupal::keyValue('canvas.auto_save');
  foreach ($store->getAll() as $key => $draft) {
    $field = isset($draft['data']['components']) ? 'components' : (isset($draft['data']['field_work_canvas']) ? 'field_work_canvas' : NULL);
    if ($field && is_array($draft['data'][$field]) && ($items = $convert($draft['data'][$field]))) {
      $draft['data'][$field] = $items;
      $store->set($key, $draft);
      print "Draft $key (" . ($draft['label'] ?? '') . "): rows and scrollers moved to Pictures.\n";
    }
  }
}
\Drupal::service('account_switcher')->switchBack();
