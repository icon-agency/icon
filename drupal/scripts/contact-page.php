<?php

/**
 * @file
 * Builds the Contact page (templates/contact.html) as a Canvas page.
 *
 * The page is found by its alias, /contact, and created when there is
 * none — so the same script builds it on a host that never had one. The
 * masthead opens dark and holds it; a two-column Columns holds the Contact
 * form block and the Offices block; the footer's contact dress is the
 * theme's, by the alias (icon_preprocess_page()).
 *
 * Run: ICON_SEED=1 drush php:script scripts/contact-page.php
 */

declare(strict_types=1);

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\user\Entity\User;

if (!getenv('ICON_SEED')) {
  throw new \RuntimeException('Refusing to run without ICON_SEED=1.');
}

\Drupal::service('account_switcher')->switchTo(User::load(1));
$uuid = \Drupal::service('uuid');
$storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
$page = NULL;
$path = \Drupal::service('path_alias.repository')->lookupByAlias('/contact', 'en');
if ($path && preg_match('#^/page/(\d+)$#', $path['path'], $m)) {
  $page = $storage->load($m[1]);
}
if (!$page) {
  $page = $storage->create(['title' => 'Contact', 'owner' => 1]);
  print "No Contact page yet: creating one.\n";
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

// ---- The masthead: dark, all the way down -----------------------------------
$add('sdc.icon.page-header', [
  'accent' => 'Contact',
  'caps' => "This is how\nwe finally meet",
  'heading_level' => 'h1',
  'opening' => 'dark-hold',
]);

// ---- The form beside the offices -------------------------------------------
$columns = $add('sdc.icon.columns', ['columns' => 2, 'gap' => 'normal']);
$add('block.icon_contact_form', [
  'label' => 'Contact form',
  'label_display' => '0',
  'lead' => 'Simply fill in the form below',
  'intro' => 'Whether it’s a brand refresh, public relations push, new website or end-to-end behaviour change campaign — we’re interested and ready to talk solutions.',
], $columns, 'column_1');
$add('block.icon_office_list', ['label' => 'Offices', 'label_display' => '0'], $columns, 'column_2');

// ---- Save -------------------------------------------------------------------
$page->set('title', 'Contact');
$page->set('description', 'Get in touch with ICON Agency — offices in Melbourne, Sydney, Brisbane and Suva. Tell us what matters to you.');
$page->set('components', $tree);
$page->set('path', ['alias' => '/contact']);
$page->setPublished(TRUE);
if ($page->hasField('moderation_state')) {
  $page->set('moderation_state', 'published');
}
$page->setNewRevision(TRUE);
$page->setRevisionLogMessage('Contact page built from templates/contact.html.');
$violations = $page->validate();
if (count($violations)) {
  foreach ($violations as $v) {
    print '  ' . $v->getPropertyPath() . ': ' . strip_tags((string) $v->getMessage()) . "\n";
  }
  throw new \RuntimeException('Contact page not saved.');
}
$page->save();
\Drupal::service(AutoSaveManager::class)->delete($page);
\Drupal::service('account_switcher')->switchBack();
print "Contact page {$page->id()} saved at /contact.\n";
