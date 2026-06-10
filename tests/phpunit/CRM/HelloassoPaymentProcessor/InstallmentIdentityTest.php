<?php


use PHPUnit\Framework\Attributes\DataProvider;
class CRM_HelloassoPaymentProcessor_InstallmentIdentityTest extends \PHPUnit\Framework\TestCase
{
    public function testExtractsStableIdentityFromWebhookPayment(): void
    {
        $identity = CRM_HelloassoPaymentProcessor_InstallmentIdentity::fromPayment([
            'id' => 86429282,
            'amount' => 1000,
            'date' => '2026-06-06T12:21:45+02:00',
            'installmentNumber' => 1,
            'state' => 'Authorized',
        ], [
            'id' => 184897788,
            'checkoutIntentId' => 12345,
        ]);

        $this->assertSame([
            'payment_id' => 86429282,
            'order_id' => 184897788,
            'checkout_intent_id' => 12345,
            'installment_number' => 1,
            'amount' => 1000,
            'payment_date' => '2026-06-06T12:21:45+02:00',
            'state' => 'Authorized',
        ], $identity);
    }

    public function testReadsOrderEmbeddedInPayment(): void
    {
        $identity = CRM_HelloassoPaymentProcessor_InstallmentIdentity::fromPayment([
            'id' => '86429283',
            'installmentNumber' => '2',
            'order' => [
                'id' => '184897788',
                'checkoutIntentId' => '12345',
            ],
        ]);

        $this->assertSame(86429283, $identity['payment_id']);
        $this->assertSame(184897788, $identity['order_id']);
        $this->assertSame(2, $identity['installment_number']);
    }

    /**
     */
    #[DataProvider("incompleteIdentityProvider")]
    public function testRejectsIncompleteIdentity(array $payment, array $order): void
    {
        $this->assertNull(
            CRM_HelloassoPaymentProcessor_InstallmentIdentity::fromPayment($payment, $order)
        );
    }

    public static function incompleteIdentityProvider(): array
    {
        return [
            'missing payment' => [
                ['installmentNumber' => 1],
                ['id' => 10],
            ],
            'missing order' => [
                ['id' => 20, 'installmentNumber' => 1],
                [],
            ],
            'missing installment number' => [
                ['id' => 20],
                ['id' => 10],
            ],
            'zero installment number' => [
                ['id' => 20, 'installmentNumber' => 0],
                ['id' => 10],
            ],
        ];
    }
}
