<?php

/**
 * @file
 * The news article's Video embed paragraph gets the same Cover option as
 * the Canvas Video embed component (user ask, 16 Oct 2026: "make sure News
 * articles have the same rules and options as the work video embed"): an
 * optional image pick from the media library, shown with the play control
 * until the film loads; empty, the film's own thumbnail is fetched.
 * Idempotent — creates the storage, the field, the widget and hides the
 * field from the display only where missing. Then `drush cex`.
 *
 * Usage: ddev exec drush php:script scripts/news-video-cover.php
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

$field = 'field_news_article_video_cover';
$bundle = 'news_article_video';

if (!FieldStorageConfig::loadByName('paragraph', $field)) {
  FieldStorageConfig::create([
    'field_name' => $field,
    'entity_type' => 'paragraph',
    'type' => 'entity_reference',
    'settings' => ['target_type' => 'media'],
    'cardinality' => 1,
  ])->save();
  print "storage created\n";
}
if (!FieldConfig::loadByName('paragraph', $bundle, $field)) {
  FieldConfig::create([
    'field_name' => $field,
    'entity_type' => 'paragraph',
    'bundle' => $bundle,
    'label' => 'Cover',
    'description' => "Optional: the still shown with the play control before the film loads, from the media library. Leave it empty and the film's own thumbnail is used.",
    'required' => FALSE,
    'settings' => [
      'handler' => 'default:media',
      'handler_settings' => [
        'target_bundles' => ['image' => 'image'],
        'sort' => ['field' => '_none', 'direction' => 'ASC'],
        'auto_create' => FALSE,
        'auto_create_bundle' => '',
      ],
    ],
  ])->save();
  print "field created\n";
}

$form = EntityFormDisplay::load("paragraph.$bundle.default");
if ($form && !$form->getComponent($field)) {
  $form->setComponent($field, ['type' => 'media_library_widget', 'weight' => 2, 'region' => 'content'])->save();
  print "widget placed\n";
}
$view = EntityViewDisplay::load("paragraph.$bundle.default");
if ($view && $view->getComponent($field)) {
  $view->removeComponent($field)->save();
  print "hidden from the display\n";
}
print "done\n";
