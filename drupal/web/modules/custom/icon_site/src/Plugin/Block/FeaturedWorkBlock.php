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
   *
   * CANVAS ROUND-TRIPS A BLOCK'S SETTINGS THROUGH THIS FORM'S VALUES: on
   * load it builds the form from the stored settings and takes the inputs'
   * default values as its model; on every change it posts that model and
   * rebuilds the settings from it with a plugin that has only DEFAULT
   * configuration. So (1) every setting is an input whose default is the
   * current value, keyed exactly as the setting; (2) the list the editor sees
   * is markup only; (3) blockSubmit() reads values, never configuration.
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $latest = !empty($this->configuration['latest']);
    $picks = array_values(array_filter(array_map('intval', $this->configuration['projects'] ?? [])));
    $shown = $latest ? array_map(fn(NodeInterface $n) => (int) $n->id(), $this->tiles()) : $picks;
    $all = Url::fromRoute('system.admin_content', [], ['query' => ['type' => 'work']])->toString();
    $form['#attached']['library'][] = 'icon_site/panel_lists';

    // THE SETTINGS, as inputs (see above). The five project fields are
    // hidden by CSS; js/panel-sortable.js writes them from the list.
    $form['head'] = [
      '#type' => 'container',
      '#weight' => 0,
      '#attributes' => ['class' => ['icon-panel', 'icon-panel--featured-head']],
      'bar' => ['#markup' => '<div class="icon-panel__bar"><p class="icon-panel__title">' . $this->t('Featured · 5 tiles') . '</p><a class="icon-panel__button" href="' . $all . '" target="_blank" rel="noopener">' . $this->t('All work') . '</a></div>'],
    ];
    $form['latest'] = [
      '#type' => 'checkbox',
      '#weight' => 1,
      '#title' => $this->t('Show the latest five automatically'),
      '#description' => $this->t('Off: the five tiles below, in order.'),
      '#default_value' => $latest,
      '#attributes' => ['class' => ['icon-panel__toggle']],
    ];
    $form['projects'] = ['#type' => 'container', '#tree' => TRUE, '#weight' => 3, '#attributes' => ['class' => ['icon-panel__hidden']]];
    for ($i = 0; $i < self::SLOTS; $i++) {
      $form['projects'][$i] = [
        '#type' => 'textfield',
        '#title' => $this->t('Project @n', ['@n' => $i + 1]),
        '#title_display' => 'invisible',
        '#default_value' => isset($picks[$i]) ? (string) $picks[$i] : '',
        '#attributes' => ['class' => ['icon-panel__project'], 'data-index' => $i, 'autocomplete' => 'off', 'tabindex' => '-1'],
        '#size' => 6,
      ];
    }

    // THE LIST: markup only.
    $options = [];
    foreach ($storage->loadByProperties(['type' => 'work', 'status' => 1]) as $node) {
      $n = self::names($node);
      $options[] = ['id' => (int) $node->id(), 'project' => $n['project'], 'client' => $n['client']];
    }
    usort($options, fn(array $a, array $b) => strcasecmp($a['project'], $b['project']));
    $form['panel'] = ['#type' => 'container', '#weight' => 2, '#attributes' => ['class' => ['icon-panel', 'icon-panel--featured']]];
    $form['panel']['card'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['icon-panel__card', $latest ? 'icon-panel__card--auto' : ''],
        'data-options' => json_encode($options, JSON_UNESCAPED_UNICODE),
      ],
    ];
    $rows = '';
    for ($i = 0; $i < self::SLOTS; $i++) {
      $nid = $shown[$i] ?? 0;
      $node = $nid ? $storage->load($nid) : NULL;
      $picked = $node instanceof NodeInterface && $node->bundle() === 'work' ? self::names($node) : NULL;
      $display = $picked
        ? '<p class="icon-panel__name">' . htmlspecialchars($picked['project'], ENT_QUOTES) . '</p><p class="icon-panel__meta">' . htmlspecialchars($picked['client'], ENT_QUOTES) . '</p>'
        : '<p class="icon-panel__name icon-panel__name--empty">' . $this->t('No project yet') . '</p>';
      $rows .= '<tr class="draggable" data-row="' . $i . '" data-id="' . ($picked ? (int) $nid : '') . '"><td>' . self::HANDLE
        . '<a href="#" class="icon-panel__pickable" role="button" aria-haspopup="listbox"><span class="icon-panel__text">' . $display . '</span><span class="icon-panel__chevron" aria-hidden="true"></span></a></td></tr>';
    }
    $form['panel']['card']['list'] = ['#markup' => '<table class="icon-panel__list"><tbody>' . $rows . '</tbody></table>'];
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
    // From the values only — see blockForm(). Canvas posts them nested the
    // way the form is shaped, so the keys are the setting names.
    $projects = $form_state->getValue('projects');
    $picks = [];
    foreach (is_array($projects) ? $projects : [] as $v) {
      $v = is_array($v) ? ($v['target_id'] ?? reset($v)) : $v;
      if (is_numeric($v) && (int) $v > 0) {
        $picks[] = (int) $v;
      }
    }
    $this->configuration['projects'] = array_values(array_unique($picks));
    $this->configuration['latest'] = (bool) $form_state->getValue('latest');
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
