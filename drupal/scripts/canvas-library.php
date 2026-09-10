<?php

/**
 * @file
 * Shapes the Canvas component library the way an editor thinks (10 Sep 2026).
 *
 * Four folders, in the order a page is built — Page, Work article, Homepage,
 * and the Parts that only go inside another block — with the names the Work
 * form's Add Block dialog uses, so an editor meets one vocabulary everywhere.
 * Drupal's own blocks and the row components nobody places by hand are
 * switched off (hidden from the library, a switch — they come back the same
 * way), and every other folder goes. Idempotent: run it again after a new
 * component registers to put it in its place.
 *
 * Run: ddev drush php:script scripts/canvas-library
 * Then: ddev drush cex -y
 */

use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\Folder;

// Folder => component ids, in library order.
$plan = [
  'Page' => [
    'sdc.icon.page-header',
    'sdc.icon.prose',
    'sdc.icon.pull-quote',
    'sdc.icon.news-article-figure',
    'sdc.icon.news-article-video',
    'sdc.icon.filmstrip',
    'block.icon_team_profiles',
    'block.icon_clients_marquee',
    'block.views_block.news-next_up',
    'block.views_block.work-next_up',
  ],
  'Work article' => [
    'sdc.icon.work-gallery',
    'sdc.icon.work-video',
    'sdc.icon.work-scroller',
    'sdc.icon.work-stats',
  ],
  'Homepage' => [
    'block.icon_hero',
    'sdc.icon.intro',
    'block.icon_featured_work',
    'block.icon_news_latest',
  ],
  'Parts (go inside a block above)' => [
    'sdc.icon.work-gallery-figure',
    'sdc.icon.work-stat',
    'sdc.icon.intro-photo',
    'sdc.icon.intro-fact',
    'sdc.icon.intro-expertise',
  ],
];

// The names editors see are NOT set here: a cache rebuild re-derives every
// label from its source (found out the hard way, Sep 2026), so a name is
// changed at the source — an SDC's `name:`, a block plugin's admin label,
// a View's block description — and then `drush cr`.

// Off the library: the theme places the chrome itself, and these rows are
// Views' to render. None is on any page.
$hide = [
  'block.system_branding_block',
  'block.system_breadcrumb_block',
  'block.system_messages_block',
  'block.system_powered_by_block',
  'block.search_form_block',
  'block.system_menu_block.footer',
  'block.system_menu_block.main',
  'sdc.navigation.message',
  'sdc.navigation.title',
  'sdc.olivero.teaser',
  'sdc.icon.news-card',
];

$storage = \Drupal::entityTypeManager()->getStorage('component');
$planned = array_merge(...array_values($plan));
foreach ($planned as $id) {
  if (!$storage->load($id)) {
    throw new \RuntimeException("Component $id is not registered — rebuild caches first.");
  }
}

// Every folder not in the plan goes; an item lives in at most one folder.
$folders = \Drupal::entityTypeManager()->getStorage('folder')->loadByProperties(['configEntityTypeId' => Component::ENTITY_TYPE_ID]);
foreach ($folders as $folder) {
  if (!array_key_exists($folder->get('name'), $plan)) {
    print "Folder gone: {$folder->get('name')}\n";
    $folder->delete();
  }
}

$weight = 0;
foreach ($plan as $name => $items) {
  $folder = Folder::loadByNameAndConfigEntityTypeId($name, Component::ENTITY_TYPE_ID)
    ?? Folder::create(['name' => $name, 'status' => TRUE, 'configEntityTypeId' => Component::ENTITY_TYPE_ID]);
  $folder->set('items', array_values($items));
  $folder->set('weight', $weight++);
  $folder->save();
  print "Folder {$name}: " . count($items) . " items\n";
}

foreach ($storage->loadMultiple() as $c) {
  $wanted = in_array($c->id(), $planned, TRUE) ? TRUE : (in_array($c->id(), $hide, TRUE) ? FALSE : $c->status());
  if ($c->status() !== $wanted) {
    $c->setStatus($wanted)->save();
    print ($wanted ? 'On:  ' : 'Off: ') . $c->id() . "\n";
  }
}
print "Library shaped.\n";
