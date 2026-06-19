<?php

/**
 * @file
 * PHPStan bootstrap: classes absent from static PHP files.
 *
 * In PHPStan 2.x, stubFiles only OVERRIDE existing class definitions.
 * To DECLARE brand-new classes not found in any scanned PHP file, use
 * bootstrapFiles (these are literally require()'d before analysis).
 *
 * This file covers:
 *  - Civi\Api4\*             (generated at CMS runtime, not in vendor source)
 *  - Civi\Checkout\*         (optional Checkout API extension)
 *  - Civi\Afform\Event\*     (optional Afform extension)
 *  - CRM_Civirules_*         (optional CiviRules extension)
 *  - CRM_Donrec_*            (optional DonRec extension)
 *  - CRM_Mjwshared_*         (optional mjwshared extension)
 */

// =============================================================================
// Civi\Api4 — fluent query builder + entity stubs
// =============================================================================

namespace Civi\Api4 {

  if (!class_exists('Civi\Api4\_ApiResult')) {
    /**
     * Result object returned by _ApiRequest::execute().
     * Implements iterable and provides first()/single() convenience methods.
     *
     * @implements \IteratorAggregate<int, array<string, mixed>>
     */
    class _ApiResult implements \IteratorAggregate, \Countable {
      /** @return \ArrayIterator<int, array<string, mixed>> */
      public function getIterator(): \ArrayIterator {
        return new \ArrayIterator([]);
      }

      public function count(): int {
        return 0;
      }

      /** @return array<string, mixed>|null */
      public function first(): ?array {
        return NULL;
      }

      /** @return array<string, mixed> */
      public function single(): array {
        return [];
      }

      /** @return mixed */
      public function column(string $field) {
        return NULL;
      }
    }
  }

  if (!class_exists('Civi\Api4\_ApiRequest')) {
    /**
     * Fluent query builder returned by all Api4 entity factory methods.
     */
    class _ApiRequest {
      /** @return static */
      public function addWhere(string $fieldName, string $op, mixed $value = NULL): static {
        return $this;
      }

      /** @return static */
      public function addValue(string $fieldName, mixed $value): static {
        return $this;
      }

      /** @return static */
      public function addOrderBy(string $fieldName, string $direction = 'ASC'): static {
        return $this;
      }

      /** @return static */
      public function setLimit(int $limit): static {
        return $this;
      }

      /**
       * @param string ...$fields
       * @return static
       */
      public function addSelect(string ...$fields): static {
        return $this;
      }

      /**
       * @param array<int, string> $select
       * @return static
       */
      public function setSelect(array $select): static {
        return $this;
      }

      /**
       * @param array<string, mixed> $values
       * @return static
       */
      public function setValues(array $values): static {
        return $this;
      }

      public function execute(): _ApiResult {
        return new _ApiResult();
      }

      /** @return static */
      public function selectRowCount(): static {
        return $this;
      }
    }
  }

