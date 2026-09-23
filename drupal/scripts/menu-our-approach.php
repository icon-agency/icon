<?php

/**
 * @file
 * "Our approach" at the top of the EXPERTISE drawer, linking to /expertise.
 *
 * Sep 2026 (user call): the EXPERTISE item in the pill is a button that
 * opens the drawer, not a link, so its page is reached from the drawer's
 * first row. Idempotent: adds the child once, keeps it first (weight -10).
 * Content, not config — run it on the host too.
 *
 * Run: ddev exec drush php:script scripts/menu-our-approach.php
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;

$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$parents = array_filter($storage->loadByProperties(['menu_name' => 'main', 'title' => 'Expertise']), fn($l) => !$l->getParentId());
$expertise = reset($parents);
if (!$expertise) {
  print "No Expertise item in the main menu.\n";
  return;
}
$parent = 'menu_link_content:' . $expertise->uuid();
$existing = $storage->loadByProperties(['menu_name' => 'main', 'title' => 'Our approach', 'parent' => $parent]);
if ($existing) {
  $link = reset($existing);
  $link->set('link', ['uri' => 'internal:/expertise'])->set('weight', -10)->set('enabled', TRUE)->save();
  print "Our approach: already there, kept first.\n";
  return;
}
MenuLinkContent::create([
  'title' => 'Our approach',
  'menu_name' => 'main',
  'link' => ['uri' => 'internal:/expertise'],
  'weight' => -10,
  'parent' => $parent,
  'enabled' => TRUE,
])->save();
print "Our approach: added, first under Expertise.\n";
