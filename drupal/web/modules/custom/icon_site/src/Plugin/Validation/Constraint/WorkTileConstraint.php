<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * A Work needs a Square tile, unless its Legacy folio card has layers.
 *
 * The Square was a hard-required field, so a project brought over from the
 * old site — a stack of layers and no new tile — could not be saved from
 * the editor: "Square field is required" (user call, 22 Sep 2026: "If
 * legacy exists, then there is a tile. Therefore I shouldn't get this
 * error"). The field is optional now and this constraint asks for it only
 * when there are no layers to stand in.
 */
#[Constraint(
  id: 'IconWorkTile',
  label: new TranslatableMarkup('Square tile or legacy layers'),
  type: ['entity_reference'],
)]
final class WorkTileConstraint extends SymfonyConstraint {

  /**
   * The message when neither the Square nor the layers is set.
   */
  public string $message = 'Square is required — unless the Legacy folio card has layers, which stand in for the tile.';

}
