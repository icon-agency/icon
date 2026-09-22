<?php

declare(strict_types=1);

namespace Drupal\icon_site\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * The Work content type's own rules.
 */
final class WorkHooks {

  /**
   * Implements hook_entity_bundle_field_info_alter().
   *
   * The Square tile is required only when the Legacy folio card has no
   * layers (the IconWorkTile constraint; user call, 22 Sep 2026). The
   * field itself is optional, so the form and the editor's review stop
   * demanding it; the constraint asks for it when nothing stands in.
   */
  #[Hook('entity_bundle_field_info_alter')]
  public function entityBundleFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type, string $bundle): void {
    if ($entity_type->id() === 'node' && $bundle === 'work' && isset($fields['field_work_tile'])) {
      $fields['field_work_tile']->addConstraint('IconWorkTile');
    }
  }

}
