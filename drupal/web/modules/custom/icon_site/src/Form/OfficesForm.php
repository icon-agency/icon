<?php

declare(strict_types=1);

namespace Drupal\icon_site\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Url;
use Drupal\icon_site\Twig\IconSiteExtension;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The global footer's offices as one list (Content → Offices).
 *
 * Drag to reorder (field_office_weight), Edit opens the office, Delete
 * removes it, Add office is the local action at the top. An unpublished
 * office stays in the list, marked, and off the footer.
 */
final class OfficesForm extends FormBase {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly StreamWrapperManagerInterface $streamWrapperManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('stream_wrapper_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'icon_site_offices';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'office')
      ->sort('field_office_weight', 'ASC')
      ->sort('title', 'ASC')
      ->execute();
    /** @var \Drupal\node\NodeInterface[] $offices */
    $offices = $storage->loadMultiple($ids);
    $here = Url::fromRoute('icon_site.offices')->toString();
    $svg = new IconSiteExtension($this->entityTypeManager, $this->streamWrapperManager);

    $form['help'] = [
      '#markup' => '<p>' . $this->t('The offices in the global footer, top to bottom. Drag to reorder and save; <em>Edit</em> changes the city, place name, address, phone, email or icon; <em>Delete</em> closes one. The icons are the site\'s <a href=":icons">Icons</a> — pick one on the office, or add a new SVG there. The footer\'s words and social links are on <a href=":footer">Footer</a>.', [
        ':icons' => Url::fromRoute('icon_site.fact_icons')->toString(),
        ':footer' => Url::fromRoute('icon_site.footer')->toString(),
      ]) . '</p>',
    ];
    $form['items'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Icon'),
        $this->t('City'),
        $this->t('Place'),
        $this->t('Contact'),
        $this->t('Operations'),
        $this->t('Weight'),
      ],
      '#empty' => $this->t('No offices yet — add one with the button above.'),
      '#tabledrag' => [['action' => 'order', 'relationship' => 'sibling', 'group' => 'item-weight']],
    ];
    $delta = max(10, count($offices));
    $weight = 0;
    foreach ($offices as $office) {
      $icon = $office->get('field_office_icon')->target_id;
      $markup = $icon ? $svg->inlineSvg((string) $icon, '') : '';
      $form['items'][$office->id()] = [
        '#attributes' => ['class' => ['draggable']],
        '#weight' => $weight,
        'icon' => ['#markup' => Markup::create('<span style="display:inline-block;width:32px;height:32px;color:#222">' . $markup . '</span>')],
        'city' => ['#markup' => htmlspecialchars($office->label(), ENT_QUOTES) . ($office->isPublished() ? '' : ' <em>(' . $this->t('not shown — unpublished') . ')</em>')],
        'place' => ['#plain_text' => (string) $office->get('field_office_place')->value],
        'contact' => ['#plain_text' => trim((string) $office->get('field_office_phone')->value . ' · ' . $office->get('field_office_email')->value, ' ·')],
        'operations' => [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => $office->toUrl('edit-form', ['query' => ['destination' => $here]]),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => $office->toUrl('delete-form', ['query' => ['destination' => $here]]),
            ],
          ],
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @name', ['@name' => $office->label()]),
          '#title_display' => 'invisible',
          '#default_value' => $weight,
          '#delta' => $delta,
          '#attributes' => ['class' => ['item-weight']],
        ],
      ];
      $weight++;
    }
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Save order'), '#button_type' => 'primary'];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $rows = $form_state->getValue('items') ?: [];
    uasort($rows, fn(array $a, array $b) => (int) $a['weight'] <=> (int) $b['weight']);
    $position = 0;
    foreach (array_keys($rows) as $id) {
      $office = $storage->load($id);
      if ($office instanceof NodeInterface && $office->access('update') && (int) $office->get('field_office_weight')->value !== $position) {
        $office->set('field_office_weight', $position)->save();
      }
      $position++;
    }
    $this->messenger()->addStatus($this->t('Saved.'));
  }

}
