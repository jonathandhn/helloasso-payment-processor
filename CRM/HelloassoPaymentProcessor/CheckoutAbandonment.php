<?php

/**
 * Expires finite plans whose HelloAsso checkout was never completed.
 */
class CRM_HelloassoPaymentProcessor_CheckoutAbandonment
{
    public const EXPIRATION_MINUTES = 45;

    public static function isExpired(
        ?string $originDate,
        DateTimeImmutable $now,
        bool $hasPayments
    ): bool {
        if ($hasPayments || !$originDate) {
            return FALSE;
        }

        try {
            $origin = new DateTimeImmutable($originDate);
        }
        catch (Exception $e) {
            return FALSE;
        }

        return $origin->modify('+' . self::EXPIRATION_MINUTES . ' minutes') <= $now;
    }

    public function expire(int $contributionId, int $paymentProcessorId): bool
    {
        $pendingStatusId = CRM_Core_PseudoConstant::getKey(
            'CRM_Contribute_BAO_Contribution',
            'contribution_status_id',
            'Pending'
        );
        $cancelledContributionStatusId = CRM_Core_PseudoConstant::getKey(
            'CRM_Contribute_BAO_Contribution',
            'contribution_status_id',
            'Cancelled'
        );
        $cancelledRecurStatusId = CRM_Core_PseudoConstant::getKey(
            'CRM_Contribute_BAO_ContributionRecur',
            'contribution_status_id',
            'Cancelled'
        );
        if (!$pendingStatusId || !$cancelledContributionStatusId || !$cancelledRecurStatusId) {
            throw new RuntimeException('Unable to resolve CiviCRM checkout abandonment statuses.');
        }

        $transaction = new CRM_Core_Transaction();
        try {
            $row = CRM_Core_DAO::executeQuery(
                'SELECT c.contribution_recur_id,
                        c.contribution_status_id,
                        cr.payment_processor_id,
                        cr.processor_id,
                        cr.trxn_id
                 FROM civicrm_contribution c
                 INNER JOIN civicrm_contribution_recur cr ON cr.id = c.contribution_recur_id
                 WHERE c.id = %1
                 FOR UPDATE',
                [1 => [$contributionId, 'Integer']]
            );
            if (
                !$row->fetch()
                || (int) $row->contribution_status_id !== (int) $pendingStatusId
                || (int) $row->payment_processor_id !== $paymentProcessorId
                || !empty($row->processor_id)
                || !empty($row->trxn_id)
            ) {
                $transaction->commit();
                return FALSE;
            }

            $recurId = (int) $row->contribution_recur_id;
            $mappedInstallments = (int) CRM_Core_DAO::singleValueQuery(
                'SELECT COUNT(*)
                 FROM civicrm_hello_asso_installment
                 WHERE contribution_recur_id = %1',
                [1 => [$recurId, 'Integer']]
            );
            if ($mappedInstallments > 0) {
                $transaction->commit();
                return FALSE;
            }

            CRM_Core_DAO::executeQuery(
                'UPDATE civicrm_contribution
                 SET contribution_status_id = %1,
                     cancel_date = NOW()
                 WHERE id = %2
                   AND contribution_status_id = %3',
                [
                    1 => [$cancelledContributionStatusId, 'Integer'],
                    2 => [$contributionId, 'Integer'],
                    3 => [$pendingStatusId, 'Integer'],
                ]
            );
            CRM_Core_DAO::executeQuery(
                'UPDATE civicrm_contribution_recur
                 SET contribution_status_id = %1,
                     cancel_date = COALESCE(cancel_date, NOW()),
                     next_sched_contribution_date = NULL,
                     modified_date = NOW()
                 WHERE id = %2',
                [
                    1 => [$cancelledRecurStatusId, 'Integer'],
                    2 => [$recurId, 'Integer'],
                ]
            );
            CRM_Core_DAO::executeQuery(
                'UPDATE civicrm_hello_asso_metadata
                 SET state = %1,
                     sync_next_date = NULL,
                     long_sync_next_date = NULL
                 WHERE contribution_id = %2',
                [
                    1 => ['Abandoned', 'String'],
                    2 => [$contributionId, 'Integer'],
                ]
            );

            $transaction->commit();
            return TRUE;
        }
        catch (Throwable $e) {
            $transaction->rollback();
            throw $e;
        }
    }

    public function markClassicContribution(int $contributionId, int $paymentProcessorId): bool
    {
        $pendingStatusId = CRM_Core_PseudoConstant::getKey(
            'CRM_Contribute_BAO_Contribution',
            'contribution_status_id',
            'Pending'
        );
        if (!$pendingStatusId) {
            throw new RuntimeException('Unable to resolve the CiviCRM pending contribution status.');
        }

        $transaction = new CRM_Core_Transaction();
        try {
            $row = CRM_Core_DAO::executeQuery(
                'SELECT c.contribution_status_id,
                        c.contribution_recur_id,
                        m.payment_processor_id,
                        m.helloasso_payment_id,
                        m.state
                 FROM civicrm_contribution c
                 INNER JOIN civicrm_hello_asso_metadata m ON m.contribution_id = c.id
                 WHERE c.id = %1
                 FOR UPDATE',
                [1 => [$contributionId, 'Integer']]
            );
            if (
                !$row->fetch()
                || (int) $row->contribution_status_id !== (int) $pendingStatusId
                || !empty($row->contribution_recur_id)
                || (int) $row->payment_processor_id !== $paymentProcessorId
                || !empty($row->helloasso_payment_id)
            ) {
                $transaction->commit();
                return FALSE;
            }

            CRM_Core_DAO::executeQuery(
                'UPDATE civicrm_hello_asso_metadata
                 SET state = %1,
                     sync_next_date = NULL,
                     long_sync_next_date = NULL
                 WHERE contribution_id = %2',
                [
                    1 => ['Abandoned', 'String'],
                    2 => [$contributionId, 'Integer'],
                ]
            );

            $changed = (string) $row->state !== 'Abandoned';
            $transaction->commit();
            return $changed;
        }
        catch (Throwable $e) {
            $transaction->rollback();
            throw $e;
        }
    }
}
