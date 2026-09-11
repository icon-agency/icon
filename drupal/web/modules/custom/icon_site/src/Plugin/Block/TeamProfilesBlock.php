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
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The team profiles: a grid of the leadership team, each opening a panel.
 *
 * The people are Team member content, in their Order; this block's one
 * setting is the heading. Its Canvas panel IS the list (user call, Sep
 * 2026: "how do I edit the profiles?"): every member in grid order, drag to
 * reorder (writes the Order field at once), Edit opening the member's form
 * in a dialog over the editor, "+ Add member" the same for a new one. A
 * card is a real link to /about/<slug>; js/team-profiles.js opens the
 * panel and keeps the address in step.
 */
#[Block(
  id: 'icon_team_profiles',
  admin_label: new TranslatableMarkup('Team profiles'),
  category: new TranslatableMarkup('ICON'),
)]
final class TeamProfilesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use PanelListTrait;

  /**
   * The About page's path: where a profile's own address hangs.
   */
  public const string BASE_PATH = '/about';

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
    return ['heading' => 'Leadership team'];
  }

  /**
   * Every Team member the editor may see, published or not, in grid order.
   *
   * @return \Drupal\node\NodeInterface[]
   *   The member nodes.
   */
  private function members(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'team_member')
      ->sort('field_team_weight', 'ASC')
      ->sort('title', 'ASC')
      ->execute();
    return array_values(array_filter($storage->loadMultiple($ids), fn(NodeInterface $n) => $n->access('view')));
  }

  /**
   * {@inheritdoc}
   *
   * The one setting is an input (Canvas round-trips settings through the
   * form's values); the people are content, listed as markup with actions:
   * drag to reorder (posts the order, then reloads), Edit, Add.
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['#attached']['library'][] = 'icon_site/panel_lists';
    $form['heading'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Heading'),
      '#default_value' => $this->configuration['heading'],
      '#description' => $this->t('Above the grid.'),
      '#weight' => 5,
      '#attributes' => ['class' => ['icon-panel__field']],
    ];
    $dialog = self::dialog(860);
    $action = Url::fromRoute('icon_site.team_action')->toString();
    $add = Url::fromRoute('node.add', ['node_type' => 'team_member'], [
      'query' => ['panel' => 1, 'use_admin_theme' => 1],
    ])->toString();
    $thumb = $this->entityTypeManager->getStorage('image_style')->load('thumbnail');
    $rows = '';
    $members = $this->members();
    foreach ($members as $node) {
      $media = $node->get('field_team_photo')->entity;
      $file = $media?->get('field_media_image')->entity;
      $src = $file ? icon_site_file_url($thumb ? $thumb->buildUrl($file->getFileUri()) : $file->getFileUri()) : '';
      $edit = $node->toUrl('edit-form', ['query' => ['panel' => 1, 'use_admin_theme' => 1]])->toString();
      $meta = (string) ($node->get('field_team_role')->value ?? '');
      if (!$node->isPublished()) {
        $meta = $this->t('Unpublished — not in the grid') . ($meta ? ' · ' . $meta : '');
      }
      $rows .= '<tr class="draggable' . ($node->isPublished() ? '' : ' icon-panel__row--off') . '" data-row="' . $node->id() . '"><td>' . self::handle((string) $node->label())
        . ($src ? '<img class="icon-panel__logo icon-panel__photo" src="' . $src . '" alt="">' : '')
        . '<div class="icon-panel__text"><p class="icon-panel__name">' . htmlspecialchars((string) $node->label(), ENT_QUOTES) . '</p><p class="icon-panel__meta">' . htmlspecialchars($meta, ENT_QUOTES) . '</p></div></td>'
        . '<td class="icon-panel__cell--action"><a class="icon-panel__action use-ajax" href="' . $edit . '"' . $dialog . '>' . $this->t('Edit') . '</a></td></tr>';
    }
    $form['panel'] = [
      '#type' => 'container',
      '#weight' => 0,
      '#attributes' => ['class' => ['icon-panel', 'icon-panel--team']],
    ];
    $form['panel']['bar'] = [
      '#markup' => '<div class="icon-panel__bar"><p class="icon-panel__title">' . $this->t('People · @n', ['@n' => count($members)]) . '</p><a class="icon-panel__button icon-panel__button--primary use-ajax" href="' . $add . '"' . $dialog . '>' . $this->t('+ Add member') . '</a></div>',
    ];
    $form['panel']['card'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['icon-panel__card'], 'data-order-url' => $action . '&op=order'],
    ];
    $form['panel']['card']['list'] = [
      '#markup' => $rows ? '<table class="icon-panel__list"><tbody>' . $rows . '</tbody></table>' : '<p class="icon-panel__note">' . $this->t('No team members yet — add one.') . '</p>',
    ];
    $form['panel']['note'] = [
      '#markup' => '<p class="icon-panel__note">' . $this->t('The grid runs these in order — drag to reorder; the order is saved at once. Edit opens the person (name, role, bio, LinkedIn, portrait) over the page; untick Published there to take them out of the grid without deleting them. A published member also has an address of their own, /about/their-name.') . '</p>',
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
    $cache = (new CacheableMetadata())->addCacheContexts(['user.permissions']);
    $members = [];
    foreach (icon_site_team_members($cache) as $node) {
      $photo = $node->get('field_team_photo')->entity;
      $link = $node->get('field_team_linkedin')->first();
      $members[] = [
        'slug' => icon_site_team_slug($node->label()),
        'name' => $node->label(),
        'role' => (string) ($node->get('field_team_role')->value ?? ''),
        // The text format's PROCESSED output, as a string: SDC validates a
        // prop nested in a list strictly (a render array is "an object where
        // a string is required"), unlike a top-level prop, where the prose
        // component gets away with one. The format's filters have already
        // sanitised it, which is what lets the template print it raw.
        'bio' => (string) ($node->get('field_team_bio')->processed ?? ''),
        'linkedin' => $link ? $link->getUrl()->toString() : '',
        'photo' => icon_site_media_source($photo, 'team_portrait', $cache),
      ];
    }
    $build = [
      '#type' => 'component',
      '#component' => 'icon:team-profiles',
      '#props' => [
        'heading' => $this->configuration['heading'] ?: 'Leadership team',
        'base' => self::BASE_PATH,
        'members' => $members,
      ],
      '#attached' => ['library' => ['icon/team-profiles']],
    ];
    $cache->applyTo($build);
    return $build;
  }

}
