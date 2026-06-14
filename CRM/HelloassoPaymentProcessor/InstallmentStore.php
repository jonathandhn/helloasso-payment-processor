<?php

/**
 * Persists the mapping between HelloAsso installments and CiviCRM contributions.
 */
class CRM_HelloassoPaymentProcessor_InstallmentStore
{
    private const TABLE_NAME = 'civicrm_hello_asso_installment';

    public function tableExists(): bool
    {
        $table = CRM_Core_DAO::executeQuery('SHOW TABLES LIKE %1', [
            1 => [self::TABLE_NAME, 'String'],
        ]);
        return (bool) $table->fetch();
    }

    public function findContributionId(
        int $paymentProcessorId,
        int $paymentId,
        int $orderId,
        int $installmentNumber
    ): ?int {
        $contributionId = CRM_Core_DAO::singleValueQuery(
            'SELECT contribution_id
             FROM ' . self::TABLE_NAME . '
             WHERE payment_processor_id = %1
               AND (
                 helloasso_payment_id = %2
                 OR (order_id = %3 AND installment_number = %4)
               )
             LIMIT 1',
            [
                1 => [$paymentProcessorId, 'Integer'],
                2 => [$paymentId, 'Integer'],
                3 => [$orderId, 'Integer'],
                4 => [$installmentNumber, 'Integer'],
            ]
        );

        return $contributionId ? (int) $contributionId : NULL;
    }

    /**
     * Insert the installment identity and lock its row for contribution creation.
     *
     * @return array{id: int, contribution_id: int|null}
     */
    public function claim(
        int $paymentProcessorId,
        int $contributionRecurId,
        array $identity
    ): array {
        $params = [
            1 => [$paymentProcessorId, 'Integer'],
            2 => [$contributionRecurId, 'Integer'],
            4 => [$identity['order_id'], 'Integer'],
            5 => [$identity['installment_number'], 'Integer'],
            6 => [$identity['payment_id'], 'Integer'],
        ];
        $checkoutIntentSql = $this->nullableParameter(
            $params,
            3,
            $identity['checkout_intent_id'],
            'Integer'
        );
        $amountSql = $this->nullableParameter($params, 7, $identity['amount'], 'Integer');
        $paymentDateSql = $this->nullableParameter($params, 8, $identity['payment_date'], 'String');
        $stateSql = $this->nullableParameter($params, 9, $identity['state'], 'String');

        CRM_Core_DAO::executeQuery(
            'INSERT INTO ' . self::TABLE_NAME . ' (
               payment_processor_id,
               contribution_recur_id,
               checkout_intent_id,
               order_id,
               installment_number,
               helloasso_payment_id,
               amount,
               payment_date,
               state,
               created_at,
               updated_at
             ) VALUES (%1, %2, ' . $checkoutIntentSql . ', %4, %5, %6, ' . $amountSql . ', ' . $paymentDateSql . ', ' . $stateSql . ', NOW(), NOW())
             ON DUPLICATE KEY UPDATE
               checkout_intent_id = COALESCE(VALUES(checkout_intent_id), checkout_intent_id),
               helloasso_payment_id = VALUES(helloasso_payment_id),
               amount = COALESCE(VALUES(amount), amount),
               payment_date = COALESCE(VALUES(payment_date), payment_date),
               state = COALESCE(VALUES(state), state),
               updated_at = NOW()',
            $params
        );

        $row = CRM_Core_DAO::executeQuery(
            'SELECT id, contribution_id
             FROM ' . self::TABLE_NAME . '
             WHERE payment_processor_id = %1
               AND (
                 helloasso_payment_id = %2
                 OR (order_id = %3 AND installment_number = %4)
               )
             LIMIT 1
             FOR UPDATE',
            [
                1 => [$paymentProcessorId, 'Integer'],
                2 => [$identity['payment_id'], 'Integer'],
                3 => [$identity['order_id'], 'Integer'],
                4 => [$identity['installment_number'], 'Integer'],
            ]
        );

        if (!$row->fetch()) {
            throw new RuntimeException('Unable to lock the HelloAsso installment identity.');
        }

        return [
            'id' => (int) $row->id,
            'contribution_id' => $row->contribution_id ? (int) $row->contribution_id : NULL,
        ];
    }

    public function attachContribution(int $rowId, int $contributionId): void
    {
        CRM_Core_DAO::executeQuery(
            'UPDATE ' . self::TABLE_NAME . '
             SET contribution_id = %1,
                 updated_at = NOW()
             WHERE id = %2',
            [
                1 => [$contributionId, 'Integer'],
                2 => [$rowId, 'Integer'],
            ]
        );
    }

    public function updateState(int $paymentProcessorId, array $identity): void
    {
        $params = [
            4 => [$paymentProcessorId, 'Integer'],
            5 => [$identity['payment_id'], 'Integer'],
            6 => [$identity['order_id'], 'Integer'],
            7 => [$identity['installment_number'], 'Integer'],
        ];
        $amountSql = $this->nullableParameter($params, 1, $identity['amount'], 'Integer');
        $paymentDateSql = $this->nullableParameter($params, 2, $identity['payment_date'], 'String');
        $stateSql = $this->nullableParameter($params, 3, $identity['state'], 'String');

        CRM_Core_DAO::executeQuery(
            'UPDATE ' . self::TABLE_NAME . '
             SET amount = COALESCE(' . $amountSql . ', amount),
                 payment_date = COALESCE(' . $paymentDateSql . ', payment_date),
                 state = COALESCE(' . $stateSql . ', state),
                 updated_at = NOW()
             WHERE payment_processor_id = %4
               AND (
                 helloasso_payment_id = %5
                 OR (order_id = %6 AND installment_number = %7)
               )',
            $params
        );
    }

    protected function nullableParameter(
        array &$params,
        int $index,
        mixed $value,
        string $type
    ): string {
        if ($value === NULL) {
            return 'NULL';
        }

        $params[$index] = [$value, $type];
        return '%' . $index;
    }
}
