<?php

/**
 * @file
 * Carries one Work (or News) node from one environment to another, by UUID.
 *
 * The sibling of page-sync.php for nodes whose body is a Canvas tree. The
 * export writes the node's fields, its Canvas tree with every media or node
 * reference as a UUID, and a manifest of the media items it needs — each
 * with the file's URI, so the files can be sent up with tar (CMS files never
 * travel in git). The import creates any media item the target lacks from
 * the file already in place at that URI (sent first), then saves the node
 * under the same UUID — and the same node id when that id is free — with
 * the target's own component versions, published as the source had it.
 *
 * Export (from the source, say local), to a file:
 *   ICON_SYNC=export ICON_NID=392 drush php:script scripts/node-sync.php
 * Send the files it lists (same paths under sites/default/files), then
 * import on the target:
 *   ICON_SEED=1 ICON_SYNC=import ICON_FILE=/tmp/node-392.json
 *   drush php:script scripts/node-sync.php
 */

declare(strict_types=1);

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

$mode = (string) getenv('ICON_SYNC');
if (!in_array($mode, ['export', 'import'], TRUE)) {
  throw new \RuntimeException('ICON_SYNC=export|import is required.');
}
$etm = \Drupal::entityTypeManager();
$fs = \Drupal::service('file_system');

/**
 * Walks inputs, handing every target_id to $fn(id) => new id.
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

/**
 * The Canvas field a node's body lives in, if any.
 */
$canvasField = static function (Node $node): ?string {
  foreach ($node->getFieldDefinitions() as $name => $def) {
    if ($def->getType() === 'component_tree') {
      return $name;
    }
  }
  return NULL;
};

