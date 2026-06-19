<?php

/**
 * @file
 * PHPStan stubs for global-namespace optional-dependency classes.
 * (CiviRules, DonRec, mjwshared — not autoloaded when extensions are absent)
 */

// ---- CiviRules (optional dependency) ----------------------------------------

abstract class CRM_Civirules_Condition {
  /** @param array<string, mixed> $conditionParams */
  public function setConditionParams(array $conditionParams): void {
  }

  abstract public function doesConditionMatch(
    CRM_Civirules_TriggerData_TriggerData $triggerData,
    CRM_Civirules_BAO_Rule $rule,
  ): bool;
}

abstract class CRM_Civirules_Trigger {
}

class CRM_Civirules_BAO_Rule {
}

class CRM_Civirules_TriggerData_TriggerData {
  /** @return array<string, mixed> */
  public function getEntityData(string $entity): array {
  }
}

class CRM_Civirules_Utils_Upgrader {
}

// ---- DonRec (optional dependency) -------------------------------------------

class CRM_Donrec_Logic_Settings {
  /** @return mixed */
  public static function get(string $key) {
  }
}

// ---- mjwshared (optional dependency) ----------------------------------------

class CRM_Mjwshared_Form_PaymentRefund {
}
