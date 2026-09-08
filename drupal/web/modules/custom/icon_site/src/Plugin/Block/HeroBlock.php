<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Block;

use Drupal\node\NodeInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * The homepage hero.
 *
 * Its slides are Hero slide content (a film or image, the client name, a
 * link) — added and edited on their own forms — and this block's one setting
 * is their ORDER, set by dragging the list in the Canvas panel. A slide not
 * yet in the order is appended, newest last, so a new slide shows straight
 * away. Renders the hero SDC with a Hero slide component per slide in its
 * slot.
 */
#[Block(
  id: 'icon_hero',
  admin_label: new TranslatableMarkup('Homepage hero'),
  category: new TranslatableMarkup('ICON'),
)]
final class HeroBlock extends BlockBase {

  /**
   * The row's reorder handle.
   *
   * Dragged by pointer, moved by keyboard (the arrow keys, Home and End —
   * js/panel-sortable.js), so it is a real, focusable control named for its
   * row. No colon in the label: the admin markup filter reads "Name:" as a
   * URL scheme and strips it.
   */
  private static function handle(string $name): string {
    $label = htmlspecialchars((string) t('Reorder @name — arrow keys move it up or down', ['@name' => $name]), ENT_QUOTES);
    return '<a class="icon-panel__handle" role="button" href="#" aria-label="' . $label . '" title="' . htmlspecialchars((string) t('Drag, or use the arrow keys, to reorder'), ENT_QUOTES) . '"></a>';
  }

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
   *   The slide nodes, keyed by node ID, in reel order.
   */
  private function slides(): array {
    return icon_site_hero_slides($this->configuration['order'] ?? []);
  }

  /**
   * Published slides this hero has not chosen — offered by the panel.
   *
   * @return \Drupal\node\NodeInterface[]
   *   The slide nodes not on the reel, oldest first.
   */
  private function available(): array {
    return icon_site_hero_available($this->configuration['order'] ?? []);
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
      '#markup' => '<div class="icon-panel__bar"><p class="icon-panel__title">' . $this->t('Slides · @count of @max', [
        '@count' => count($slides),
        '@max' => self::MAX,
      ]) . '</p><a class="icon-panel__button icon-panel__button--primary use-ajax" href="' . $add . '"' . $dialog . '>' . $this->t('+ Add slide') . '</a></div>',
    ];
    $row = function (NodeInterface $slide) use ($dialog): string {
      $media = $slide->get('field_slide_media')->entity;
      $kind = $media ? ($media->bundle() === 'video' ? $this->t('Film') : $this->t('Image')) : $this->t('No media');
      $link = $slide->get('field_slide_link')->first();
      $meta = $kind . ($link ? ' · ' . preg_replace('#^https?://[^/]+#', '', $link->getUrl()->toString()) : '');
      $edit = $slide->toUrl('edit-form', ['query' => ['panel' => 1, 'use_admin_theme' => 1]])->toString();
      $name = htmlspecialchars((string) $slide->label(), ENT_QUOTES);
      return '<tr class="draggable" data-row="' . $slide->id() . '"><td>' . self::handle((string) $slide->label())
        . '<div class="icon-panel__text"><p class="icon-panel__name">' . $name . '</p><p class="icon-panel__meta">' . htmlspecialchars((string) $meta, ENT_QUOTES) . '</p></div></td>'
        . '<td class="icon-panel__cell--action">'
        . '<a href="#" role="button" class="icon-panel__action icon-panel__reel-add" aria-label="' . htmlspecialchars((string) $this->t('Add @name to the reel', ['@name' => $slide->label()]), ENT_QUOTES) . '">' . $this->t('Add to reel') . '</a>'
        . '<a class="icon-panel__action use-ajax" href="' . $edit . '"' . $dialog . '>' . $this->t('Edit') . '</a>'
        . '</td></tr>';
    };
    $reel = implode('', array_map($row, array_values($slides)));
    $available = $this->available();
    $spare = implode('', array_map($row, array_values($available)));
    $form['panel']['card'] = ['#type' => 'container', '#attributes' => ['class' => ['icon-panel__card']]];
    $form['panel']['card']['list'] = [
      '#markup' => '<table class="icon-panel__list icon-panel__list--reel"><tbody>' . $reel . '</tbody></table>'
        // A class, not the hidden attribute — the admin markup filter drops it.
      . '<p class="icon-panel__note icon-panel__empty-note' . ($reel ? ' is-hidden' : '') . '">' . $this->t('Nothing on the reel yet — add a slide below, or make a new one.') . '</p>',
    ];
    $form['panel']['spare'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['icon-panel__group'],
        'data-group' => 'available',
      ] + ($spare ? [] : ['hidden' => 'hidden']),
    ];
    $form['panel']['spare']['title'] = ['#markup' => '<p class="icon-panel__group-title">' . $this->t('Available — not on this reel') . '</p>'];
    $form['panel']['spare']['card'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['icon-panel__card', 'icon-panel__card--spare']],
    ];
    $form['panel']['spare']['card']['list'] = ['#markup' => '<table class="icon-panel__list icon-panel__list--available"><tbody>' . $spare . '</tbody></table>'];
    $form['panel']['note'] = [
      '#markup' => '<p class="icon-panel__note">' . $this->t('The reel plays top to bottom — drag to reorder. Edit opens the slide (film or image, client name, link) over the page, with Remove from reel — the slide stays under Available, ready to add back. A new slide goes straight onto the reel. Films must be 6 seconds long, muted.') . '</p>',
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
    $reel = icon_site_hero_reel($this->configuration['order'] ?? []);
    $slots = [];
    foreach ($reel['slides'] as $slide) {
      $source = $slide['source'];
      $slots[] = [
        '#type' => 'component',
        '#component' => 'icon:hero-slide',
        '#props' => [
          'client' => $slide['client'],
          'url' => $slide['url'],
          $source['type'] === 'video' ? 'video' : 'image' => $source['type'] === 'video'
            ? ['src' => $source['src'], 'poster' => $source['poster'] ?? '']
            : [
              'src' => $source['src'],
              'alt' => $source['alt'] ?? '',
              'width' => $source['width'] ?? NULL,
              'height' => $source['height'] ?? NULL,
            ],
        ],
      ];
    }
    $build = $slots ? ['#type' => 'component', '#component' => 'icon:hero', '#slots' => ['slides' => $slots]] : [];
    // The reel's full cacheability — tags, the slides' and media's access
    // contexts, max-age — on the result, empty or not (the first slide
    // added invalidates an empty one)
    $reel['cache']->applyTo($build);
    return $build;
  }

}
