<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\media\Entity\Media;

/**
 * The homepage hero — up to four pieces the editor composes: a film or image
 * from the media library, the client name (shown bottom-left, as a link),
 * and where it links. Independent of the Work items, so the reel can run
 * cuts the case studies don't. Rendered straight into the hero SDC.
 *
 * Canvas edits this form in its side panel.
 */
#[Block(
  id: 'icon_hero',
  admin_label: new TranslatableMarkup('Homepage hero'),
  category: new TranslatableMarkup('ICON'),
)]
final class HeroBlock extends BlockBase {

  public const int SLOTS = 4;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['pieces' => []];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $pieces = $this->configuration['pieces'] ?? [];
    $form['pieces'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      'help' => [
        '#markup' => '<p>' . $this->t('Up to four slides, in reel order. Each is a film or an image from the media library with the client name. <strong>Films must be 6 seconds long</strong>, muted, and loop — the reel plays each slide for 3 seconds with a cross-fade. To add media: Media → Add media → Video (MP4) or Image, then pick it here by name.') . '</p>',
      ],
    ];
    for ($i = 0; $i < self::SLOTS; $i++) {
      $piece = $pieces[$i] ?? [];
      $form['pieces'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Slide @n', ['@n' => $i + 1]),
        '#open' => $i === 0 || !empty($piece['media']),
        'media' => [
          '#type' => 'entity_autocomplete',
          '#target_type' => 'media',
          '#selection_settings' => ['target_bundles' => ['video', 'image']],
          '#title' => $this->t('Film or image'),
          '#description' => $this->t('A Video (MP4, 6 seconds) or an Image media item.'),
          '#default_value' => !empty($piece['media']) ? Media::load($piece['media']) : NULL,
        ],
        'client' => [
          '#type' => 'textfield',
          '#title' => $this->t('Client name'),
          '#description' => $this->t('Shown bottom-left while this slide plays.'),
          '#default_value' => $piece['client'] ?? '',
          '#maxlength' => 128,
        ],
        'url' => [
          '#type' => 'textfield',
          '#title' => $this->t('Link'),
          '#description' => $this->t('Where the client name goes, e.g. /work/raise-it. Leave empty for no link.'),
          '#default_value' => $piece['url'] ?? '',
          '#maxlength' => 255,
        ],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $pieces = [];
    foreach ($form_state->getValue('pieces', []) as $i => $piece) {
      if (!is_int($i) || empty($piece['media'])) {
        continue;
      }
      $pieces[] = [
        'media' => (int) $piece['media'],
        'client' => trim((string) ($piece['client'] ?? '')),
        'url' => trim((string) ($piece['url'] ?? '')),
      ];
    }
    $this->configuration['pieces'] = $pieces;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $pieces = [];
    $tags = [];
    foreach ($this->configuration['pieces'] ?? [] as $piece) {
      $media = !empty($piece['media']) ? Media::load($piece['media']) : NULL;
      $source = $media ? icon_site_media_source($media, 'work_media') : [];
      if (!$source) {
        continue;
      }
      $tags = array_merge($tags, $media->getCacheTags());
      $pieces[] = ['client' => $piece['client'] ?? '', 'url' => $piece['url'] ?? '', 'media' => $source];
    }
    if (!$pieces) {
      return [];
    }
    return [
      '#type' => 'component',
      '#component' => 'icon:hero',
      '#props' => ['pieces' => $pieces],
      '#cache' => ['tags' => $tags],
    ];
  }

}
