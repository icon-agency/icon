<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The homepage's Featured Work grid — five projects picked by the editor,
 * rendered by the work View's `featured` display (the split / wide /
 * tall-left rows).
 */
#[Block(
  id: 'icon_featured_work',
  admin_label: new TranslatableMarkup('Featured work'),
  category: new TranslatableMarkup('ICON'),
)]
final class FeaturedWorkBlock extends WorkPicksBlockBase {

  protected function display(): string { return 'featured'; }

  protected function slots(): int { return 5; }

  protected function help(): string {
    return (string) $this->t('Five slots, in the order the homepage shows them: a split pair, the wide feature, then a tall-left pair. Start typing a project title. Leave all five empty to show the latest five.');
  }

}
