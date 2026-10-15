<?php

/**
 * @file
 * The old site's paths, redirected to where their content lives now.
 *
 * 29 Sep 2026 (the backend audit's follow-up: "old-site redirects — news
 * and work paths from the current live site need mapping before launch").
 * Reads scripts/data/old-site-redirects.csv — old_path, new_path, basis,
 * status — crawled from iconagency.com.au's own listings that day: every
 * news story, every case study and the top-level pages. News stories kept
 * their paths in the import, so only the dozen never imported are in it;
 * a case study that is on the new site goes to it, one that is not goes to
 * /work; the three renamed pages go to their new names. Rows marked
 * `review` are a judgement (no equivalent page) and are still created: a
 * redirect to the section beats a 404, and the row can be edited in
 * Configuration → URL redirects afterwards.
 *
 * ADD-ONLY and idempotent, so it needs no seed guard: a row is skipped when
 * its old path already resolves on this site (an alias or a route) or
 * already has a redirect; nothing is replaced or deleted. 301s, to the
 * target's system path so a later alias change does not break them.
 *
 * Run: ddev exec drush php:script scripts/redirects.php
 * Dry run: ddev exec "ICON_DRY=1 drush php:script scripts/redirects.php"
 */

use Drupal\redirect\Entity\Redirect;

$dry = getenv('ICON_DRY') === '1';
$aliases = \Drupal::service('path_alias.manager');
$validator = \Drupal::service('path.validator');
$storage = \Drupal::entityTypeManager()->getStorage('redirect');
$rows = array_map('str_getcsv', file(__DIR__ . '/data/old-site-redirects.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
$header = array_shift($rows);
$made = $resolves = $exists = $bad = 0;
foreach ($rows as $row) {
  [$old, $new] = [$row[0], $row[1]];
  $source = ltrim($old, '/');
  // The target must be a page of this site.
  $target = $aliases->getPathByAlias($new);
  if (!$validator->getUrlIfValidWithoutAccessCheck($target)) {
    print "SKIP $old: the target $new is not a page here\n";
    $bad++;
    continue;
  }
  // An old path that is a page here needs no redirect.
  if ($aliases->getPathByAlias($old) !== $old || $validator->getUrlIfValidWithoutAccessCheck($old)) {
    $resolves++;
    continue;
  }
  if ($storage->loadByProperties(['redirect_source__path' => $source])) {
    $exists++;
    continue;
  }
  if (!$dry) {
    Redirect::create([
      'redirect_source' => ['path' => $source, 'query' => []],
      'redirect_redirect' => ['uri' => 'internal:' . $target],
      'status_code' => 301,
      'language' => 'und',
    ])->save();
  }
  $made++;
}
print ($dry ? 'DRY RUN — would create ' : 'Created ') . "$made redirect(s); $exists already had one; $resolves resolve as pages here; $bad with a missing target. " . count($rows) . " rows read.\n";
