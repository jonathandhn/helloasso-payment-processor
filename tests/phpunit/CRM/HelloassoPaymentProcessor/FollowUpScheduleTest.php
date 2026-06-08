<?php

class CRM_HelloassoPaymentProcessor_FollowUpScheduleTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider scheduleProvider
     */
    public function testUsesExpectedSchedule(string $scheme, array $expected): void
    {
        $processor = (new ReflectionClass(CRM_Core_Payment_HelloAsso::class))
            ->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($processor, 'getLongFollowUpDays');
        $method->setAccessible(TRUE);

        $this->assertSame($expected, $method->invoke($processor, $scheme));
    }

    public static function scheduleProvider(): array
    {
        return [
            'ordinary card' => ['card', [14, 45, 90]],
            'ordinary SEPA' => ['sepa', [30, 90, 180]],
            'card installment' => ['installment-card', [1, 7, 30]],
            'SEPA installment' => ['installment-sepa', [9, 15, 30]],
            'refused installment recovery' => ['installment-recovery', [1, 7, 15, 30]],
        ];
    }

    public function testDetectsSepaInstallmentScheme(): void
    {
        $processor = (new ReflectionClass(CRM_Core_Payment_HelloAsso::class))
            ->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($processor, 'detectLongFollowUpScheme');
        $method->setAccessible(TRUE);

        $this->assertSame(
            'installment-sepa',
            $method->invoke($processor, [
                'installmentNumber' => 2,
                'paymentMeans' => 'Sepa',
            ])
        );
    }
}
