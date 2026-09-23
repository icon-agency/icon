<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The "Our expertise" list, fixed.
 *
 * The links are the EXPERTISE item's children in the main menu — the
 * services the navigation's drawer shows, less its "Our approach" row to
 * the Expertise page itself — so the list lives in one place
 * (user call, 23 Sep 2026: "this section is more of a one off … set it up
 * as a fixed component"). Rendered by the expertise-links SDC with an
 * Expertise link item per child; the settings are the label that locks
 * beside the list and whether it stands on the page gutter.
 */
#[Block(
  id: 'icon_expertise_links',
  admin_label: new TranslatableMarkup('Expertise links'),
  category: new TranslatableMarkup('ICON'),
)]
final class ExpertiseLinksBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly MenuLinkTreeInterface $menuTree,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('menu.link_tree'));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['heading' => 'Our expertise', 'gutter' => TRUE];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['heading'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#description' => $this->t('The words that lock beside the list.'),
      '#default_value' => $this->configuration['heading'] ?? 'Our expertise',
      '#maxlength' => 64,
      '#required' => TRUE,
    ];
    $form['gutter'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('On the page gutter'),
      '#description' => $this->t('On, when the list stands on the page like any block. Off inside the intro band or a box, which pad it already.'),
      '#default_value' => !empty($this->configuration['gutter']),
    ];
    $form['note'] = [
      '#markup' => '<p class="icon-panel__note">' . $this->t('The links are the Expertise item\'s children in the <a href=":url" target="_blank" rel="noopener">main menu</a> — the same five the navigation shows. Edit them there and every placement follows.', [
        ':url' => Url::fromRoute('entity.menu.edit_form', ['menu' => 'main'])->toString(),
      ]) . '</p>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['heading'] = trim((string) $form_state->getValue('heading')) ?: 'Our expertise';
    $this->configuration['gutter'] = (bool) $form_state->getValue('gutter');
  }

  /**
   * The Expertise item's children: [label, url] pairs, in menu order.
   */
  protected function links(): array {
    $tree = $this->menuTree->load('main', (new MenuTreeParameters())->setMaxDepth(2)->onlyEnabledLinks());
    $tree = $this->menuTree->transform($tree, [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);
    foreach ($tree as $element) {
      if (!$element->access?->isAllowed()) {
        continue;
      }
      $title = strtolower(trim((string) $element->link->getTitle()));
      if ($title !== 'expertise' && $element->link->getUrlObject()->toString() !== '/expertise') {
        continue;
      }
      $links = [];
      $own = $element->link->getUrlObject()->toString();
      foreach ($element->subtree as $child) {
        if (!$child->access?->isAllowed()) {
          continue;
        }
        $url = $child->link->getUrlObject()->toString();
        // The drawer's overview row ("Our approach", to the Expertise page
        // itself) is the navigation's, not a service: the list skips it.
        if ($url === $own || $url === '/expertise') {
          continue;
        }
        $links[] = [(string) $child->link->getTitle(), $url];
      }
      return $links;
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $items = [];
    foreach ($this->links() as [$label, $url]) {
      $items[] = [
        '#type' => 'component',
        '#component' => 'icon:intro-expertise',
        '#props' => ['label' => $label, 'url' => $url],
      ];
    }
    return [
      '#type' => 'component',
      '#component' => 'icon:expertise-links',
      '#props' => [
        'label' => $this->configuration['heading'] ?? 'Our expertise',
        'gutter' => !empty($this->configuration['gutter']),
      ],
      '#slots' => ['links' => $items],
      '#cache' => ['tags' => ['config:system.menu.main'], 'contexts' => ['user.permissions']],
    ];
  }

}
