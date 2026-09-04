<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\node\Plugin\EntityReferenceSelection\NodeSelection;

/**
 * Work items, searched by project name, client or title, labelled
 * "Project — Client" — the pickers' search box.
 */
#[EntityReferenceSelection(
  id: 'icon_work',
  label: new TranslatableMarkup('Work projects (project, client or title)'),
  entity_types: ['node'],
  group: 'icon_work',
  weight: 0,
)]
final class WorkSelection extends NodeSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    // The parent's query matches the label (title) only; replace that with
    // title OR project name OR client, on published Work.
    $query = parent::buildEntityQuery(NULL, $match_operator);
    $query->condition('type', 'work');
    $query->condition('status', 1);
    if ($match !== NULL && $match !== '') {
      $or = $query->orConditionGroup()
        ->condition('title', $match, $match_operator)
        ->condition('field_work_project', $match, $match_operator)
        ->condition('field_work_client', $match, $match_operator);
      $query->condition($or);
    }
    $query->sort('field_work_project', 'ASC')->sort('title', 'ASC');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $query = $this->buildEntityQuery($match, $match_operator);
    if ($limit > 0) {
      $query->range(0, $limit);
    }
    $ids = $query->execute();
    if (!$ids) {
      return [];
    }
    $options = [];
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($ids) as $node) {
      $options['work'][$node->id()] = self::label($node);
    }
    return $options;
  }

  /**
   * "Project — Client" (the title when there is no project name).
   */
  public static function label(NodeInterface $node): string {
    $project = trim((string) ($node->get('field_work_project')->value ?? '')) ?: $node->label();
    $client = trim((string) ($node->get('field_work_client')->value ?? ''));
    return $client !== '' ? "$project — $client" : $project;
  }

}
