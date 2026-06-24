<?php

/**
 * Verifie que la contribution d'ancrage d'un echeancier est ramenee
 * au premier terme reellement attendu.
 *
 */
#[\PHPUnit\Framework\Attributes\Group('headless')]
class CRM_HelloassoPaymentProcessor_InstallmentAnchorContributionIntegrationTest
    extends CRM_HelloassoPaymentProcessor_Base_CiviHeadlessTestCase
{
    public function testPreparedInstallmentAmountsRealignAnchorContributionAndLineItems(): void
    {
        $processorId = $this->createTestProcessor();
        $contactId = $this->createTestContact();
        $recurId = $this->createTestRecur($processorId, ['contact_id' => $contactId]);
        $contributionId = $this->createTestContribution($contactId, $processorId, [
            'total_amount' => 10.00,
            'contribution_status_id' => 'Pending',
            'contribution_recur_id' => $recurId,
        ]);
        \Civi\Api4\LineItem::delete(FALSE)
            ->addWhere('contribution_id', '=', $contributionId)
            ->execute();

        \Civi\Api4\LineItem::create(FALSE)
            ->setValues([
                'entity_table' => 'civicrm_contribution',
                'entity_id' => $contributionId,
                'contribution_id' => $contributionId,
                'label' => 'Montant de la contribution',
                'qty' => 1,
                'unit_price' => 10.00,
                'line_total' => 10.00,
                'financial_type_id' => 1,
            ])
            ->execute();

        $processorArray = civicrm_api3('PaymentProcessor', 'getsingle', ['id' => $processorId]);
        $processor = new CRM_Core_Payment_HelloAsso('test', $processorArray);
        $method = new ReflectionMethod($processor, 'synchronizeInitialInstallmentContributionAmountShape');
        $method->setAccessible(TRUE);

        $method->invoke(
            $processor,
            $contributionId,
            500
        );

        $contribution = civicrm_api3('Contribution', 'getsingle', ['id' => $contributionId]);
        $lineItems = \Civi\Api4\LineItem::get(FALSE)
            ->addSelect('unit_price', 'line_total')
            ->addWhere('entity_table', '=', 'civicrm_contribution')
            ->addWhere('entity_id', '=', $contributionId)
            ->execute()
            ->indexBy('id');
        $lineItemRows = iterator_to_array($lineItems);

        $this->assertSame(5.00, (float) $contribution['total_amount']);
        $this->assertNotEmpty($lineItemRows);
        $this->assertSame(
            5.00,
            round(array_sum(array_map(
                static fn(array $lineItem): float => (float) $lineItem['line_total'],
                $lineItemRows
            )), 2)
        );
    }

    public function testNonRecurrentMixedContributionKeepsDonationAndMembershipShape(): void
    {
        $processorId = $this->createTestProcessor();
        $contactId = $this->createTestContact();
        $contributionId = $this->createTestContribution($contactId, $processorId, [
            'total_amount' => 150.00,
            'contribution_status_id' => 'Pending',
        ]);
        \Civi\Api4\LineItem::delete(FALSE)
            ->addWhere('contribution_id', '=', $contributionId)
            ->execute();

        \Civi\Api4\LineItem::create(FALSE)
            ->setValues([
                'entity_table' => 'civicrm_contribution',
                'entity_id' => $contributionId,
                'contribution_id' => $contributionId,
                'label' => 'Don',
                'qty' => 1,
                'unit_price' => 110.00,
                'line_total' => 110.00,
                'financial_type_id' => 1,
            ])
            ->execute();
        \Civi\Api4\LineItem::create(FALSE)
            ->setValues([
                'entity_table' => 'civicrm_membership',
                'entity_id' => $contributionId,
                'contribution_id' => $contributionId,
                'label' => 'Cotisation',
                'qty' => 1,
                'unit_price' => 40.00,
                'line_total' => 40.00,
                'financial_type_id' => 1,
            ])
            ->execute();

        $processorArray = civicrm_api3('PaymentProcessor', 'getsingle', ['id' => $processorId]);
        $processor = new CRM_Core_Payment_HelloAsso('test', $processorArray);
        $method = new ReflectionMethod($processor, 'synchronizeInitialInstallmentContributionAmountShape');
        $method->setAccessible(TRUE);

        $method->invoke(
            $processor,
            $contributionId,
            15000
        );

        $contribution = civicrm_api3('Contribution', 'getsingle', ['id' => $contributionId]);
        $lineItems = \Civi\Api4\LineItem::get(FALSE)
            ->addSelect('entity_table', 'unit_price', 'line_total')
            ->addWhere('contribution_id', '=', $contributionId)
            ->addOrderBy('id', 'ASC')
            ->execute();
        $lineItemRows = iterator_to_array($lineItems);

        $this->assertSame(150.00, (float) $contribution['total_amount']);
        $this->assertCount(2, $lineItemRows);
        $this->assertSame('civicrm_contribution', $lineItemRows[0]['entity_table']);
        $this->assertSame(110.00, (float) $lineItemRows[0]['unit_price']);
        $this->assertSame(110.00, (float) $lineItemRows[0]['line_total']);
        $this->assertSame('civicrm_membership', $lineItemRows[1]['entity_table']);
        $this->assertSame(40.00, (float) $lineItemRows[1]['unit_price']);
        $this->assertSame(40.00, (float) $lineItemRows[1]['line_total']);
    }

    public function testRecurrentMixedContributionAllocatesEveryBusinessLineAcrossInstallments(): void
    {
        $processorId = $this->createTestProcessor();
        $contactId = $this->createTestContact();
        $recurId = $this->createTestRecur($processorId, [
            'contact_id' => $contactId,
            'amount' => 60.00,
            'installments' => 3,
        ]);
        $anchorContributionId = $this->createTestContribution($contactId, $processorId, [
            'total_amount' => 180.00,
            'contribution_status_id' => 'Pending',
            'contribution_recur_id' => $recurId,
        ]);
        \Civi\Api4\LineItem::delete(FALSE)
            ->addWhere('contribution_id', '=', $anchorContributionId)
            ->execute();

        $this->createMixedLineItems($anchorContributionId);

        $allocation = new CRM_HelloassoPaymentProcessor_InstallmentLineItemAllocation();
        $this->assertTrue($allocation->capturePlan(
            $recurId,
            $anchorContributionId,
            [6000, 6000, 6000]
        ));

        $processorArray = civicrm_api3('PaymentProcessor', 'getsingle', ['id' => $processorId]);
        $processor = new CRM_Core_Payment_HelloAsso('test', $processorArray);
        $method = new ReflectionMethod($processor, 'synchronizeInitialInstallmentContributionAmountShape');
        $method->setAccessible(TRUE);

        $installmentContributionIds = [$anchorContributionId];
        for ($installmentNumber = 2; $installmentNumber <= 3; $installmentNumber++) {
            $contributionId = $this->createTestContribution($contactId, $processorId, [
                'total_amount' => 180.00,
                'contribution_status_id' => 'Pending',
                'contribution_recur_id' => $recurId,
            ]);
            \Civi\Api4\LineItem::delete(FALSE)
                ->addWhere('contribution_id', '=', $contributionId)
                ->execute();
            $this->createMixedLineItems($contributionId, $anchorContributionId);
            $installmentContributionIds[] = $contributionId;
        }

        $totalsByEntityTable = [
            'civicrm_contribution' => 0.0,
            'civicrm_membership' => 0.0,
            'civicrm_participant' => 0.0,
        ];
        foreach ($installmentContributionIds as $index => $contributionId) {
            $method->invoke($processor, $contributionId, 6000, $index + 1);

            $contribution = civicrm_api3('Contribution', 'getsingle', ['id' => $contributionId]);
            $this->assertSame(60.00, (float) $contribution['total_amount']);

            $lineItems = \Civi\Api4\LineItem::get(FALSE)
                ->addSelect('entity_table', 'line_total')
                ->addWhere('contribution_id', '=', $contributionId)
                ->addOrderBy('id', 'ASC')
                ->execute();
            $lineItemRows = iterator_to_array($lineItems);

            $this->assertCount(3, $lineItemRows);
            $this->assertSame(
                60.00,
                round(array_sum(array_map(
                    static fn(array $lineItem): float => (float) $lineItem['line_total'],
                    $lineItemRows
                )), 2)
            );
            foreach ($lineItemRows as $lineItem) {
                $totalsByEntityTable[$lineItem['entity_table']] += (float) $lineItem['line_total'];
            }
        }

        $this->assertSame(90.00, round($totalsByEntityTable['civicrm_contribution'], 2));
        $this->assertSame(40.00, round($totalsByEntityTable['civicrm_membership'], 2));
        $this->assertSame(50.00, round($totalsByEntityTable['civicrm_participant'], 2));
    }

    private function createMixedLineItems(int $contributionId, ?int $businessEntityId = NULL): void
    {
        $businessEntityId ??= $contributionId;
        foreach ([
            ['civicrm_contribution', $contributionId, 'Don', 90.00],
            ['civicrm_membership', $businessEntityId, 'Cotisation', 40.00],
            ['civicrm_participant', $businessEntityId, 'Billet', 50.00],
        ] as [$entityTable, $entityId, $label, $amount]) {
            CRM_Core_DAO::executeQuery(
                'INSERT INTO civicrm_line_item
                    (entity_table, entity_id, contribution_id, label, qty,
                     unit_price, line_total, financial_type_id)
                 VALUES (%1, %2, %3, %4, 1, %5, %5, 1)',
                [
                    1 => [$entityTable, 'String'],
                    2 => [$entityId, 'Integer'],
                    3 => [$contributionId, 'Integer'],
                    4 => [$label, 'String'],
                    5 => [$amount, 'Money'],
                ]
            );
        }
    }
}
