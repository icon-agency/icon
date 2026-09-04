<?php

/**
 * @file
 * The homepage as a Canvas Page — templates/homeC.html composed from five
 * top-level components: the Homepage hero block (icon_site — its setting is
 * the order of the Hero slide content it renders into the hero SDC), the intro SDC (with its
 * filmstrip photos, fact cards and expertise links as child components in
 * its two slots), the Featured work block (icon_site — five ordered picks
 * rendered into the featured-work SDC's row slots), the Clients marquee
 * marquee block (Logo media → the clients SDC), the news View's latest block. Creates (or
 * rebuilds) the Page at /home and makes it the front page.
 * Run from drupal/:  ddev drush php:script scripts/home-page.php
 */

use Drupal\canvas\Entity\Page;
use Drupal\Component\Uuid\Php as Uuid;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

$uuid = new Uuid();
$components = \Drupal::entityTypeManager()->getStorage('component');

/** One image media per file in sample-content/home, reused across runs. */
$media = function (string $file, string $alt): Media {
  $existing = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties(['bundle' => 'image', 'name' => $file]);
  if ($existing) {
    return reset($existing);
  }
  $fs = \Drupal::service('file_system');
  $dest = 'public://home';
  $fs->prepareDirectory($dest, $fs::CREATE_DIRECTORY | $fs::MODIFY_PERMISSIONS);
  $uri = $fs->copy(DRUPAL_ROOT . "/../sample-content/home/$file", "$dest/$file", $fs::EXISTS_REPLACE);
  $f = File::create(['uri' => $uri, 'status' => 1]);
  $f->save();
  $m = Media::create(['bundle' => 'image', 'name' => $file, 'field_media_image' => ['target_id' => $f->id(), 'alt' => $alt]]);
  $m->save();
  return $m;
};

/** One Icon media per SVG in sample-content/icons — the fact cards' icon set, reused across runs. */
$icon = function (string $file, string $name): Media {
  $existing = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties(['bundle' => 'icon', 'name' => $name]);
  if ($existing) {
    return reset($existing);
  }
  $fs = \Drupal::service('file_system');
  $dest = 'public://icons';
  $fs->prepareDirectory($dest, $fs::CREATE_DIRECTORY | $fs::MODIFY_PERMISSIONS);
  $uri = $fs->copy(DRUPAL_ROOT . "/../sample-content/icons/$file", "$dest/$file", $fs::EXISTS_REPLACE);
  $f = File::create(['uri' => $uri, 'status' => 1]);
  $f->save();
  static $weight = 0;
  $m = Media::create(['bundle' => 'icon', 'name' => $name, 'field_media_file' => ['target_id' => $f->id()], 'field_logo_weight' => $weight++]);
  $m->save();
  return $m;
};

/** One tree item; SDC props in Canvas's collapsed syntax, block settings as given. A child names its parent + slot; a label names the instance in the layers panel. */
$item = function (string $component_id, array $inputs, bool $sdc = TRUE, ?string $parent = NULL, ?string $slot = NULL, ?string $label = NULL) use ($components, $uuid): array {
  $component = $components->load($component_id);
  if (!$component) {
    throw new \RuntimeException("Component $component_id is not available — check /admin/appearance/component/status");
  }
  $source = $component->getComponentSource();
  $stored = [];
  if ($sdc) {
    foreach ($inputs as $name => $value) {
      $stored[$name] = $source->getDefaultStaticPropSource($name, FALSE)->withValue($value)->getValue();
    }
  }
  else {
    $stored = $inputs + $source->getDefaultExplicitInput();
  }
  return [
    'uuid' => $uuid->generate(),
    'component_id' => $component->id(),
    'component_version' => $component->getActiveVersion(),
    'parent_uuid' => $parent,
    'slot' => $slot,
    'label' => $label,
    // `{}` not `[]` for a component with nothing to store: Canvas rewrites `[]`
    // on load, which reads as an unsaved change in the editor.
    'inputs' => json_encode($stored ?: new \stdClass()),
  ];
};
$block = fn(string $id, string $label) => $item("block.views_block.$id", ['label' => $label, 'label_display' => '0', 'views_label' => '', 'items_per_page' => NULL], FALSE);

$intro = $item('sdc.icon.intro', [
  'accent' => 'A strategic',
  'caps' => 'communications agency for complex change',
  'statement' => 'ICON is an independent Australian agency with over 24 years of experience. We’ve built deep expertise across government, technology, health, education and social impact, helping organisations and change-makers create work that matters.',
  'about_url' => ['uri' => '/about', 'options' => []],
  'about_label' => 'About us',
  'expertise_label' => 'Our expertise',
]);
$in = fn(string $slot) => fn(string $id, array $inputs) => $item($id, $inputs, TRUE, $intro['uuid'], $slot);
$strip = $in('strip');
$link = $in('expertise');
$photo = fn(int $n, string $alt) => $strip('sdc.icon.intro-photo', ['image' => ['target_id' => $media("team-$n.jpg", $alt)->id()]]);
$icon_names = ['trophy' => 'Trophy', 'world' => 'World', 'peace' => 'Peace sign', 'flame' => 'Flame'];
$fact = fn(string $icon_key, string $title, string $label) => $strip('sdc.icon.intro-fact', ['icon' => ['target_id' => (int) $icon("$icon_key.svg", $icon_names[$icon_key])->id()], 'title' => $title, 'label' => $label]);

