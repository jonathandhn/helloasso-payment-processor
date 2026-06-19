/*jshint esversion: 6 */
(function($, ts) {
  var script = {
    name: 'CRM.helloasso',
    paymentProcessorID: null,

    getSelectedPaymentProcessorId: function() {
      if (typeof CRM.payment === 'undefined' || CRM.payment.form === null) {
        return null;
      }
      return CRM.payment.getPaymentProcessorSelectorValue();
    },

    isOurProcessorSelected: function() {
      return this.getSelectedPaymentProcessorId() === this.paymentProcessorID;
    },

    cleanupState: function() {
      if (typeof CRM.payment === 'undefined' || CRM.payment.form === null) {
        return;
      }

      CRM.payment.form.dataset.submitted = 'false';
      CRM.payment.form.dataset.submitdontprocess = 'false';
      if (CRM.payment.submitButtons !== null) {
        for (let i = 0; i < CRM.payment.submitButtons.length; ++i) {
          CRM.payment.submitButtons[i].removeAttribute('disabled');
        }
      }

      var errorElement = document.getElementById('card-errors');
      if (errorElement !== null) {
        errorElement.style.display = 'none';
        errorElement.textContent = '';
      }
    },

    ensureErrorElement: function() {
      if (document.getElementById('card-errors') !== null || CRM.payment.form === null) {
        return;
      }

      var billingBlock = CRM.payment.form.querySelector('#billing-payment-block') || CRM.payment.form;
      var errorElement = document.createElement('div');
      errorElement.id = 'card-errors';
      errorElement.style.display = 'none';
      errorElement.className = 'messages status no-popup error';
      billingBlock.prepend(errorElement);
    },

    handleProcessorSwitch: function() {
      var newPaymentProcessorID = this.getSelectedPaymentProcessorId();
      if (newPaymentProcessorID !== this.paymentProcessorID) {
        this.cleanupState();
      }
    },

    handleSubmit: function(event) {
      if (!this.isOurProcessorSelected()) {
        return true;
      }

      if (CRM.payment.form.dataset.submitdontprocess === 'true') {
        CRM.payment.form.dataset.submitdontprocess = 'false';
        return true;
      }

      if (CRM.payment.form.dataset.submitted === 'true') {
        event.preventDefault();
        return false;
      }

      CRM.payment.form.dataset.submitted = 'true';

      if (!CRM.payment.validateCiviDiscount()) {
        event.preventDefault();
        return false;
      }

      if (!CRM.payment.validateForm()) {
        event.preventDefault();
        return false;
      }

      if (!CRM.payment.validateReCaptcha()) {
        event.preventDefault();
        return false;
      }

      if (CRM.payment.getIsDrupalWebform()) {
        if (event.currentTarget && event.currentTarget.value) {
          CRM.payment.addDrupalWebformActionElement(event.currentTarget.value);
        }

        if (CRM.payment.submitButtons !== null) {
          for (let i = 0; i < CRM.payment.submitButtons.length; ++i) {
            CRM.payment.submitButtons[i].setAttribute('disabled', true);
          }
        }
      }

      return true;
    },

    bindSubmitButtons: function() {
      if (CRM.payment.form === null) {
        return;
      }

      CRM.payment.getBillingSubmit();
      CRM.payment.setBillingFieldsRequiredForJQueryValidate();
      CRM.payment.addHandlerNonPaymentSubmitButtons();
      CRM.payment.addSupportForCiviDiscount();

      if (CRM.payment.submitButtons !== null) {
        for (let i = 0; i < CRM.payment.submitButtons.length; ++i) {
          CRM.payment.submitButtons[i].removeEventListener('click', this._boundSubmitHandler);
          CRM.payment.submitButtons[i].addEventListener('click', this._boundSubmitHandler);
        }
      }
    },

    bindPaymentProcessorSelector: function() {
      if (CRM.payment.form === null) {
        return;
      }

      var selectors = CRM.payment.form.querySelectorAll('input[name="payment_processor_id"], select[name="payment_processor_id"]');
      for (let i = 0; i < selectors.length; ++i) {
        selectors[i].removeEventListener('change', this._boundSwitchHandler);
        selectors[i].addEventListener('change', this._boundSwitchHandler);
      }
    },

    bindDrupalAjaxRedirectCommand: function() {
      if (typeof Drupal === 'undefined' || !Drupal.AjaxCommands || Drupal.AjaxCommands.prototype.helloassoRedirect) {
        return;
      }

      Drupal.AjaxCommands.prototype.helloassoRedirect = function(ajax, response) {
        if (!response.url) {
          return;
        }

        try {
          window.top.location.href = response.url;
        }
        catch (e) {
          window.location.href = response.url;
        }
      };
    },

    handleReload: function() {
      CRM.payment.debugging(this.name, 'HandleReload');
      CRM.payment.getBillingForm();
      if (CRM.payment.form === null) {
        return;
      }

      this.ensureErrorElement();
      this.cleanupState();
      this.bindSubmitButtons();
      this.bindPaymentProcessorSelector();
      CRM.payment.triggerEvent('crmBillingFormReloadComplete', this.name);
    },

    init: function() {
      this.bindDrupalAjaxRedirectCommand();

      if (typeof CRM.payment === 'undefined' || typeof CRM.vars.helloassoPayment === 'undefined') {
        return;
      }

      this.paymentProcessorID = parseInt(CRM.vars.helloassoPayment.id);
      this._boundSubmitHandler = this.handleSubmit.bind(this);
      this._boundSwitchHandler = this.handleProcessorSwitch.bind(this);

      CRM.payment.registerScript(this.name);

      CRM.$(this.handleReload.bind(this));
      $(document).ajaxComplete(function(event, xhr, settings) {
        if (CRM.payment.isAJAXPaymentForm(settings.url)) {
          script.handleReload();
        }
      });
    }
  };

  script.init();
}(CRM.$, CRM.ts('helloasso-payment-processor')));
