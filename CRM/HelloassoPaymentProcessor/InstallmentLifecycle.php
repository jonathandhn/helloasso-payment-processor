<?php

/**
 * Keeps the finite ContributionRecur lifecycle aligned with HelloAsso terms.
 */
class CRM_HelloassoPaymentProcessor_InstallmentLifecycle
{
    /**
     * @param array<int, array{installment_number:int, payment_date:?string, state:?string}> $terms
     *
     * @return array{
     *   status:string,
     *   next_sched_contribution_date:?string,
     *   end_date:?string,
     *   failure_count:int
     * }
     */
    public static function derive(array $terms, int $expectedInstallments, ?string $fallbackNextDate = NULL): array
    {
        usort($terms, static function (array $left, array $right): int {
            return $left['installment_number'] <=> $right['installment_number'];
        });

        $successful = 0;
        $hasRefused = FALSE;
        $hasContested = FALSE;
        $hasTerminalFailure = FALSE;
        $failureCount = 0;
        $lastSuccessfulDate = NULL;
        $nextDate = NULL;

        foreach ($terms as $term) {
            $state = (string) ($term['state'] ?? '');
            $outcome = CRM_HelloassoPaymentProcessor_PaymentState::outcome($state);
            $paymentDate = self::normalizeDate($term['payment_date'] ?? NULL);

            if ($outcome === CRM_HelloassoPaymentProcessor_PaymentState::SUCCESS) {
                $successful++;
                if ($paymentDate !== NULL) {
                    $lastSuccessfulDate = $paymentDate;
                }
                continue;
            }

            if ($state === 'Refused') {
                $hasRefused = TRUE;
                $failureCount++;
            }
            elseif ($outcome === CRM_HelloassoPaymentProcessor_PaymentState::CONTESTED) {
                $hasContested = TRUE;
                $failureCount++;
            }
            elseif ($outcome === CRM_HelloassoPaymentProcessor_PaymentState::FAILED) {
                $hasTerminalFailure = TRUE;
                $failureCount++;
            }

            if ($nextDate === NULL && $paymentDate !== NULL) {
                $nextDate = $paymentDate;
            }
        }

        if ($hasContested) {
            return self::result('Chargeback', NULL, $lastSuccessfulDate, max(1, $failureCount));
        }

        if ($hasTerminalFailure) {
            return self::result('Failed', NULL, $lastSuccessfulDate, max(1, $failureCount));
        }

        if ($hasRefused) {
            return self::result('Overdue', $nextDate, NULL, max(1, $failureCount));
        }

        if ($expectedInstallments > 0 && $successful >= $expectedInstallments) {
            return self::result('Completed', NULL, $lastSuccessfulDate, 0);
        }

        if ($nextDate === NULL && count($terms) < $expectedInstallments) {
            $nextDate = self::normalizeDate($fallbackNextDate);
        }

        return self::result('In Progress', $nextDate, NULL, 0);
    }

    public function synchronize(int $contributionRecurId): void
    {
        $recur = CRM_Core_DAO::executeQuery(
            'SELECT installments, failure_count, next_sched_contribution_date, contribution_status_id
             FROM civicrm_contribution_recur
             WHERE id = %1',
            [1 => [$contributionRecurId, 'Integer']]
        );
        if (!$recur->fetch()) {
            return;
        }
        $cancelledStatusId = CRM_Core_PseudoConstant::getKey(
            'CRM_Contribute_BAO_ContributionRecur',
            'contribution_status_id',
            'Cancelled'
        );
        if ($cancelledStatusId && (int) $recur->contribution_status_id === (int) $cancelledStatusId) {
            return;
        }

        $terms = [];
        $dao = CRM_Core_DAO::executeQuery(
            'SELECT installment_number, payment_date, state
             FROM civicrm_hello_asso_installment
             WHERE contribution_recur_id = %1
             ORDER BY installment_number',
            [1 => [$contributionRecurId, 'Integer']]
        );
        while ($dao->fetch()) {
            $terms[] = [
                'installment_number' => (int) $dao->installment_number,
                'payment_date' => $dao->payment_date ?: NULL,
                'state' => $dao->state ?: NULL,
            ];
        }

        if (!$terms) {
            return;
        }

        $lifecycle = self::derive(
            $terms,
            (int) $recur->installments,
            $recur->next_sched_contribution_date ?: NULL
        );
        $statusId = CRM_Core_PseudoConstant::getKey(
            'CRM_Contribute_BAO_ContributionRecur',
            'contribution_status_id',
            $lifecycle['status']
        );
        if (!$statusId) {
            throw new RuntimeException('Unknown ContributionRecur status: ' . $lifecycle['status']);
        }

        CRM_Core_DAO::executeQuery(
            'UPDATE civicrm_contribution_recur
             SET contribution_status_id = %1,
                 next_sched_contribution_date = %2,
                 end_date = %3,
                 failure_count = %4,
                 modified_date = NOW()
             WHERE id = %5',
            [
                1 => [$statusId, 'Integer'],
                2 => [$lifecycle['next_sched_contribution_date'], 'Timestamp'],
                3 => [$lifecycle['end_date'], 'Timestamp'],
                4 => [$lifecycle['failure_count'], 'Integer'],
                5 => [$contributionRecurId, 'Integer'],
            ]
        );
    }

    private static function normalizeDate(mixed $value): ?string
    {
        if (empty($value)) {
            return NULL;
        }

        try {
            return (new DateTimeImmutable((string) $value))->format('YmdHis');
        }
        catch (Exception $e) {
            return NULL;
        }
    }

    private static function result(string $status, ?string $nextDate, ?string $endDate, int $failureCount): array
    {
        return [
            'status' => $status,
            'next_sched_contribution_date' => $nextDate,
            'end_date' => $endDate,
            'failure_count' => $failureCount,
        ];
    }
}
