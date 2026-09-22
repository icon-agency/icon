<?php

/**
 * @file
 * Builds the Creative page (/creative) as a Canvas page from the live site's
 * own words (https://iconagency.com.au/icon-creative, 18 Sep 2026), on the
 * pattern Matt set on /digital in the editor: a split Masthead (eyebrow,
 * caps heading, a Line above and below, a Picture beside it), a Filmstrip,
 * a Pull quote, a two-column Picture and copy, the services three to a row
 * (each a Content block: an h4, the line and a plain list in the Small
 * style), a closing Pull quote and the Work: latest rail on the category.
 *
 * The film is the old page's hero loop, which the script fetches itself —
 * with its poster — into the media library the first time it runs, so the
 * same script builds the page on a host without a file going through git
 * (user ask: "download and use the videos"; the old page carries one). The
 * Filmstrip's photos are filler from the library's team folder and its
 * fact cards are the About page's own figures (user ask: "add filler
 * images stats blocks for the film strip") — for the editor to replace.
 *
 * Found by its alias, created when there is none; run it again to reset the
 * page to this. Also points the Main navigation's Creative link at it.
 *
 * Run: ddev exec "ICON_SEED=1 drush php:script scripts/creative-page.php"
 */

require_once __DIR__ . '/_guard.php';

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\user\Entity\User;

\Drupal::service('account_switcher')->switchTo(User::load(1));
$uuid = \Drupal::service('uuid');
$etm = \Drupal::entityTypeManager();
$storage = $etm->getStorage('canvas_page');
$version = static function (string $id) use ($etm): string {
  $c = $etm->getStorage('component')->load($id);
  if (!$c) {
    throw new \RuntimeException("Component $id is not registered.");
  }
  return (string) $c->get('active_version');
};

// ---- The film: the old page's hero loop, fetched once -----------------------
$fetch = static function (string $url, string $destination): File {
  $fs = \Drupal::service('file_system');
  $dir = dirname($destination);
  $fs->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  $data = (string) \Drupal::httpClient()->get($url, ['timeout' => 180])->getBody();
  $uri = $fs->saveData($data, $destination, FileExists::Replace);
  $file = File::create(['uri' => $uri, 'uid' => 1, 'status' => 1]);
  $file->save();
  return $file;
};
$film_name = 'ICON Creative loop';
$found = $etm->getStorage('media')->loadByProperties(['bundle' => 'video', 'name' => $film_name]);
$film = $found ? reset($found) : NULL;
if (!$film) {
  $base = 'https://drupal.iconagency.com.au/files/agency/';
  print "Fetching the film (about 25 MB)…\n";
  $video = $fetch($base . '2024-07/website_V14_SHORT%20LOOP_1.mp4', 'public://' . date('Y-m') . '/icon-creative-loop.mp4');
  $poster = $fetch($base . 'styles/xlarge/public/2024-07/website_V14_SHORT%20LOOP_1.mp4.png', 'public://posters/icon-creative-loop.png');
  $film = Media::create([
    'bundle' => 'video',
    'name' => $film_name,
    'uid' => 1,
    'status' => 1,
    'field_media_video_file' => ['target_id' => $video->id()],
    'field_media_poster' => ['target_id' => $poster->id(), 'alt' => 'ICON Creative showreel'],
    'field_media_category' => 'content',
  ]);
  $film->save();
  print "Film saved as media {$film->id()}.\n";
}

// ---- Filler from the library: team photos, a work picture, the icons -------
$images = static function (string $folder, int $count) use ($etm): array {
  $ids = \Drupal::entityQuery('media')->accessCheck(FALSE)->condition('bundle', 'image')
    ->condition('field_media_category', $folder)->sort('mid')->range(0, $count)->execute();
  return array_values(array_map('intval', $ids));
};
$photos = $images('intro', 6) ?: $images('work', 6);
$feature = $images('work', 1) ?: $photos;
$icon = static function (string $name) use ($etm): ?int {
  $found = $etm->getStorage('media')->loadByProperties(['bundle' => 'icon', 'name' => $name]);
  return $found ? (int) reset($found)->id() : NULL;
};

