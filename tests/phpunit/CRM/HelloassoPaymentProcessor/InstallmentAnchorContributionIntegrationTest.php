<?php

/**
 * Verifie que la contribution d'ancrage d'un echeancier est ramenee
 * au premier terme reellement attendu.
 *
 * @group headless
 */
class CRM_HelloassoPaymentProcessor_InstallmentAnchorContributionIntegrationTest
    extends CRM_HelloassoPaymentProcessor_Base_CiviHeadlessTestCase
{
    public function testPreparedInstallmentAmountsRealignAnchorContributionAndLineItems(): void
    {
        $processorId = $this->createTestProcessor();
        $contactId = $this->createTestContact();
        $contributionId = $this->createTestContribution($contactId, $processorId, [
            'total_amount' => 10.00,
            'contribution_status_id' => 'Pending',
        ]);

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
}
