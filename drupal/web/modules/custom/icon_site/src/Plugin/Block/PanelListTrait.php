<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Block;

/**
 * The pieces every Canvas panel list shares.
 *
 * Lifted at the third block (the hero, the clients marquee, then the
 * filmstrip): the row's reorder handle and the dialog attributes that open
 * a form over the editor.
 */
trait PanelListTrait {

  /**
   * The row's reorder handle.
   *
   * Dragged by pointer, moved by keyboard (the arrow keys, Home and End —
   * js/panel-sortable.js), so it is a real, focusable control named for its
   * row. No colon in the label: the admin markup filter reads "Name:" as a
   * URL scheme and strips it.
   */
  protected static function handle(string $name): string {
    $label = htmlspecialchars((string) t('Reorder @name — arrow keys move it up or down', ['@name' => $name]), ENT_QUOTES);
    return '<a class="icon-panel__handle" role="button" href="#" aria-label="' . $label . '" title="' . htmlspecialchars((string) t('Drag, or use the arrow keys, to reorder'), ENT_QUOTES) . '"></a>';
  }

  /**
   * The attributes that open a link's form in a dialog over the editor.
   *
   * `use_admin_theme` on the link itself is Canvas's own switch: without it
   * the editor's ajax requests render in canvas_stark, whose form markup is
   * for the React panel, not a dialog.
   */
  protected static function dialog(int $width = 860): string {
    return ' data-dialog-type="dialog" data-dialog-options=\'{"target":"icon-panel-dialog","modal":true,"width":"' . $width . '","classes":{"ui-dialog":"icon-panel-dialog"}}\'';
  }

}
