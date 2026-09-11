<?php

declare(strict_types=1);

namespace Drupal\icon_site\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The Team profiles panel's action on Team members: their order.
 *
 * `ids`, in order, are written to each member's Order field — the grid's
 * sort — the way the marquee panel writes the logos'. Everything else the
 * panel does (Edit, Add) is the member's own form in a dialog.
 */
final class TeamActionController extends ControllerBase {

  /**
   * Applies the `op` query parameter's action to the requested members.
   */
  public function act(Request $request): JsonResponse {
    if ((string) $request->query->get('op') !== 'order') {
      throw new BadRequestHttpException('Unknown action.');
    }
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = array_values(array_filter(array_map('intval', explode(',', (string) $request->query->get('ids')))));
    foreach ($ids as $weight => $id) {
      $node = $storage->load($id);
      if ($node instanceof NodeInterface && $node->bundle() === 'team_member' && $node->access('update') && (int) $node->get('field_team_weight')->value !== $weight) {
        $node->set('field_team_weight', $weight);
        $node->setNewRevision(TRUE);
        $node->setRevisionUserId((int) $this->currentUser()->id());
        $node->setRevisionLogMessage('Team order (Canvas panel)');
        $node->save();
      }
    }
    return new JsonResponse(['ok' => TRUE, 'count' => count($ids)]);
  }

}
