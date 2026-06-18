<?php

/**
 * Synchronizes a successful remote installment-plan cancellation locally.
 */
class CRM_HelloassoPaymentProcessor_InstallmentCancellation
{
    /**
     * @return array{contribution_recur_id:int, installments:int, contributions:int}
     */
    public function synchronize(
        int $paymentProcessorId,
        int $orderId,
        ?int $contributionRecurId = NULL
    ): array {
        $transaction = new CRM_Core_Transaction();
        try {
            $contributionRecurId = $this->resolveContributionRecurId(
                $paymentProcessorId,
                $orderId,
                $contributionRecurId
            );
            if (!$contributionRecurId) {
                throw new RuntimeException('Unable to find the local recurring contribution for the cancelled HelloAsso order.');
            }

            $now = new DateTimeImmutable('now');
            $installmentIds = [];
            $contributionIds = [];
            $dao = CRM_Core_DAO::executeQuery(
                'SELECT id, contribution_id, payment_date, state
                 FROM civicrm_hello_asso_installment
                 WHERE payment_processor_id = %1
                   AND contribution_recur_id = %2
                   AND order_id = %3
                 FOR UPDATE',
                [
                    1 => [$paymentProcessorId, 'Integer'],
                    2 => [$contributionRecurId, 'Integer'],
                    3 => [$orderId, 'Integer'],
                ]
            );
            while ($dao->fetch()) {
                if (!self::isFutureUncollected($dao->state ?: NULL, $dao->payment_date ?: NULL, $now)) {
                    continue;
                }
                $installmentIds[] = (int) $dao->id;
                if ($dao->contribution_id) {
                    $contributionIds[] = (int) $dao->contribution_id;
                }
            }

            $cancelledContributions = $this->cancelPendingContributions($contributionIds);
            $this->cancelInstallments($installmentIds);
            $this->cancelContributionRecur($contributionRecurId);
            $this->stopContributionFollowUps($contributionIds);

            $transaction->commit();

            return [
                'contribution_recur_id' => $contributionRecurId,
                'installments' => count($installmentIds),
                'contributions' => $cancelledContributions,
            ];
        }
        catch (Throwable $e) {
            $transaction->rollback();
            throw $e;
        }
    }

    public static function isFutureUncollected(
        ?string $state,
        ?string $paymentDate,
        DateTimeImmutable $now
    ): bool {
        if (!$paymentDate || CRM_HelloassoPaymentProcessor_PaymentState::outcome((string) $state)
            !== CRM_HelloassoPaymentProcessor_PaymentState::PENDING) {
            return FALSE;
        }

        try {
            return new DateTimeImmutable($paymentDate) > $now;
        }
        catch (Exception $e) {
            return FALSE;
        }
    }

    private function resolveContributionRecurId(
        int $paymentProcessorId,
        int $orderId,
        ?int $contributionRecurId
    ): ?int {
        $params = [
            1 => [$paymentProcessorId, 'Integer'],
            2 => [$orderId, 'Integer'],
        ];
        $recurClause = '';
        if ($contributionRecurId) {
            $params[3] = [$contributionRecurId, 'Integer'];
            $recurClause = ' AND contribution_recur_id = %3';
        }

        $mappedRecurId = CRM_Core_DAO::singleValueQuery(
            'SELECT contribution_recur_id
             FROM civicrm_hello_asso_installment
             WHERE payment_processor_id = %1
               AND order_id = %2' . $recurClause . '
             LIMIT 1',
            $params
        );
        if ($mappedRecurId) {
            return (int) $mappedRecurId;
        }

        if (!$contributionRecurId) {
            return NULL;
        }

        $recurId = CRM_Core_DAO::singleValueQuery(
            'SELECT id
             FROM civicrm_contribution_recur
             WHERE id = %1
               AND payment_processor_id = %2
               AND (processor_id = %3 OR trxn_id = %3)
             LIMIT 1',
            [
                1 => [$contributionRecurId, 'Integer'],
                2 => [$paymentProcessorId, 'Integer'],
                3 => [(string) $orderId, 'String'],
            ]
        );

        return $recurId ? (int) $recurId : NULL;
    }

