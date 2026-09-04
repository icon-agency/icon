<?php

declare(strict_types=1);

namespace Drupal\icon_site\Form;

/**
 * The fact-card icons as one list — see MediaListFormBase. The names are
 * what the Fact card's Icon dropdown shows.
 */
final class FactIconsForm extends MediaListFormBase {

  public function getFormId(): string {
    return 'icon_site_fact_icons';
  }

  protected function bundle(): string {
    return 'icon';
  }

  protected function fileField(): string {
    return 'field_media_file';
  }

  protected function weightField(): string {
    return 'field_logo_weight';
  }

  protected function routeName(): string {
    return 'icon_site.fact_icons';
  }

  protected function help(): string {
    return (string) $this->t('The icons the homepage fact cards can use, in the order the Icon dropdown lists them. Upload SVGs drawn in a single colour with <code>fill="currentColor"</code> — they are inlined and take the card\'s white. Drag to reorder; the name is what the dropdown shows. <em>Edit</em> replaces the file or renames it; <em>Delete</em> removes it (a card still using it shows no icon).');
  }

  protected function nameLabel(): string {
    return (string) $this->t('Name');
  }

  protected function fileLabel(): string {
    return (string) $this->t('Icon');
  }

}
