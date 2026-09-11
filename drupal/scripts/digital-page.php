<?php

/**
 * @file
 * Builds the Digital expertise page (/services/digital) as a Canvas page
 * from the live site's own words (https://iconagency.com.au/services/digital,
 * 11 Sep 2026): a dark Masthead, the intro, the eight service blocks two to
 * a row in Columns, and the work rail. Found by alias, created when there
 * is none; run it again to reset the page to this. Then edit in Canvas.
 *
 * Run: ddev exec "ICON_SEED=1 drush php:script scripts/digital-page.php"
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

$page = NULL;
$path = \Drupal::service('path_alias.repository')->lookupByAlias('/services/digital', 'en');
if ($path && preg_match('#^/page/(\d+)$#', $path['path'], $m)) {
  $page = $storage->load($m[1]);
}
if (!$page) {
  $page = $storage->create(['title' => 'Digital', 'owner' => 1]);
  print "No Digital page yet: creating one.\n";
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
$prose = static fn(string $html): array => ['text' => ['value' => $html, 'format' => 'canvas_html_block']];

// ---- Masthead: opens dark, as the live page does ----------------------------
$add('sdc.icon.page-header', [
  'accent' => 'Digital',
  'caps' => 'Better user experiences. Making it human.',
  'heading_level' => 'h1',
  'opening' => 'dark',
]);

// ---- Intro ------------------------------------------------------------------
$add('sdc.icon.prose', $prose(
  '<p>ICON’s digital agency service team works at the intersection of technology, empathy and accessibility. With a deep belief in human-centred design and a passion for excellence, we craft digital experiences that are intuitive and inclusive.</p>' .
  '<p>If you’re ready to transform your government, B2B or B2C website or digital service, we can help you get there.</p>'
));
$add('sdc.icon.prose', $prose(
  '<h2>Who we are and where you’ll find us</h2>' .
  '<p>We are user researchers, UX specialists, designers, full-stack developers, content strategists and writers in Melbourne, Sydney, Canberra and Brisbane.</p>'
));

// ---- The services, two to a row -------------------------------------------
$services = [
  ['Research, data & user needs analysis', 'Understand your users’ motivations, needs and emotional states.', ['Stakeholder workshops', 'Data collection and analysis', 'Card sorting', 'Tree testing', 'Information architecture', 'Content mapping', 'Functional analysis']],
  ['CX, UX and UI design', 'Bring your digital brand to life through guides, systems and libraries.', ['Service design', 'Customer experience design', 'User experience design', 'User interface design', 'Conversational interface design', 'User journey mapping', 'User testing', 'Design systems', 'Component libraries', 'Digital brand and style guides']],
  ['Website development', 'Leverage open-source frameworks and solutions to create fit-for-purpose, scalable and secure websites.', ['Drupal development', 'GovCMS development', 'Salesforce CRM and marketing automation', 'SaaS and PaaS solutions', 'WordPress', 'Vue.js and React', 'Content migration', 'API customisation and integration']],
  ['Website accessibility & compliance', 'Ensure your service adheres to modern accessibility standards, making it usable by all users regardless of their environment or ability.', ['Accessibility audits and updates', 'WCAG 2.2 Accessibility (A to AAA standard)', 'Accessibility testing (automated and community testing)', 'Meeting NDIS accessibility standards', 'Meeting government accessibility standards', 'Technical development']],
  ['Hosting, security & support', 'Our support team is ready to monitor, patch and enhance your website via a competitive service agreement.', ['Amazon Web Services (AWS) and Amazee.io', 'GovCMS SaaS and PaaS', 'Drupal hosting', 'Web protection — Cloudflare, CDN, WAF and DDoS', 'Monitoring and security patching', 'Development environments and backups']],
  ['Digital marketing', 'We deliver engaging experiences that convert visitors into lifetime customers.', ['Market research and strategy development', 'Creative development, production and copywriting', 'Digital marketing platform set-up and management', 'Digital media planning, buying and automation', 'Microsite and sales funnel design and development', 'Data analysis, reporting and SEO']],
  ['Digital content strategy', 'We have a dedicated team of content strategists, writers, editors, storytellers and graphic designers at your service.', ['Writing and editing for the web', 'Content strategy development', 'Content governance and publishing workflows', 'Tone of voice and writing guides', 'Graphic design and infographics', 'Storytelling', 'Video, photography and podcast production']],
  ['Ecommerce', 'Our best-in-class UX and development team craft exceptional user experiences for modern ecommerce businesses.', ['Magento, WooCommerce and Shopify development', 'Best-in-class UX design', 'A/B split testing', 'Sales funnel optimisation', 'Hosting and support packages', 'Integrated brand, PR and marketing services']],
];
$service = static fn(array $s): string => '<h3>' . htmlspecialchars($s[0], ENT_QUOTES) . '</h3><p>' . htmlspecialchars($s[1], ENT_QUOTES) . '</p><ul class="list-slash">' . implode('', array_map(fn($i) => '<li>' . htmlspecialchars($i, ENT_QUOTES) . '</li>', $s[2])) . '</ul>';
foreach (array_chunk($services, 2) as $pair) {
  $row = $add('sdc.icon.columns', ['columns' => 2, 'gap' => 'normal']);
  foreach ($pair as $i => $s) {
    $add('sdc.icon.prose', $prose($service($s)), $row, 'column_' . ($i + 1));
  }
}

// ---- Latest work --------------------------------------------------------
$add('block.views_block.work-latest', ['label' => 'Latest work', 'label_display' => '0', 'views_label' => '', 'items_per_page' => NULL]);

// ---- Save ---------------------------------------------------------------
$page->set('title', 'Digital');
$page->set('description', 'ICON is a full-service digital agency specialising in experience design and website development. We help you develop effective digital strategies for your business.');
$page->set('components', $tree);
$page->set('path', ['alias' => '/services/digital']);
$page->setPublished(TRUE);
if ($page->hasField('moderation_state')) {
  $page->set('moderation_state', 'published');
}
$page->setNewRevision(TRUE);
$page->setRevisionLogMessage('Digital page built from iconagency.com.au/services/digital.');
$page->save();
\Drupal::service(\Drupal\canvas\AutoSave\AutoSaveManager::class)->delete($page);
print "Saved canvas_page {$page->id()} with " . count($tree) . " components at /services/digital\n";
