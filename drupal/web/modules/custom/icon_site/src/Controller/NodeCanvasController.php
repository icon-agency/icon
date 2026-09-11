<?php

declare(strict_types=1);

namespace Drupal\icon_site\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * The "Build in Canvas" tab on a node that carries a Canvas body.
 *
 * Canvas's editor lives at /canvas/editor/node/{nid}; nothing in the admin
 * links there for a node (the Pages list is Canvas pages only), so the tab
 * does — on the Work article, whose body is a Canvas field (Sep 2026).
 */
final class NodeCanvasController extends ControllerBase {

  /**
   * Sends the editor to the node's Canvas editor.
   */
  public function go(NodeInterface $node): RedirectResponse {
    return new RedirectResponse(Url::fromRoute('canvas.boot.entity', [
      'entity_type' => 'node',
      'entity' => $node->id(),
    ])->toString());
  }

  /**
   * Only a node with a Canvas field, and only for who may update it.
   */
  public function access(NodeInterface $node, AccountInterface $account): AccessResultInterface {
    $has_canvas = $node->hasField('field_work_canvas');
    return AccessResult::allowedIf($has_canvas)
      ->andIf($node->access('update', $account, TRUE))
      ->addCacheableDependency($node);
  }

}
