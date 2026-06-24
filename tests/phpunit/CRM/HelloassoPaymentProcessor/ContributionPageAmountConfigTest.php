<?php

class CRM_HelloassoPaymentProcessor_ContributionPageAmountConfigTest extends CRM_HelloassoPaymentProcessor_Base_CiviHeadlessTestCase
{
    public function testHelloAssoProcessorTypeSupportsRecurringPayments(): void
    {
        $processorType = civicrm_api3('PaymentProcessorType', 'getsingle', [
            'name' => 'HelloAsso',
        ]);

        $this->assertSame('1', (string) $processorType['is_recur']);
    }
}
