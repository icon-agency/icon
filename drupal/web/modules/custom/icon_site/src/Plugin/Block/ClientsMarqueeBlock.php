<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * The client logos marquee: every Logo media item, in the order of the
 * Client logos list (/admin/content/client-logos), rendered by the clients
 * SDC. The one setting is the section's accessible label; the panel points
 * at the list for everything else.
 */
#[Block(
  id: 'icon_clients_marquee',
  admin_label: new TranslatableMarkup('Clients marquee'),
  category: new TranslatableMarkup('ICON'),
)]
final class ClientsMarqueeBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['heading' => 'Selected clients'];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $url = Url::fromRoute('icon_site.client_logos')->toString();
    $form['help'] = [
      '#markup' => '<p>' . $this->t('The marquee shows every client logo in the order of the <a href=":url" target="_blank" rel="noopener">Client logos</a> list — add, drag to reorder, rename or remove logos there.', [':url' => $url]) . '</p>',
    ];
    $form['heading'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Accessible label'),
      '#description' => $this->t('Read by screen readers as the name of this section; not shown.'),
      '#default_value' => $this->configuration['heading'] ?? 'Selected clients',
      '#maxlength' => 128,
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['heading'] = trim((string) $form_state->getValue('heading'));
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $storage = \Drupal::entityTypeManager()->getStorage('media');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('bundle', 'logo')
      ->condition('status', 1)
      ->sort('field_logo_weight', 'ASC')
      ->sort('name', 'ASC')
      ->execute();
    $logos = [];
    $tags = ['media_list'];
    foreach ($storage->loadMultiple($ids) as $media) {
      $file = $media->get('field_media_file')->entity;
      if (!$file) {
        continue;
      }
      $logos[] = [
        'src' => \Drupal::service('file_url_generator')->generateString($file->getFileUri()),
        'alt' => $media->label(),
      ];
      $tags = Cache::mergeTags($tags, $media->getCacheTags());
    }
    if (!$logos) {
      return [];
    }
    return [
      '#type' => 'component',
      '#component' => 'icon:clients',
      '#props' => ['logos' => $logos, 'heading' => $this->configuration['heading'] ?: 'Selected clients'],
      '#cache' => ['tags' => $tags],
    ];
  }

}
