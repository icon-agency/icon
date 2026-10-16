<?php

/**
 * @file
 * Democracy Cards' results as a Results band (user ask, 16 Oct 2026: "could
 * you get this to fit the results component"). The old page listed the
 * figures as bold bullets inside the Solution text; here the Solution text
 * closes before them, a Content block carries the "Results" heading, a
 * Result stats band holds the four figures, and the two closing sentences
 * follow as text. The film the old page had between the two stays where it
 * is. Runs once — a node already carrying a Results band is left alone;
 * an open draft gets the same.
 *
 * Usage: ddev exec drush php:script scripts/results-democracy-cards.php
 */

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\user\Entity\User;

\Drupal::service('account_switcher')->switchTo(User::load(1));
$etm = \Drupal::entityTypeManager();
$uuid = \Drupal::service('uuid');
$version = static fn(string $id): string => (string) $etm->getStorage('component')->load($id)->get('active_version');
$enc = static fn(array $inputs): string => json_encode($inputs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$item = static fn(string $component, array $inputs, ?string $parent = NULL, ?string $slot = NULL) => [
  'parent_uuid' => $parent,
  'slot' => $slot,
  'uuid' => $uuid->generate(),
  'component_id' => $component,
  'component_version' => $version($component),
  'inputs' => $enc($inputs),
  'label' => NULL,
];
$prose = static fn(string $html) => ['text' => ['value' => $html, 'format' => 'canvas_html_block']];

$results = [
  ['value' => '2.2+', 'unit' => 'million', 'label' => 'Australians reached through precision-targeted digital delivery'],
  ['value' => '85,553', 'unit' => '', 'label' => 'Click-throughs to educational resources, well above engagement benchmarks'],
  ['value' => '$0.35', 'unit' => '', 'label' => 'Cost per click'],
  ['value' => 'Top 5', 'unit' => '', 'label' => '‘Misinformation Explained’ became the most-viewed campaign resource and one of MoAD’s most-visited pages of the year'],
];
$closing = '<p>Balanced sensitivity and clarity in addressing misinformation, strengthening public trust.</p><p>Established a high-impact, cost-effective model for future democratic engagement initiatives.</p>';

/**
 * Reshapes the tree; TRUE when it did.
 */
$reshape = static function (array &$items) use ($item, $prose, $enc, $results, $closing): bool {
  foreach ($items as $i) {
    if ($i['component_id'] === 'sdc.icon.work-stats') {
      return FALSE;
    }
  }
  foreach ($items as $k => $i) {
    if ($i['component_id'] !== 'sdc.icon.prose') {
      continue;
    }
    $inputs = is_string($i['inputs']) ? json_decode($i['inputs'], TRUE) : $i['inputs'];
    $html = (string) ($inputs['text']['value'] ?? '');
    if (!preg_match('#^(.*?)<!---->\s*<h2>Results</h2><ul>.*?</ul>#is', $html, $m)) {
      continue;
    }
    // the Solution text, closed before the results
    $inputs['text']['value'] = trim(str_replace('<!---->', '', $m[1]));
    $items[$k]['inputs'] = is_string($i['inputs']) ? $enc($inputs) : $inputs;
    // after the film that follows it, when one does
    $at = $k + 1;
    if (($items[$at]['component_id'] ?? '') === 'sdc.icon.news-article-video') {
      $at++;
    }
    $band = $item('sdc.icon.work-stats', []);
    $new = [$item('sdc.icon.prose', $prose('<h2>Results</h2>')), $band];
    foreach ($results as $r) {
      $new[] = $item('sdc.icon.work-stat', array_filter($r, static fn($v) => $v !== ''), $band['uuid'], 'results');
    }
    $new[] = $item('sdc.icon.prose', $prose($closing));
    array_splice($items, $at, 0, $new);
    return TRUE;
  }
  return FALSE;
};

$storage = $etm->getStorage('node');
$nodes = $storage->loadByProperties(['type' => 'work', 'title' => 'Democracy Cards']);
$autosave = \Drupal::service(AutoSaveManager::class);
foreach ($nodes as $node) {
  $items = $node->get('field_work_canvas')->getValue();
  if ($reshape($items)) {
    $node->set('field_work_canvas', $items);
    $violations = $node->validate();
    if (count($violations)) {
      print "not saved — " . strip_tags((string) $violations[0]->getMessage()) . "\n";
      continue;
    }
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Mirrored from iconagency.com.au; the results as a Results band (scripts/results-democracy-cards.php).');
    $node->save();
    print $node->label() . " (node " . $node->id() . "): the results band placed\n";
  }
  else {
    print $node->label() . ": nothing to do\n";
  }
  $draft = $autosave->getAutoSaveEntity($node);
  if (!$draft->isEmpty()) {
    $draft_entity = $draft->entity;
    $items = $draft_entity->get('field_work_canvas')->getValue();
    if ($reshape($items)) {
      $draft_entity->set('field_work_canvas', $items);
      $autosave->saveEntity($draft_entity);
      print "  (draft) the results band placed\n";
    }
    $fresh = $storage->loadUnchanged($node->id());
    $draft = $autosave->getAutoSaveEntity($fresh);
    if (!$draft->isEmpty() && $draft->entity->getChangedTime() < $fresh->getChangedTime()) {
      $draft_entity = $draft->entity;
      $draft_entity->setChangedTime($fresh->getChangedTime());
      $autosave->saveEntity($draft_entity);
    }
  }
}
print "Done.\n";
