<?php

/**
 * @file
 * The gate every seed / mirror script passes through. These scripts REPLACE
 * content (matched by alias or title) and delete the paragraphs it had —
 * they are for a fresh local site, never for one with editorial content.
 * They refuse to run on a production platform environment, and otherwise
 * only when the run is explicitly allowed:
 *
 *   ddev exec "ICON_SEED=1 drush php:script scripts/<script>.php"
 */

if (in_array(getenv('PLATFORM_ENVIRONMENT_TYPE') ?: getenv('DRUPAL_ENVIRONMENT') ?: '', ['production', 'prod'], TRUE)) {
  fwrite(STDERR, "Refusing: this is a production environment. Seed and mirror scripts replace content.\n");
  exit(1);
}
if (getenv('ICON_SEED') !== '1') {
  fwrite(STDERR, "Refusing: seed and mirror scripts replace content (matched by alias or title) and delete its old paragraphs.\n"
    . "Run one on purpose with:  ddev exec \"ICON_SEED=1 drush php:script " . basename($_SERVER['SCRIPT_NAME'] ?? 'scripts/x.php') . "\"\n");
  exit(1);
}
