<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * One office, open, on any page — chosen in the panel.
 *
 * The same row the Offices: all block lays out for every office
 * (Content → Offices, through icon_site_offices()), one at a time, so an
 * editor composes offices with Columns (user ask, Sep 2026: "a single
 * office, with the ability to select the office; I can use columns").
 */
#[Block(
  id: 'icon_office',
  admin_label: new TranslatableMarkup('Offices: one'),
  category: new TranslatableMarkup('ICON'),
)]
final class OfficeBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['office' => 0] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $options = [];
    foreach (icon_site_offices()['offices'] as $office) {
      $options[$office['nid']] = $office['city'] . ($office['place'] !== '' ? ' — ' . $office['place'] : '');
    }
    $form['office'] = [
      '#type' => 'select',
      '#title' => $this->t('Office'),
      '#options' => $options,
      '#default_value' => (int) ($this->configuration['office'] ?? 0),
      '#empty_option' => $this->t('- Choose -'),
      '#description' => $this->t('The offices are Content → Offices; add, edit or reorder them there.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['office'] = (int) $form_state->getValue('office');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $offices = icon_site_offices();
    $nid = (int) ($this->configuration['office'] ?? 0);
    $rows = array_values(array_filter($offices['offices'], static fn(array $o): bool => $o['nid'] === $nid));
    $build = [
      '#type' => 'component',
      '#component' => 'icon:office-list',
      '#props' => ['offices' => $rows],
    ];
    (new CacheableMetadata())->addCacheTags($offices['tags'])->applyTo($build);
    return $build;
  }

}
