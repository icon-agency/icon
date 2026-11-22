<?php

/**
 * @file
 * Links the section pages' Featured lists to the expertise landing pages.
 *
 * 9 Nov 2026 (user ask: "see the Featured links on the left of Creative,
 * Communications, Digital, etc, they link to the new SEO pages … update
 * the cross linking to our SEO pages"). Each of Digital, Creative,
 * Communications and Reputation opens its services grid on a Featured box
 * — one prose component, an h5 over a slash list of plain items. This
 * wraps the items that have a landing page in a link and leaves the rest
 * as text; nothing else on the page is touched. An item already linked is
 * left alone, so the script is idempotent, and it refuses a page whose
 * Featured list it cannot find exactly once.
 *
 * Run (on either side): ICON_SEED=1 drush php:script scripts/featured-links.php
 * Dry run:              ICON_DRY=1 drush php:script scripts/featured-links.php
 */

declare(strict_types=1);

use Drupal\user\Entity\User;

$dry = getenv('ICON_DRY') === '1';
if (!$dry && !getenv('ICON_SEED')) {
  throw new \RuntimeException('Refusing to write without ICON_SEED=1 (ICON_DRY=1 previews).');
}
\Drupal::service('account_switcher')->switchTo(User::load(1));

// alias => [item text => landing page path]
$links = [
  '/digital' => [
    'Drupal' => '/drupal-developers',
    'Hosting' => '/managed-application-hosting',
    'GovCMS' => '/govcms-digital-government',
    'SalesForce' => '/transforming-government-services-salesforce-drupal-and-govcms',
  ],
  '/creative' => [
    'Brand' => '/creative/branding-design',
    'Campaigns' => '/creative/strategy-planning-and-media-buying',
    'Behaviour change' => '/behaviour-change',
  ],
  '/communications' => [
    'Social' => '/digital/digital-marketing',
  ],
  '/reputation' => [
    'Media training' => '/pr-communications/media-training',
  ],
];

$storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
$aliases = \Drupal::service('path_alias.repository');
foreach ($links as $alias => $items) {
  $path = $aliases->lookupByAlias($alias, 'en');
  $page = $path && preg_match('#^/page/(\d+)$#', $path['path'], $m) ? $storage->load($m[1]) : NULL;
  if (!$page) {
    print "SKIP $alias: no page\n";
    continue;
  }
  // Every target must be a page here before anything is linked.
  foreach ($items as $text => $target) {
    if (!$aliases->lookupByAlias($target, 'en')) {
      throw new \RuntimeException("$alias: the target $target is not a page here.");
    }
  }
  $tree = $page->get('components')->getValue();
  $hits = [];
  foreach ($tree as $i => $item) {
    if ($item['component_id'] !== 'sdc.icon.prose') {
      continue;
    }
    $decoded = is_string($item['inputs']) ? json_decode($item['inputs'], TRUE) : $item['inputs'];
    if (str_contains((string) ($decoded['text']['value'] ?? ''), 'Featured</h5><ul class="list-slash">')) {
      $hits[] = $i;
    }
  }
  if (count($hits) !== 1) {
    throw new \RuntimeException("$alias: found " . count($hits) . " Featured lists, expected one.");
  }
  $i = $hits[0];
  $inputs = is_string($tree[$i]['inputs']) ? json_decode($tree[$i]['inputs'], TRUE) : $tree[$i]['inputs'];
  $html = $inputs['text']['value'];
  $changed = 0;
  foreach ($items as $text => $target) {
    $quoted = preg_quote($text, '/');
    if (preg_match('/<a [^>]*>' . $quoted . '<\/a>/', $html)) {
      print "  $alias: $text already linked\n";
      continue;
    }
    // <p class="… is-small …">TEXT</p> or …<strong>TEXT</strong></p>
    $pattern = '/(<p class="[^"]*is-small[^"]*">(?:<strong>)?)' . $quoted . '((?:<\/strong>)?<\/p>)/';
    $count = 0;
    $html = preg_replace($pattern, '$1<a href="' . $target . '">' . $text . '</a>$2', $html, 1, $count);
    if ($count !== 1) {
      throw new \RuntimeException("$alias: the item \"$text\" was not found in the Featured list.");
    }
    $changed++;
  }
  if (!$changed) {
    print "$alias: nothing to do\n";
    continue;
  }
  $inputs['text']['value'] = $html;
  print ($dry ? "DRY " : '') . "$alias (page {$page->id()}): $changed item(s) linked\n   " . strip_tags($html, '<a>') . "\n";
  if ($dry) {
    continue;
  }
  $tree[$i]['inputs'] = json_encode($inputs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $page->set('components', $tree);
  $page->setNewRevision(TRUE);
  $page->setRevisionLogMessage('Featured list linked to the expertise landing pages.');
  $page->save();
}
