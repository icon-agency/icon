<?php

/**
 * @file
 * The "Case study" pattern: the skeleton of a Work article body — a text
 * block for the challenge, an empty Gallery row, a text block for what
 * was made, a Results band with two results, and a closing text block —
 * so a new article opens on the shape rather than a blank tree. Insert it
 * from the library's Patterns tab; the masthead is added by the theme on
 * the article's first save, so the pattern carries none. Run it again to
 * reset the pattern to this.
 *
 * Run: ddev exec "ICON_SEED=1 drush php:script scripts/case-study-pattern.php"
 */

use Drupal\canvas\Entity\Pattern;

require_once __DIR__ . '/_guard.php';

$uuid = \Drupal::service('uuid');
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
    'inputs' => json_encode($inputs ?: new \stdClass(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    'label' => NULL,
  ];
  return $id;
};
$prose = static fn(string $html): array => ['text' => ['value' => $html, 'format' => 'canvas_html_block']];

$add('sdc.icon.prose', $prose('<h2 class="is-sentence">The challenge</h2><p>What the client faced, in a paragraph or two. What was at stake, and why it was hard.</p>'));
$add('sdc.icon.work-gallery', []);
$add('sdc.icon.prose', $prose('<h2 class="is-sentence">What we made</h2><p>The idea, then the work: the strategy, the creative, the channels. Keep each paragraph to one thought.</p>'));
$band = $add('sdc.icon.work-stats', []);
$add('sdc.icon.work-stat', ['value' => '12', 'unit' => 'million', 'label' => 'Earned impressions'], $band, 'results');
$add('sdc.icon.work-stat', ['value' => '2x', 'unit' => 'coverage', 'label' => 'Against the year before'], $band, 'results');
$add('sdc.icon.prose', $prose('<h2 class="is-sentence">Why it mattered</h2><p>The outcome for the client and for the people the work reached.</p>'));

$pattern = Pattern::load('case_study') ?? Pattern::create(['id' => 'case_study', 'label' => 'Case study']);
$pattern->set('label', 'Case study');
$pattern->set('component_tree', $tree);
$pattern->save();
print 'Pattern case_study saved with ' . count($tree) . " components\n";
