<?php

declare(strict_types=1);

namespace Drupal\icon_site\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\icon_site\Plugin\Block\TeamProfilesBlock;
use Symfony\Component\HttpFoundation\Request;

/**
 * Lets /about/<team member> reach the About page.
 *
 * A team member's panel has an address of its own — the history gets
 * /about/joanne-painter as it opens — so the address has to load: on the
 * way in, a path that is the About page's plus a published member's slug
 * is rewritten to the page's own, and js/team-profiles.js reads the
 * address it was given and opens that panel. The browser's URL is left as
 * it was. An unknown slug is left alone, so it 404s as it should.
 *
 * Priority 300: ahead of the alias processor (100), which then maps /about
 * to the Canvas page the same as any other request for it.
 */
final class TeamProfilePathProcessor implements InboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request): string {
    $base = TeamProfilesBlock::BASE_PATH;
    if (preg_match('#^' . preg_quote($base, '#') . '/([a-z0-9-]+)$#', $path, $m) && in_array($m[1], icon_site_team_slugs(), TRUE)) {
      // The Redirect module's route normaliser would otherwise send the
      // browser to the page's canonical alias — /about — and the slug, the
      // whole point, would be lost before the page loaded. This attribute is
      // its documented opt-out, for this request only.
      $request->attributes->set('_disable_route_normalizer', TRUE);
      return $base;
    }
    return $path;
  }

}
