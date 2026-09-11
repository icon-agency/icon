<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The filmstrip: photos and fact cards drifting past, on any page.
 *
 * Its panel is the strip (user call, Sep 2026: "an easy way to add images
 * and stats"): the PHOTOS are Image media tagged Intro — a reel of the
 * chosen ones, dragged into order, and the rest under Available with an
 * Add — plus "+ Add photo", which uploads one over the editor; the FACT
 * CARDS are up to six rows of icon, heading and label, typed in place. The
 * strip interleaves them, a fact card after every N photos (N is a
 * setting), and renders the filmstrip SDC with a Photo or Fact card
 * component per card in its slot — the same components the homepage intro
 * places by hand.
 */
#[Block(
  id: 'icon_filmstrip',
  admin_label: new TranslatableMarkup('Filmstrip'),
  category: new TranslatableMarkup('ICON'),
)]
final class FilmstripBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use PanelListTrait;

  /**
   * The image style a card's photo is served at (a 4:5 card up to 26rem).
   */
  public const string STYLE = 'filmstrip_card';

  /**
   * The media Type the panel offers as photos.
   */
  public const string PHOTO_TYPE = 'intro';

  /**
   * Fact card rows in the panel.
   */
  public const int STATS = 6;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'heading' => 'The ICON team',
      'photos' => [],
      'stats' => [],
      'every' => 2,
    ];
  }

  /**
   * The chosen photos, in order, then every other published Intro photo.
   *
   * @return array{0: \Drupal\media\MediaInterface[], 1: \Drupal\media\MediaInterface[]}
   *   The strip's photos keyed by media ID in strip order, and the spares.
   */
  private function photos(): array {
    $storage = $this->entityTypeManager->getStorage('media');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('bundle', ['image', 'video'], 'IN')
      ->condition('status', 1)
      ->condition('field_media_category', self::PHOTO_TYPE)
      ->sort('created', 'DESC')
      ->execute();
    $all = $storage->loadMultiple($ids);
    $chosen = [];
    foreach ($this->configuration['photos'] ?? [] as $id) {
      if (isset($all[$id])) {
        $chosen[$id] = $all[$id];
      }
    }
    return [$chosen, array_diff_key($all, $chosen)];
  }

  /**
   * The Icon media, for the fact cards' icon select, in the list's order.
   *
   * @return array<int, string>
   *   Icon names keyed by media ID.
   */
  private function icons(): array {
    $storage = $this->entityTypeManager->getStorage('media');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('bundle', 'icon')
      ->condition('status', 1)
      ->sort('field_logo_weight', 'ASC')
      ->sort('name', 'ASC')
      ->execute();
    $options = [];
    foreach ($storage->loadMultiple($ids) as $media) {
      $options[(int) $media->id()] = (string) $media->label();
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   *
   * Canvas round-trips the settings through this form's VALUES, so every
   * setting is an input: the photo order is one hidden field written by
   * js/panel-sortable.js from the reel (the list itself is markup), and the
   * fact cards are plain fields.
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    [$chosen, $spare] = $this->photos();
    $form['#attached']['library'][] = 'icon_site/panel_lists';
    $form['order'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Photo order'),
      '#title_display' => 'invisible',
      '#default_value' => implode(',', array_keys($chosen)),
      '#attributes' => ['class' => ['icon-panel__order'], 'autocomplete' => 'off', 'tabindex' => '-1'],
      '#wrapper_attributes' => ['class' => ['icon-panel__hidden']],
    ];
    $add = Url::fromRoute('entity.media.add_form', ['media_type' => 'image'], [
      'query' => ['panel' => 1, 'use_admin_theme' => 1, 'category' => self::PHOTO_TYPE],
    ])->toString();
    // A looping film in the strip (user ask, Sep 2026): a Video media item
    // tagged Intro, uploaded the same way.
    $add_film = Url::fromRoute('entity.media.add_form', ['media_type' => 'video'], [
      'query' => ['panel' => 1, 'use_admin_theme' => 1, 'category' => self::PHOTO_TYPE],
    ])->toString();
    $dialog = self::dialog(760);
    $thumb = $this->entityTypeManager->getStorage('image_style')->load('thumbnail');
    $row = function (MediaInterface $media) use ($dialog, $thumb): string {
      // The media's own thumbnail: the picture itself, a film's poster.
      $file = $media->get('thumbnail')->entity;
      $src = $file ? icon_site_file_url($thumb ? $thumb->buildUrl($file->getFileUri()) : $file->getFileUri()) : '';
      $edit = $media->toUrl('edit-form', ['query' => ['panel' => 1, 'use_admin_theme' => 1]])->toString();
      $name = htmlspecialchars((string) $media->label(), ENT_QUOTES);
      return '<tr class="draggable" data-row="' . $media->id() . '"><td>' . self::handle((string) $media->label())
        . ($src ? '<img class="icon-panel__logo icon-panel__photo" src="' . $src . '" alt="">' : '')
        . '<div class="icon-panel__text"><p class="icon-panel__name">' . $name . '</p></div></td>'
        . '<td class="icon-panel__cell--action">'
        . '<a href="#" role="button" class="icon-panel__action icon-panel__reel-add" aria-label="' . htmlspecialchars((string) $this->t('Add @name to the strip', ['@name' => $media->label()]), ENT_QUOTES) . '">' . $this->t('Add to strip') . '</a>'
        . '<a class="icon-panel__action use-ajax" href="' . $edit . '"' . $dialog . '>' . $this->t('Edit') . '</a>'
        . '</td></tr>';
    };
    $reel = implode('', array_map($row, array_values($chosen)));
    $spares = implode('', array_map($row, array_values($spare)));
    $form['panel'] = [
      '#type' => 'container',
      '#weight' => 0,
      '#attributes' => ['class' => ['icon-panel', 'icon-panel--filmstrip', 'icon-panel--reel']],
    ];
    $form['panel']['bar'] = [
      '#markup' => '<div class="icon-panel__bar"><p class="icon-panel__title">' . $this->t('Photos · @count of @max', [
        '@count' => count($chosen),
        '@max' => count($chosen) + count($spare),
      ]) . '</p><span class="icon-panel__buttons"><a class="icon-panel__button icon-panel__button--primary use-ajax" href="' . $add . '"' . $dialog . '>' . $this->t('+ Add photo') . '</a> <a class="icon-panel__button use-ajax" href="' . $add_film . '"' . $dialog . '>' . $this->t('+ Add film') . '</a></span></div>',
    ];
    $form['panel']['card'] = ['#type' => 'container', '#attributes' => ['class' => ['icon-panel__card']]];
    $form['panel']['card']['list'] = [
      '#markup' => '<table class="icon-panel__list icon-panel__list--reel"><tbody>' . $reel . '</tbody></table>'
      . '<p class="icon-panel__note icon-panel__empty-note' . ($reel ? ' is-hidden' : '') . '">' . $this->t('No photos in the strip yet — add one below, or upload a new one.') . '</p>',
    ];
    $form['panel']['spare'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['icon-panel__group'],
        'data-group' => 'available',
      ] + ($spares ? [] : ['hidden' => 'hidden']),
    ];
    $form['panel']['spare']['title'] = ['#markup' => '<p class="icon-panel__group-title">' . $this->t('Available — Intro photos and films not in this strip') . '</p>'];
    $form['panel']['spare']['card'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['icon-panel__card', 'icon-panel__card--spare']],
    ];
    $form['panel']['spare']['card']['list'] = ['#markup' => '<table class="icon-panel__list icon-panel__list--available"><tbody>' . $spares . '</tbody></table>'];
    $form['panel']['note'] = [
      '#markup' => '<p class="icon-panel__note">' . $this->t('The strip runs these in order — drag to reorder. Add photo or Add film uploads a new one, tagged Intro, straight into the strip; any image or video tagged Intro in the media library is offered under Available. A film loops, muted.') . '</p>',
    ];

    $form['every'] = [
      '#type' => 'number',
      '#title' => $this->t('A fact card after every'),
      '#field_suffix' => $this->t('photos'),
      '#default_value' => (int) ($this->configuration['every'] ?? 2),
      '#min' => 1,
      '#max' => 8,
      '#weight' => 2,
      '#attributes' => ['class' => ['icon-panel__field']],
    ];
    $icons = $this->icons();
    $form['stats'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#weight' => 3,
      '#attributes' => ['class' => ['icon-panel', 'icon-panel--stats']],
    ];
    $form['stats']['#prefix'] = '<p class="icon-panel__group-title">' . $this->t('Fact cards') . '</p>';
    $stats = array_values($this->configuration['stats'] ?? []);
    for ($i = 0; $i < self::STATS; $i++) {
      $stat = $stats[$i] ?? [];
      $form['stats'][$i] = [
        '#type' => 'details',
        '#title' => $stat['title'] ?? '' ? $stat['title'] : $this->t('Fact card @n', ['@n' => $i + 1]),
        '#open' => (bool) ($stat['title'] ?? ''),
      ];
      $form['stats'][$i]['icon'] = [
        '#type' => 'select',
        '#title' => $this->t('Icon'),
        '#options' => $icons,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => (int) ($stat['icon'] ?? 0) ?: '',
        '#description' => $i === 0 ? $this->t('The site\'s icon set — add to it at <a href=":url" target="_blank" rel="noopener">Content → Fact icons</a>.', [':url' => Url::fromRoute('icon_site.fact_icons')->toString()]) : NULL,
      ];
      $form['stats'][$i]['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Heading'),
        '#default_value' => $stat['title'] ?? '',
        '#maxlength' => 80,
        '#placeholder' => $this->t('14 Agency of the Year awards'),
      ];
      $form['stats'][$i]['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => $stat['label'] ?? '',
        '#maxlength' => 80,
        '#placeholder' => $this->t('Since 2021'),
      ];
    }
    $form['heading'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Accessible label'),
      '#description' => $this->t('Read by screen readers as the name of the strip; not shown.'),
      '#default_value' => $this->configuration['heading'] ?? 'The ICON team',
      '#maxlength' => 128,
      '#required' => TRUE,
      '#weight' => 5,
      '#attributes' => ['class' => ['icon-panel__field']],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $order = $form_state->getValue('order');
    $this->configuration['photos'] = is_string($order)
      ? array_values(array_unique(array_filter(array_map('intval', explode(',', $order)))))
      : [];
    $stats = [];
    foreach (is_array($form_state->getValue('stats')) ? $form_state->getValue('stats') : [] as $row) {
      $title = trim((string) ($row['title'] ?? ''));
      if ($title === '') {
        continue;
      }
      $stats[] = [
        'icon' => (int) ($row['icon'] ?? 0),
        'title' => $title,
        'label' => trim((string) ($row['label'] ?? '')),
      ];
    }
    $this->configuration['stats'] = $stats;
    $this->configuration['every'] = max(1, min(8, (int) $form_state->getValue('every')));
    $this->configuration['heading'] = trim((string) $form_state->getValue('heading')) ?: 'The ICON team';
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $cache = (new CacheableMetadata())->addCacheContexts(['user.permissions']);
    $storage = $this->entityTypeManager->getStorage('media');
    $photos = [];
    foreach ($storage->loadMultiple($this->configuration['photos'] ?? []) as $id => $media) {
      $source = icon_site_media_source($media, self::STYLE, $cache);
      if ($source) {
        $photos[$id] = $source;
      }
    }
    // The configured order, not the storage's.
    $photos = array_replace(array_intersect_key(array_flip($this->configuration['photos'] ?? []), $photos), $photos);
    $stats = array_values($this->configuration['stats'] ?? []);
    foreach ($stats as $stat) {
      if (($icon = $storage->load($stat['icon'] ?? 0)) instanceof MediaInterface) {
        $cache->addCacheableDependency($icon);
      }
    }
    $every = max(1, (int) ($this->configuration['every'] ?? 2));
    $cards = [];
    $next = 0;
    $fact = static fn(array $stat): array => [
      '#type' => 'component',
      '#component' => 'icon:intro-fact',
      '#props' => [
        'icon' => (int) ($stat['icon'] ?? 0),
        'title' => (string) $stat['title'],
        'label' => (string) ($stat['label'] ?? ''),
      ],
    ];
    $n = 0;
    foreach ($photos as $source) {
      // A film is its own prop (the component's image prop cannot carry
      // one); a picture as before.
      $props = ($source['type'] ?? '') === 'video'
        ? ['film' => array_filter(['src' => $source['src'], 'poster' => $source['poster'] ?? NULL])]
        : [
          'image' => [
            'src' => $source['src'],
            'alt' => $source['alt'] ?? '',
            'width' => $source['width'] ?? NULL,
            'height' => $source['height'] ?? NULL,
          ],
        ];
      $cards[] = [
        '#type' => 'component',
        '#component' => 'icon:intro-photo',
        '#props' => $props,
      ];
      if (++$n % $every === 0 && isset($stats[$next])) {
        $cards[] = $fact($stats[$next++]);
      }
    }
    while (isset($stats[$next])) {
      $cards[] = $fact($stats[$next++]);
    }
    $build = $cards ? [
      '#type' => 'component',
      '#component' => 'icon:filmstrip',
      '#props' => ['label' => $this->configuration['heading'] ?: 'The ICON team'],
      '#slots' => ['cards' => $cards],
    ] : [];
    $cache->applyTo($build);
    return $build;
  }

}
