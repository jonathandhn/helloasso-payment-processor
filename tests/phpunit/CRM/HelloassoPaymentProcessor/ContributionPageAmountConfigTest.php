<?php

class CRM_HelloassoPaymentProcessor_ContributionPageAmountConfigTest extends CRM_HelloassoPaymentProcessor_Base_CiviHeadlessTestCase
{
    public function testContributionPageAmountConfigAddsRecurringUiForHelloAsso(): void
    {
        $processorId = $this->createTestProcessor([
            'is_test' => 0,
            'is_recur' => 0,
            'name' => 'HelloAsso_live_' . uniqid(),
        ]);

        CRM_Financial_BAO_PaymentProcessor::getAllPaymentProcessors('live', TRUE);

        $form = $this->getMockBuilder(CRM_Core_Form::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_template_vars', 'assign', 'elementExists', 'addElement', 'addCheckBox'])
            ->getMock();

        $form->expects($this->once())
            ->method('get_template_vars')
            ->with('recurringPaymentProcessor')
            ->willReturn([]);

        $form->expects($this->once())
            ->method('assign')
            ->with('recurringPaymentProcessor', [$processorId]);

        $form->expects($this->once())
            ->method('elementExists')
            ->with('is_recur')
            ->willReturn(FALSE);

        $form->expects($this->exactly(3))
            ->method('addElement');

        $form->expects($this->once())
            ->method('addCheckBox')
            ->with(
                'recur_frequency_unit',
                $this->anything(),
                $this->isArray(),
            );

        helloasso_payment_processor_fix_contribution_page_amount_config($form);
    }
}