// templates/homeC.html's strip, in order: photo, photo, fact, photo, photo, fact …
$strip_items = [
  $photo(1, 'ICON team'), $photo(2, 'ICON team'),
  $fact('trophy', '14 Agency of the Year awards', 'Since 2021'),
  $photo(3, 'ICON team on Sydney Harbour'), $photo(4, 'ICON studio'),
  $fact('world', '83 global partners', 'In 60 countries'),
  $photo(5, 'ICON team'), $photo(6, 'ICON team'),
  $fact('peace', '24+ years of experience', 'An independent Australian agency'),
  $photo(7, 'ICON team'), $photo(8, 'ICON team'),
  $fact('flame', 'Australian Government Drupal Services Panel', 'We’re GovCMS specialists'),
];
$expertise_items = [];
foreach ([['Creative', '/services#creative'], ['Communications', '/services#communications'], ['Digital', '/services/digital'], ['Reputation', '/services#reputation'], ['Production', '/services#production']] as [$label, $url]) {
  $expertise_items[] = $link('sdc.icon.intro-expertise', ['label' => $label, 'url' => ['uri' => $url, 'options' => []]]);
}

// The hero's slides are Hero slide content (one per work sample: film or
// image by media type, the client name as the title, a link), reused across
// runs by title; the block's setting is their order.
$slide_ids = [];
foreach ([
  ['nwmphn-raise-it.mp4', 'PHN North Western Melbourne', '/work/raise-it'],
  ['moad-hero.png', 'Museum of Australian Democracy (MoAD)', '/work/democracy-cards'],
  ['icq-banner.jpg', 'Cancer Institute NSW', '/work/icanquit'],
  ['nike-banner.mp4', 'Nike Melbourne Marathon Festival', '/work/melbourne-marathon-running-wings'],
] as [$file, $client, $url]) {
  $m = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties(['name' => $file]);
  if (!$m) {
    continue;
  }
  $existing = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'hero_slide', 'title' => $client]);
  $slide = $existing ? reset($existing) : \Drupal\node\Entity\Node::create(['type' => 'hero_slide', 'title' => $client]);
  $slide->set('field_slide_media', ['target_id' => (int) reset($m)->id()]);
  $slide->set('field_slide_link', ['uri' => 'internal:' . $url]);
  $slide->setPublished()->save();
  $slide_ids[] = (int) $slide->id();
}
$hero = $item('block.icon_hero', ['label' => 'Homepage hero', 'label_display' => '0', 'order' => $slide_ids], FALSE);

// Featured work: the five picks by alias, in grid order (a split pair, the
// wide feature, a tall-left pair) — the block's one setting.
$featured = $item('block.icon_featured_work', [
  'label' => 'Featured work',
  'label_display' => '0',
  'projects' => array_values(array_filter(array_map(
    fn(string $alias) => ($p = \Drupal::service('path_alias.repository')->lookupByAlias($alias, 'en')['path'] ?? NULL) ? (int) substr($p, 6) : NULL,
    ['/work/raise-it', '/work/permanent-protection-visa', '/work/fit-for-every-run', '/work/icanquit', '/work/democracy-cards'],
  ))),
], FALSE);

$tree = [
  // The hero and its four slides (templates/homeC.html's reel): each slide
  // is a Hero slide component in the hero's slot — media from the work
  // samples (a film or an image, by media type), the client name (also the
  // instance label, so the layers panel reads it) and where it links.
  $hero,
  $intro,
  ...$strip_items,
  ...$expertise_items,
  $featured,
  // The marquee block has no settings — its panel links to the Client logos list.
  $item('block.icon_clients_marquee', ['label' => 'Clients marquee', 'label_display' => '0'], FALSE),
  $block('news-latest', 'Latest news'),
];

$existing = \Drupal::entityTypeManager()->getStorage('canvas_page')->loadByProperties(['title' => 'Home']);
$page = $existing ? reset($existing) : Page::create(['title' => 'Home']);
$page->set('components', $tree);
$page->set('path', ['alias' => '/home']);
$page->set('status', 1);
$violations = $page->validate();
if (count($violations)) {
  foreach ($violations as $v) {
    echo "  ! {$v->getPropertyPath()}: {$v->getMessage()}\n";
  }
  throw new \RuntimeException('Validation failed for the homepage');
}
$page->save();
\Drupal::configFactory()->getEditable('system.site')->set('page.front', '/page/' . $page->id())->save();
echo ($existing ? 'updated ' : 'created ') . "canvas page {$page->id()} at /home — set as the front page\n";
