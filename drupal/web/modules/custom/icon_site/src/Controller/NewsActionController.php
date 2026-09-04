<?php

declare(strict_types=1);

namespace Drupal\icon_site\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The news feed panel's one-click actions: pin / unpin (sticky) and
 * promote / unpromote (the homepage flag). Ajax callers get a command that
 * reloads the editor; anything else is sent back where it came from.
 */
final class NewsActionController extends ControllerBase {

  public function act(Request $request): AjaxResponse|RedirectResponse {
    $node = $this->entityTypeManager()->getStorage('node')->load((int) $request->query->get('nid'));
    $op = (string) $request->query->get('op');
    if (!$node instanceof NodeInterface || $node->bundle() !== 'news' || !$node->access('update')) {
      throw new BadRequestHttpException('Not a news story you can edit.');
    }
    match ($op) {
      'pin' => $node->setSticky(TRUE)->setPromoted(TRUE),
      'unpin' => $node->setSticky(FALSE),
      // Adding from the panel pins as well: the feed is date-ordered, so an
      // older story only appears when pinned.
      'promote' => $node->setPromoted(TRUE)->setSticky(TRUE),
      'unpromote' => $node->setPromoted(FALSE)->setSticky(FALSE),
      default => throw new BadRequestHttpException('Unknown action.'),
    };
    $node->save();
    if ($request->isXmlHttpRequest() || $request->query->has('_wrapper_format')) {
      $response = new AjaxResponse();
      $response->addCommand(new InvokeCommand('body', 'trigger', ['icon-panel:saved']));
      return $response;
    }
    return new RedirectResponse($request->headers->get('referer') ?: '/');
  }

}
