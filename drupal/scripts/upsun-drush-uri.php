<?php

/**
 * @file
 * Upsun deploy hook: writes .drush/drush.yml with the site's primary URL, so
 * drush (cron, deploy) generates absolute links for the right host. Reads the
 * platform's PLATFORM_ROUTES (base64 JSON keyed by URL) — no library needed.
 */

if (PHP_SAPI !== 'cli') {
  exit;
}
$routes = getenv('PLATFORM_ROUTES') ? json_decode(base64_decode(getenv('PLATFORM_ROUTES')), TRUE) : [];
$app = getenv('PLATFORM_APPLICATION_NAME') ?: 'drupal';
$candidates = [];
foreach ($routes as $url => $route) {
  if (($route['type'] ?? '') === 'upstream' && strpos($route['upstream'] ?? '', $app) === 0) {
    $candidates[] = ['url' => $url, 'primary' => !empty($route['primary']), 'https' => str_starts_with($url, 'https://')];
  }
}
usort($candidates, fn($a, $b) => [!$a['primary'], !$a['https'], strlen($a['url'])] <=> [!$b['primary'], !$b['https'], strlen($b['url'])]);
$url = $candidates[0]['url'] ?? NULL;
if (!$url) {
  fwrite(STDERR, "upsun-drush-uri: no upstream route found for $app\n");
  exit(1);
}
$dir = dirname(__DIR__) . '/.drush';
if (!is_dir($dir)) {
  mkdir($dir, 0755, TRUE);
}
file_put_contents("$dir/drush.yml", "# Generated on deploy by scripts/upsun-drush-uri.php.\noptions:\n  uri: " . json_encode($url, JSON_UNESCAPED_SLASHES) . "\n");
echo "drush uri: $url\n";
