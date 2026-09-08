<?php

/**
 * @file
 * Drupal Rector: the deprecation checks the quality gate runs (dry-run) and
 * the fixes `npm run check:rector:fix` applies, for the custom code.
 */

declare(strict_types=1);

use DrupalRector\Set\Drupal10SetList;
use DrupalRector\Set\Drupal11SetList;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
  $rectorConfig->sets([
    Drupal10SetList::DRUPAL_10,
    Drupal11SetList::DRUPAL_11,
  ]);
  $rectorConfig->paths([
    __DIR__ . '/web/modules/custom',
    __DIR__ . '/web/themes/custom',
  ]);
  $rectorConfig->skip([
    __DIR__ . '/web/themes/custom/icon/js',
  ]);
  $rectorConfig->fileExtensions(['php', 'module', 'theme', 'install', 'inc']);
  $rectorConfig->importNames(TRUE, FALSE);
  $rectorConfig->importShortClasses(FALSE);
};
