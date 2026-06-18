(function(angular) {
  angular.module('crmHelloassoPaymentProcessor').component('helloassoInstallments', {
    require: {
      checkout: '^^afCheckoutBlock',
      field: '^^afField'
    },
    templateUrl: '~/crmHelloassoPaymentProcessor/installmentsField.html',
    controller: function($scope) {
      const ts = $scope.ts = CRM.ts('helloasso-payment-processor');
      const config = CRM.vars.helloassoAfform || {};

      this.$onInit = () => {
        this.supportsInstallments = config.supportsInstallments !== false;
        const defn = this.field.defn || {};
        this.enabled = defn.helloasso_installments_enabled === true
          || defn.helloasso_installments_enabled === 1
          || defn.helloasso_installments_enabled === '1';
        if (!this.supportsInstallments) {
          this.enabled = false;
        }
        this.minimum = clamp(defn.helloasso_installments_min, 2, 12, 2);
        this.maximum = clamp(defn.helloasso_installments_max, this.minimum, 12, 12);
        this.options = [
          {id: '', label: ts('Pay in full')}
        ];

        for (let count = this.minimum; count <= this.maximum; count++) {
          this.options.push({
            id: String(count),
            label: ts('%1 monthly installments', {1: count})
          });
        }

        if (!this.enabled) {
          delete this.checkout.checkout_params.is_recur;
          delete this.checkout.checkout_params.helloasso_installments;
          delete this.checkout.checkout_params.helloasso_installments_enabled;
          delete this.checkout.checkout_params.helloasso_installments_min;
          delete this.checkout.checkout_params.helloasso_installments_max;
          return;
        }

        this.checkout.checkout_params.helloasso_installments_enabled = true;
        this.checkout.checkout_params.helloasso_installments_min = this.minimum;
        this.checkout.checkout_params.helloasso_installments_max = this.maximum;

        const selected = Number(this.checkout.checkout_params.helloasso_installments);
        if (selected && (selected < this.minimum || selected > this.maximum)) {
          this.checkout.checkout_params.helloasso_installments = '';
        }

        this.checkout.checkout_params.is_recur =
          this.checkout.checkout_params.helloasso_installments !== '';
      };

      this.onInstallmentsChange = () => {
        this.checkout.checkout_params.is_recur = this.checkout.checkout_params.helloasso_installments !== '';
      };

      function clamp(value, minimum, maximum, fallback) {
        const parsed = Number(value);
        if (!Number.isInteger(parsed)) {
          return fallback;
        }
        return Math.min(maximum, Math.max(minimum, parsed));
      }
    }
  });

  angular.module('crmHelloassoPaymentProcessor').component('helloassoCheckoutAdmin', {
    bindings: {
      node: '='
    },
    templateUrl: '~/crmHelloassoPaymentProcessor/checkoutBlockAdminSettings.html',
    controller: function($scope) {
      $scope.ts = CRM.ts('helloasso-payment-processor');
      this.supportsInstallments = (CRM.vars.helloassoAfform || {}).supportsInstallments !== false;
    }
  });
})(angular);
