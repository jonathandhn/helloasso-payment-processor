<?php

class CRM_HelloassoPaymentProcessor_InstallmentPlanTest extends \PHPUnit\Framework\TestCase
{
    public function testBuildsCheckoutAmountsFromCiviInstallmentAmount(): void
    {
        $plan = CRM_HelloassoPaymentProcessor_InstallmentPlan::buildMonthly(
            2500,
            3,
            1,
            'month',
            new DateTimeImmutable('2026-06-06 12:00:00', new DateTimeZone('Europe/Paris'))
        );

        $this->assertSame(7500, $plan['totalAmount']);
        $this->assertSame(2500, $plan['initialAmount']);
        $this->assertSame([
            ['amount' => 2500, 'date' => '2026-07-06'],
            ['amount' => 2500, 'date' => '2026-08-06'],
        ], $plan['terms']);
    }

    public function testCapsCollectionDayAtTwentySeven(): void
    {
        $plan = CRM_HelloassoPaymentProcessor_InstallmentPlan::buildMonthly(
            1000,
            2,
            1,
            'month',
            new DateTimeImmutable('2026-01-31')
        );

        $this->assertSame([
            ['amount' => 1000, 'date' => '2026-02-27'],
        ], $plan['terms']);
    }

    /**
     * @dataProvider invalidPlanProvider
     */
    public function testRejectsUnsupportedCiviRecurringProperties(
        int $amount,
        int $installments,
        int $interval,
        string $unit
    ): void {
        $this->expectException(InvalidArgumentException::class);

        CRM_HelloassoPaymentProcessor_InstallmentPlan::buildMonthly(
            $amount,
            $installments,
            $interval,
            $unit,
            new DateTimeImmutable('2026-06-06')
        );
    }

    public static function invalidPlanProvider(): array
    {
        return [
            'zero amount' => [0, 3, 1, 'month'],
            'open ended' => [1000, 0, 1, 'month'],
            'single payment' => [1000, 1, 1, 'month'],
            'more than twelve payments' => [1000, 13, 1, 'month'],
            'two month interval' => [1000, 3, 2, 'month'],
            'yearly interval' => [1000, 3, 1, 'year'],
        ];
    }
}
