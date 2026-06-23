<?php

/**
 * Persists and applies the line-item allocation of a finite installment plan.
 */
class CRM_HelloassoPaymentProcessor_InstallmentLineItemAllocation
{
    private const TABLE_NAME = 'civicrm_hello_asso_installment_line_item';

    public function tableExists(): bool
    {
        $table = CRM_Core_DAO::executeQuery('SHOW TABLES LIKE %1', [
            1 => [self::TABLE_NAME, 'String'],
        ]);
        return (bool) $table->fetch();
    }

    /**
     * Capture the original order shape and allocate every scheduled payment.
     *
     * Existing plans are immutable: retries must not snapshot an anchor whose
     * line items have already been reduced to the first installment.
     *
     * @param int[] $installmentAmountsCents
     */
    public function capturePlan(
        int $contributionRecurId,
        int $contributionId,
        array $installmentAmountsCents
    ): bool {
        if (
            $contributionRecurId < 1
            || $contributionId < 1
            || $installmentAmountsCents === []
            || min($installmentAmountsCents) < 0
        ) {
            return FALSE;
        }
        if (!$this->tableExists()) {
            throw new RuntimeException('The HelloAsso installment line-item allocation table is missing.');
        }

        $existing = (int) CRM_Core_DAO::singleValueQuery(
            'SELECT COUNT(*)
             FROM ' . self::TABLE_NAME . '
             WHERE contribution_recur_id = %1',
            [1 => [$contributionRecurId, 'Integer']]
        );
        if ($existing > 0) {
            return TRUE;
        }

        $lineItems = $this->loadContributionLineItems($contributionId);
        if ($lineItems === []) {
            return FALSE;
        }

        $weights = array_map(
            static fn(array $lineItem): int => max(0, $lineItem['original_amount']),
            $lineItems
        );
        $planTotal = array_sum($installmentAmountsCents);
        if (array_sum($weights) !== $planTotal) {
            throw new RuntimeException(
                'The HelloAsso installment total does not match the contribution line-item total.'
            );
        }

        $remainingAmounts = $weights;
        $transaction = new CRM_Core_Transaction();
        try {
            foreach ($installmentAmountsCents as $installmentIndex => $installmentAmount) {
                $isLastInstallment = $installmentIndex === array_key_last($installmentAmountsCents);
                $installmentAllocation = $isLastInstallment
                    ? $remainingAmounts
                    : $this->allocateAmountCentsByWeight(
                        $installmentAmount,
                        $remainingAmounts
                    );
                if (array_sum($installmentAllocation) !== $installmentAmount) {
                    throw new RuntimeException('Unable to allocate the HelloAsso installment across line items.');
                }

                foreach ($lineItems as $lineItemIndex => $lineItem) {
                    $allocatedAmount = $installmentAllocation[$lineItemIndex];
                    $params = [
                        1 => [$contributionRecurId, 'Integer'],
                        2 => [$installmentIndex + 1, 'Integer'],
                        3 => [$lineItemIndex + 1, 'Integer'],
                        4 => [$lineItem['id'], 'Integer'],
                        5 => [$lineItem['entity_table'], 'String'],
                        6 => [$lineItem['entity_id'], 'Integer'],
                        11 => [$lineItem['financial_type_id'], 'Integer'],
                        8 => [$lineItem['label'], 'String'],
                        9 => [$lineItem['original_amount'], 'Integer'],
                        10 => [$allocatedAmount, 'Integer'],
                    ];
                    $priceFieldValueSql = 'NULL';
                    if ($lineItem['price_field_value_id'] !== NULL) {
                        $priceFieldValueSql = '%7';
                        $params[7] = [$lineItem['price_field_value_id'], 'Integer'];
                    }
                    CRM_Core_DAO::executeQuery(
                        'INSERT INTO ' . self::TABLE_NAME . ' (
                           contribution_recur_id,
                           installment_number,
                           line_item_ordinal,
                           source_line_item_id,
                           entity_table,
                           entity_id,
                           price_field_value_id,
                           financial_type_id,
                           label,
                           original_amount,
                           allocated_amount,
                           created_at
                         ) VALUES (%1, %2, %3, %4, %5, %6, ' . $priceFieldValueSql . ', %11, %8, %9, %10, NOW())',
                        $params
                    );
                    $remainingAmounts[$lineItemIndex] -= $allocatedAmount;
                }
            }
            $transaction->commit();
        }
        catch (Throwable $e) {
            $transaction->rollback();
            throw $e;
        }