$page = NULL;
$path = \Drupal::service('path_alias.repository')->lookupByAlias('/creative', 'en');
if ($path && preg_match('#^/page/(\d+)$#', $path['path'], $m)) {
  $page = $storage->load($m[1]);
}
if (!$page) {
  $page = $storage->create(['title' => 'Creative', 'owner' => 1]);
  print "No Creative page yet: creating one.\n";
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
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES);

// ---- Masthead: the split one, as /digital wears it --------------------------
$masthead = $add('sdc.icon.split-masthead', [
  'eyebrow' => 'Creative',
  'title' => 'Unmissable ideas. Undeniable impact.',
  'style' => 'caps',
  'heading_level' => 'h1',
  'opening' => 'dark',
]);
$add('sdc.icon.divider', [], $masthead, 'above');
$add('sdc.icon.divider', [], $masthead, 'below');
$add('sdc.icon.news-article-figure', ['picture' => ['target_id' => (int) $film->id()], 'shape' => 'square', 'parallax' => TRUE], $masthead, 'aside');

// ---- Filmstrip: filler photos and the About page's own figures --------------
$strip = $add('sdc.icon.filmstrip', ['label' => 'ICON Creative']);
$facts = [
  ['Lightbulb', '2002', 'Founded in Melbourne'],
  ['Palette', '5 disciplines', 'PR, digital, creative, UX and service design'],
  ['World', '90+ agencies', 'Our PROI network, across 65 countries'],
];
foreach ($photos as $i => $mid) {
  $add('sdc.icon.intro-photo', ['media' => ['target_id' => $mid]], $strip, 'cards');
  // A fact card after every second photo.
  if ($i % 2 === 1 && ($fact = array_shift($facts))) {
    $add('sdc.icon.intro-fact', array_filter(['icon' => ($id = $icon($fact[0])) ? ['target_id' => $id] : NULL, 'title' => $fact[1], 'label' => $fact[2]]), $strip, 'cards');
  }
}

// ---- The statement ----------------------------------------------------------
$add('sdc.icon.pull-quote', [
  'text' => 'There’s a lot of noise on a lot of channels, and very little time to make your point. We put the right idea in the right place at the right time.',
  'variant' => 'upright',
]);

// ---- A picture beside the copy ----------------------------------------------
$pair = $add('sdc.icon.columns', ['columns' => 2, 'gap' => 'wide', 'box' => 'none']);
if ($feature) {
  $add('sdc.icon.news-article-figure', ['picture' => ['target_id' => $feature[0]], 'shape' => 'square', 'parallax' => TRUE], $pair, 'column_1');
}
$add('sdc.icon.prose', $prose('<h3>Independent thinking&nbsp;<br>that makes you matter.</h3>'), $pair, 'column_2');
$add('sdc.icon.prose', $prose('<p class="is-small">From behaviour change campaigns inspired by deep local insight to digital transformation ideas focussed on the needs of future generations, our teams are putting brands, products and culture-defining messages in front of the right people and in the right places at the right time.</p>'), $pair, 'column_2');
$add('sdc.icon.prose', $prose('<h4>What we do and why we do it</h4>'), $pair, 'column_2');
$add('sdc.icon.prose', $prose('<p class="is-small">A creative agency of makers, shakers, interdisciplinary groundbreakers. Thinking, playing, testing, never resting – we make your story matter. Whether it’s strategic consultation to build a new brand or a creative idea that makes you part of culture, we make it… and we make it matter.</p>'), $pair, 'column_2');

$add('sdc.icon.divider', []);

