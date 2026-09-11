<?php

/**
 * @file
 * Carries a Canvas page's tree from one environment to another, by UUID.
 *
 * Entity IDs are not stable between environments but UUIDs are, so the
 * export writes every media and node reference in the tree as a UUID and
 * the import looks each up on its own side. A reference the other side
 * does not have (a file uploaded locally: files never travel) drops that
 * component and its children, and says so.
 *
 * Export (from the source, say local), to a file:
 *   ICON_SYNC=export ICON_ALIAS=/about drush php:script scripts/page-sync.php
 * Import (on the target, say the host), from that file:
 *   ICON_SEED=1 ICON_SYNC=import ICON_ALIAS=/about ICON_FILE=/tmp/about.json
 *   drush php:script scripts/page-sync.php
 */

declare(strict_types=1);

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\user\Entity\User;

$mode = (string) getenv('ICON_SYNC');
$alias = (string) getenv('ICON_ALIAS');
if (!in_array($mode, ['export', 'import'], TRUE) || $alias === '') {
  throw new \RuntimeException('ICON_SYNC=export|import and ICON_ALIAS=/path are required.');
}
$etm = \Drupal::entityTypeManager();
$pages = $etm->getStorage('canvas_page');
$page = NULL;
$path = \Drupal::service('path_alias.repository')->lookupByAlias($alias, 'en');
if ($path && preg_match('#^/page/(\d+)$#', $path['path'], $m)) {
  $page = $pages->load($m[1]);
}

/**
 * Walks inputs, handing every target_id to $fn(entity_type, id) => new id.
 */
$walk = static function (array $inputs, callable $fn) use (&$walk): array {
  foreach ($inputs as $key => $value) {
    if (is_array($value)) {
      if (array_key_exists('target_id', $value) && is_scalar($value['target_id'])) {
        $inputs[$key]['target_id'] = $fn($value['target_id']);
      }
      else {
        $inputs[$key] = $walk($value, $fn);
      }
    }
  }
  return $inputs;
};

if ($mode === 'export') {
  if (!$page) {
    throw new \RuntimeException("No page at $alias.");
  }
  $refs = [];
  $components = [];
  foreach ($page->get('components')->getValue() as $item) {
    $inputs = is_string($item['inputs']) ? (json_decode($item['inputs'], TRUE) ?: []) : ($item['inputs'] ?? []);
    $inputs = $walk($inputs, static function ($id) use (&$refs, $etm) {
      // A reference is a media item (pictures, films, icons) or a node.
      foreach (['media', 'node'] as $type) {
        $entity = $etm->getStorage($type)->load((int) $id);
        if ($entity) {
          $refs[$type][(string) $id] = ['uuid' => $entity->uuid(), 'label' => $entity->label()];
          return "$type:" . $entity->uuid();
        }
      }
      return $id;
    });
    $components[] = [
      'uuid' => $item['uuid'],
      'parent_uuid' => $item['parent_uuid'],
      'slot' => $item['slot'],
      'component_id' => $item['component_id'],
      'component_version' => $item['component_version'],
      'inputs' => $inputs,
      'label' => $item['label'],
    ];
  }
  print json_encode([
    'alias' => $alias,
    'title' => $page->label(),
    'description' => (string) ($page->get('description')->value ?? ''),
    'components' => $components,
    'refs' => $refs,
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
  return;
}

// ---- import -----------------------------------------------------------------
if (!getenv('ICON_SEED')) {
  throw new \RuntimeException('Refusing to import without ICON_SEED=1.');
}
$file = (string) getenv('ICON_FILE');
$data = json_decode((string) file_get_contents($file), TRUE);
if (!is_array($data) || empty($data['components'])) {
  throw new \RuntimeException("Nothing to import in $file.");
}
\Drupal::service('account_switcher')->switchTo(User::load(1));
if (!$page) {
  $page = $pages->create(['title' => $data['title'], 'owner' => 1]);
  print "No page at $alias yet: creating one.\n";
}
$versions = static function (string $id) use ($etm): string {
  $c = $etm->getStorage('component')->load($id);
  if (!$c) {
    throw new \RuntimeException("Component $id is not registered here.");
  }
  return (string) $c->get('active_version');
};
$dropped = [];
$tree = [];
foreach ($data['components'] as $item) {
  $missing = NULL;
  $inputs = $walk($item['inputs'] ?: [], static function ($ref) use (&$missing, $etm) {
    if (!is_string($ref) || !preg_match('/^(media|node):(.+)$/', $ref, $m)) {
      return $ref;
    }
    $found = $etm->getStorage($m[1])->loadByProperties(['uuid' => $m[2]]);
    if (!$found) {
      $missing = $ref;
      return 0;
    }
    return (int) reset($found)->id();
  });
  if ($missing) {
    $dropped[$item['uuid']] = $item['component_id'] . ' (' . $missing . ')';
    continue;
  }
  $tree[] = [
    'parent_uuid' => $item['parent_uuid'],
    'slot' => $item['slot'],
    'uuid' => $item['uuid'],
    'component_id' => $item['component_id'],
    // This side's version of the component, not the source's.
    'component_version' => $versions($item['component_id']),
    'inputs' => json_encode($inputs ?: new \stdClass(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    'label' => $item['label'],
  ];
}
// A dropped component's children go with it.
do {
  $before = count($tree);
  $tree = array_values(array_filter($tree, static function (array $item) use (&$dropped): bool {
    if ($item['parent_uuid'] && isset($dropped[$item['parent_uuid']])) {
      $dropped[$item['uuid']] = $item['component_id'] . ' (child of a dropped component)';
      return FALSE;
    }
    return TRUE;
  }));
} while (count($tree) !== $before);

$page->set('title', $data['title']);
$page->set('description', $data['description']);
$page->set('components', $tree);
$page->set('path', ['alias' => $alias]);
$page->setPublished(TRUE);
if ($page->hasField('moderation_state')) {
  $page->set('moderation_state', 'published');
}
$page->setNewRevision(TRUE);
$page->setRevisionLogMessage("Synced from another environment (scripts/page-sync.php).");
$violations = $page->validate();
if (count($violations)) {
  foreach ($violations as $v) {
    print '  ' . $v->getPropertyPath() . ': ' . strip_tags((string) $v->getMessage()) . "\n";
  }
  throw new \RuntimeException("Page $alias not saved.");
}
$page->save();
\Drupal::service(AutoSaveManager::class)->delete($page);
\Drupal::service('account_switcher')->switchBack();
foreach ($dropped as $what) {
  print "Dropped: $what\n";
}
print "Page {$page->id()} at $alias: " . count($tree) . " components saved.\n";
