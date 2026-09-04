<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

/**
 * A block whose content is N Work items picked by the editor.
 *
 * Renders through a display of the work View, passing the picks as its
 * `nid` argument (`nid+nid+…`) in the chosen order; no picks → the display's
 * own default (the latest). Canvas edits the settings form in its side
 * panel, so the picks are made on the page itself.
 */
abstract class WorkPicksBlockBase extends BlockBase {

  /** The work View display that renders the picks. */
  abstract protected function display(): string;

  /** How many picks. */
  abstract protected function slots(): int;

  /** The fieldset's help text. */
  abstract protected function help(): string;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['projects' => []];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $picked = $this->configuration['projects'] ?? [];
    $form['projects'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Projects'),
      '#description' => $this->help(),
      '#tree' => TRUE,
    ];
    for ($i = 0; $i < $this->slots(); $i++) {
      $form['projects'][$i] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#selection_settings' => ['target_bundles' => ['work']],
        '#title' => $this->t('Project @n', ['@n' => $i + 1]),
        '#default_value' => isset($picked[$i]) ? Node::load($picked[$i]) : NULL,
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['projects'] = array_values(array_filter(array_map('intval', $form_state->getValue('projects', []))));
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $ids = array_values(array_filter(array_map('intval', $this->configuration['projects'] ?? [])));
    $build = views_embed_view('work', $this->display(), $ids ? implode('+', $ids) : NULL) ?? [];
    $build['#cache']['tags'][] = 'node_list:work';
    return $build;
  }

}
