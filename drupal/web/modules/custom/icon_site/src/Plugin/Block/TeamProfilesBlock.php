<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The team profiles: a grid of portraits, each opening a panel.
 *
 * Every published Team member (Content → Team members) in Order, rendered
 * by the team-profiles SDC: the grid of name / role / portrait, and the
 * panel that slides in over the page when one is chosen, with the bio and
 * previous / next. The panel has an address of its own — BASE_PATH/<slug>
 * — which js/team-profiles.js writes to the history and
 * TeamProfilePathProcessor accepts on the way in, so a link to a person
 * opens the About page with their panel already open.
 */
#[Block(
  id: 'icon_team_profiles',
  admin_label: new TranslatableMarkup('Team profiles'),
  category: new TranslatableMarkup('ICON'),
)]
final class TeamProfilesBlock extends BlockBase {

  /**
   * The page the panels live under. The About page's alias.
   */
  public const BASE_PATH = '/about';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['heading' => 'Leadership team'];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['heading'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Heading'),
      '#default_value' => $this->configuration['heading'],
      '#description' => $this->t('Above the grid. The people come from Content → Team members, in their Order.'),
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
