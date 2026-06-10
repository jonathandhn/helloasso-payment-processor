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

  function buildBlock() {
    if ($('#helloasso-quickform-checkout').length) {
      return;
    }

    var $block = $('<div>', {
      id: 'helloasso-quickform-checkout',
      class: 'helloasso-quickform-checkout crm-public-form-item',
      hidden: true
    });

    if (config.supportsInstallments) {
      var $row = $('<div>', {class: 'crm-section helloasso-installments-section'});
      $('<div>', {class: 'label', text: config.installmentsLabel}).appendTo($row);
      var $content = $('<div>', {class: 'content'}).appendTo($row);
      var $select = $('<select>', {
        id: 'helloasso_installments',
        name: 'helloasso_installments',
        class: 'crm-form-select'
      });
      $('<option>', {
        value: '',
        text: config.oneTimeLabel
      }).appendTo($select);
      for (var installment = 2; installment <= 12; installment++) {
        $('<option>', {
          value: installment,
          text: installment
        }).appendTo($select);
      }
      $select
        .val(config.installmentsValue || $('#installments').val() || '')
        .appendTo($content);
      $('<div>', {
        class: 'description',
        text: config.installmentsDescription
      }).appendTo($content);
      $('<div>', {class: 'clear'}).appendTo($row);
      $row.appendTo($block);
    }

    var $message = $('<div>', {
      id: 'helloasso-quickform-redirect-message',
      class: 'helloasso-redirect-message messages status no-popup',
      hidden: true,
      text: config.message
    });

    var $recurringSection = $('.is_recur-section').first();
    var $paymentSection = $('.payment_processor-section').first();
    if (config.supportsInstallments && $recurringSection.length) {
      $recurringSection.after($block);
    }
    else {
      $('.crm-submit-buttons').first().before($block);
    }

    if ($paymentSection.length) {
      $paymentSection.after($message);
    }
    else {
      $block.after($message);
    }
  }

  function refresh() {
    buildBlock();
    var selected = selectedProcessorId();
    var isHelloAsso = processorIds.indexOf(selected) !== -1;
    var wasHelloAsso = $('#helloasso-quickform-checkout').is(':visible');
    $('#helloasso-quickform-checkout').prop('hidden', !isHelloAsso);
    $('#helloasso-quickform-redirect-message').prop('hidden', !isHelloAsso);
    if (config.supportsInstallments) {
      if (wasHelloAsso && !isHelloAsso) {
        synchronizeNativeFields(true);
      }
      $('.is_recur-section').toggle(!isHelloAsso);
      if (wasHelloAsso && !isHelloAsso && $('.is_recur-section').length) {
        $('html, body').animate({
          scrollTop: $('.is_recur-section').first().offset().top - 24
        }, 250);
      }
    }
  }

  function synchronizeNativeFields(force) {
    if (!force && processorIds.indexOf(selectedProcessorId()) === -1) {
      return;
    }
    var value = $('#helloasso_installments').val();
    if (!value) {
      $('#installments').val('');
      $('[name="is_recur"]').prop('checked', false).val(0);
      return;
    }
    $('#installments').val(value);
    $('[name="is_recur"]').prop('checked', true).val(1);
    $('[name="frequency_unit"]').val('month');
    $('[name="frequency_interval"]').val(1);
  }

  $(function() {
    refresh();
    $(document).on('change', '[name="payment_processor_id"]', refresh);
    $('form').on('submit', function() {
      synchronizeNativeFields(false);
    });
  });
})(CRM.$, CRM);
