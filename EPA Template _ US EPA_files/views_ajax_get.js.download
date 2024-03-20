(function ($, drupalSettings) {
  // Store the original beforeSerialize, as we want to continue using
  // it after we've overridden it.
  Drupal.Ajax.prototype.originalBeforeSerialize = Drupal.Ajax.prototype.beforeSerialize;

  /**
   * Override core's beforeSerialize.
   *
   * We switch to using GET if this is for an ajax View.
   * We also avoid adding ajax_html_id and ajax_page_state.
   * (This happens in core's beforeSerialize).
   */
  Drupal.Ajax.prototype.beforeSerialize = function (element, options) {

    // If this is for a view, switch to GET.
    if (options.url &&
      options.url.indexOf('/views/ajax') !== -1 &&
      drupalSettings.viewsAjaxGet &&
      typeof drupalSettings.viewsAjaxGet[options.data.view_name] !== 'undefined') {

      // @See Drupal.Ajax.prototype.beforeSerialize
      if (this.form) {
        var settings = this.settings || drupalSettings;
        Drupal.detachBehaviors(this.form, settings, 'serialize');
      }

      options.type = 'GET';

      // Reset URL to ensure params in current url and params from form
      // don't conflict.
      options.url = Drupal.url('views/ajax');

      return;
    }

    return this.originalBeforeSerialize(element, options);
  };

})(jQuery, drupalSettings);
