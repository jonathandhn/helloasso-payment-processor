<?php

/**
 * Resolves contribution statuses supported by the installed CiviCRM core.
 */
class CRM_HelloassoPaymentProcessor_ContributionStatus
{
    public function getProcessingStatusName(): string
    {
        $pendingStatusId = $this->getStatusId('Pending');
        $inProgressStatusId = $this->getStatusId('In Progress');

        if (
            !$pendingStatusId
            || !$inProgressStatusId
            || !$this->isValidStatusTransition($pendingStatusId, $inProgressStatusId)
        ) {
            return 'Pending';
        }

        return 'In Progress';
    }

    protected function getStatusId(string $statusName): ?int
    {
        $statusId = CRM_Core_PseudoConstant::getKey(
            'CRM_Contribute_BAO_Contribution',
            'contribution_status_id',
            $statusName
        );

        return $statusId ? (int) $statusId : NULL;
    }

    protected function isValidStatusTransition(int $oldStatusId, int $newStatusId): bool
    {
        if (!is_callable(['CRM_Contribute_BAO_Contribution', 'isValidStatusTransition'])) {
            return FALSE;
        }

        return CRM_Contribute_BAO_Contribution::isValidStatusTransition($oldStatusId, $newStatusId);
    }
}
