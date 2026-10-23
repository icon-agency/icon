<?php

declare(strict_types=1);

namespace Drupal\icon_site\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\icon_site\Plugin\Block\HeroBlock;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Content → Homepage hero: the reel as one list.
 *
 * The hero is baked into the front page (23 Oct 2026); this is where its
 * reel is managed — every published Hero slide in one table, a tick for
 * the ones on the reel, drag to order them. The login page and the
 * footer's mask play the same reel. Edit opens the slide; Add slide makes
 * a new one (ticked onto the reel when saved, by the form's own default).
 *
 * @phpstan-consistent-constructor
 */
final class HeroReelForm extends FormBase {

  public function __construct(protected readonly EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'icon_site_hero_reel';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $order = icon_site_hero_order()['order'];
    $reel = icon_site_hero_slides($order);
    $spare = icon_site_hero_available($order);
    $here = Url::fromRoute('icon_site.hero_reel')->toString();

    $form['help'] = ['#markup' => '<p>' . $this->t('The homepage hero plays the ticked slides, top to bottom — drag the handles to order them, up to @max. An unticked slide stays here, ready to add back. The login page and the footer’s ICON mask play the same reel. <em>Edit</em> opens the slide: its film or image, client name and link. Films must be 6 seconds long and muted; the reel loops them.', ['@max' => HeroBlock::MAX]) . '</p>'];
    $form['slides'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('On the reel'),
        $this->t('Slide'),
        $this->t('Media'),
        $this->t('Link'),
        $this->t('Operations'),
        $this->t('Weight'),
      ],
      '#empty' => $this->t('No hero slides yet — add one with the button above.'),
      '#tabledrag' => [['action' => 'order', 'relationship' => 'sibling', 'group' => 'slide-weight']],
    ];
    $weight = 0;
    $rows = $reel + $spare;
    $delta = max(10, count($rows));
    foreach ($rows as $slide) {
      if (!$slide instanceof NodeInterface) {
        continue;
      }
      $media = $slide->get('field_slide_media')->entity;
      $kind = $media ? ($media->bundle() === 'video' ? $this->t('Film') : $this->t('Image')) : $this->t('No media');
      $link = $slide->get('field_slide_link')->first();
      $form['slides'][$slide->id()] = [
        '#attributes' => ['class' => ['draggable']],
        '#weight' => $weight,
        'on' => [
          '#type' => 'checkbox',
          '#title' => $this->t('@name on the reel', ['@name' => $slide->label()]),
          '#title_display' => 'invisible',
          '#default_value' => isset($reel[$slide->id()]),
        ],
        'name' => ['#markup' => '<strong>' . $slide->toLink()->toString() . '</strong>'],
        'media' => ['#markup' => $media ? $kind . ' · ' . $media->label() : $kind],
        'link' => ['#markup' => $link ? preg_replace('#^https?://[^/]+#', '', $link->getUrl()->toString()) : ''],
        'operations' => [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => $slide->toUrl('edit-form', ['query' => ['destination' => $here]]),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => $slide->toUrl('delete-form', ['query' => ['destination' => $here]]),
            ],
          ],
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @name', ['@name' => $slide->label()]),
          '#title_display' => 'invisible',
          '#default_value' => $weight,
          '#delta' => $delta,
          '#attributes' => ['class' => ['slide-weight']],
        ],
      ];
      $weight++;
    }
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save the reel'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $rows = $form_state->getValue('slides') ?: [];
    uasort($rows, fn(array $a, array $b) => (int) $a['weight'] <=> (int) $b['weight']);
    $order = [];
    foreach ($rows as $id => $row) {
      if (!empty($row['on'])) {
        $order[] = (int) $id;
      }
    }
    if (count($order) > HeroBlock::MAX) {
      $this->messenger()->addWarning($this->t('The reel takes @max slides; the first @max ticked are on it.', ['@max' => HeroBlock::MAX]));
      $order = array_slice($order, 0, HeroBlock::MAX);
    }
    icon_site_hero_order_set($order);
    $this->messenger()->addStatus($this->t('The reel has been saved: @count slide(s).', ['@count' => count($order)]));
  }

}
