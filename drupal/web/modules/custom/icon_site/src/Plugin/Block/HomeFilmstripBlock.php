<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The homepage's filmstrip, mirrored: the cards the home page's Intro holds.
 *
 * The homepage Intro places its Photo and Fact cards by hand in its strip
 * slot. A page that should show the SAME strip — the 404 and 403 pages
 * (user call, 6 Nov 2026: "so when the homepage is updated, the 404 and 403
 * update the same") — puts this block in its own Intro's strip slot
 * instead of copying the cards. It reads the front page's component tree,
 * takes the first Intro's strip children in their stored order, and renders
 * each through the same Photo and Fact card components. It depends on the
 * front page entity, so saving the home page refreshes every mirror.
 */
#[Block(
  id: 'icon_home_filmstrip',
  admin_label: new TranslatableMarkup('Filmstrip: the homepage\'s'),
  category: new TranslatableMarkup('ICON'),
)]
final class HomeFilmstripBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'), $container->get('config.factory'));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    // Kept for the strip's accessible label should the block ever render a
    // strip of its own; in the Intro's slot the Intro's label stands.
    return ['heading' => 'The ICON team'];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $cache = (new CacheableMetadata())->addCacheContexts(['user.permissions']);
    $site = $this->configFactory->get('system.site');
    $cache->addCacheableDependency($site);
    $front = (string) $site->get('page.front');
    if (!preg_match('#^/page/(\d+)$#', $front, $m)) {
      return [];
    }
    $home = $this->entityTypeManager->getStorage('canvas_page')->load((int) $m[1]);
    if (!$home) {
      return [];
    }
    $cache->addCacheableDependency($home);

    // The first Intro on the home page, then its strip's cards in order.
    $items = $home->get('components')->getValue();
    $intro = NULL;
    foreach ($items as $item) {
      if (($item['component_id'] ?? '') === 'sdc.icon.intro') {
        $intro = $item['uuid'];
        break;
      }
    }
    $cards = [];
    $media = $this->entityTypeManager->getStorage('media');
    foreach ($intro ? $items : [] as $item) {
      if (($item['parent_uuid'] ?? NULL) !== $intro || ($item['slot'] ?? NULL) !== 'strip') {
        continue;
      }
      $inputs = is_string($item['inputs'] ?? NULL) ? (json_decode($item['inputs'], TRUE) ?: []) : ($item['inputs'] ?? []);
      switch ($item['component_id'] ?? '') {
        case 'sdc.icon.intro-photo':
          $id = (int) ($inputs['media']['target_id'] ?? 0);
          if ($id && ($pick = $media->load($id))) {
            $cache->addCacheableDependency($pick);
            $cards[] = [
              '#type' => 'component',
              '#component' => 'icon:intro-photo',
              '#props' => ['media' => $id],
            ];
          }
          break;

        case 'sdc.icon.intro-fact':
          $icon = (int) ($inputs['icon']['target_id'] ?? 0);
          if ($icon && ($pick = $media->load($icon))) {
            $cache->addCacheableDependency($pick);
          }
          $cards[] = [
            '#type' => 'component',
            '#component' => 'icon:intro-fact',
            '#props' => [
              'icon' => $icon,
              'title' => (string) ($inputs['title'] ?? ''),
              'label' => (string) ($inputs['label'] ?? ''),
            ],
          ];
          break;
      }
    }
    // The CARDS only, for a strip slot that is already a filmstrip (the
    // Intro's): a second filmstrip inside the first left one strip dragging
    // the other (user catch, 6 Nov 2026: "its broken"). Canvas wraps a
    // block's output in its own div; filmstrip.css and js/filmstrip.js
    // unwrap that so the cards sit in the track as the Intro's own would.
    $build = $cards;
    $cache->applyTo($build);
    return $build;
  }

}
