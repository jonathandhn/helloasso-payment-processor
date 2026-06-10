<?php


use PHPUnit\Framework\Attributes\DataProvider;
class CRM_HelloassoPaymentProcessor_FutureInstallmentReminderGuardTest extends \PHPUnit\Framework\TestCase
{
    public function testRecognizesFuturePendingInstallment(): void
    {
        $this->assertTrue(
            CRM_HelloassoPaymentProcessor_FutureInstallmentReminderGuard::isFuturePendingInstallment(
                [
                    'installment_number' => 9,
                    'payment_date' => '2027-02-06 00:00:00',
                    'state' => 'Pending',
                ],
                new DateTimeImmutable('2026-06-07 00:00:00', new DateTimeZone('UTC'))
            )
        );
    }

    /**
     */
    #[DataProvider("reminderCandidateProvider")]
    public function testAllowsNormalReminderCandidates(array $installment): void
    {
        $this->assertFalse(
            CRM_HelloassoPaymentProcessor_FutureInstallmentReminderGuard::isFuturePendingInstallment(
                $installment,
                new DateTimeImmutable('2026-06-07 00:00:00', new DateTimeZone('UTC'))
            )
        );
    }

    public static function reminderCandidateProvider(): array
    {
        return [
            'authorized installment' => [[
                'installment_number' => 2,
                'payment_date' => '2026-07-06 00:00:00',
                'state' => 'Authorized',
            ]],
            'first checkout payment' => [[
                'installment_number' => 1,
                'payment_date' => '2026-07-06 00:00:00',
                'state' => 'Pending',
            ]],
            'past pending installment' => [[
                'installment_number' => 2,
                'payment_date' => '2026-06-06 00:00:00',
                'state' => 'Pending',
            ]],
            'unmapped contribution' => [[]],
        ];
    }
}
