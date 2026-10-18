<?php

/**
 * @file
 * A Testimonial's own colour pair (user ask, 19 Oct 2026: "control the
 * background colour and foreground text/logo, like the work page, for each
 * client testimonial"): two hex fields on the Testimonial content type —
 * Ground colour and Ink colour — that the Testimonial (item) block hands
 * the SDC, whose data the carousel dresses its box in while that quote
 * shows. Idempotent: storage, fields, widgets only where missing, hidden
 * from the display. Then `drush cex`.
 *
 * Usage: ddev exec drush php:script scripts/testimonial-colours.php
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

$fields = [
  'field_testimonial_bg' => ['Ground colour', "Optional: the carousel's box wears this colour while this quote shows — the client's own, like a case study's page colour. A hex value, e.g. #d20f89. Needs a box on the Testimonials group.", 30],
  'field_testimonial_ink' => ['Ink colour', 'The text, arrows, line and logo colour on that ground, a hex value, e.g. #ffffff. Used with Ground colour.', 31],
];
foreach ($fields as $name => [$label, $description, $weight]) {
  if (!FieldStorageConfig::loadByName('node', $name)) {
    FieldStorageConfig::create(['field_name' => $name, 'entity_type' => 'node', 'type' => 'string', 'settings' => ['max_length' => 9], 'cardinality' => 1])->save();
    print "$name storage\n";
  }
  if (!FieldConfig::loadByName('node', 'testimonial', $name)) {
    FieldConfig::create(['field_name' => $name, 'entity_type' => 'node', 'bundle' => 'testimonial', 'label' => $label, 'description' => $description, 'required' => FALSE])->save();
    print "$name field\n";
  }
  $form = EntityFormDisplay::load('node.testimonial.default');
  if ($form && !$form->getComponent($name)) {
    $form->setComponent($name, ['type' => 'string_textfield', 'weight' => $weight, 'region' => 'content', 'settings' => ['size' => 12, 'placeholder' => '']])->save();
    print "$name widget\n";
  }
  $view = EntityViewDisplay::load('node.testimonial.default');
  if ($view && $view->getComponent($name)) {
    $view->removeComponent($name)->save();
  }
}
print "done\n";
