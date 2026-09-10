<?php

declare(strict_types=1);

namespace Drupal\icon_site\Twig;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use enshrined\svgSanitize\Sanitizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig helpers for the homepage SDCs.
 */
final class IconSiteExtension extends AbstractExtension {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly StreamWrapperManagerInterface $streamWrapperManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('icon_inline_svg', [$this, 'inlineSvg'], ['is_safe' => ['html']]),
    ];
  }

  /**
   * An SVG file inlined, so `fill="currentColor"` takes the surrounding ink.
   *
   * The file is an Icon media ID, a stream-wrapper URI such as
   * public://icons/trophy.svg, or its URL.
   *
   * Only files under the site's files directory are read; anything that is
   * not an <svg> document renders nothing. Scripts and event handlers are
   * stripped — the files come from editors, not the public — and the root
   * element gets the class and aria-hidden the design system's inline icons
   * carry.
   */
  public function inlineSvg(string|int|null $uri, string $class = ''): string {
    if (!$uri) {
      return '';
    }
    $uri = (string) $uri;
    if (ctype_digit($uri)) {
      // A media ID (the fact card's icon prop) — its file.
      $media = $this->entityTypeManager->getStorage('media')->load((int) $uri);
      // The media's own view access decides, not the file's public URL.
      if (!$media || !$media->access('view')) {
        return '';
      }
      $file = $media->hasField('field_media_file') ? $media->get('field_media_file')->entity : NULL;
      if (!$file) {
        return '';
      }
      $uri = $file->getFileUri();
    }
    if (!str_contains($uri, '://')) {
      // A URL — map the public files path back to the stream wrapper.
      $base = $this->streamWrapperManager->getViaScheme('public')->getDirectoryPath();
      $path = parse_url($uri, PHP_URL_PATH) ?: '';
      $prefix = '/' . trim($base, '/') . '/';
      if (!str_starts_with($path, $prefix)) {
        return '';
      }
      $uri = 'public://' . rawurldecode(substr($path, strlen($prefix)));
    }
    if (!str_starts_with($uri, 'public://') || !is_file($uri) || !str_ends_with(strtolower($uri), '.svg')) {
      return '';
    }
    // A real SVG sanitiser (enshrined/svg-sanitize — the one svg_image uses):
    // the document is parsed and rebuilt from an allow-list of elements and
    // attributes, so scripts, event handlers, javascript: and data: URIs,
    // foreignObject and remote references are gone by construction rather
    // than by pattern. An unparseable file renders nothing.
    $sanitizer = new Sanitizer();
    $sanitizer->removeRemoteReferences(TRUE);
    $sanitizer->minify(TRUE);
    $svg = $sanitizer->sanitize((string) file_get_contents($uri));
    if (!is_string($svg) || $svg === '') {
      return '';
    }
    $start = stripos($svg, '<svg');
    if ($start === FALSE) {
      return '';
    }
    $svg = substr($svg, $start);
    // The ROOT's own class / hidden / size attributes give way to the caller's
    // (the CSS sizes the icon); the sanitiser has already vetted the rest.
    // Scoped to the root tag: a plain limit of four replacements walked the
    // whole document and stripped whatever it met first, so an icon whose
    // root carried fewer than four of them lost a child's class or size
    // instead (found in review, Sep 2026).
    $attrs = ($class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"' : '') . ' aria-hidden="true"';
    return preg_replace_callback(
      '#^<svg\b[^>]*>#i',
      static fn (array $m): string => '<svg' . $attrs . preg_replace('#\s+(class|aria-hidden|width|height)\s*=\s*("[^"]*"|\'[^\']*\')#i', '', substr($m[0], 4)),
      $svg,
      1
    ) ?? '';
  }

}
