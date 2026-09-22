<?php

/**
 * @file
 * The Testimonial content type: a client quote as content, picked in the
 * Testimonial (item) panel on any page (user ask, Sep 2026: "Is there a
 * way I can easily select previously added and the ability to add new").
 *
 * Name (the title), Role and company, Quote (long text, required) and
 * Logo (a client logo or a picture from the media library). Not moderated,
 * like Offices and Team members. Idempotent: creates what is missing and
 * leaves what is there; the config is exported after it runs.
 *
 * Run: ddev exec "ICON_SEED=1 drush php:script scripts/testimonial-type.php"
 */

require_once __DIR__ . '/_guard.php';

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

if (!NodeType::load('testimonial')) {
  NodeType::create([
    'type' => 'testimonial',
    'name' => 'Testimonial',
    'description' => 'A client quote for the Testimonials carousel: who said it, their role and company, the company’s mark. Picked in the Testimonial (item) panel on any page.',
    'new_revision' => TRUE,
    'preview_mode' => 0,
    'display_submitted' => FALSE,
  ])->save();
  print "Content type created.\n";
}
$title = BaseFieldOverride::loadByName('node', 'testimonial', 'title')
  ?: BaseFieldOverride::createFromBaseFieldDefinition(\Drupal::service('entity_field.manager')->getBaseFieldDefinitions('node')['title'], 'testimonial');
$title->setLabel('Name')->setDescription('Who said it.')->save();

$fields = [
  // Role and Company are two fields, one under the other (user call, Sep
  // 2026: "Split this so 'Role: …' and next line add field 'Company: …'").
  ['field_testimonial_role', 'string', ['max_length' => 255], 'Role', 'Their role, e.g. Marketing Director.', 'string_textfield', 1],
  ['field_testimonial_company', 'string', ['max_length' => 255], 'Company', 'Where, e.g. IMG.', 'string_textfield', 2],
  ['field_testimonial_quote', 'string_long', [], 'Quote', 'What they said, without quotation marks. A line break in the text breaks the line.', 'string_textarea', 3],
  ['field_testimonial_logo', 'entity_reference', ['target_type' => 'media'], 'Logo', 'The company’s mark, from the media library — a client logo or a picture, shown in one colour. Optional.', 'media_library_widget', 4],
  // User ask, Sep 2026: "Sometimes the logo looks too small with the height
  // and width rules. can you add bump up logo size option or tickbox?"
  ['field_testimonial_logo_large', 'boolean', [], 'Larger logo', 'Tick for a mark that sits small in the standard box — a tall or square one. Half again the size.', 'boolean_checkbox', 5],
];
$form = EntityFormDisplay::load('node.testimonial.default')
  ?: EntityFormDisplay::create(['targetEntityType' => 'node', 'bundle' => 'testimonial', 'mode' => 'default', 'status' => TRUE]);
$view = EntityViewDisplay::load('node.testimonial.default')
  ?: EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => 'testimonial', 'mode' => 'default', 'status' => TRUE]);
foreach ($fields as [$name, $type, $storage, $label, $description, $widget, $weight]) {
  $field_storage = FieldStorageConfig::loadByName('node', $name);
  if (!$field_storage) {
    $field_storage = FieldStorageConfig::create(['field_name' => $name, 'entity_type' => 'node', 'type' => $type, 'settings' => $storage, 'cardinality' => 1]);
    $field_storage->save();
  }
  if (!FieldConfig::loadByName('node', 'testimonial', $name)) {
    // The storage object itself, not its name: made in this same run, the
    // name lookup inside FieldConfig does not find it yet.
    $config = [
      'field_storage' => $field_storage,
      'bundle' => 'testimonial',
      'label' => $label,
      'description' => $description,
      'required' => $name === 'field_testimonial_quote',
    ];
    if ($type === 'entity_reference') {
      $config['settings'] = [
        'handler' => 'default:media',
        'handler_settings' => ['target_bundles' => ['logo' => 'logo', 'image' => 'image'], 'sort' => ['field' => '_none'], 'auto_create' => FALSE],
      ];
    }
    FieldConfig::create($config)->save();
  }
  $options = ['type' => $widget, 'weight' => $weight];
  if ($widget === 'media_library_widget') {
    $options['settings'] = ['media_types' => ['logo', 'image']];
  }
  if ($widget === 'boolean_checkbox') {
    $options['settings'] = ['display_label' => TRUE];
  }
  // An existing field keeps its label and description current.
  $existing = FieldConfig::loadByName('node', 'testimonial', $name);
  if ($existing && ($existing->getLabel() !== $label || $existing->getDescription() !== $description)) {
    $existing->setLabel($label)->setDescription($description)->save();
  }
  $form->setComponent($name, $options);
  $view->setComponent($name, ['label' => 'above', 'weight' => $weight]);
}
$form->setComponent('title', ['type' => 'string_textfield', 'weight' => 0]);
$form->setComponent('status', ['type' => 'boolean_checkbox', 'weight' => 10]);
foreach (['promote', 'sticky', 'uid', 'created'] as $hidden) {
  $form->removeComponent($hidden);
}
$form->save();
$view->save();
print 'Testimonial form: ' . implode(', ', array_keys($form->getComponents())) . "\n";
