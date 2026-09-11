<?php

/**
 * @file
 * Builds the News and Work landings as Canvas pages (Sep 2026).
 *
 * Each is its own Masthead — accent, headline and "Opens on" editable like
 * any page's — with the Subscribe bar in its slot, over the listing block:
 * the View's `landing` display (chips, stories or tiles, pager, lazy
 * loading). Found by alias (/news, /work), created when there is none, so
 * the same script builds them on a host that never had them.
 *
 * Run: ddev exec "ICON_SEED=1 drush php:script scripts/landing-pages.php"
 */

require_once __DIR__ . '/_guard.php';

$uuid = \Drupal::service('uuid');
$storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
$version = static function (string $id): string {
  $c = \Drupal::entityTypeManager()->getStorage('component')->load($id);
  if (!$c) {
    throw new \RuntimeException("Component $id is not registered.");
  }
  return (string) $c->get('active_version');
};

$landings = [
  [
    'alias' => '/news',
    'title' => 'News',
    'description' => 'News, insights and updates from ICON — an independent Australian communications agency.',
    'accent' => 'News',
    'caps' => 'The latest updates and insights',
    // dark, handed over at the first story's foot (the listing's own carrier)
    'opening' => 'dark',
    'block' => 'block.views_block.news-landing',
    'label' => 'News listing',
  ],
  [
    'alias' => '/work',
    'title' => 'Work',
    'description' => 'Selected work and case studies — campaigns, digital products and behaviour change from ICON.',
    'accent' => 'Work',
    'caps' => 'Selected work and case studies',
    'opening' => 'dark',
    'block' => 'block.views_block.work-landing',
    'label' => 'Work listing',
  ],
];

foreach ($landings as $l) {
  $page = NULL;
  $path = \Drupal::service('path_alias.repository')->lookupByAlias($l['alias'], 'en');
  if ($path && preg_match('#^/page/(\d+)$#', $path['path'], $m)) {
    $page = $storage->load($m[1]);
  }
  if (!$page) {
    $page = $storage->create(['title' => $l['title'], 'owner' => 1]);
    print "No {$l['title']} page yet: creating one.\n";
  }
  $tree = [];
  $add = static function (string $component, array $inputs, ?string $parent = NULL, ?string $slot = NULL) use (&$tree, $uuid, $version): string {
    $id = $uuid->generate();
    $tree[] = [
      'parent_uuid' => $parent,
      'slot' => $slot,
      'uuid' => $id,
      'component_id' => $component,
      'component_version' => $version($component),
      'inputs' => json_encode($inputs ?: new \stdClass(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      'label' => NULL,
    ];
    return $id;
  };
  $masthead = $add('sdc.icon.page-header', [
    'accent' => $l['accent'],
    'caps' => $l['caps'],
    'heading_level' => 'h1',
    'opening' => $l['opening'],
  ]);
  $add('sdc.icon.subscribe-bar', [], $masthead, 'extras');
  $add($l['block'], [
    'label' => $l['label'],
    'label_display' => '0',
    'views_label' => '',
    'items_per_page' => NULL,
  ]);
  $page->set('title', $l['title']);
  $page->set('description', $l['description']);
  $page->set('components', $tree);
  $page->set('path', ['alias' => $l['alias']]);
  $page->setPublished(TRUE);
  if ($page->hasField('moderation_state')) {
    $page->set('moderation_state', 'published');
  }
  $page->setNewRevision(TRUE);
  $page->setRevisionLogMessage($l['title'] . ' landing as a Canvas page.');
  $page->save();
  // The editor loads the auto-save draft in preference to the saved entity.
  \Drupal::service(\Drupal\canvas\AutoSave\AutoSaveManager::class)->delete($page);
  print "Saved canvas_page {$page->id()} ({$l['title']}) with " . count($tree) . " components at {$l['alias']}\n";
}
