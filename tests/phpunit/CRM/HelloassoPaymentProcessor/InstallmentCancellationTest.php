<?php

class CRM_HelloassoPaymentProcessor_InstallmentCancellationTest extends \PHPUnit\Framework\TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-06-08 12:00:00');
    }

    public function testSelectsOnlyFuturePendingInstallments(): void
    {
        $this->assertTrue(
            CRM_HelloassoPaymentProcessor_InstallmentCancellation::isFutureUncollected(
                'Pending',
                '2026-07-08 12:00:00',
                $this->now
            )
        );
        $this->assertTrue(
            CRM_HelloassoPaymentProcessor_InstallmentCancellation::isFutureUncollected(
                'WaitingBankWithdraw',
                '2026-07-08 12:00:00',
                $this->now
            )
        );
    }

    public function testDoesNotSelectCollectedOrPastInstallments(): void
    {
        $this->assertFalse(
            CRM_HelloassoPaymentProcessor_InstallmentCancellation::isFutureUncollected(
                'Authorized',
                '2026-07-08 12:00:00',
                $this->now
            )
        );
        $this->assertFalse(
            CRM_HelloassoPaymentProcessor_InstallmentCancellation::isFutureUncollected(
                'Pending',
                '2026-06-08 11:59:59',
                $this->now
            )
        );
        $this->assertFalse(
            CRM_HelloassoPaymentProcessor_InstallmentCancellation::isFutureUncollected(
                'Refused',
                '2026-07-08 12:00:00',
                $this->now
            )
        );
    }

    public function testRejectsMissingOrInvalidDates(): void
    {
        $this->assertFalse(
            CRM_HelloassoPaymentProcessor_InstallmentCancellation::isFutureUncollected(
                'Pending',
                NULL,
                $this->now
            )
        );
        $this->assertFalse(
            CRM_HelloassoPaymentProcessor_InstallmentCancellation::isFutureUncollected(
                'Pending',
                'not-a-date',
                $this->now
            )
        );
    }
}
