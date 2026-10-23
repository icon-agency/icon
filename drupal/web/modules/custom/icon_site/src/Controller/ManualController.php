<?php

declare(strict_types=1);

namespace Drupal\icon_site\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * The editors' manual at /admin/manual.
 *
 * The manual is a static page the module ships
 * (icon_site/manual/index.html, with its screenshots); this route is its
 * address in the admin Navigation bar — the bar's link block only renders
 * ROUTED paths — and sends the reader on to the page itself.
 */
final class ManualController extends ControllerBase {

  /**
   * Redirects to the manual's static page.
   */
  public function go(): RedirectResponse {
    return new RedirectResponse(base_path() . 'modules/custom/icon_site/manual/index.html');
  }

}
