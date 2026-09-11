<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The offices, open (templates/contact.html), on any page.
 *
 * The same rows the footer folds into its accordion — Content → Offices,
 * through icon_site_offices() — laid out open by the office-list SDC.
 */
#[Block(
  id: 'icon_office_list',
  admin_label: new TranslatableMarkup('Offices'),
  category: new TranslatableMarkup('ICON'),
)]
final class OfficeListBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $offices = icon_site_offices();
    $build = [
      '#type' => 'component',
      '#component' => 'icon:office-list',
      '#props' => ['offices' => $offices['offices']],
    ];
    (new CacheableMetadata())->addCacheTags($offices['tags'])->applyTo($build);
    return $build;
  }

}
