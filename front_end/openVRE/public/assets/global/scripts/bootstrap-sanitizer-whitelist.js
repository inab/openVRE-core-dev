/**
 * Extend Bootstrap 3.4.x tooltip/popover HTML whiteList for openVRE markup.
 * Keep sanitize enabled; do not allow scripts or event-handler attributes.
 * Must load after bootstrap.min.js and before app.min.js.
 */
(function ($) {
  'use strict';

  if (!$ || !$.fn || !$.fn.tooltip || !$.fn.tooltip.Constructor) {
    return;
  }

  var defaults = $.fn.tooltip.Constructor.DEFAULTS;
  if (!defaults || !defaults.whiteList) {
    return;
  }

  var whiteList = defaults.whiteList;

  // Metadata popovers embed <table> rows (datatables-page.js).
  ['table', 'thead', 'tbody', 'tr', 'th', 'td'].forEach(function (tag) {
    if (!Object.prototype.hasOwnProperty.call(whiteList, tag)) {
      whiteList[tag] = [];
    }
  });

  // Help tooltips commonly use <p align="…" style="…">.
  whiteList.p = (whiteList.p || []).slice();
  ['align', 'style'].forEach(function (attr) {
    if (whiteList.p.indexOf(attr) === -1) {
      whiteList.p.push(attr);
    }
  });

  // Leave sanitize on (Bootstrap 3.4.1 default).
  defaults.sanitize = true;
})(window.jQuery);
