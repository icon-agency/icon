<?php

/**
 * @file
 * Lifts every Run component's children to the top of each Work article's
 * tree, in the Run's place, and drops the Run: the runs are inferred on
 * the page since Sep 2026 (the Paragraphs rule, back), so a Run in the
 * tree is one more thing for an editor to understand. Run it again for
 * any article that still has one.
 *
 * Run: ddev exec "ICON_SEED=1 drush php:script scripts/work-runs-unwrap.php"
 */

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

require_once __DIR__ . '/_guard.php';

\Drupal::service('account_switcher')->switchTo(User::load(1));

$nids = getenv('ICON_NIDS')
  ? array_map('intval', explode(',', (string) getenv('ICON_NIDS')))
  : \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'work')->execute();

foreach (Node::loadMultiple($nids) as $node) {
  if ($node->bundle() !== 'work') {
    continue;
  }
  $values = $node->get('field_work_canvas')->getValue();
  $runs = array_filter($values, static fn(array $v): bool => $v['component_id'] === 'sdc.icon.run');
  if (!$runs) {
    print "Node {$node->id()} ({$node->label()}): no Run\n";
    continue;
  }
  $run_uuids = array_column($runs, 'uuid');
  // The items in tree order: a Run's children stand where the Run stood,
  // at the top level, in their own order; the Run itself goes.
  $top = array_values(array_filter($values, static fn(array $v): bool => empty($v['parent_uuid'])));
  $rest = array_values(array_filter($values, static fn(array $v): bool => !empty($v['parent_uuid'])));
  $out = [];
  foreach ($top as $item) {
    if ($item['component_id'] !== 'sdc.icon.run') {
      $out[] = $item;
      continue;
    }
    foreach ($rest as $child) {
      if ($child['parent_uuid'] === $item['uuid']) {
        $child['parent_uuid'] = NULL;
        $child['slot'] = NULL;
        $out[] = $child;
      }
    }
  }
  foreach ($rest as $child) {
    if (!in_array($child['parent_uuid'], $run_uuids, TRUE)) {
      $out[] = $child;
    }
  }
  $node->set('field_work_canvas', $out);
  $violations = $node->validate();
  if (count($violations)) {
    print "Node {$node->id()} ({$node->label()}): NOT saved —\n";
    foreach ($violations as $v) {
      print '  ' . $v->getPropertyPath() . ': ' . strip_tags((string) $v->getMessage()) . "\n";
    }
    continue;
  }
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('The Runs lifted out of the tree; the runs are inferred (scripts/work-runs-unwrap.php).');
  $node->save();
  \Drupal::service(AutoSaveManager::class)->delete($node);
  print "Node {$node->id()} ({$node->label()}): " . count($runs) . " Run(s) lifted\n";
}
