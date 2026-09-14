<?php

/**
 * @file
 * Seeds the Main navigation menu with the header's links.
 *
 * The header reads Structure → Menus → Main navigation
 * (icon_site_primary_nav()); this puts the links the template used to
 * hard-code into it, once — a menu that already has links is left alone.
 *
 * Run: ICON_SEED=1 drush php:script scripts/main-menu.php
 */

declare(strict_types=1);

use Drupal\menu_link_content\Entity\MenuLinkContent;

if (!getenv('ICON_SEED')) {
  throw new \RuntimeException('Refusing to run without ICON_SEED=1.');
}
$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
if ($storage->loadByProperties(['menu_name' => 'main'])) {
  print "Main navigation already has links: leaving it alone.\n";
  return;
}
$add = static function (string $title, string $path, int $weight, ?string $parent = NULL, ?string $fragment = NULL): MenuLinkContent {
  $link = MenuLinkContent::create([
    'title' => $title,
    'menu_name' => 'main',
    'link' => ['uri' => 'internal:' . $path, 'options' => $fragment ? ['fragment' => $fragment] : []],
    'weight' => $weight,
    'expanded' => TRUE,
    'parent' => $parent,
  ]);
  $link->save();
  return $link;
};
$add('Work', '/work', 0);
$expertise = $add('Expertise', '/services', 1);
$parent = 'menu_link_content:' . $expertise->uuid();
$w = 0;
foreach ([
  ['Interdisciplinary', NULL],
  ['Creative', 'creative'],
  ['Communications', 'communications'],
  ['Digital', 'digital'],
  ['Reputation', 'reputation'],
  ['Behaviour change', 'behaviour-change'],
  ['Production', 'production'],
] as [$title, $fragment]) {
  $add($title, '/services', $w++, $parent, $fragment);
}
$add('About', '/about', 2);
$add('News', '/news', 3);
$add('Contact', '/contact', 4);
print "Main navigation seeded: 5 links, 7 under Expertise.\n";
