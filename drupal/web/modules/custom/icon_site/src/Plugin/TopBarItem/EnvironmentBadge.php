<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\TopBarItem;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\navigation\Attribute\TopBarItem;
use Drupal\navigation\TopBarItemBase;
use Drupal\navigation\TopBarRegion;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows the environment indicator's name and colours in the top bar.
 *
 * Environment Indicator only integrates with the classic toolbar; on a site
 * running the Navigation module it falls back to its own in-flow bar at the
 * top of the page, which Navigation's fixed top bar then covers while the
 * page is still pushed down by the bar's height. icon_site_page_top_alter()
 * drops that bar and this item carries the environment into the top bar.
 */
#[TopBarItem(
  id: 'icon_environment',
  region: TopBarRegion::Context,
  label: new TranslatableMarkup('Environment'),
  weight: -10,
)]
final class EnvironmentBadge extends TopBarItemBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the top bar item.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly AccountInterface $currentUser,
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['config:environment_indicator.indicator'],
      ],
    ];
    if (!$this->moduleHandler->moduleExists('environment_indicator') || !$this->currentUser->hasPermission('access environment indicator')) {
      return $build;
    }
    $config = $this->configFactory->get('environment_indicator.indicator');
    $name = (string) $config->get('name');
    if ($name === '') {
      return $build;
    }
    $build['badge'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $name,
      '#attributes' => [
        'class' => ['toolbar-badge', 'icon-environment-badge'],
        'style' => sprintf(
          '--icon-env-bg: %s; --icon-env-fg: %s;',
          self::colour((string) $config->get('bg_color'), '#2f6f2f'),
          self::colour((string) $config->get('fg_color'), '#ffffff'),
        ),
      ],
      '#attached' => ['library' => ['icon_site/environment_badge']],
    ];
    // MERGE — applyTo() replaces a render array's #cache outright, so
    // createFromObject($config)->applyTo() dropped the user.permissions
    // context set above and the badge could be cached across roles (found
    // in review, Sep 2026).
    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($config)
      ->applyTo($build);
    return $build;
  }

  /**
   * Keeps a configured colour to a hex value; anything else gets the default.
   */
  private static function colour(string $value, string $default): string {
    return preg_match('/^#[0-9a-f]{3,8}$/i', $value) ? $value : $default;
  }

}
