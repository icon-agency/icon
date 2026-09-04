<?php

declare(strict_types=1);

namespace Drupal\icon_site\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\media\MediaInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The marquee panel's actions on Logo media: `order` (ids, in order, written
 * to the Order field) and `remove` (unpublish — the file stays in the
 * library). Ajax callers get a command that reloads the editor.
 */
final class LogoActionController extends ControllerBase {

  public function act(Request $request): AjaxResponse|JsonResponse|RedirectResponse {
    $storage = $this->entityTypeManager()->getStorage('media');
    $op = (string) $request->query->get('op');
    if ($op === 'order') {
      $ids = array_values(array_filter(array_map('intval', explode(',', (string) $request->query->get('ids')))));
      foreach ($ids as $weight => $id) {
        $media = $storage->load($id);
        if ($media instanceof MediaInterface && $media->bundle() === 'logo' && $media->access('update') && (int) $media->get('field_logo_weight')->value !== $weight) {
          $media->set('field_logo_weight', $weight)->save();
        }
      }
      return new JsonResponse(['ok' => TRUE, 'count' => count($ids)]);
    }
    $media = $storage->load((int) $request->query->get('id'));
    if (!$media instanceof MediaInterface || $media->bundle() !== 'logo' || !$media->access('update')) {
      throw new BadRequestHttpException('Not a logo you can edit.');
    }
    match ($op) {
      'remove' => $media->setUnpublished(),
      'restore' => $media->setPublished(),
      default => throw new BadRequestHttpException('Unknown action.'),
    };
    $media->save();
    if ($request->isXmlHttpRequest() || $request->query->has('_wrapper_format')) {
      $response = new AjaxResponse();
      $response->addCommand(new InvokeCommand('body', 'trigger', ['icon-panel:saved']));
      return $response;
    }
    return new RedirectResponse($request->headers->get('referer') ?: '/');
  }

}
