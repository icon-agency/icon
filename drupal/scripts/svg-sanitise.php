<?php

/**
 * @file
 * Sanitises every SVG the site holds, in place (audit H6, 23 Oct 2026).
 *
 * New uploads are cleaned by icon_site_file_presave(); this walks the files
 * already in the folder — the client logos and the icons — through the same
 * sanitiser. Idempotent: a clean file is left alone. Run with
 * `drush php:script scripts/svg-sanitise.php`, locally and on the host.
 */

$storage = \Drupal::entityTypeManager()->getStorage('file');
$ids = $storage->getQuery()->accessCheck(FALSE)->condition('uri', '%.svg', 'LIKE')->execute();
$rewritten = $clean = $skipped = 0;
foreach ($storage->loadMultiple($ids) as $file) {
  $result = icon_site_sanitise_svg_file($file);
  if ($result === TRUE) {
    $file->save();
    $rewritten++;
    echo "rewritten: ", $file->getFileUri(), "\n";
  }
  elseif ($result === FALSE) {
    $clean++;
  }
  else {
    $skipped++;
    echo "skipped: ", $file->getFileUri(), "\n";
  }
}
echo "svg: $rewritten rewritten, $clean already clean, $skipped skipped of ", count($ids), "\n";