/**
 * A media item's manifest entry: what the other side needs to rebuild it.
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
  // A film's poster is its thumbnail file; a picture's thumbnail is itself.
  if ($media->bundle() !== 'image' && $media->hasField('thumbnail') && ($thumb = $media->get('thumbnail')->entity) && $thumb->getFileUri() !== $entry['uri']) {
    $entry['poster'] = ['uri' => $thumb->getFileUri(), 'filename' => $thumb->getFilename(), 'filemime' => $thumb->getMimeType()];
  }
  return $entry;
};

// ---- export -----------------------------------------------------------------
if ($mode === 'export') {
  $nid = (int) getenv('ICON_NID');
  $node = $nid ? Node::load($nid) : NULL;
  if (!$node) {
    throw new \RuntimeException('ICON_NID must name a node here.');
  }
  $media = [];
  $noteMedia = static function (int $id) use (&$media, $etm, $describe): ?string {
    $m = $etm->getStorage('media')->load($id);
    if (!$m) {
      return NULL;
    }
    $media[$m->uuid()] = $media[$m->uuid()] ?? $describe($m);
    return $m->uuid();
  };
  $fields = [];
  foreach ($node->getFieldDefinitions() as $name => $def) {
    $storage = $def->getFieldStorageDefinition();
    if ($storage->isBaseField() || $def->isComputed() || $def->getType() === 'component_tree') {
      continue;
    }
    $values = $node->get($name)->getValue();
    if ($def->getType() === 'entity_reference' && $storage->getSetting('target_type') === 'media') {
      $values = array_values(array_filter(array_map(static function (array $v) use ($noteMedia) {
        $uuid = !empty($v['target_id']) ? $noteMedia((int) $v['target_id']) : NULL;
        return $uuid ? ['uuid' => $uuid] : NULL;
      }, $values)));
    }
    $fields[$name] = $values;
  }
  $components = [];
  if ($field = $canvasField($node)) {
    foreach ($node->get($field)->getValue() as $item) {
      $inputs = is_string($item['inputs']) ? (json_decode($item['inputs'], TRUE) ?: []) : ($item['inputs'] ?? []);
      $inputs = $walk($inputs, static function ($id) use ($noteMedia, $etm) {
        if ($uuid = $noteMedia((int) $id)) {
          return "media:$uuid";
        }
        $n = $etm->getStorage('node')->load((int) $id);
        return $n ? 'node:' . $n->uuid() : $id;
      });
      $components[] = [
        'uuid' => $item['uuid'],
        'parent_uuid' => $item['parent_uuid'],
        'slot' => $item['slot'],
        'component_id' => $item['component_id'],
        'inputs' => $inputs,
        'label' => $item['label'],
      ];
    }
  }
  print json_encode([
    'nid' => (int) $node->id(),
    'uuid' => $node->uuid(),
    'type' => $node->bundle(),
    'title' => $node->label(),
    'alias' => \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $node->id()),
    'published' => $node->isPublished(),
    'created' => (int) $node->getCreatedTime(),
    'canvas_field' => $field,
    'fields' => $fields,
    'components' => $components,
    'media' => array_values($media),
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
  return;
}

// ---- import -----------------------------------------------------------------
if (!getenv('ICON_SEED')) {
  throw new \RuntimeException('Refusing to import without ICON_SEED=1.');
}
$data = json_decode((string) file_get_contents((string) getenv('ICON_FILE')), TRUE);
if (!is_array($data) || empty($data['uuid'])) {
  throw new \RuntimeException('Nothing to import in ICON_FILE.');
}
\Drupal::service('account_switcher')->switchTo(User::load(1));

// Every media item first, from the files already in place.
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
// uuid => media id, for the uuids the target holds under another item.
$aliases = [];
foreach ($data['media'] as $entry) {
  if ($etm->getStorage('media')->loadByProperties(['uuid' => $entry['uuid']])) {
    continue;
  }
  // The same file may already be a media item here under its own uuid (a
  // folio built on both sides): use that item rather than doubling it.
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
    throw new \RuntimeException("Media {$entry['name']} has no file in the export.");
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
print "Media: $created created, $matched matched by file, " . (count($data['media']) - $created - $matched) . " already here.\n";

$mediaId = static function (string $uuid) use ($etm, $aliases): int {
  if (isset($aliases[$uuid])) {
    return $aliases[$uuid];
  }
  $found = $etm->getStorage('media')->loadByProperties(['uuid' => $uuid]);
  if (!$found) {
    throw new \RuntimeException("Media $uuid is not here.");
  }
  return (int) reset($found)->id();
};

// The node: the same one if it is here, else new under the same uuid (and id).
$found = $etm->getStorage('node')->loadByProperties(['uuid' => $data['uuid']]);
$sameId = Node::load((int) $data['nid']);
if ($found) {
  $node = reset($found);
  print "Node {$node->id()} is here: updating it.\n";
}
elseif ($sameId && $sameId->bundle() === $data['type'] && $sameId->label() === $data['title']) {
  // Built on both sides: the same folio under its own uuid. Keep that uuid.
  $node = $sameId;
  print "Node {$node->id()} is here under another uuid: updating it.\n";
}
else {
  $values = ['type' => $data['type'], 'uuid' => $data['uuid'], 'uid' => 1, 'created' => $data['created']];
  if (!$sameId) {
    $values['nid'] = (int) $data['nid'];
  }
  $node = Node::create($values);
}
$node->setTitle($data['title']);
foreach ($data['fields'] as $name => $values) {
  if (!$node->hasField($name)) {
    print "  (no field $name here: skipped)\n";
    continue;
  }
  $def = $node->getFieldDefinition($name);
  if ($def->getType() === 'entity_reference' && $def->getFieldStorageDefinition()->getSetting('target_type') === 'media') {
    $values = array_map(static fn(array $v) => ['target_id' => $mediaId($v['uuid'])], $values);
  }
  $node->set($name, $values);
}
if (!empty($data['canvas_field']) && $node->hasField($data['canvas_field'])) {
  $versions = static function (string $id) use ($etm): string {
    $c = $etm->getStorage('component')->load($id);
    if (!$c) {
      throw new \RuntimeException("Component $id is not registered here.");
    }
    return (string) $c->get('active_version');
  };
  $tree = [];
  foreach ($data['components'] as $item) {
    $inputs = $walk($item['inputs'] ?: [], static function ($ref) use ($etm, $mediaId) {
      if (!is_string($ref) || !preg_match('/^(media|node):(.+)$/', $ref, $m)) {
        return $ref;
      }
      if ($m[1] === 'media') {
        return $mediaId($m[2]);
      }
      $found = $etm->getStorage('node')->loadByProperties(['uuid' => $m[2]]);
      if (!$found) {
        throw new \RuntimeException("Node $m[2] (a reference in the tree) is not here.");
      }
      return (int) reset($found)->id();
    });
    $tree[] = [
      'parent_uuid' => $item['parent_uuid'],
      'slot' => $item['slot'],
      'uuid' => $item['uuid'],
      'component_id' => $item['component_id'],
      'component_version' => $versions($item['component_id']),
      'inputs' => json_encode($inputs ?: new \stdClass(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      'label' => $item['label'],
    ];
  }
  $node->set($data['canvas_field'], $tree);
}
if (!empty($data['alias'])) {
  $node->set('path', ['alias' => $data['alias']]);
}
$published = $data['published'] ?? TRUE;
$node->setPublished($published);
if ($node->hasField('moderation_state')) {
  $node->set('moderation_state', $published ? 'published' : 'draft');
}
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Synced from another environment (scripts/node-sync.php).');
$violations = $node->validate();
if (count($violations)) {
  foreach ($violations as $v) {
    print '  ' . $v->getPropertyPath() . ': ' . strip_tags((string) $v->getMessage()) . "\n";
  }
  throw new \RuntimeException('Node not saved.');
}
$node->save();
\Drupal::service(AutoSaveManager::class)->delete($node);
\Drupal::service('account_switcher')->switchBack();
print "Node {$node->id()} \"{$node->label()}\" at {$data['alias']}: " . count($data['components']) . " components, published=" . (int) $published . ".\n";
