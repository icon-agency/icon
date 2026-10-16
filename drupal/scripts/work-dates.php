<?php

/**
 * @file
 * Every Work folio dated as the old site dates it (user call, 16 Oct 2026:
 * "match the work site dates on the old site"), the Work landing being in
 * date order. The date is the data's (scripts/data/work-folios.json), which
 * the mirror stamps on a build; this covers the folios built by hand
 * (iCanQuit, Nike) and any whose date the data has since corrected.
 * Idempotent: a node already on its date is left alone.
 *
 * Usage: ddev exec drush php:script scripts/work-dates.php
 */

use Drupal\user\Entity\User;

\Drupal::service('account_switcher')->switchTo(User::load(1));
$storage = \Drupal::entityTypeManager()->getStorage('node');
$data = json_decode(file_get_contents(__DIR__ . '/data/work-folios.json'), TRUE);
$n = 0;
// Two old pages that became ONE folio (the audit's Airwallex pair) take the
// newer of their dates, or the two entries would take turns.
$dates = [];
foreach ($data as $folio) {
  if (empty($folio['date'])) {
    continue;
  }
  $key = $folio['project'] . '|' . $folio['client'];
  $dates[$key] = max($dates[$key] ?? '', $folio['date']);
}
foreach ($data as $folio) {
  $key = $folio['project'] . '|' . $folio['client'];
  if (!isset($dates[$key])) {
    continue;
  }
  $date = $dates[$key];
  unset($dates[$key]);
  $nodes = $storage->loadByProperties(['type' => 'work', 'title' => $folio['project'], 'field_work_client' => $folio['client']]);
  if (!$nodes) {
    continue;
  }
  $node = reset($nodes);
  $time = strtotime($date);
  if (date('Y-m-d', $node->getCreatedTime()) === $date) {
    continue;
  }
  $node->setCreatedTime($time);
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage("Dated to the old site's listing ($date; scripts/work-dates.php).");
  $node->save();
  print $node->label() . " → $date\n";
  $n++;
}
print "$n dated.\n";