  if (!class_exists('Civi\Api4\Contribution')) {
    class Contribution {
      public static function get(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
      public static function create(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
      public static function update(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
      public static function delete(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
      public static function save(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
    }
  }

  if (!class_exists('Civi\Api4\ContributionRecur')) {
    class ContributionRecur {
      public static function get(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
      public static function create(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
      public static function update(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
      public static function delete(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
    }
  }

  if (!class_exists('Civi\Api4\PaymentProcessor')) {
    class PaymentProcessor {
      public static function get(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
      public static function create(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
      public static function update(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
    }
  }

  if (!class_exists('Civi\Api4\PaymentprocessorWebhook')) {
    class PaymentprocessorWebhook {
      public static function get(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
      public static function create(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
      public static function update(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
      public static function delete(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
    }
  }

  if (!class_exists('Civi\Api4\OptionValue')) {
    class OptionValue {
      public static function get(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
      public static function create(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
      public static function update(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
    }
  }

  if (!class_exists('Civi\Api4\Contact')) {
    class Contact {
      public static function get(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
      public static function create(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
      public static function update(bool $checkPermissions = TRUE): _ApiRequest { return new _ApiRequest(); }
    }
  }

}

// =============================================================================
// Civi\Checkout — optional Checkout API extension
// =============================================================================

namespace Civi\Checkout {

  if (!interface_exists('Civi\Checkout\CheckoutOptionInterface')) {
    interface CheckoutOptionInterface {}
  }

  if (!interface_exists('Civi\Checkout\AfformCheckoutOptionInterface')) {
    interface AfformCheckoutOptionInterface extends CheckoutOptionInterface {}
  }

  if (!class_exists('Civi\Checkout\CheckoutSession')) {
    class CheckoutSession {
      public const STATUS_CANCEL = 'cancel';
      public const STATUS_SUCCESS = 'success';
      public const STATUS_FAIL = 'fail';
      public const STATUS_PENDING = 'pending';

      public function getContributionId(): int { return 0; }
      public function isTestMode(): bool { return FALSE; }
      public function getLandingUrl(): string { return ''; }

      /** @return mixed */
      public function getCheckoutParam(string $key) { return NULL; }

      public function setResponseItem(string $key, mixed $value): void {}

      public function success(): void {}
      public function cancel(): void {}
      public function fail(): void {}
      public function pending(): void {}
    }
  }

}

// =============================================================================
// Civi\Afform\Event — optional Afform extension
// =============================================================================

namespace Civi\Afform\Event {

  if (!class_exists('Civi\Afform\Event\AfformValidateEvent')) {
    class AfformValidateEvent {}
  }

}

// =============================================================================
// Global namespace: CRM_Civirules_* and other optional deps
// =============================================================================

namespace {

  if (!class_exists('CRM_Civirules_BAO_Rule')) {
    class CRM_Civirules_BAO_Rule {}
  }

  if (!class_exists('CRM_Civirules_TriggerData_TriggerData')) {
    class CRM_Civirules_TriggerData_TriggerData {
      /** @return array<string, mixed> */
      public function getEntityData(string $entity): array { return []; }

      /** @return mixed */
      public function getEntityId() { return NULL; }
    }
  }

  if (!class_exists('CRM_Civirules_Condition')) {
    /**
     * CiviRules condition base class.
     * Methods return mixed to allow covariant narrowing in concrete subclasses.
     */
    class CRM_Civirules_Condition {
      /** @param array<string, mixed> $conditionParams */
      public function setConditionParams(array $conditionParams): void {}

      /** @return mixed */
      public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
        return TRUE;
      }

      /** @return mixed */
      public function doesConditionMatch(
        CRM_Civirules_TriggerData_TriggerData $triggerData,
        CRM_Civirules_BAO_Rule $rule,
      ) {
        return TRUE;
      }

      /** @return mixed */
      public function doesWorkWithTrigger(
        CRM_Civirules_Trigger $trigger,
        CRM_Civirules_BAO_Rule $rule,
      ) {
        return TRUE;
      }

      /** @return mixed */
      public function getExtraDataInputUrl(int $ruleConditionId) {
        return FALSE;
      }

      /** @return mixed */
      public function userFriendlyConditionParams() {
        return '';
      }
    }
  }

  if (!class_exists('CRM_Civirules_Trigger')) {
    class CRM_Civirules_Trigger {
      public function doesProvideEntity(string $entity): bool { return FALSE; }
    }
  }

  if (!class_exists('CRM_Civirules_Utils_Upgrader')) {
    class CRM_Civirules_Utils_Upgrader {
      public static function insertConditionsFromJson(string $jsonFile): void {}
    }
  }

  if (!class_exists('CRM_Donrec_Logic_Settings')) {
    class CRM_Donrec_Logic_Settings {
      /** @return mixed */
      public static function get(string $key) { return NULL; }

      /** @param mixed ...$args */
      public static function validateContribution(mixed ...$args): bool { return TRUE; }
    }
  }

  if (!class_exists('CRM_Mjwshared_Form_PaymentRefund')) {
    class CRM_Mjwshared_Form_PaymentRefund {}
  }

}
