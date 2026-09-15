/* exclusive-styles.js — CKEditor 5's Style menu treats every block style as
 * a layer: Paragraph — Large and Paragraph — Small can both sit on one
 * paragraph (user catch, Sep 2026: `<p class="is-large is-small">`). The
 * site's styles are alternatives, so applying one takes the others defined
 * for the same element off the selected blocks — the plugin's own toggle,
 * run in reverse. Plain script on the core DLL (Drupal resolves
 * `iconExclusiveStyles.IconExclusiveStyles` from window.CKEditor5). */
(function (CKEditor5) {
  "use strict";

  class IconExclusiveStyles extends CKEditor5.core.Plugin {
    static get pluginName() {
      return "IconExclusiveStyles";
    }

    afterInit() {
      const editor = this.editor;
      const command = editor.commands.get("style");
      if (!command || !editor.plugins.has("GeneralHtmlSupport")) {
        return;
      }
      const ghs = editor.plugins.get("GeneralHtmlSupport");
      const definitions = (editor.config.get("style.definitions") || []).filter(function (d) {
        return d.element && Array.isArray(d.classes) && d.classes.length;
      });
      let busy = false;

      // After the command has applied `styleName`, take the rival styles off.
      command.on("execute", function (evt, args) {
        if (busy) {
          return;
        }
        const styleName = args && args[0] && args[0].styleName;
        const applied = definitions.find(function (d) { return d.name === styleName; });
        if (!applied) {
          return;
        }
        const attribute = ghs.getGhsAttributeNameForElement(applied.element);
        const blocks = Array.from(editor.model.document.selection.getSelectedBlocks());
        const hasClasses = function (block, classes) {
          const attr = block.getAttribute(attribute);
          const list = attr && Array.isArray(attr.classes) ? attr.classes : [];
          return classes.every(function (c) { return list.includes(c); });
        };
        // The applied style is on the selection now; if it is not, this was
        // a toggle OFF and there is nothing to clear.
        if (!blocks.some(function (b) { return hasClasses(b, applied.classes); })) {
          return;
        }
        const rivals = definitions.filter(function (d) {
          return d !== applied && d.element === applied.element
            && blocks.some(function (b) { return hasClasses(b, d.classes); });
        });
        if (!rivals.length) {
          return;
        }
        busy = true;
        try {
          rivals.forEach(function (d) {
            editor.execute("style", { styleName: d.name });
          });
        }
        finally {
          busy = false;
        }
      }, { priority: "lowest" });
    }
  }

  window.CKEditor5 = window.CKEditor5 || {};
  window.CKEditor5.iconExclusiveStyles = { IconExclusiveStyles: IconExclusiveStyles };
})(window.CKEditor5);
