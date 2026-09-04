<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * The homepage hero. Its slides are Hero slide content (a film or image,
 * the client name, a link) — added and edited on their own forms — and this
 * block's one setting is their ORDER, set by dragging the list in the
 * Canvas panel. A slide not yet in the order is appended, newest last, so a
 * new slide shows straight away. Renders the hero SDC with a Hero slide
 * component per slide in its slot.
 */
#[Block(
  id: 'icon_hero',
  admin_label: new TranslatableMarkup('Homepage hero'),
  category: new TranslatableMarkup('ICON'),
)]
final class HeroBlock extends BlockBase {

  /** The drag handle (js/panel-sortable.js listens for it). */
  private const HANDLE = '<span class="icon-panel__handle" title="Drag to reorder" aria-hidden="true"></span>';

  public const int MAX = 8;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['order' => []];
  }

  /**
   * Every published Hero slide, in the configured order (unknown ones last).
   *
   * @return \Drupal\node\NodeInterface[]
   */
  private function slides(): array {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'hero_slide')
      ->condition('status', 1)
      ->sort('created', 'ASC')
      ->execute();
    $slides = $storage->loadMultiple($ids);
    $rank = array_flip(array_map('intval', $this->configuration['order'] ?? []));
    uasort($slides, fn(NodeInterface $a, NodeInterface $b) => ($rank[(int) $a->id()] ?? PHP_INT_MAX) <=> ($rank[(int) $b->id()] ?? PHP_INT_MAX));
    return $slides;
  }

  /**
   * {@inheritdoc}
   *
   * Canvas round-trips the settings through this form's VALUES (it builds
   * the form from the settings, takes the defaults as its model, and
   * rebuilds the settings from the posted model with a default-configured
   * plugin), so the one setting is one input — `order`, hidden by CSS,
   * written by js/panel-sortable.js — and the list is markup only.
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $slides = $this->slides();
    $add = Url::fromRoute('node.add', ['node_type' => 'hero_slide'], ['query' => ['panel' => 1, 'use_admin_theme' => 1]])->toString();
    $form['#attached']['library'][] = 'icon_site/panel_lists';
    $form['order'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Slide order'),
      '#title_display' => 'invisible',
      '#default_value' => implode(',', array_keys($slides)),
      '#attributes' => ['class' => ['icon-panel__order'], 'autocomplete' => 'off', 'tabindex' => '-1'],
      '#wrapper_attributes' => ['class' => ['icon-panel__hidden']],
    ];
    // Add / Edit open the slide form in a dialog over the editor (the form
    // closes it on save — icon_site_form_node_form_alter()). `use_admin_theme`
    // is Canvas's own switch: without it the editor's ajax requests render in
    // canvas_stark, whose form markup is for the React panel, not a dialog.
    $dialog = ' data-dialog-type="dialog" data-dialog-options=\'{"target":"icon-panel-dialog","modal":true,"width":"860","classes":{"ui-dialog":"icon-panel-dialog"}}\'';
    $form['panel'] = ['#type' => 'container', '#attributes' => ['class' => ['icon-panel', 'icon-panel--hero']]];
    $form['panel']['bar'] = [
      '#markup' => '<div class="icon-panel__bar"><p class="icon-panel__title">' . $this->t('Slides · @count of @max', ['@count' => count($slides), '@max' => self::MAX]) . '</p><a class="icon-panel__button icon-panel__button--primary use-ajax" href="' . $add . '"' . $dialog . '>' . $this->t('+ Add slide') . '</a></div>',
    ];
    $rows = '';
    foreach ($slides as $slide) {
      $media = $slide->get('field_slide_media')->entity;
      $kind = $media ? ($media->bundle() === 'video' ? $this->t('Film') : $this->t('Image')) : $this->t('No media');
      $link = $slide->get('field_slide_link')->first();
      $meta = $kind . ($link ? ' · ' . preg_replace('#^https?://[^/]+#', '', $link->getUrl()->toString()) : '');
      $edit = $slide->toUrl('edit-form', ['query' => ['panel' => 1, 'use_admin_theme' => 1]])->toString();
      $rows .= '<tr class="draggable" data-row="' . $slide->id() . '"><td>' . self::HANDLE
        . '<div class="icon-panel__text"><p class="icon-panel__name">' . htmlspecialchars($slide->label(), ENT_QUOTES) . '</p><p class="icon-panel__meta">' . htmlspecialchars((string) $meta, ENT_QUOTES) . '</p></div></td>'
        . '<td class="icon-panel__cell--action"><a class="icon-panel__action use-ajax" href="' . $edit . '"' . $dialog . '>' . $this->t('Edit') . '</a></td></tr>';
    }
    $form['panel']['card'] = ['#type' => 'container', '#attributes' => ['class' => ['icon-panel__card']]];
    $form['panel']['card']['list'] = [
      '#markup' => $rows
        ? '<table class="icon-panel__list"><tbody>' . $rows . '</tbody></table>'
        : '<p class="icon-panel__note">' . $this->t('No slides yet — add one.') . '</p>',
    ];
    $form['panel']['note'] = [
      '#markup' => '<p class="icon-panel__note">' . $this->t('Drag to reorder — the reel plays top to bottom. Edit opens the slide (film or image, client name, link) over the page; the list refreshes when you save. Films must be 6 seconds long, muted.') . '</p>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $order = $form_state->getValue('order');
    $this->configuration['order'] = is_string($order)
      ? array_values(array_unique(array_filter(array_map('intval', explode(',', $order)))))
      : [];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $slots = [];
    $tags = ['node_list:hero_slide'];
    foreach (array_slice($this->slides(), 0, self::MAX) as $slide) {
      $media = $slide->get('field_slide_media')->entity;
      $source = $media ? icon_site_media_source($media, 'work_media') : [];
      if (!$source) {
        continue;
      }
      $link = $slide->get('field_slide_link')->first();
      $slots[] = [
        '#type' => 'component',
        '#component' => 'icon:hero-slide',
        '#props' => [
          'client' => $slide->label(),
          'url' => $link ? $link->getUrl()->toString() : '',
          $source['type'] === 'video' ? 'video' : 'image' => $source['type'] === 'video'
            ? ['src' => $source['src']]
            : ['src' => $source['src'], 'alt' => $source['alt'] ?? '', 'width' => $source['width'] ?? NULL, 'height' => $source['height'] ?? NULL],
        ],
      ];
      $tags = Cache::mergeTags($tags, $slide->getCacheTags(), $media->getCacheTags());
    }
    if (!$slots) {
      return [];
    }
    return [
      '#type' => 'component',
      '#component' => 'icon:hero',
      '#slots' => ['slides' => $slots],
      '#cache' => ['tags' => $tags],
    ];
  }

}
