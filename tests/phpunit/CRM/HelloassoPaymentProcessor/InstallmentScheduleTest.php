<?php


use PHPUnit\Framework\Attributes\DataProvider;
class CRM_HelloassoPaymentProcessor_InstallmentScheduleTest extends \PHPUnit\Framework\TestCase
{
    public function testBuildsMonthlyCheckoutTermsWithoutLosingCents(): void
    {
        $schedule = CRM_HelloassoPaymentProcessor_InstallmentSchedule::buildMonthly(
            10000,
            3,
            new DateTimeImmutable('2026-06-06 12:00:00', new DateTimeZone('Europe/Paris')),
            15
        );

        $this->assertSame(10000, $schedule['totalAmount']);
        $this->assertSame(3334, $schedule['initialAmount']);
        $this->assertSame([
            ['amount' => 3333, 'date' => '2026-07-15'],
            ['amount' => 3333, 'date' => '2026-08-15'],
        ], $schedule['terms']);
        $this->assertSame(
            $schedule['totalAmount'],
            $schedule['initialAmount'] + array_sum(array_column($schedule['terms'], 'amount'))
        );
    }

    public function testSupportsTwelveTotalMonthlyPayments(): void
    {
        $schedule = CRM_HelloassoPaymentProcessor_InstallmentSchedule::buildMonthly(
            12000,
            12,
            new DateTimeImmutable('2026-01-31'),
            27
        );

        $this->assertCount(11, $schedule['terms']);
        $this->assertSame('2026-02-27', $schedule['terms'][0]['date']);
        $this->assertSame('2026-12-27', $schedule['terms'][10]['date']);
    }

    /**
     */
    #[DataProvider("invalidScheduleProvider")]
    public function testRejectsSchedulesOutsideHelloAssoRules(
        int $totalAmount,
        int $installmentCount,
        int $collectionDay
    ): void {
        $this->expectException(InvalidArgumentException::class);

        CRM_HelloassoPaymentProcessor_InstallmentSchedule::buildMonthly(
            $totalAmount,
            $installmentCount,
            new DateTimeImmutable('2026-06-06'),
            $collectionDay
        );
    }

    public static function invalidScheduleProvider(): array
    {
        return [
            'single payment' => [1000, 1, 15],
            'more than twelve total payments' => [13000, 13, 15],
            'collection day after 27' => [1000, 2, 28],
            'collection day before 1' => [1000, 2, 0],
            'zero-value installment' => [2, 3, 15],
        ];
    }
}
