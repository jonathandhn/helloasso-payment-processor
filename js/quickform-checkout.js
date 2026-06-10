(function($, CRM) {
  'use strict';

  var config = CRM.vars.helloassoQuickForm || {};
  var processorIds = (config.processorIds || []).map(String);

  function selectedProcessorId() {
    var $checked = $('[name="payment_processor_id"]:checked');
    if ($checked.length) {
      return String($checked.val());
    }
    var $field = $('[name="payment_processor_id"]').first();
    return $field.length ? String($field.val() || '') : '';
  }

  function isHelloAssoSelected() {
    return processorIds.indexOf(selectedProcessorId()) !== -1;
  }

  function isRecurChecked() {
    return $('[name="is_recur"]').is(':checked');
  }

  function ensureRedirectMessage() {
    if ($('#helloasso-quickform-redirect-message').length) {
      return;
    }

    var $message = $('<div>', {
      id: 'helloasso-quickform-redirect-message',
      class: 'helloasso-redirect-message messages status no-popup',
      hidden: true,
      text: config.message
    });

    var $paymentSection = $('.payment_processor-section').first();
    if ($paymentSection.length) {
      $paymentSection.after($message);
      return;
    }

    $('.crm-submit-buttons').first().before($message);
  }

  function setRecurringFieldState(disabled) {
    $('[name="frequency_unit"]').prop('disabled', disabled);
    $('[name="frequency_interval"]').prop('disabled', disabled);
  }

  function synchronizeRecurringFields() {
    if (!isHelloAssoSelected() || !config.supportsInstallments || !isRecurChecked()) {
      setRecurringFieldState(false);
      return;
    }

    $('[name="frequency_unit"]').val('month');
    $('[name="frequency_interval"]').val(1);
    setRecurringFieldState(true);
  }

  function refresh() {
    ensureRedirectMessage();
    var isHelloAsso = isHelloAssoSelected();
    $('#helloasso-quickform-redirect-message').prop('hidden', !isHelloAsso);
    synchronizeRecurringFields();
  }

  $(function() {
    refresh();
    $(document).on('change', '[name="payment_processor_id"]', refresh);
    $(document).on('change', '[name="is_recur"]', refresh);
    $(document).on('change', '[name="installments"]', refresh);
    $('form').on('submit', function() {
      setRecurringFieldState(false);
      synchronizeRecurringFields();
    });
  });
})(CRM.$, CRM);
