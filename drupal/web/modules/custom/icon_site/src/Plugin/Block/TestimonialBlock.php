<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * One client quote in the Testimonials carousel, picked from the content.
 *
 * The quotes are Testimonial content (Content → the Testimonial type):
 * the panel picks one, or adds a new one in a dialog over the editor and
 * picks it (user ask, Sep 2026: "Is there a way I can easily select
 * previously added and the ability to add new"). Renders the testimonial
 * SDC with the node's fields; place it in a Testimonials (group).
 */
#[Block(
  id: 'icon_testimonial',
  admin_label: new TranslatableMarkup('Testimonial (item)'),
  category: new TranslatableMarkup('ICON'),
)]
final class TestimonialBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use PanelListTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['testimonial' => 0] + parent::defaultConfiguration();
  }

  /**
   * The published testimonials, newest first, keyed by node ID.
   *
   * @return \Drupal\node\NodeInterface[]
   *   The nodes.
   */
  private function testimonials(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(TRUE)
      ->condition('type', 'testimonial')->condition('status', 1)
      ->sort('changed', 'DESC')->execute();
    return $storage->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['#attached']['library'][] = 'icon_site/panel_lists';
    $options = [];
    foreach ($this->testimonials() as $node) {
      $company = trim((string) $node->get('field_testimonial_company')->value);
      $options[(int) $node->id()] = $node->label() . ($company !== '' ? ' — ' . $company : '');
    }
    $current = (int) ($this->configuration['testimonial'] ?? 0);
    $form['testimonial'] = [
      '#type' => 'select',
      '#title' => $this->t('Testimonial'),
      '#options' => $options,
      '#default_value' => $current,
      '#empty_option' => $this->t('- Choose -'),
      '#attributes' => ['class' => ['icon-panel__pick']],
      '#description' => $this->t('Every published testimonial, newest first. The same list is at <a href=":url" target="_blank" rel="noopener">Content</a>.', [
        ':url' => Url::fromRoute('system.admin_content', [], ['query' => ['type' => 'testimonial']])->toString(),
      ]),
    ];
    $dialog = self::dialog(860);
    $add = Url::fromRoute('node.add', ['node_type' => 'testimonial'], [
      'query' => ['panel' => 1, 'use_admin_theme' => 1],
    ])->toString();
    $actions = '<a class="icon-panel__button icon-panel__button--primary use-ajax" href="' . $add . '"' . $dialog . '>' . $this->t('+ Add a testimonial') . '</a>';
    if ($current && isset($options[$current])) {
      $edit = Url::fromRoute('entity.node.edit_form', ['node' => $current], [
        'query' => ['panel' => 1, 'use_admin_theme' => 1],
      ])->toString();
      $actions .= ' <a class="icon-panel__button use-ajax" href="' . $edit . '"' . $dialog . '>' . $this->t('Edit this one') . '</a>';
    }
    $form['panel'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['icon-panel', 'icon-panel--testimonial']],
      'bar' => ['#markup' => '<div class="icon-panel__bar">' . $actions . '</div>'],
      'note' => ['#markup' => '<p class="icon-panel__note">' . $this->t('A new testimonial is picked here as soon as it is saved. Edit changes it everywhere it is placed.') . '</p>'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['testimonial'] = (int) $form_state->getValue('testimonial');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $nid = (int) ($this->configuration['testimonial'] ?? 0);
    $node = $nid ? $this->entityTypeManager->getStorage('node')->load($nid) : NULL;
    $cache = (new CacheableMetadata())->addCacheTags(['node_list:testimonial']);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'testimonial' || !$node->access('view')) {
      $build = ['#markup' => ''];
      $cache->applyTo($build);
      return $build;
    }
    $cache->addCacheableDependency($node);
    $build = [
      '#type' => 'component',
      '#component' => 'icon:testimonial',
      '#props' => array_filter([
        'quote' => (string) $node->get('field_testimonial_quote')->value,
        'name' => (string) $node->label(),
        'role' => $node->hasField('field_testimonial_role') ? (string) $node->get('field_testimonial_role')->value : '',
        'company' => (string) $node->get('field_testimonial_company')->value,
        'logo' => $node->get('field_testimonial_logo')->target_id ? (int) $node->get('field_testimonial_logo')->target_id : NULL,
        'logo_large' => $node->hasField('field_testimonial_logo_large') && (bool) $node->get('field_testimonial_logo_large')->value,
      ]),
    ];
    $cache->applyTo($build);
    return $build;
  }

}
