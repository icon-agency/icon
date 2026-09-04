<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * The homepage's Featured Work grid — a FIXED five-tile rhythm (a split
 * pair, the wide feature, a tall-left pair). The block's one setting is the
 * five picks, in order: the Canvas panel is a draggable list of five rows,
 * each a dropdown of every published Work item by title with an Edit link
 * to the article. Empty picks fall back to the latest work. Renders the
 * featured-work SDC, filling its three row slots with the picks' teasers.
 */
#[Block(
  id: 'icon_featured_work',
  admin_label: new TranslatableMarkup('Featured work'),
  category: new TranslatableMarkup('ICON'),
)]
final class FeaturedWorkBlock extends BlockBase {

  /** The drag handle (js/panel-sortable.js listens for it). */
  private const HANDLE = '<span class="icon-panel__handle" title="Drag to reorder" aria-hidden="true"></span>';

  public const int SLOTS = 5;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['projects' => []];
  }

  /**
   * Every published Work item, by title.
   *
   * @return \Drupal\node\NodeInterface[]
   */
  private function allWork(): array {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', 'work')->condition('status', 1)->sort('title', 'ASC')->execute();
    return $storage->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  /**
   * A Work item's short project name (its title when none is set) + client.
   */
  private static function names(NodeInterface $node): array {
    $project = $node->hasField('field_work_project') ? trim((string) $node->get('field_work_project')->value) : '';
    return [
      'project' => $project !== '' ? $project : $node->label(),
      'client' => trim((string) ($node->get('field_work_client')->value ?? '')),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $work = $this->allWork();
    uasort($work, fn(NodeInterface $a, NodeInterface $b) => strcasecmp(self::names($a)['project'], self::names($b)['project']));
    $options = ['' => $this->t('Choose a project…')];
    foreach ($work as $node) {
      ['project' => $project, 'client' => $client] = self::names($node);
      $options[$node->id()] = $client !== '' ? "$project — $client" : $project;
    }
    $picks = array_values(array_filter(array_map('intval', $this->configuration['projects'] ?? [])));
    $all = Url::fromRoute('system.admin_content', [], ['query' => ['type' => 'work']])->toString();
    $form['#attached']['library'][] = 'icon_site/panel_lists';
    $form['panel'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['icon-panel']],
    ];
    $form['panel']['bar'] = [
      '#markup' => '<div class="icon-panel__bar"><p class="icon-panel__title">' . $this->t('Featured · 5 tiles') . '</p><a class="icon-panel__button" href="' . $all . '" target="_blank" rel="noopener">' . $this->t('All work') . '</a></div>',
    ];
    $form['panel']['card'] = ['#type' => 'container', '#attributes' => ['class' => ['icon-panel__card']]];
    $form['panel']['card']['projects'] = [
      '#type' => 'table',
      '#header' => [$this->t('Project')],
      '#attributes' => ['class' => ['icon-panel__list']],
    ];
    for ($i = 0; $i < self::SLOTS; $i++) {
      $nid = $picks[$i] ?? 0;
      $picked = $nid && isset($work[$nid]) ? self::names($work[$nid]) : NULL;
      // What the row shows: the pick's project name over its client. The
      // native select sits over it, transparent, so the whole card opens
      // the list; the chevron top-right is the affordance.
      $display = $picked
        ? '<p class="icon-panel__name">' . htmlspecialchars($picked['project'], ENT_QUOTES) . '</p><p class="icon-panel__meta">' . htmlspecialchars($picked['client'], ENT_QUOTES) . '</p>'
        : '<p class="icon-panel__name icon-panel__name--empty">' . $this->t('Choose a project…') . '</p>';
      $form['panel']['card']['projects'][$i] = [
        '#attributes' => ['class' => ['draggable'], 'data-row' => $i],
        '#weight' => $i,
        'cell' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['icon-panel__row']],
          'handle' => ['#markup' => self::HANDLE],
          'pick' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['icon-panel__pick']],
          'display' => ['#markup' => '<div class="icon-panel__text">' . $display . '</div>'],
          'nid' => [
            '#type' => 'select',
            '#title' => $this->t('Project @n', ['@n' => $i + 1]),
            '#title_display' => 'invisible',
            '#options' => $options,
            '#default_value' => $picked ? $nid : '',
            '#attributes' => ['class' => ['icon-panel__select']],
          ],
          ],
        ],
      ];
    }
    // THE ORDER of the five rows, as one text field the sorter writes
    // (js/panel-sortable.js) — Canvas ignores per-row weight selects.
    $form['panel']['order'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tile order'),
      '#title_display' => 'invisible',
      '#default_value' => implode(',', range(0, self::SLOTS - 1)),
      '#attributes' => ['class' => ['icon-panel__order'], 'autocomplete' => 'off', 'tabindex' => '-1'],
      '#wrapper_attributes' => ['class' => ['icon-panel__order-wrap']],
    ];
    $form['panel']['note'] = [
      '#markup' => '<p class="icon-panel__note">' . $this->t('A split pair, the wide feature, a tall-left pair. Drag rows to reorder; click a row to pick a project. Leave all five empty to show the latest five.') . '</p>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    // Canvas posts the block form with #tree semantics (nested under the
    // panel container); the ordinary block UI posts it flat.
    $rows = $form_state->getValue(['panel', 'card', 'projects']) ?: $form_state->getValue('projects') ?: [];
    $order = $form_state->getValue(['panel', 'order']) ?? $form_state->getValue('order') ?? '';
    $sequence = array_filter(array_map('intval', explode(',', (string) $order)), fn(int $i) => isset($rows[$i]));
    foreach (array_keys($rows) as $i) {
      if (!in_array($i, $sequence, TRUE)) {
        $sequence[] = $i;
      }
    }
    $picks = [];
    foreach ($sequence as $i) {
      $nid = $rows[$i]['cell']['pick']['nid'] ?? $rows[$i]['pick']['nid'] ?? $rows[$i]['nid'] ?? NULL;
      if (!empty($nid)) {
        $picks[] = (int) $nid;
      }
    }
    $this->configuration['projects'] = array_values(array_unique($picks));
  }

  /**
   * The five tiles: the picks in order, topped up with the latest work.
   *
   * @return \Drupal\node\NodeInterface[]
   */
  private function tiles(): array {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $picks = array_values(array_filter(array_map('intval', $this->configuration['projects'] ?? [])));
    $nodes = [];
    foreach ($picks as $nid) {
      $node = $storage->load($nid);
      if ($node instanceof NodeInterface && $node->bundle() === 'work' && $node->access('view')) {
        $nodes[$nid] = $node;
      }
    }
    if (count($nodes) < self::SLOTS) {
      $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', 'work')->condition('status', 1)
        ->sort('created', 'DESC')->range(0, self::SLOTS + count($nodes))->execute();
      foreach ($storage->loadMultiple($ids) as $node) {
        if (count($nodes) >= self::SLOTS) {
          break;
        }
        $nodes[$node->id()] ??= $node;
      }
    }
    return array_slice(array_values($nodes), 0, self::SLOTS);
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $tiles = $this->tiles();
    if (!$tiles) {
      return [];
    }
    $builder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $tags = ['node_list:work'];
    $teasers = [];
    foreach ($tiles as $i => $node) {
      $teaser = $builder->view($node, 'teaser');
      if ($i === 2) {
        $teaser['#work_wide'] = TRUE;
        $teaser['#cache']['keys'][] = 'wide';
      }
      $teasers[] = $teaser;
      $tags = Cache::mergeTags($tags, $node->getCacheTags());
    }
    return [
      '#type' => 'component',
      '#component' => 'icon:featured-work',
      '#slots' => [
        'pair_top' => array_slice($teasers, 0, 2),
        'feature' => array_slice($teasers, 2, 1),
        'pair_bottom' => array_slice($teasers, 3, 2),
      ],
      '#cache' => ['tags' => $tags],
    ];
  }

}
