<?php

declare(strict_types=1);

namespace Drupal\icon_site\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * The admin Navigation sidebar's links.
 */
final class NavigationHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_menu_links_discovered_alter().
   *
   * The sidebar's link to the content list reads "Content" and sits right
   * under Create (user call, Sep 2026): the editors' list is the second
   * thing in the bar. Canvas retitles that link "CMS" in its own alter,
   * ordered after Navigation's; this one is ordered after Canvas's.
   */
  #[Hook('menu_links_discovered_alter', order: new OrderAfter(['canvas']))]
  public function menuLinksDiscoveredAlter(array &$links): void {
    if (isset($links['navigation.content'])) {
      $links['navigation.content']['title'] = $this->t('Content');
      $links['navigation.content']['weight'] = -8;
    }
  }

}
