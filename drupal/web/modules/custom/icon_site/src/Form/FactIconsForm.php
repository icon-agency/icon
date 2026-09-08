<?php

declare(strict_types=1);

namespace Drupal\icon_site\Form;

/**
 * The fact-card icons as one list — see MediaListFormBase.
 *
 * The names are what the Fact card's Icon dropdown shows.
 */
final class FactIconsForm extends MediaListFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'icon_site_fact_icons';
  }

  /**
   * {@inheritdoc}
   */
  protected function bundle(): string {
    return 'icon';
  }

  /**
   * {@inheritdoc}
   */
  protected function fileField(): string {
    return 'field_media_file';
  }

  /**
   * {@inheritdoc}
   */
  protected function weightField(): string {
    return 'field_logo_weight';
  }

  /**
   * {@inheritdoc}
   */
  protected function routeName(): string {
    return 'icon_site.fact_icons';
  }

  /**
   * {@inheritdoc}
   */
  protected function help(): string {
    return (string) $this->t('The site\'s SVG icons — the homepage fact cards\' Icon dropdown and the footer offices\' icon picker both list them, in this order. Upload SVGs drawn in a single colour with <code>fill="currentColor"</code> — they are inlined and take the card\'s white. Drag to reorder; the name is what the dropdown shows. <em>Edit</em> replaces the file or renames it; <em>Delete</em> removes it (a card or office still using it shows no icon).');
  }

  /**
   * {@inheritdoc}
   */
  protected function nameLabel(): string {
    return (string) $this->t('Name');
  }

  /**
   * {@inheritdoc}
   */
  protected function fileLabel(): string {
    return (string) $this->t('Icon');
  }

}
