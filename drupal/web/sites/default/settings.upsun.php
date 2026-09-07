<?php

/**
 * @file
 * Upsun settings — included by settings.php when PLATFORM_PROJECT is set.
 * Reads the platform's environment (PLATFORM_RELATIONSHIPS is base64 JSON)
 * directly, so no config-reader package is needed.
 */

$relationships = getenv('PLATFORM_RELATIONSHIPS') ? json_decode(base64_decode(getenv('PLATFORM_RELATIONSHIPS')), TRUE) : [];

// The database (relationship "database" in .upsun/config.yaml).
if (!empty($relationships['database'][0])) {
  $creds = $relationships['database'][0];
  $databases['default']['default'] = [
    'driver' => $creds['scheme'],
    'database' => $creds['path'],
    'username' => $creds['username'],
    'password' => $creds['password'],
    'host' => $creds['host'],
    'port' => $creds['port'],
    'pdo' => [PDO::MYSQL_ATTR_COMPRESS => !empty($creds['query']['compression'])],
    'init_commands' => [
      'isolation_level' => 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED',
    ],
  ];
}

// Errors: hidden on production, verbose on every other environment.
if (getenv('PLATFORM_ENVIRONMENT_TYPE')) {
  $config['system.logging']['error_level'] = getenv('PLATFORM_ENVIRONMENT_TYPE') === 'production' ? 'hide' : 'verbose';
}

// Runtime paths on the app's mounts (.upsun/config.yaml mounts).
if ($app_dir = getenv('PLATFORM_APP_DIR')) {
  $settings['file_private_path'] = $settings['file_private_path'] ?? $app_dir . '/private';
  $settings['file_temp_path'] = $settings['file_temp_path'] ?? $app_dir . '/tmp';
  $settings['php_storage']['default']['directory'] = $settings['php_storage']['default']['directory'] ?? $settings['file_private_path'];
  $settings['php_storage']['twig']['directory'] = $settings['php_storage']['twig']['directory'] ?? $settings['file_private_path'];
}
if (empty($settings['hash_salt']) && getenv('PLATFORM_PROJECT_ENTROPY')) {
  $settings['hash_salt'] = getenv('PLATFORM_PROJECT_ENTROPY');
}
if (getenv('PLATFORM_TREE_ID')) {
  $settings['deployment_identifier'] = $settings['deployment_identifier'] ?? getenv('PLATFORM_TREE_ID');
}

// The platform's router already replaces the Host header with the route that
// was matched, so every Host that reaches PHP is one of ours.
$settings['trusted_host_patterns'] = ['.*'];
