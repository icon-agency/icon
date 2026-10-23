<?php

declare(strict_types=1);

namespace Drupal\icon_site\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * The admin Navigation sidebar's links.
 */
final class NavigationHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_menu_links_discovered_alter().
   *
   * The bar shaped for this site (23 Oct 2026; user ask: "is this the best
   * menu set up? … Should it match Create menu, i.e. Edit > and the same
   * structure?"): Create first, then the Edit group
   * (icon_site.links.menu.yml — every list an editor opens to change
   * something), Pages, Media, and the generic list at the foot as "All
   * content"; Create's own list runs Work and News first, the everyday two.
   * Blocks and Files sit last and are for administrators only — the editor
   * role has no access to either. The bar's content menu is assembled by
   * other modules' alters (Navigation adds Create, Content, Blocks, Files
   * and Media; Canvas retitles Content "CMS"), so this one is ordered LAST.
   * (Sep 2026: this alter only put "Content" second, under Create.)
   */
  #[Hook('menu_links_discovered_alter', order: Order::Last)]
  public function menuLinksDiscoveredAlter(array &$links): void {
    $weights = [
      'navigation.create' => -10,
      'icon_site.edit' => -9,
      'navigation.pages' => -8,
      'navigation.media' => -7,
      'navigation.content' => 5,
      'navigation.blocks' => 10,
      'navigation.files' => 11,
      'navigation.content.node_type.work' => -10,
      'navigation.content.node_type.news' => -9,
      'navigation.content.node_type.team_member' => -5,
      'navigation.content.node_type.testimonial' => -4,
      'navigation.content.node_type.hero_slide' => 0,
      'navigation.content.node_type.office' => 5,
    ];
    foreach ($weights as $id => $weight) {
      if (isset($links[$id])) {
        $links[$id]['weight'] = $weight;
      }
    }
    if (isset($links['navigation.content'])) {
      $links['navigation.content']['title'] = $this->t('All content');
      $links['navigation.content']['options']['icon']['icon_id'] = 'database';
    }
  }

}