        return TRUE;
    }

    public function hasInstallment(int $contributionRecurId, int $installmentNumber): bool
    {
        if (!$this->tableExists() || $contributionRecurId < 1 || $installmentNumber < 1) {
            return FALSE;
        }

        return (int) CRM_Core_DAO::singleValueQuery(
            'SELECT COUNT(*)
             FROM ' . self::TABLE_NAME . '
             WHERE contribution_recur_id = %1
               AND installment_number = %2',
            [
                1 => [$contributionRecurId, 'Integer'],
                2 => [$installmentNumber, 'Integer'],
            ]
        ) > 0;
    }

    public function applyInstallment(
        int $contributionRecurId,
        int $installmentNumber,
        int $contributionId,
        int $targetAmountCents
    ): bool {
        if (
            !$this->tableExists()
            || $contributionRecurId < 1
            || $installmentNumber < 1
            || $contributionId < 1
            || $targetAmountCents < 0
        ) {
            return FALSE;
        }

        $allocations = [];
        $query = CRM_Core_DAO::executeQuery(
            'SELECT line_item_ordinal,
                    source_line_item_id,
                    entity_table,
                    entity_id,
                    price_field_value_id,
                    financial_type_id,
                    label,
                    allocated_amount
             FROM ' . self::TABLE_NAME . '
             WHERE contribution_recur_id = %1
               AND installment_number = %2
             ORDER BY line_item_ordinal ASC',
            [
                1 => [$contributionRecurId, 'Integer'],
                2 => [$installmentNumber, 'Integer'],
            ]
        );
        while ($query->fetch()) {
            $allocations[] = [
                'source_line_item_id' => (int) $query->source_line_item_id,
                'entity_table' => (string) $query->entity_table,
                'entity_id' => (int) $query->entity_id,
                'price_field_value_id' => $query->price_field_value_id === NULL
                    ? NULL
                    : (int) $query->price_field_value_id,
                'financial_type_id' => (int) $query->financial_type_id,
                'label' => (string) $query->label,
                'allocated_amount' => (int) $query->allocated_amount,
            ];
        }
        if ($allocations === [] || array_sum(array_column($allocations, 'allocated_amount')) !== $targetAmountCents) {
            return FALSE;
        }

        $lineItems = $this->matchContributionLineItems(
            $this->loadContributionLineItems($contributionId),
            $allocations,
            $contributionId
        );
        if ($lineItems === NULL) {
            return FALSE;
        }

        $transaction = new CRM_Core_Transaction();
        try {
            CRM_Core_DAO::executeQuery(
                'UPDATE civicrm_contribution
                 SET total_amount = %1
                 WHERE id = %2',
                [
                    1 => [round($targetAmountCents / 100, 2), 'Money'],
                    2 => [$contributionId, 'Integer'],
                ]
            );

            foreach ($lineItems as $index => $lineItem) {
                $lineTotal = round($allocations[$index]['allocated_amount'] / 100, 2);
                $qty = abs($lineItem['qty']) > 0.0000001 ? $lineItem['qty'] : 1.0;
                $unitPrice = round($lineTotal / $qty, 2);

                CRM_Core_DAO::executeQuery(
                    'UPDATE civicrm_line_item
                     SET line_total = %1,
                         unit_price = %2
                     WHERE id = %3',
                    [
                        1 => [$lineTotal, 'Money'],
                        2 => [$unitPrice, 'Money'],
                        3 => [$lineItem['id'], 'Integer'],
                    ]
                );
                CRM_Core_DAO::executeQuery(
                    'UPDATE civicrm_financial_item
                     SET amount = %1
                     WHERE entity_table = %2
                       AND entity_id = %3',
                    [
                        1 => [$lineTotal, 'Money'],
                        2 => ['civicrm_line_item', 'String'],
                        3 => [$lineItem['id'], 'Integer'],
                    ]
                );
            }
            $transaction->commit();
        }
        catch (Throwable $e) {
            $transaction->rollback();
            throw $e;
        }

        return TRUE;
    }

    /**
     * @return array<int, array{
     *   id:int,
     *   entity_table:string,
     *   entity_id:int,
     *   price_field_value_id:int|null,
     *   financial_type_id:int,
     *   label:string,
     *   qty:float,
     *   original_amount:int
     * }>
     */
    private function loadContributionLineItems(int $contributionId): array
    {
        $lineItems = [];
        $query = CRM_Core_DAO::executeQuery(
            'SELECT id,
                    entity_table,
                    entity_id,
                    price_field_value_id,
                    financial_type_id,
                    label,
                    qty,
                    line_total
             FROM civicrm_line_item
             WHERE contribution_id = %1
             ORDER BY id ASC',
            [1 => [$contributionId, 'Integer']]
        );
        while ($query->fetch()) {
            $lineItems[] = [
                'id' => (int) $query->id,
                'entity_table' => (string) $query->entity_table,
                'entity_id' => (int) $query->entity_id,
                'price_field_value_id' => $query->price_field_value_id === NULL
                    ? NULL
                    : (int) $query->price_field_value_id,
                'financial_type_id' => (int) $query->financial_type_id,
                'label' => (string) $query->label,
                'qty' => (float) $query->qty,
                'original_amount' => (int) round(((float) $query->line_total) * 100),
            ];
        }
        return $lineItems;
    }

    private function matchesAllocation(array $lineItem, array $allocation, int $contributionId): bool
    {
        if ($lineItem['id'] === $allocation['source_line_item_id']) {
            return TRUE;
        }

        $entityMatches = $lineItem['entity_table'] === 'civicrm_contribution'
            ? $lineItem['entity_id'] === $contributionId
            : $lineItem['entity_id'] === $allocation['entity_id'];

        return $lineItem['entity_table'] === $allocation['entity_table']
            && $entityMatches
            && $lineItem['price_field_value_id'] === $allocation['price_field_value_id']
            && $lineItem['financial_type_id'] === $allocation['financial_type_id']
            && $lineItem['label'] === $allocation['label']
            && $lineItem['id'] !== $allocation['source_line_item_id'];
    }

    /**
     * @return array<int, array>|null
     */
    private function matchContributionLineItems(
        array $lineItems,
        array $allocations,
        int $contributionId
    ): ?array {
        if (count($lineItems) !== count($allocations)) {
            return NULL;
        }

        $matched = [];
        $usedLineItemIds = [];
        foreach ($allocations as $allocation) {
            $match = NULL;
            foreach ($lineItems as $lineItem) {
                if (
                    isset($usedLineItemIds[$lineItem['id']])
                    || !$this->matchesAllocation($lineItem, $allocation, $contributionId)
                ) {
                    continue;
                }
                $match = $lineItem;
                break;
            }
            if ($match === NULL) {
                return NULL;
            }
            $matched[] = $match;
            $usedLineItemIds[$match['id']] = TRUE;
        }

        return $matched;
    }

    /**
     * @param int[] $weights
     *
     * @return int[]
     */
    private function allocateAmountCentsByWeight(int $targetAmountCents, array $weights): array
    {
        $sum = array_sum($weights);
        if ($sum <= 0) {
            $allocated = array_fill(0, count($weights), 0);
            $allocated[0] = $targetAmountCents;
            return $allocated;
        }

        $allocated = [];
        $remainders = [];
        $allocatedTotal = 0;
        foreach ($weights as $index => $weight) {
            $numerator = $targetAmountCents * $weight;
            $allocated[$index] = intdiv($numerator, $sum);
            $remainders[$index] = $numerator % $sum;
            $allocatedTotal += $allocated[$index];
        }

        arsort($remainders, SORT_NUMERIC);
        foreach (array_keys($remainders) as $index) {
            if ($allocatedTotal >= $targetAmountCents) {
                break;
            }
            $allocated[$index]++;
            $allocatedTotal++;
        }
        ksort($allocated);

        return array_values($allocated);
    }
}
