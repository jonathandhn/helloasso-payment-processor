<?php

/**
 * Keeps scheduled installments out of abandoned-cart reminder workflows.
 */
class CRM_HelloassoPaymentProcessor_FutureInstallmentReminderGuard
{
    public function isFuturePendingContribution(int $contributionId): bool
    {
        if ($contributionId < 1 || !$this->tableExists()) {
            return FALSE;
        }

        try {
            $row = CRM_Core_DAO::executeQuery(
                'SELECT installment_number, payment_date, state
                 FROM civicrm_hello_asso_installment
                 WHERE contribution_id = %1
                 LIMIT 1',
                [
                    1 => [$contributionId, 'Integer'],
                ]
            );
            if (!$row->fetch()) {
                return FALSE;
            }

            return self::isFuturePendingInstallment(
                [
                    'installment_number' => $row->installment_number,
                    'payment_date' => $row->payment_date,
                    'state' => $row->state,
                ],
                new DateTimeImmutable('now', new DateTimeZone('UTC'))
            );
        }
        catch (Throwable $e) {
            Civi::log()->warning('HelloAsso future-installment reminder guard failed open: ' . $e->getMessage());
            return FALSE;
        }
    }

    public static function isFuturePendingInstallment(
        array $installment,
        DateTimeImmutable $now
    ): bool {
        if (($installment['state'] ?? NULL) !== 'Pending') {
            return FALSE;
        }

        if ((int) ($installment['installment_number'] ?? 0) < 2) {
            return FALSE;
        }

        if (empty($installment['payment_date'])) {
            return FALSE;
        }

        try {
            $paymentDate = new DateTimeImmutable(
                (string) $installment['payment_date'],
                new DateTimeZone('UTC')
            );
        }
        catch (Exception $e) {
            return FALSE;
        }

        return $paymentDate > $now;
    }

    private function tableExists(): bool
    {
        try {
            $table = CRM_Core_DAO::executeQuery(
                'SHOW TABLES LIKE %1',
                [
                    1 => ['civicrm_hello_asso_installment', 'String'],
                ]
            );
            return (bool) $table->fetch();
        }
        catch (Throwable $e) {
            return FALSE;
        }
    }
}