// ---- Creative services: three to a row --------------------------------------
$head = $add('sdc.icon.columns', ['columns' => 2, 'gap' => 'normal', 'box' => 'none']);
$add('sdc.icon.prose', $prose('<h2>Creative<br>services</h2>'), $head, 'column_1');
$add('sdc.icon.prose', $prose('<p>Engage with us to deliver a single service or a fully realised campaign.</p>'), $head, 'column_1');
$services = [
  ['Creative', 'Powerful ideas that let brands reimagine the old and ideate the new.', ['Creative workshops', 'Territory exploration', 'Concept development and storyboards', 'Print and direct mail', 'End-to-end integrated campaigns', 'Social and digital marketing campaigns']],
  ['Brand design', 'Building and driving business growth through brands.', ['Brand transformation', 'Brand communications', 'Brandmark design', 'Brand governance and management', 'Employer branding and EVP']],
  ['Strategic planning', 'Mining deep human insights and data to shape break-through creative campaigns.', ['Behavioural science', 'Data insights', 'Research and analysis', 'Campaign strategy', 'Social and content strategy', 'Communications design', 'Digital strategy and planning', 'Media planning']],
  ['Behaviour change', 'Applying human science and behavioural economics to drive new behaviour for the public good.', ['Behavioural mapping', 'Research design and testing', 'Qualitative and quantitative research', 'Campaign planning and set-up', 'Integrated design and management', 'Campaign analytics and evaluation']],
  ['Branded storytelling', 'Building brand love through authentic, engaging content.', ['Native content', 'Brand and corporate storytelling', 'Video and testimonial films', 'Whitepapers', 'User-generated content campaigns', 'Employee advocacy', 'Infographics', 'Video and audio production']],
];
$service = static fn(array $s): string => '<h4>' . $e($s[0]) . '</h4><p class="is-small">' . $e($s[1]) . '</p><ul>' . implode('', array_map(fn($i) => '<li><p class="is-small">' . $e($i) . '</p></li>', $s[2])) . '</ul>';
foreach (array_chunk($services, 3) as $three) {
  $add('sdc.icon.spacer', ['size' => 'normal']);
  $row = $add('sdc.icon.columns', ['columns' => 3, 'gap' => 'normal', 'box' => 'none']);
  foreach ($three as $i => $s) {
    $add('sdc.icon.prose', $prose($service($s)), $row, 'column_' . ($i + 1));
  }
}
$add('sdc.icon.spacer', ['size' => 'normal']);

// ---- The close, and the work ------------------------------------------------
$add('sdc.icon.pull-quote', [
  'text' => 'Ideas that won’t be ignored and results that can’t be denied – that’s what matters to us.',
  'variant' => 'upright',
]);
$add('block.icon_work_latest', ['label' => 'Work: latest', 'label_display' => '0', 'accent' => 'Latest', 'caps' => 'Creative work', 'category' => 'creative', 'count' => 6]);

// ---- Save: the words the old page carried in its head -----------------------
$page->set('title', 'Creative');
$page->set('description', 'As a creative agency, we make brands matter through the application of deep human insights and connected thinking.');
$page->set('components', $tree);
$page->set('path', ['alias' => '/creative']);
$page->setPublished(TRUE);
if ($page->hasField('moderation_state')) {
  $page->set('moderation_state', 'published');
}
$page->setNewRevision(TRUE);
$page->setRevisionLogMessage('Creative page built from iconagency.com.au/icon-creative.');
$violations = $page->validate();
if (count($violations)) {
  foreach ($violations as $violation) {
    print $violation->getPropertyPath() . ': ' . strip_tags((string) $violation->getMessage()) . "\n";
  }
  throw new \RuntimeException('The Creative page did not validate.');
}
$page->save();
\Drupal::service(\Drupal\canvas\AutoSave\AutoSaveManager::class)->delete($page);
print "Saved canvas_page {$page->id()} with " . count($tree) . " components at /creative\n";

foreach ($etm->getStorage('menu_link_content')->loadByProperties(['menu_name' => 'main', 'title' => 'Creative']) as $link) {
  $link->set('link', ['uri' => 'internal:/creative', 'options' => []])->save();
  print "Main navigation: Creative → /creative\n";
}