    private function cancelPendingContributions(array $contributionIds): int
    {
        if (!$contributionIds) {
            return 0;
        }

        $pendingStatusId = CRM_Core_PseudoConstant::getKey(
            'CRM_Contribute_BAO_Contribution',
            'contribution_status_id',
            'Pending'
        );
        $cancelledStatusId = CRM_Core_PseudoConstant::getKey(
            'CRM_Contribute_BAO_Contribution',
            'contribution_status_id',
            'Cancelled'
        );
        if (!$pendingStatusId || !$cancelledStatusId) {
            throw new RuntimeException('Unable to resolve CiviCRM contribution cancellation statuses.');
        }

        $cancelled = 0;
        foreach (array_unique($contributionIds) as $contributionId) {
            $currentStatusId = CRM_Core_DAO::singleValueQuery(
                'SELECT contribution_status_id
                 FROM civicrm_contribution
                 WHERE id = %1
                 FOR UPDATE',
                [1 => [$contributionId, 'Integer']]
            );
            if ((int) $currentStatusId !== (int) $pendingStatusId) {
                continue;
            }

            CRM_Core_DAO::executeQuery(
                'UPDATE civicrm_contribution
                 SET contribution_status_id = %1,
                     cancel_date = NOW()
                 WHERE id = %2
                   AND contribution_status_id = %3',
                [
                    1 => [$cancelledStatusId, 'Integer'],
                    2 => [$contributionId, 'Integer'],
                    3 => [$pendingStatusId, 'Integer'],
                ]
            );
            $cancelled++;
        }

        return $cancelled;
    }

    private function cancelInstallments(array $installmentIds): void
    {
        foreach ($installmentIds as $installmentId) {
            CRM_Core_DAO::executeQuery(
                "UPDATE civicrm_hello_asso_installment
                 SET state = 'Canceled',
                     updated_at = NOW()
                 WHERE id = %1",
                [1 => [$installmentId, 'Integer']]
            );
        }
    }

    private function cancelContributionRecur(int $contributionRecurId): void
    {
        $cancelledStatusId = CRM_Core_PseudoConstant::getKey(
            'CRM_Contribute_BAO_ContributionRecur',
            'contribution_status_id',
            'Cancelled'
        );
        if (!$cancelledStatusId) {
            throw new RuntimeException('Unable to resolve the CiviCRM recurring contribution cancellation status.');
        }

        CRM_Core_DAO::executeQuery(
            'UPDATE civicrm_contribution_recur
             SET contribution_status_id = %1,
                 cancel_date = COALESCE(cancel_date, NOW()),
                 next_sched_contribution_date = NULL,
                 modified_date = NOW()
             WHERE id = %2',
            [
                1 => [$cancelledStatusId, 'Integer'],
                2 => [$contributionRecurId, 'Integer'],
            ]
        );
    }

    private function stopContributionFollowUps(array $contributionIds): void
    {
        if (!$contributionIds || !$this->tableExists('civicrm_hello_asso_metadata')) {
            return;
        }

        foreach (array_unique($contributionIds) as $contributionId) {
            CRM_Core_DAO::executeQuery(
                'UPDATE civicrm_hello_asso_metadata
                 SET sync_next_date = NULL,
                     long_sync_next_date = NULL
                 WHERE contribution_id = %1',
                [1 => [$contributionId, 'Integer']]
            );
        }
    }

    private function tableExists(string $tableName): bool
    {
        $dao = CRM_Core_DAO::executeQuery(
            'SHOW TABLES LIKE %1',
            [1 => [$tableName, 'String']]
        );
        return (bool) $dao->fetch();
    }
}
