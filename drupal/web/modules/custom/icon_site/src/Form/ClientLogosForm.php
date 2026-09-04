<?php

declare(strict_types=1);

namespace Drupal\icon_site\Form;

/**
 * The client logos as one list — see MediaListFormBase.
 */
final class ClientLogosForm extends MediaListFormBase {

  public function getFormId(): string {
    return 'icon_site_client_logos';
  }

  protected function bundle(): string {
    return 'logo';
  }

  protected function fileField(): string {
    return 'field_media_file';
  }

  protected function weightField(): string {
    return 'field_logo_weight';
  }

  protected function routeName(): string {
    return 'icon_site.client_logos';
  }

  protected function help(): string {
    return (string) $this->t('These are the logos in the homepage marquee, in order. Drag the handles to reorder; the alt text is what screen readers announce and what the list shows. <em>Edit</em> replaces the file (SVG or PNG) or renames it; <em>Delete</em> removes it from the site.');
  }

  protected function nameLabel(): string {
    return (string) $this->t('Alt text');
  }

  protected function fileLabel(): string {
    return (string) $this->t('Logo');
  }

}
