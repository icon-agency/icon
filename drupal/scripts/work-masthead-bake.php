<?php

/**
 * @file
 * Bakes the Work masthead into the page (23 Oct 2026).
 *
 * Every Work article whose tree holds a Work masthead component hands its
 * values to the fields and loses the component, as a new revision — the
 * theme draws the frame from the fields from here on. Idempotent. Run
 * locally and on the host after deploy:
 * `drush php:script scripts/work-masthead-bake.php`.
 */

$storage = \Drupal::entityTypeManager()->getStorage('node');
$ids = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'work')->execute();
$done = $clean = 0;
foreach ($ids as $id) {
  $vid = $storage->getLatestRevisionId($id);
  $node = $vid ? $storage->loadRevision($vid) : $storage->load($id);
  if (!$node || _icon_site_work_masthead_delta($node) === NULL) {
    $clean++;
    continue;
  }
  // presave does the work: the fields from the masthead, the masthead out
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('Work masthead baked into the page: its values in the fields, the component out of the tree (23 Oct 2026).');
  $node->save();
  $done++;
  echo "baked: ", $node->label(), " (#", $node->id(), ")\n";
}
echo "work: $done baked, $clean already clean of ", count($ids), "\n";
