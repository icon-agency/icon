<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Validation\Constraint;

use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the IconWorkTile constraint.
 */
final class WorkTileConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$value instanceof FieldItemListInterface || !$constraint instanceof WorkTileConstraint) {
      return;
    }
    if (!$value->isEmpty()) {
      return;
    }
    $node = $value->getEntity();
    if ($node->hasField('field_work_legacy_layers') && !$node->get('field_work_legacy_layers')->isEmpty()) {
      return;
    }
    $this->context->addViolation($constraint->message);
  }

}
