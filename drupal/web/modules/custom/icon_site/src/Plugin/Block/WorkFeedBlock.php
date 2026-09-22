<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\views\Views;

/**
 * The "Latest work" rail on any page, with its heading and its feed chosen.
 *
 * The work View's latest display, embedded with what the panel sets: the
 * two-voice heading ("Latest" over "Work" to start — user ask, Sep 2026:
 * "allow me to page edit this heading"), the category the feed is drawn
 * from ("I want to be able to select what is in the feed (i.e. Digital)")
 * and how many. The View draws the cards; views-view--work--latest.html.twig
 * reads the heading off the display (icon_preprocess_views_view()).
 */
#[Block(
  id: 'icon_work_latest',
  admin_label: new TranslatableMarkup('Work: latest'),
  category: new TranslatableMarkup('ICON'),
)]
final class WorkFeedBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'accent' => 'Latest',
      'caps' => 'Work',
      'category' => '',
      'count' => 2,
    ];
  }

  /**
   * The work categories, value => label, from the field's own list.
   */
  private static function categories(): array {
    $storage = FieldStorageConfig::loadByName('node', 'field_work_category');
    $allowed = $storage ? (array) $storage->getSetting('allowed_values') : [];
    $options = [];
    foreach ($allowed as $value => $label) {
      $options[(string) $value] = (string) $label;
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['accent'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Heading, first line'),
      '#description' => $this->t('The serif line, in sentence case.'),
      '#default_value' => $this->configuration['accent'],
      '#maxlength' => 40,
    ];
    $form['caps'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Heading, second line'),
      '#description' => $this->t('The caps line. Type it in sentence case; the CSS sets the capitals.'),
      '#default_value' => $this->configuration['caps'],
      '#maxlength' => 40,
    ];
    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Feed'),
      '#description' => $this->t('Which work the rail draws from, newest first. A category with no work yet shows no rail at all.'),
      '#options' => ['' => $this->t('All work')] + self::categories(),
      '#default_value' => $this->configuration['category'],
    ];
    $form['count'] = [
      '#type' => 'select',
      '#title' => $this->t('Items shown'),
      '#options' => array_combine(range(1, 6), range(1, 6)),
      '#default_value' => max(1, min(6, (int) $this->configuration['count'])),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['accent'] = trim((string) $form_state->getValue('accent'));
    $this->configuration['caps'] = trim((string) $form_state->getValue('caps'));
    $this->configuration['category'] = (string) $form_state->getValue('category');
    $this->configuration['count'] = max(1, min(6, (int) $form_state->getValue('count')));
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $view = Views::getView('work');
    if (!$view || !$view->setDisplay('latest')) {
      return ['#cache' => ['tags' => ['config:views.view.work']]];
    }
    $category = (string) ($this->configuration['category'] ?? '');
    $view->setItemsPerPage(max(1, min(6, (int) ($this->configuration['count'] ?? 2))));
    // The heading and the feed ride on the display for the template
    // (icon_preprocess_views_view() reads them back).
    $view->display_handler->setOption('icon_heading', [
      'accent' => (string) ($this->configuration['accent'] ?? '') ?: 'Latest',
      'caps' => (string) ($this->configuration['caps'] ?? '') ?: 'Work',
      'category' => $category,
    ]);
    // The display inherits the category argument: 'all' is its exception,
    // which is every work item.
    $build = $view->buildRenderable('latest', [$category ?: 'all'], FALSE) ?: [];
    $build['#cache']['tags'][] = 'node_list:work';
    return $build;
  }

}
