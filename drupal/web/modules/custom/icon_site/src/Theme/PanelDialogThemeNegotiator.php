<?php

declare(strict_types=1);

namespace Drupal\icon_site\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Every request of a form opened from the Canvas panel (`?panel=1`) renders
 * in the admin theme — including the ajax posts the form makes from inside
 * its dialog.
 *
 * Canvas's own negotiator (priority 1001) switches to the admin theme on
 * `use_admin_theme`, but deliberately switches BACK to canvas_stark for the
 * media library's "Insert selected" post, because in its panel that is
 * where the React widget takes over. In a plain dialog that post updates
 * the media widget with canvas_stark markup — custom elements, no real
 * inputs — and the next save finds no media. This runs first.
 */
final class PanelDialogThemeNegotiator implements ThemeNegotiatorInterface {

  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match): bool {
    return (bool) $this->requestStack->getCurrentRequest()?->query->get('panel');
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match): ?string {
    $theme = $this->configFactory->get('system.theme');
    return (string) ($theme->get('admin') ?: $theme->get('default'));
  }

}
