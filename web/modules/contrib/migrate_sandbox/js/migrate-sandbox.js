(function ($, Drupal, drupalSettings) {

  "use strict";

  Drupal.behaviors.migrateSandbox = {
    attach: function (context) {
      // Search process pipeline for possible escapees once a second.
      $(once('migrate-sandbox-fe-validate', 'body')).each(function() {
        var warnAbout = drupalSettings.migrate_sandbox_warnings;
        var lastPipeline = '';
        setInterval(function() {
          var $process = $('[name="process"]');
          var pipeline = $process.val();
          if (pipeline !== lastPipeline) {
            lastPipeline = pipeline;
            var $warningHeader = $('.warning-header').addClass('hidden');
            warnAbout.forEach(function(plugin) {
              var $warning =  $('[data-plugin="' + plugin + '"]');
              $warning.addClass('hidden');
              var regex = new RegExp('plugin\: *\'?\"?' + plugin, 'g');
              if (pipeline && regex.test(pipeline)) {
                $warning.removeClass('hidden');
                $warningHeader.removeClass('hidden');
              }
            });
          }
          }, 1000);
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
