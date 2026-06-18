<?php

class CRM_HelloassoPaymentProcessor_InstallmentFollowUpTest extends \PHPUnit\Framework\TestCase
{
    public function testRecognizesFuturePendingInstallment(): void
    {
        $this->assertTrue(
            CRM_HelloassoPaymentProcessor_InstallmentFollowUp::isFuturePending(
                [
                    'state' => 'Pending',
                    'installmentNumber' => 9,
                    'date' => '2027-02-06T00:00:00Z',
                ],
                new DateTimeImmutable('2026-06-07T00:00:00Z')
            )
        );
    }

    /**
     * @dataProvider notFuturePendingProvider
     */
    public function testRejectsPaymentsThatAreNotFuturePendingInstallments(array $payment): void
    {
        $this->assertFalse(
            CRM_HelloassoPaymentProcessor_InstallmentFollowUp::isFuturePending(
                $payment,
                new DateTimeImmutable('2026-06-07T00:00:00Z')
            )
        );
    }

    public static function notFuturePendingProvider(): array
    {
        return [
            'authorized' => [[
                'state' => 'Authorized',
                'installmentNumber' => 2,
                'date' => '2026-07-06T00:00:00Z',
            ]],
            'first payment' => [[
                'state' => 'Pending',
                'installmentNumber' => 1,
                'date' => '2026-07-06T00:00:00Z',
            ]],
            'past due' => [[
                'state' => 'Pending',
                'installmentNumber' => 2,
                'date' => '2026-06-06T00:00:00Z',
            ]],
            'missing date' => [[
                'state' => 'Pending',
                'installmentNumber' => 2,
            ]],
        ];
    }

    public function testUsesScheduledPaymentDateBeforeCreationDate(): void
    {
        $origin = CRM_HelloassoPaymentProcessor_InstallmentFollowUp::originDate(
            [
                'date' => '2027-02-06T00:00:00Z',
                'meta' => [
                    'createdAt' => '2026-06-06T18:17:15Z',
                ],
            ],
            new DateTimeImmutable('2026-06-07T00:00:00Z')
        );

        $this->assertSame('2027-02-06 00:00:00', $origin->format('Y-m-d H:i:s'));
    }
}
