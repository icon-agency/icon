<?php

/**
 * @file
 * Post-update hooks for the ICON site module.
 */

declare(strict_types=1);

/**
 * Carries the footer's words from config into state, where deploys leave them.
 *
 * `drush deploy` runs the database updates BEFORE it imports config, so this
 * reads the site's own current footer — an editor's changes included — and
 * keeps it, ahead of the import that would have reset it (backend audit M8).
 * A site whose footer is already in state is left alone.
 */
function icon_site_post_update_footer_to_state(): string {
  $state = \Drupal::state();
  if (is_array($state->get('icon_site.footer'))) {
    return 'The footer is in state already.';
  }
  $values = \Drupal::config('icon_site.footer')->getRawData();
  unset($values['_core'], $values['langcode']);
  $state->set('icon_site.footer', $values);
  return 'The footer\'s ' . count($values) . ' settings carried into state.';
}
