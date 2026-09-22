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

// Folder => component ids, in library order. BY WHAT A BLOCK DOES, not
// by page (user call, Sep 2026: About, Digital and the Work article all
// draw on one set, so page folders made editors guess).
$plan = [
  // The masthead first — a page starts with one — then the layout pieces
  // (Columns, room for more), then the copy (user call, Sep 2026).
  'Mastheads' => [
    'sdc.icon.page-header',
    'sdc.icon.split-masthead',
    'sdc.icon.work-masthead',
    'sdc.icon.subscribe-bar',
  ],
  'Layout' => [
    'sdc.icon.columns',
    'sdc.icon.divider',
    'sdc.icon.spacer',
  ],
  'Text' => [
    'sdc.icon.prose',
    'sdc.icon.pull-quote',
  ],
  // The "Our expertise" list, lifted out of the intro so it can stand on
  // the Expertise landing page (user ask, Sep 2026); the intro's slot takes
  // the same items.
  'Testimonials' => [
    'sdc.icon.testimonials',
    'sdc.icon.testimonial',
  ],
  'Expertise' => [
    'sdc.icon.expertise-links',
    'sdc.icon.intro-expertise',
  ],
  'Results' => [
    'sdc.icon.work-stats',
    'sdc.icon.work-stat',
  ],
  // One Picture everywhere (user call, Sep 2026): the article figure, the
  // gallery figure and the scroller's cards are the one component, laid
  // out by Columns and boxes; the scroller holds Pictures in its slot.
  'Pictures' => [
    'sdc.icon.news-article-figure',
    'sdc.icon.work-scroller',
  ],
  'Filmstrip' => [
    'sdc.icon.filmstrip',
    'sdc.icon.intro-photo',
    'sdc.icon.intro-fact',
  ],
  'Video' => [
    'sdc.icon.news-article-video',
    'sdc.icon.work-video',
  ],
  // The agency's own pieces — the people, the offices, the clients, the
  // form — after the media, before the listings (user call, Sep 2026).
  'ICON' => [
    'sdc.icon.venn',
    'block.icon_contact_form',
    'block.icon_team_profiles',
    'block.icon_office',
    'block.icon_clients_marquee',
  ],
  'Listings' => [
    'block.views_block.news-landing',
    'block.views_block.work-landing',
    'block.icon_work_latest',
    'block.icon_news_latest',
  ],
  'Homepage' => [
    'block.icon_hero',
    'block.icon_featured_work',
    'sdc.icon.intro',
  ],
];

// The names editors see are NOT set here: a cache rebuild re-derives every
// label from its source (found out the hard way, Sep 2026), so a name is
// changed at the source — an SDC's `name:`, a block plugin's admin label,
// a View's block description — and then `drush cr`.

// Off the library: the theme places the chrome itself, and these rows are
// Views' to render. None is on any page.
$hide = [
  // Canvas 1.11's page-variant marker: the theme composes its own shell.
  'marker.page_content',
  // The bare Views block: Work: latest (icon_work_latest) embeds the same
  // display with its heading and feed chosen in the panel (Sep 2026).
  'block.views_block.work-latest',
  // The Run: the runs are inferred on the page since Sep 2026 (user call —
  // a Run could be missed); the older articles' Runs still render.
  'sdc.icon.run',
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
  // The Filmstrip BLOCK, whose panel bundled photos and fact cards into one
  // form: one system now (user call, Sep 2026) — the strip is the Filmstrip
  // component with Photo and Fact card components in its slot, as the
  // homepage intro has always been (scripts/filmstrip-components.php).
  'block.icon_filmstrip',
  // Offices: all — the contact page is being rebuilt from Offices: one in
  // Columns (user call, Sep 2026); the block stays for the page until then.
  'block.icon_office_list',
  // The Gallery row and its figure: Columns and boxes holds Pictures now
  // (scripts/pictures.php moved every placed row); the components stay for
  // the articles still composed from paragraphs.
  'sdc.icon.work-gallery',
  'sdc.icon.work-gallery-figure',
  // The "Next up" rails: the article templates place them under the story
  // they belong to (node--work--full, node--news--full); on a page an
  // editor builds they would show two arbitrary items (user, Sep 2026:
  // "what is this?").
  'block.views_block.news-next_up',
  'block.views_block.work-next_up',
  // The SDCs the Contact form and Offices blocks render — the blocks are
  // the library entries; these read nothing on their own.
  'sdc.icon.contact-form',
  'sdc.icon.office-list',
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
