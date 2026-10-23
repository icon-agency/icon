<?php

/**
 * @file
 * Bakes the homepage hero into the front page (23 Oct 2026).
 *
 * Seeds the saved reel (State `icon_site.hero_reel`) from the front page's
 * hero block order, then takes the block out of the page's component tree
 * as a new revision — the theme prints the reel from the saved order from
 * here on (icon_preprocess_page()). Idempotent: nothing to seed or remove
 * is reported and left. Run locally and on the host after deploy:
 * `drush php:script scripts/hero-bake.php`.
 */

$order = icon_site_hero_order();
$saved = \Drupal::state()->get('icon_site.hero_reel');
if (!is_array($saved)) {
  icon_site_hero_order_set($order['order']);
  echo "reel saved: ", implode(',', $order['order']), "\n";
}
else {
  echo "reel already saved: ", implode(',', $saved), "\n";
}
$storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
$front = (string) \Drupal::config('system.site')->get('page.front');
$url = $front ? \Drupal::service('path.validator')->getUrlIfValid($front) : NULL;
$page = $url && $url->isRouted() && !empty($url->getRouteParameters()['canvas_page']) ? $storage->load($url->getRouteParameters()['canvas_page']) : NULL;
if (!$page) {
  echo "front page: not a Canvas page\n";
  return;
}
$values = $page->get('components')->getValue();
$kept = array_values(array_filter($values, fn(array $v) => ($v['component_id'] ?? '') !== 'block.icon_hero'));
if (count($kept) === count($values)) {
  echo "front page: no hero block in the tree\n";
}
else {
  $page->set('components', $kept);
  $page->setNewRevision(TRUE);
  $page->setRevisionLogMessage('The Homepage hero block taken out of the tree: the hero is baked into the front page (23 Oct 2026).');
  $page->save();
  echo "front page: hero block removed, revision ", $page->getRevisionId(), "\n";
}
$auto = \Drupal::service(\Drupal\canvas\AutoSave\AutoSaveManager::class);
$draft = $auto->getAutoSaveEntity($page, TRUE)->entity ?? NULL;
if ($draft) {
  foreach ($draft->get('components') as $item) {
    if (($item->getValue()['component_id'] ?? '') === 'block.icon_hero') {
      $auto->delete($page);
      echo "autosave: cleared (it held the hero block)\n";
      break;
    }
  }
}
