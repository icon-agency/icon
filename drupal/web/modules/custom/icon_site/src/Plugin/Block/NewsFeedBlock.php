<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\views\Views;

/**
 * The homepage news feed: the newest promoted stories, pinned ones first.
 * Its one setting is how many; its Canvas panel shows the stories in the
 * feed with a pin toggle, Edit (a dialog) and Remove, plus a searchable
 * "Add a story" that promotes one. The rule stays the site's own promote /
 * sticky flags, so the news form and this panel agree.
 */
#[Block(
  id: 'icon_news_latest',
  admin_label: new TranslatableMarkup('News feed (homepage)'),
  category: new TranslatableMarkup('ICON'),
)]
final class NewsFeedBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['count' => 3];
  }

  /**
   * The feed: promoted, published, pinned first, then newest.
   *
   * @return \Drupal\node\NodeInterface[]
   */
  private function feed(int $count): array {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(TRUE)
      ->condition('type', 'news')->condition('status', 1)->condition('promote', 1)
      ->sort('sticky', 'DESC')->sort('field_news_date', 'DESC')->sort('created', 'DESC')
      ->range(0, $count)->execute();
    return $storage->loadMultiple($ids);
  }

  /**
   * "Category · date" for a story.
   */
  private static function meta(NodeInterface $node): string {
    $allowed = $node->get('field_news_category')->getFieldDefinition()->getSetting('allowed_values');
    $category = $allowed[$node->get('field_news_category')->value] ?? '';
    $date = $node->get('field_news_date')->value ?: date('Y-m-d', (int) $node->getCreatedTime());
    return trim($category . ' · ' . date('j M Y', strtotime($date)), ' ·');
  }

  /**
   * {@inheritdoc}
   *
   * Canvas round-trips the settings through the form's values: the one
   * setting is one input; everything else is markup with actions.
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $count = max(1, min(6, (int) ($this->configuration['count'] ?? 3)));
    $form['#attached']['library'][] = 'icon_site/panel_lists';
    $form['count'] = [
      '#type' => 'select',
      '#title' => $this->t('Stories shown'),
      '#options' => array_combine(range(1, 6), range(1, 6)),
      '#default_value' => $count,
      '#attributes' => ['class' => ['icon-panel__count']],
      '#weight' => 1,
    ];
    $dialog = ' data-dialog-type="dialog" data-dialog-options=\'{"target":"icon-panel-dialog","modal":true,"width":"900","classes":{"ui-dialog":"icon-panel-dialog"}}\'';
    $action = Url::fromRoute('icon_site.news_action')->toString();
    $rows = '';
    foreach ($this->feed($count) as $node) {
      $pinned = $node->isSticky();
      $edit = $node->toUrl('edit-form', ['query' => ['panel' => 1, 'use_admin_theme' => 1]])->toString();
      $rows .= '<tr data-row="' . $node->id() . '"><td>'
        . '<a href="' . $action . '&nid=' . $node->id() . '&op=' . ($pinned ? 'unpin' : 'pin') . '" class="icon-panel__pin use-ajax' . ($pinned ? ' is-pinned' : '') . '" title="' . ($pinned ? $this->t('Pinned to the top — click to unpin') : $this->t('Pin to the top of the feed')) . '" aria-label="' . ($pinned ? $this->t('Unpin') : $this->t('Pin')) . '"></a>'
        . '<div class="icon-panel__text"><p class="icon-panel__name">' . htmlspecialchars($node->label(), ENT_QUOTES) . '</p><p class="icon-panel__meta">' . htmlspecialchars(self::meta($node), ENT_QUOTES) . ($pinned ? ' · <strong>' . $this->t('Pinned') . '</strong>' : '') . '</p></div></td>'
        . '<td class="icon-panel__cell--action"><a class="icon-panel__action use-ajax" href="' . $edit . '"' . $dialog . '>' . $this->t('Edit') . '</a>'
        . '<a class="icon-panel__action icon-panel__action--quiet use-ajax" href="' . $action . '&nid=' . $node->id() . '&op=unpromote" title="' . $this->t('Take this story off the homepage (it stays published)') . '">' . $this->t('Remove') . '</a></td></tr>';
    }
    // Every published story NOT in the feed, for the "Add a story" dropdown
    // (js/panel-sortable.js filters it as you type). Adding one pins it —
    // the feed is the newest by date, so an older story only shows when
    // pinned; unpin it later and it falls back into date order.
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $in_feed = array_map(fn(NodeInterface $n) => (int) $n->id(), $this->feed($count));
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', 'news')->condition('status', 1)->sort('field_news_date', 'DESC')->execute();
    $options = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      if (in_array((int) $node->id(), $in_feed, TRUE)) {
        continue;
      }
      $options[] = ['id' => (int) $node->id(), 'project' => $node->label(), 'client' => self::meta($node)];
    }
    $form['panel'] = ['#type' => 'container', '#weight' => 2, '#attributes' => ['class' => ['icon-panel', 'icon-panel--news']]];
    $form['panel']['bar'] = [
      '#markup' => '<div class="icon-panel__bar"><p class="icon-panel__title">' . $this->t('On the homepage · @n', ['@n' => substr_count($rows, '<tr ')]) . '</p><a class="icon-panel__button" href="' . Url::fromRoute('system.admin_content', [], ['query' => ['type' => 'news']])->toString() . '" target="_blank" rel="noopener">' . $this->t('All news') . '</a></div>',
    ];
    $form['panel']['card'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['icon-panel__card'], 'data-options' => json_encode($options, JSON_UNESCAPED_UNICODE)],
    ];
    $form['panel']['card']['list'] = [
      '#markup' => ($rows ? '<table class="icon-panel__list"><tbody>' . $rows . '</tbody></table>' : '<p class="icon-panel__note">' . $this->t('No stories are promoted to the homepage yet.') . '</p>')
        . '<a href="#" class="icon-panel__pickable icon-panel__pickable--add" role="button" data-action-url="' . $action . '&op=promote" aria-haspopup="listbox"><span class="icon-panel__text"><p class="icon-panel__name icon-panel__name--empty">' . $this->t('+ Add a story to the homepage') . '</p></span><span class="icon-panel__chevron" aria-hidden="true"></span></a>',
    ];
    $form['panel']['note'] = [
      '#markup' => '<p class="icon-panel__note">' . $this->t('The feed is the newest promoted stories, by date. A pinned story stays at the top whatever its date — so adding an older story pins it. Pin, Remove and Add take effect at once and refresh the editor; Edit opens the story over the page. The same switches are on every story\'s form as "Promote to homepage" and "Pin on homepage".') . '</p>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['count'] = max(1, min(6, (int) $form_state->getValue('count')));
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $view = Views::getView('news');
    if (!$view || !$view->setDisplay('latest')) {
      return [];
    }
    $view->setItemsPerPage(max(1, min(6, (int) ($this->configuration['count'] ?? 3))));
    $build = $view->buildRenderable('latest', [], FALSE) ?: [];
    $build['#cache']['tags'][] = 'node_list:news';
    return $build;
  }

}
