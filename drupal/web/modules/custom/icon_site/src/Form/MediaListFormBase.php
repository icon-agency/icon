<?php

declare(strict_types=1);

namespace Drupal\icon_site\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * One media bundle as one list: drag to reorder (a weight field), rename
 * inline (the name is the alt text / label), Edit (replace the file or
 * rename), Delete, add from the local action at the top. The client logos
 * and the fact-card icons are both this.
 */
abstract class MediaListFormBase extends FormBase {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_url_generator'),
    );
  }

  /** The media bundle listed. */
  abstract protected function bundle(): string;

  /** The bundle's file source field. */
  abstract protected function fileField(): string;

  /** The bundle's order field. */
  abstract protected function weightField(): string;

  /** The route of this list (operations come back here). */
  abstract protected function routeName(): string;

  /** The intro paragraph. */
  abstract protected function help(): string;

  /** Column label for the name (e.g. "Alt text"). */
  abstract protected function nameLabel(): string;

  /** Column label for the file (e.g. "Logo"). */
  abstract protected function fileLabel(): string;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $storage = $this->entityTypeManager->getStorage('media');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('bundle', $this->bundle())
      ->sort($this->weightField(), 'ASC')
      ->sort('name', 'ASC')
      ->execute();
    /** @var \Drupal\media\MediaInterface[] $items */
    $items = $storage->loadMultiple($ids);
    $here = Url::fromRoute($this->routeName())->toString();

    $form['help'] = ['#markup' => '<p>' . $this->help() . '</p>'];
    $form['items'] = [
      '#type' => 'table',
      '#header' => [$this->fileLabel(), $this->nameLabel(), $this->t('Operations'), $this->t('Weight')],
      '#empty' => $this->t('Nothing here yet — add one with the button above.'),
      '#tabledrag' => [[
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'item-weight',
      ]],
    ];
    $delta = max(10, count($items));
    $weight = 0;
    foreach ($items as $item) {
      $file = $item->get($this->fileField())->entity;
      $form['items'][$item->id()] = [
        '#attributes' => ['class' => ['draggable']],
        '#weight' => $weight,
        'file' => $file ? [
          '#type' => 'html_tag',
          '#tag' => 'img',
          '#attributes' => [
            'src' => $this->fileUrlGenerator->generateString($file->getFileUri()),
            'alt' => '',
            // Fixed box + contain: an SVG without its own width/height would
            // otherwise render at 0px; the faint ground keeps white marks visible.
            'style' => 'width: 120px; height: 48px; object-fit: contain; background: #eee; padding: 4px; box-sizing: border-box;',
          ],
        ] : ['#markup' => $this->t('(no file)')],
        'name' => [
          '#type' => 'textfield',
          '#title' => $this->nameLabel(),
          '#title_display' => 'invisible',
          '#default_value' => $item->label(),
          '#size' => 40,
          '#maxlength' => 255,
          '#required' => TRUE,
        ],
        'operations' => [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => $item->toUrl('edit-form', ['query' => ['destination' => $here]]),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => $item->toUrl('delete-form', ['query' => ['destination' => $here]]),
            ],
          ],
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @name', ['@name' => $item->label()]),
          '#title_display' => 'invisible',
          '#default_value' => $weight,
          '#delta' => $delta,
          '#attributes' => ['class' => ['item-weight']],
        ],
      ];
      $weight++;
    }
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save order and names'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $storage = $this->entityTypeManager->getStorage('media');
    $rows = $form_state->getValue('items') ?: [];
    uasort($rows, fn(array $a, array $b) => (int) $a['weight'] <=> (int) $b['weight']);
    $position = 0;
    foreach ($rows as $id => $row) {
      $item = $storage->load($id);
      if (!$item instanceof MediaInterface) {
        continue;
      }
      $name = trim((string) $row['name']);
      if ((int) $item->get($this->weightField())->value !== $position || $item->label() !== $name) {
        $item->set($this->weightField(), $position);
        $item->setName($name);
        $item->save();
      }
      $position++;
    }
    $this->messenger()->addStatus($this->t('Saved.'));
  }

}
