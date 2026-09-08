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
   * The row's reorder handle: dragged by pointer, moved by keyboard (the
   * arrow keys, Home and End — js/panel-sortable.js), so it is a real,
   * focusable control named for its row. No colon in the label: the admin
   * markup filter reads "Name:" as a URL scheme and strips it.
   */
  private static function handle(string $name): string {
    $label = htmlspecialchars((string) t('Reorder @name — arrow keys move it up or down', ['@name' => $name]), ENT_QUOTES);
    return '<a class="icon-panel__handle" role="button" href="#" aria-label="' . $label . '" title="' . htmlspecialchars((string) t('Drag, or use the arrow keys, to reorder'), ENT_QUOTES) . '"></a>';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['heading' => 'Selected clients'];
  }

  /**
   * {@inheritdoc}
   *
   * The one setting is an input (Canvas round-trips settings through the
   * form's values); the logos are content, listed as markup with actions:
   * drag to reorder (writes the Order field), Edit / Remove, Add.
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['#attached']['library'][] = 'icon_site/panel_lists';
    $form['heading'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Accessible label'),
      '#description' => $this->t('Read by screen readers as the name of this section; not shown.'),
      '#default_value' => $this->configuration['heading'] ?? 'Selected clients',
      '#maxlength' => 128,
      '#required' => TRUE,
      '#weight' => 5,
      '#attributes' => ['class' => ['icon-panel__field']],
    ];
    $dialog = ' data-dialog-type="dialog" data-dialog-options=\'{"target":"icon-panel-dialog","modal":true,"width":"760","classes":{"ui-dialog":"icon-panel-dialog"}}\'';
    $action = Url::fromRoute('icon_site.logo_action')->toString();
    $add = Url::fromRoute('entity.media.add_form', ['media_type' => 'logo'], ['query' => ['panel' => 1, 'use_admin_theme' => 1]])->toString();
    $storage = \Drupal::entityTypeManager()->getStorage('media');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('bundle', 'logo')->condition('status', 1)->sort('field_logo_weight', 'ASC')->sort('name', 'ASC')->execute();
    $rows = '';
    foreach ($storage->loadMultiple($ids) as $media) {
      $file = $media->get('field_media_file')->entity;
      $src = $file ? \Drupal::service('file_url_generator')->generateString($file->getFileUri()) : '';
      $edit = $media->toUrl('edit-form', ['query' => ['panel' => 1, 'use_admin_theme' => 1]])->toString();
      $rows .= '<tr class="draggable" data-row="' . $media->id() . '"><td>' . self::handle((string) $media->label())
        . ($src ? '<img class="icon-panel__logo" src="' . $src . '" alt="">' : '')
        . '<div class="icon-panel__text"><p class="icon-panel__name">' . htmlspecialchars($media->label(), ENT_QUOTES) . '</p><p class="icon-panel__meta">' . htmlspecialchars((string) ($file ? strtoupper(pathinfo($file->getFilename(), PATHINFO_EXTENSION)) : ''), ENT_QUOTES) . '</p></div></td>'
        . '<td class="icon-panel__cell--action"><a class="icon-panel__action use-ajax" href="' . $edit . '"' . $dialog . '>' . $this->t('Edit') . '</a></td></tr>';
    }
    $count = substr_count($rows, '<tr ');
    $form['panel'] = ['#type' => 'container', '#weight' => 0, '#attributes' => ['class' => ['icon-panel', 'icon-panel--clients']]];
    $form['panel']['bar'] = [
      '#markup' => '<div class="icon-panel__bar"><p class="icon-panel__title">' . $this->t('Logos · @n', ['@n' => $count]) . '</p><a class="icon-panel__button icon-panel__button--primary use-ajax" href="' . $add . '"' . $dialog . '>' . $this->t('+ Add logo') . '</a></div>',
    ];
    $form['panel']['card'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['icon-panel__card'], 'data-order-url' => $action . '&op=order'],
    ];
    $form['panel']['card']['list'] = [
      '#markup' => $rows ? '<table class="icon-panel__list"><tbody>' . $rows . '</tbody></table>' : '<p class="icon-panel__note">' . $this->t('No logos yet — add one.') . '</p>',
    ];
    $form['panel']['note'] = [
      '#markup' => '<p class="icon-panel__note">' . $this->t('The marquee runs these in order. Drag to reorder — the order is saved at once. The name is the alt text screen readers announce. Edit replaces the file (SVG or PNG), renames it, or removes it from the marquee. The same list is at <a href=":url" target="_blank" rel="noopener">Content → Client logos</a>.', [':url' => Url::fromRoute('icon_site.client_logos')->toString()]) . '</p>',
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
      // empty, but tagged: the first logo invalidates this result
      return ['#cache' => ['tags' => $tags]];
    }
    return [
      '#type' => 'component',
      '#component' => 'icon:clients',
      '#props' => ['logos' => $logos, 'heading' => $this->configuration['heading'] ?: 'Selected clients'],
      '#cache' => ['tags' => $tags],
    ];
  }

}
