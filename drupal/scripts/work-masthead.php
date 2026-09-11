<?php

/**
 * @file
 * Gives each Work article its Work masthead component: a save of every
 * Work node, on which icon_site_node_presave() puts the component first
 * in the tree from the article's fields when there is none. Run it again
 * whenever an article is found without one.
 *
 * Run: ddev exec "ICON_SEED=1 drush php:script scripts/work-masthead.php"
 * One node, or some: ICON_NIDS=16,300 in the environment as well.
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
  if (_icon_site_work_masthead_delta($node) !== NULL) {
    print "Node {$node->id()} ({$node->label()}): has its masthead\n";
    continue;
  }
  // The presave adds the masthead; what it makes is validated first.
  icon_site_node_presave($node);
  $violations = $node->validate();
  if (count($violations)) {
    print "Node {$node->id()} ({$node->label()}): NOT saved —\n";
    foreach ($violations as $v) {
      print '  ' . $v->getPropertyPath() . ': ' . strip_tags((string) $v->getMessage()) . "\n";
    }
    continue;
  }
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('The frame as a Work masthead component (scripts/work-masthead.php).');
  $node->save();
  \Drupal::service(AutoSaveManager::class)->delete($node);
  print "Node {$node->id()} ({$node->label()}): masthead added\n";
}
