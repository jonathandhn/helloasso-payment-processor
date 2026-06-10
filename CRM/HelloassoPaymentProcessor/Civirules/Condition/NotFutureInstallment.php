<?php

if (class_exists('CRM_Civirules_Condition')) {

/**
 * Optional CiviRules condition excluding scheduled HelloAsso installments.
 *
 * This file is loaded only when CiviRules instantiates the registered condition.
 */
class CRM_HelloassoPaymentProcessor_Civirules_Condition_NotFutureInstallment extends CRM_Civirules_Condition
{
    public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData)
    {
        $contributionId = $this->getContributionId($triggerData);
        if (!$contributionId) {
            return TRUE;
        }

        try {
            $guard = new CRM_HelloassoPaymentProcessor_FutureInstallmentReminderGuard();
            return !$guard->isFuturePendingContribution($contributionId);
        }
        catch (Throwable $e) {
            Civi::log()->warning('HelloAsso optional CiviRules condition failed open: ' . $e->getMessage());
            return TRUE;
        }
    }

    /**
     * @param int $ruleConditionId
     */
    public function getExtraDataInputUrl($ruleConditionId)
    {
        return FALSE;
    }

    public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule)
    {
        return $trigger->doesProvideEntity('Contribution');
    }

    public function userFriendlyConditionParams()
    {
        return E::ts('Contribution is not a future scheduled HelloAsso installment.');
    }

    private function getContributionId(CRM_Civirules_TriggerData_TriggerData $triggerData): int
    {
        $contribution = $triggerData->getEntityData('Contribution');
        foreach (['id', 'contribution_id'] as $key) {
            if (!empty($contribution[$key])) {
                return (int) $contribution[$key];
            }
        }

        return (int) $triggerData->getEntityId();
    }
}

}
