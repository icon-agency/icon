<?php

/**
 * @file
 * Carries a Canvas page's tree from one environment to another, by UUID.
 *
 * Entity IDs are not stable between environments but UUIDs are, so the
 * export writes every media and node reference in the tree as a UUID and
 * the import looks each up on its own side. The export also carries a
 * manifest of every media item the tree uses (5 Nov 2026), and the import
 * creates the ones it lacks from files already in place (files never
 * travel in the JSON: send them up first, as the folio sync does). A
 * reference that still cannot be resolved drops that component and its
 * children, and says so.
 *
 * ICON_ADD_ONLY=1 on import refuses to touch a page already at the alias:
 * the guard for carrying new pages to a host whose pages editors own.
 *
 * Export (from the source, say local), to a file:
 *   ICON_SYNC=export ICON_ALIAS=/about drush php:script scripts/page-sync.php
 * Import (on the target, say the host), from that file:
 *   ICON_SEED=1 ICON_SYNC=import ICON_ALIAS=/about ICON_FILE=/tmp/about.json
 *   drush php:script scripts/page-sync.php
 */

declare(strict_types=1);

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
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

$fs = \Drupal::service('file_system');

/**
 * A media item's manifest entry: what the other side needs to rebuild it
 * (the same shape as scripts/node-sync.php's).
 */
$describe = static function (Media $media) use ($fs): array {
  $source = $media->getSource()->getConfiguration()['source_field'];
  $item = $media->get($source)->first();
  $file = $item?->entity;
  $real = $file ? $fs->realpath($file->getFileUri()) : FALSE;
  $entry = [
    'uuid' => $media->uuid(),
    'bundle' => $media->bundle(),
    'name' => $media->label(),
    'source_field' => $source,
    'uri' => $file ? $file->getFileUri() : NULL,
    'filename' => $file ? $file->getFilename() : NULL,
    'filemime' => $file ? $file->getMimeType() : NULL,
    'filesize' => $real && file_exists($real) ? filesize($real) : 0,
    'alt' => $item->alt ?? NULL,
    'title' => $item->title ?? NULL,
    'width' => $item->width ?? NULL,
    'height' => $item->height ?? NULL,
    'poster' => NULL,
  ];
  if ($media->bundle() !== 'image' && $media->hasField('thumbnail') && ($thumb = $media->get('thumbnail')->entity) && $thumb->getFileUri() !== $entry['uri']) {
    $entry['poster'] = ['uri' => $thumb->getFileUri(), 'filename' => $thumb->getFilename(), 'filemime' => $thumb->getMimeType()];
  }
  return $entry;
};

if ($mode === 'export') {
  if (!$page) {
    throw new \RuntimeException("No page at $alias.");
  }
  $refs = [];
  $manifest = [];
  $components = [];
  foreach ($page->get('components')->getValue() as $item) {
    $inputs = is_string($item['inputs']) ? (json_decode($item['inputs'], TRUE) ?: []) : ($item['inputs'] ?? []);
    $inputs = $walk($inputs, static function ($id) use (&$refs, &$manifest, $describe, $etm) {
      // A reference is a media item (pictures, films, icons) or a node.
      foreach (['media', 'node'] as $type) {
        $entity = $etm->getStorage($type)->load((int) $id);
        if ($entity) {
          $refs[$type][(string) $id] = ['uuid' => $entity->uuid(), 'label' => $entity->label()];
          if ($type === 'media' && !isset($manifest[$entity->uuid()])) {
            $manifest[$entity->uuid()] = $describe($entity);
          }
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
    'published' => $page->isPublished(),
    'components' => $components,
    'refs' => $refs,
    'media' => array_values($manifest),
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
if ($page && getenv('ICON_ADD_ONLY')) {
  throw new \RuntimeException("ADD_ONLY: page {$page->id()} ({$page->label()}) is already at $alias — nothing written.");
}
\Drupal::service('account_switcher')->switchTo(User::load(1));
if (!$page) {
  $page = $pages->create(['title' => $data['title'], 'owner' => 1]);
  print "No page at $alias yet: creating one.\n";
}

// Every media item in the manifest first, from files already in place.
$fileFor = static function (array $f) use ($etm, $fs): File {
  $existing = $etm->getStorage('file')->loadByProperties(['uri' => $f['uri']]);
  if ($existing) {
    return reset($existing);
  }
  $real = $fs->realpath($f['uri']);
  if (!$real || !file_exists($real)) {
    throw new \RuntimeException("File not in place: {$f['uri']} — send it up first.");
  }
  $file = File::create([
    'uri' => $f['uri'],
    'filename' => $f['filename'],
    'filemime' => $f['filemime'],
    'uid' => 1,
    'status' => 1,
  ]);
  $file->save();
  return $file;
};
$created = 0;
$matched = 0;
// uuid => media id, for the uuids this side holds under another item.
$aliases = [];
foreach ($data['media'] ?? [] as $entry) {
  if ($etm->getStorage('media')->loadByProperties(['uuid' => $entry['uuid']])) {
    continue;
  }
  if (!empty($entry['uri']) && ($file = $etm->getStorage('file')->loadByProperties(['uri' => $entry['uri']]))) {
    $fid = (int) reset($file)->id();
    $holder = $etm->getStorage('media')->getQuery()->accessCheck(FALSE)
      ->condition('bundle', $entry['bundle'])
      ->condition($entry['source_field'] . '.target_id', $fid)
      ->range(0, 1)->execute();
    if ($holder) {
      $aliases[$entry['uuid']] = (int) reset($holder);
      $matched++;
      continue;
    }
  }
  if (empty($entry['uri'])) {
    print "Media {$entry['name']} has no file in the export: its component will drop.\n";
    continue;
  }
  $real = $fs->realpath($entry['uri']);
  if (!$real || !file_exists($real)) {
    print "Media {$entry['name']}: {$entry['uri']} is not in place here, its component will drop.\n";
    continue;
  }
  $file = $fileFor($entry);
  $values = [
    'uuid' => $entry['uuid'],
    'bundle' => $entry['bundle'],
    'name' => $entry['name'],
    'uid' => 1,
    'status' => 1,
    $entry['source_field'] => array_filter([
      'target_id' => $file->id(),
      'alt' => $entry['alt'],
      'title' => $entry['title'],
      'width' => $entry['width'],
      'height' => $entry['height'],
    ], static fn($v) => $v !== NULL),
  ];
  $media = Media::create($values);
  if (!empty($entry['poster'])) {
    $poster = $fileFor($entry['poster']);
    $media->set('thumbnail', ['target_id' => $poster->id()]);
  }
  $media->save();
  $created++;
}
if (!empty($data['media'])) {
  print "Media: $created created, $matched matched by file, " . (count($data['media']) - $created - $matched) . " already here.\n";
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
  $inputs = $walk($item['inputs'] ?: [], static function ($ref) use (&$missing, $etm, $aliases) {
    if (!is_string($ref) || !preg_match('/^(media|node):(.+)$/', $ref, $m)) {
      return $ref;
    }
    if ($m[1] === 'media' && isset($aliases[$m[2]])) {
      return $aliases[$m[2]];
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
// The page's own state travels with it: an unpublished draft stays one.
$published = $data['published'] ?? TRUE;
$page->setPublished($published);
if ($page->hasField('moderation_state')) {
  $page->set('moderation_state', $published ? 'published' : 'draft');
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
