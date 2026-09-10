<?php

/**
 * @file
 * Rebuilds the About page (canvas_page 2) from the live site's own words.
 *
 * Run: ddev drush php:script scripts/about-page
 * Content source: https://iconagency.com.au/about (10 Sep 2026).
 */

$uuid = \Drupal::service('uuid');
$storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
$page = $storage->load(2);
if (!$page) {
  throw new \RuntimeException('canvas_page 2 not found.');
}

$version = static function (string $id): string {
  $c = \Drupal::entityTypeManager()->getStorage('component')->load($id);
  if (!$c) {
    throw new \RuntimeException("Component $id is not registered.");
  }
  return (string) $c->get('active_version');
};

$tree = [];
$add = static function (string $component, array $inputs, ?string $parent = NULL, ?string $slot = NULL) use (&$tree, $uuid, $version): string {
  $id = $uuid->generate();
  $tree[] = [
    'parent_uuid' => $parent,
    'slot' => $slot,
    'uuid' => $id,
    'component_id' => $component,
    'component_version' => $version($component),
    'inputs' => json_encode($inputs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    'label' => NULL,
  ];
  return $id;
};
$prose = static fn(string $html): array => ['text' => ['value' => $html, 'format' => 'canvas_html_block']];

// ---- The masthead -----------------------------------------------------------
// handover FALSE: the dark opening is a route-level decision the listings make
// in icon_preprocess_html(); a Canvas page opens light, so the hook would have
// nothing to hand over from.
$add('sdc.icon.page-header', [
  'accent' => 'About',
  'caps' => 'Makers, shakers, interdisciplinary groundbreakers',
  'heading_level' => 'h1',
  'handover' => FALSE,
]);

// ---- Who we are -------------------------------------------------------------
$add('sdc.icon.prose', $prose(
  '<p>We make it meaningful. Make it original. Make it loved.</p>' .
  '<p>ICON is an integrated team. PR, digital, creative, UX and service design work together around one strategy and one delivery, which is how a national communications campaign, a behaviour change programme, a website rebuild and a digital service improvement can all be the same piece of work.</p>'
));

$add('sdc.icon.pull-quote', [
  'text' => 'We think big. We collaborate. We bring the right message, to the right audience, at the right time.',
  'variant' => 'upright',
]);

// ---- The facts --------------------------------------------------------------
$band = $add('sdc.icon.work-stats', []);
foreach ([
  ['2002', '', 'Founded in Melbourne, and founder-owned ever since'],
  ['4', 'offices', 'Naarm, Gadigal, Meanjin and Suva'],
  ['5', 'disciplines', 'PR, digital, creative, UX and service design'],
  ['90+', 'agencies', 'Our PROI network, across 65 countries'],
] as [$value, $unit, $label]) {
  $add('sdc.icon.work-stat', ['value' => $value, 'unit' => $unit, 'label' => $label], $band, 'results');
}

// ---- Our journey ------------------------------------------------------------
$add('sdc.icon.prose', $prose(
  '<h2>Our journey</h2>' .
  '<p>ICON is founder-owned and founder-led, by Joanne Painter and Christopher Dodds. Chris founded the agency in 2002. Joanne has led its national expansion since 2017, opening Canberra, Sydney and Brisbane.</p>' .
  '<p>We are female-led and independent. There are no external investors and no parent company, so the people who own the work are the people who do it.</p>'
));

// ---- Our makers -------------------------------------------------------------
$add('sdc.icon.prose', $prose(
  '<h2>Our makers</h2>' .
  '<p>We are a diverse group of creators, thinkers, writers and technologists. Our craft is our passion, our value is our impact.</p>'
));

// ---- Leadership -------------------------------------------------------------
// The Team profiles block: the people are Team member content (Content →
// Team members), each opening a panel at /about/<name>. It replaced a
// Content block listing them (10 Sep 2026).
$add('block.icon_team_profiles', ['label' => 'Team profiles', 'label_display' => '0', 'heading' => 'Leadership team']);

// ---- Partners ---------------------------------------------------------------
$add('sdc.icon.prose', $prose(
  '<h2>Our partners</h2>' .
  '<p>ICON is a member of the Public Relations Organisation International, which puts more than ninety agencies in sixty-five countries within reach of our clients.</p>' .
  '<p>We work alongside 2M Language Services, Polaron Language Services, YoungBloods, Man Cave, Max Solutions, Polity Research, AP2, Island Spirit, GARUWA, CPRA, Atomic 212, Xenai Digital and Shine Solutions.</p>'
));

// ---- The client marquee -----------------------------------------------------
$add('block.icon_clients_marquee', ['label' => 'Clients marquee', 'label_display' => '0', 'heading' => 'Who we work with']);

// ---- Save -------------------------------------------------------------------
$page->set('title', 'About');
$page->set('description', 'ICON is an independent, founder-owned Australian agency. PR, digital, creative, UX and service design working as one team, across Naarm, Gadigal, Meanjin and Suva.');
$page->set('components', $tree);
$page->set('path', ['alias' => '/about']);
$page->setPublished(TRUE);
$page->setNewRevision(TRUE);
$page->setRevisionLogMessage('About page rebuilt from iconagency.com.au/about.');
$page->save();

// The editor loads the AUTO-SAVE draft in preference to the saved entity, so
// a draft left from before this script ran would shadow everything it just
// wrote — open the page in Canvas and the content appears to vanish. Clear it
// so the editor starts from what is saved.
\Drupal::service(\Drupal\canvas\AutoSave\AutoSaveManager::class)->delete($page);

print "Saved canvas_page {$page->id()} with " . count($tree) . " components at " . $page->toUrl()->toString() . ", auto-save draft cleared\n";
