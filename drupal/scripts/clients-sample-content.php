<?php

/**
 * @file
 * Sample Logo media — the eighteen client logos of templates/homeC.html, in
 * its marquee order, from drupal/sample-content/clients/ (the theme's SVGs,
 * which follow assets/client-logos/README.md). Idempotent (matched by name).
 * Run from drupal/:  ddev drush php:script scripts/clients-sample-content.php
 */

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

$fs = \Drupal::service('file_system');
$dest = 'public://client-logos';
$fs->prepareDirectory($dest, $fs::CREATE_DIRECTORY | $fs::MODIFY_PERMISSIONS);
$logos = [
  ['meta.svg', 'Meta'], ['dcceew.svg', 'Department of Climate Change, Energy, the Environment and Water'], ['the-athletes-foot.svg', 'The Athlete’s Foot'],
  ['beyond-blue.svg', 'Beyond Blue'], ['schneider-electric.svg', 'Schneider Electric'], ['moad.svg', 'Museum of Australian Democracy'],
  ['kmart.svg', 'Kmart'], ['australian-wildlife-conservancy.svg', 'Australian Wildlife Conservancy'], ['cefc.svg', 'Clean Energy Finance Corporation'],
  ['myfitnesspal.svg', 'MyFitnessPal'], ['dss.svg', 'Department of Social Services'], ['australian-space-agency.svg', 'Australian Space Agency'],
  ['san-churro.svg', 'San Churro'], ['asqa.svg', 'ASQA'], ['phn.svg', 'PHN North Western Melbourne'],
  ['mari-group.svg', 'MARI Group'], ['ces-victoria.svg', 'Commissioner for Environmental Sustainability, Victoria'], ['ifs.svg', 'IFS'],
];
foreach ($logos as $i => [$file, $name]) {
  $existing = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties(['bundle' => 'logo', 'name' => $name]);
  if ($existing) {
    $m = reset($existing);
    $m->set('field_logo_weight', $i)->save();
    echo "kept   $name\n";
    continue;
  }
  $uri = $fs->copy(DRUPAL_ROOT . "/../sample-content/clients/$file", "$dest/$file", $fs::EXISTS_REPLACE);
  $f = File::create(['uri' => $uri, 'status' => 1]);
  $f->save();
  Media::create(['bundle' => 'logo', 'name' => $name, 'field_media_file' => ['target_id' => $f->id()], 'field_logo_weight' => $i, 'status' => 1])->save();
  echo "created $name\n";
}
echo "done\n";
