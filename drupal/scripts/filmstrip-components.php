<?php

/**
 * @file
 * The Filmstrip BLOCK becomes the Filmstrip COMPONENT and its cards.
 *
 * One system for the strip (user call, Sep 2026: "The Stat cards should
 * work like the homepage intro. I feel like these are two systems"): the
 * homepage intro's strip is Photo and Fact card components in a slot,
 * each clicked and edited in the panel, reordered in Layers. The About
 * page carried a block whose panel bundled photos, films and fact cards
 * into one form. This turns every placed block into the component
 * (`sdc.icon.filmstrip`, slot `cards`) with a Photo per picture or film
 * and a Fact card per row, interleaved as the block drew them — a fact
 * card after every N photos — so the page looks the same.
 *
 * Idempotent: a page with no block is left alone. Run on the host after
 * the deploy that hides the block from the library:
 *   ICON_SEED=1 drush php:script scripts/filmstrip-components.php
 */

declare(strict_types=1);

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\Page;
use Drupal\media\Entity\Media;
use Drupal\user\Entity\User;

if (!getenv('ICON_SEED')) {
  throw new \RuntimeException('Refusing to run without ICON_SEED=1.');
}

$uuid = \Drupal::service('uuid');
$version = static function (string $id): string {
  $c = Component::load($id);
  if (!$c) {
    throw new \RuntimeException("Component $id is not registered.");
  }
  return (string) $c->get('active_version');
};
$item = static fn(string $component, array $inputs, ?string $parent, ?string $slot) => [
  'parent_uuid' => $parent,
  'slot' => $slot,
  'uuid' => $uuid->generate(),
  'component_id' => $component,
  'component_version' => $version($component),
  'inputs' => json_encode($inputs ?: new \stdClass(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
  'label' => NULL,
];

/**
 * The block's settings as the component and its cards.
 */
$cards = static function (array $settings, ?string $parent, ?string $slot) use ($item): array {
  $strip = $item('sdc.icon.filmstrip', array_filter(['label' => (string) ($settings['heading'] ?? '')]), $parent, $slot);
  $out = [$strip];
  $facts = array_values($settings['stats'] ?? []);
  $every = max(1, (int) ($settings['every'] ?? 2));
  $n = 0;
  $fact = 0;
  foreach ($settings['photos'] ?? [] as $mid) {
    $media = Media::load((int) $mid);
    if (!$media) {
      continue;
    }
    $key = $media->bundle() === 'video' ? 'film' : 'image';
    $out[] = $item('sdc.icon.intro-photo', [$key => ['target_id' => (int) $media->id()]], $strip['uuid'], 'cards');
    if (++$n % $every === 0 && isset($facts[$fact])) {
      $f = $facts[$fact++];
      $out[] = $item('sdc.icon.intro-fact', array_filter([
        'icon' => ['target_id' => (int) ($f['icon'] ?? 0)],
        'title' => (string) ($f['title'] ?? ''),
        'label' => (string) ($f['label'] ?? ''),
      ]), $strip['uuid'], 'cards');
    }
  }
  // Fact cards the photos did not reach still ride at the end.
  for (; isset($facts[$fact]); $fact++) {
    $f = $facts[$fact];
    $out[] = $item('sdc.icon.intro-fact', array_filter([
      'icon' => ['target_id' => (int) ($f['icon'] ?? 0)],
      'title' => (string) ($f['title'] ?? ''),
      'label' => (string) ($f['label'] ?? ''),
    ]), $strip['uuid'], 'cards');
  }
  return $out;
};

// The save is a Published → Published transition, which the script's
// anonymous account may not make: it runs as user 1.
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(User::load(1));
$autosave = \Drupal::service(AutoSaveManager::class);
foreach (Page::loadMultiple() as $page) {
  $values = $page->get('components')->getValue();
  $next = [];
  $changed = FALSE;
  foreach ($values as $value) {
    if (($value['component_id'] ?? '') !== 'block.icon_filmstrip') {
      $next[] = $value;
      continue;
    }
    $settings = json_decode((string) $value['inputs'], TRUE) ?: [];
    $new = $cards($settings, $value['parent_uuid'] ?? NULL, $value['slot'] ?? NULL);
    // The strip keeps the block's own uuid, so nothing pointing at it moves.
    $new[0]['uuid'] = $value['uuid'];
    foreach ($new as $i => $card) {
      if ($i > 0) {
        $new[$i]['parent_uuid'] = $value['uuid'];
      }
    }
    array_push($next, ...$new);
    $changed = TRUE;
    print "Page {$page->id()} ({$page->label()}): the block becomes the component with " . (count($new) - 1) . " cards\n";
  }
  if (!$changed) {
    continue;
  }
  $page->set('components', $next);
  $violations = $page->validate();
  if (count($violations)) {
    print "Page {$page->id()}: NOT saved —\n";
    foreach ($violations as $v) {
      print '  ' . $v->getPropertyPath() . ': ' . strip_tags((string) $v->getMessage()) . "\n";
    }
    continue;
  }
  $page->save();
  // An open editor's draft still holds the block; it is dropped.
  $autosave->delete($page);
  print "Page {$page->id()}: saved\n";
}
$switcher->switchBack();
