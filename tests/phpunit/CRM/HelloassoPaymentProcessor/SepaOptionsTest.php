<?php

class CRM_HelloassoPaymentProcessor_SepaOptionsTest extends \PHPUnit\Framework\TestCase
{
    public function testEnablesSepaInCheckoutPayload(): void
    {
        $this->assertSame(
            ['paymentOptions' => ['enableSepa' => TRUE]],
            CRM_HelloassoPaymentProcessor_SepaOptions::build(TRUE)
        );
    }

    public function testOmitsPaymentOptionsWhenSepaIsDisabled(): void
    {
        $this->assertSame([], CRM_HelloassoPaymentProcessor_SepaOptions::build(FALSE));
    }
}
