<?php

/**
 * @file
 * Moves the gallery figures and Work mastheads placed before to the one pick.
 *
 * Gallery row picture's `media` (pictures) + `film` + `film_layer` became
 * `picture` and `float`, one pick each, picture or film; Work masthead's
 * `banner` + `banner_film` became `banner`. An instance placed before still
 * renders through the old inputs; this rewrites it onto the current
 * version. Saved pages and articles only — an open draft keeps what it
 * holds and still renders. Idempotent.
 *
 * Run: ICON_SEED=1 drush php:script scripts/single-picks.php
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
$versions = [
  'sdc.icon.work-gallery-figure' => (string) Component::load('sdc.icon.work-gallery-figure')->get('active_version'),
  'sdc.icon.work-masthead' => (string) Component::load('sdc.icon.work-masthead')->get('active_version'),
];
$id = static fn($v): ?int => is_array($v) && isset($v['target_id']) ? (int) $v['target_id'] : NULL;

$convert = static function (array $items) use ($versions, $id): ?array {
  $changed = FALSE;
  foreach ($items as &$item) {
    if (!isset($versions[$item['component_id']])) {
      continue;
    }
    $inputs = is_string($item['inputs']) ? (json_decode($item['inputs'], TRUE) ?: []) : $item['inputs'];
    if ($item['component_id'] === 'sdc.icon.work-gallery-figure') {
      if (isset($inputs['picture']) || (!isset($inputs['media']) && !isset($inputs['film']))) {
        continue;
      }
      $pictures = array_values(array_filter(array_map($id, $inputs['media'] ?? [])));
      $film = $id($inputs['film'] ?? NULL);
      $layered = str_starts_with((string) ($inputs['style'] ?? ''), 'layered');
      // The old twig's order: the film took the ground, or the float when
      // film_layer said so; the first picture took the other place.
      if ($film && ($inputs['film_layer'] ?? 'ground') === 'float') {
        $picture = $pictures[0] ?? NULL;
        $float = $film;
      }
      elseif ($film) {
        $picture = $film;
        $float = $layered ? ($pictures[0] ?? NULL) : NULL;
      }
      else {
        $picture = $pictures[0] ?? NULL;
        $float = $layered ? ($pictures[1] ?? NULL) : NULL;
      }
      unset($inputs['media'], $inputs['film'], $inputs['film_layer']);
      if ($picture) {
        $inputs['picture'] = ['target_id' => $picture];
      }
      if ($float) {
        $inputs['float'] = ['target_id' => $float];
      }
    }
    else {
      $film = $id($inputs['banner_film'] ?? NULL);
      if (!$film) {
        continue;
      }
      unset($inputs['banner_film']);
      $inputs['banner'] = ['target_id' => $film];
    }
    $item['inputs'] = json_encode($inputs ?: new \stdClass(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $item['component_version'] = $versions[$item['component_id']];
    $changed = TRUE;
  }
  unset($item);
  return $changed ? $items : NULL;
};

foreach (Page::loadMultiple() as $page) {
  if ($items = $convert($page->get('components')->getValue())) {
    $page->set('components', $items)->setNewRevision(TRUE);
    $page->setRevisionLogMessage('Gallery figures and mastheads: one media pick (scripts/single-picks.php).');
    $page->save();
    print "Page {$page->id()} ({$page->label()}): moved to the one pick.\n";
  }
}
$nids = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'work')->execute();
foreach (Node::loadMultiple($nids) as $node) {
  if ($node->hasField('field_work_canvas') && ($items = $convert($node->get('field_work_canvas')->getValue()))) {
    $node->set('field_work_canvas', $items)->setNewRevision(TRUE);
    $node->save();
    print "Node {$node->id()} ({$node->label()}): moved to the one pick.\n";
  }
}
\Drupal::service('account_switcher')->switchBack();
