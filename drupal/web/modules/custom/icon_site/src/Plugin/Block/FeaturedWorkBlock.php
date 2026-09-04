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
    return ['projects' => [], 'latest' => FALSE];
  }

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
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $picks = array_values(array_filter(array_map('intval', $this->configuration['projects'] ?? [])));
    if (!empty($this->configuration['latest'])) {
      // Preview what the grid shows: the newest five.
      $picks = array_map(fn(NodeInterface $n) => (int) $n->id(), $this->tiles());
    }
    $all = Url::fromRoute('system.admin_content', [], ['query' => ['type' => 'work']])->toString();
    $form['#attached']['library'][] = 'icon_site/panel_lists';
    $form['panel'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['icon-panel']],
    ];
    $latest = !empty($this->configuration['latest']);
    $form['panel']['bar'] = [
      '#markup' => '<div class="icon-panel__bar"><p class="icon-panel__title">' . $this->t('Featured · 5 tiles') . '</p><a class="icon-panel__button" href="' . $all . '" target="_blank" rel="noopener">' . $this->t('All work') . '</a></div>',
    ];
    // Automatic or hand-picked. On, the grid is the five newest work items
    // and the list below is only a preview of that; off, the list is the
    // grid.
    $form['panel']['latest'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the latest five automatically'),
      '#description' => $this->t('Off: the five tiles below, in order.'),
      '#default_value' => $latest,
      '#attributes' => ['class' => ['icon-panel__toggle']],
    ];
    // Every published Work item for the searchable dropdown (js/panel-sortable.js
    // filters it client-side); the autocomplete input is the value's transport.
    $options = [];
    foreach ($storage->loadByProperties(['type' => 'work', 'status' => 1]) as $node) {
      $n = self::names($node);
      $options[] = ['id' => (int) $node->id(), 'project' => $n['project'], 'client' => $n['client']];
    }
    usort($options, fn(array $a, array $b) => strcasecmp($a['project'], $b['project']));
    $form['panel']['card'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['icon-panel__card', $latest ? 'icon-panel__card--auto' : ''],
        'data-options' => json_encode($options, JSON_UNESCAPED_UNICODE),
      ],
    ];
    $form['panel']['card']['projects'] = [
      '#type' => 'table',
      '#header' => [$this->t('Project'), $this->t('Search')],
      '#attributes' => ['class' => ['icon-panel__list']],
    ];
    for ($i = 0; $i < self::SLOTS; $i++) {
      $nid = $picks[$i] ?? 0;
      $node = $nid ? $storage->load($nid) : NULL;
      $picked = $node instanceof NodeInterface && $node->bundle() === 'work' ? self::names($node) : NULL;
      $display = $picked
        ? '<p class="icon-panel__name">' . htmlspecialchars($picked['project'], ENT_QUOTES) . '</p><p class="icon-panel__meta">' . htmlspecialchars($picked['client'], ENT_QUOTES) . '</p>'
        : '<p class="icon-panel__name icon-panel__name--empty">' . $this->t('No project yet') . '</p>';
      $form['panel']['card']['projects'][$i] = [
        '#attributes' => ['class' => ['draggable'], 'data-row' => $i],
        '#weight' => $i,
        // The pick, shown (an <a>: #markup's admin XSS filter drops <button>);
        // then the search that changes it — an entity
        // autocomplete over Work, matching project, client or title
        // (WorkSelection). Picking a suggestion re-renders the row.
        'info' => ['#markup' => self::HANDLE . '<a href="#" class="icon-panel__pickable" role="button" aria-haspopup="listbox"><span class="icon-panel__text">' . $display . '</span><span class="icon-panel__chevron" aria-hidden="true"></span></a>'],
        'pick' => [
          '#type' => 'entity_autocomplete',
          '#target_type' => 'node',
          '#selection_handler' => 'icon_work',
          '#title' => $this->t('Change project @n', ['@n' => $i + 1]),
          '#title_display' => 'invisible',
          '#attributes' => ['class' => ['icon-panel__search'], 'autocomplete' => 'off', 'tabindex' => '-1'],
          '#size' => 30,
          '#wrapper_attributes' => ['class' => ['icon-panel__cell--search']],
        ],
        // The current pick rides along so an untouched row keeps it — as a
        // text field, hidden by CSS: Canvas does not transport `hidden`
        // inputs (a first version lost every pick on the panel's first
        // auto-submit that way).
        'current' => [
          '#type' => 'textfield',
          '#title' => $this->t('Current project @n', ['@n' => $i + 1]),
          '#title_display' => 'invisible',
          '#default_value' => $picked ? (string) $nid : '',
          '#attributes' => ['class' => ['icon-panel__current'], 'autocomplete' => 'off', 'tabindex' => '-1'],
          '#size' => 4,
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
      '#markup' => '<p class="icon-panel__note">' . ($latest
        ? $this->t('Showing the five newest work items, newest first — this list is what visitors see. Turn the switch off to pick and order tiles yourself.')
        : $this->t('A split pair, the wide feature, a tall-left pair. Drag rows to reorder; click a tile to search and pick its project.')) . '</p>',
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
      // A picked suggestion wins; otherwise the row keeps what it had.
      $nid = $rows[$i]['pick'] ?? NULL;
      $nid = is_array($nid) ? ($nid['target_id'] ?? NULL) : $nid;
      if (empty($nid)) {
        $nid = $rows[$i]['current'] ?? NULL;
      }
      if (!empty($nid)) {
        $picks[] = (int) $nid;
      }
    }
    $this->configuration['projects'] = array_values(array_unique($picks));
    $latest = $form_state->getValue(['panel', 'latest']) ?? $form_state->getValue('latest') ?? FALSE;
    $this->configuration['latest'] = (bool) $latest;
  }

  /**
   * The five tiles: the picks in order, topped up with the latest work.
   *
   * @return \Drupal\node\NodeInterface[]
   */
  private function tiles(): array {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $picks = empty($this->configuration['latest']) ? array_values(array_filter(array_map('intval', $this->configuration['projects'] ?? []))) : [];
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
