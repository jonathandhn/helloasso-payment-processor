<?php

class CRM_HelloassoPaymentProcessor_CheckoutAbandonmentTest extends \PHPUnit\Framework\TestCase
{
    public function testExpiresCheckoutAfterFortyFiveMinutesWithoutPayment(): void
    {
        $this->assertTrue(
            CRM_HelloassoPaymentProcessor_CheckoutAbandonment::isExpired(
                '2026-06-08 12:00:00',
                new DateTimeImmutable('2026-06-08 12:45:00'),
                FALSE
            )
        );
    }

    public function testKeepsCheckoutBeforeExpiration(): void
    {
        $this->assertFalse(
            CRM_HelloassoPaymentProcessor_CheckoutAbandonment::isExpired(
                '2026-06-08 12:00:00',
                new DateTimeImmutable('2026-06-08 12:44:59'),
                FALSE
            )
        );
    }

    public function testUsesUtcForStoredMetadataTimestamps(): void
    {
        $previousTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');

        try {
            $this->assertFalse(
                CRM_HelloassoPaymentProcessor_CheckoutAbandonment::isExpired(
                    '2026-08-09 16:10:42',
                    new DateTimeImmutable('2026-08-09 16:15:04', new DateTimeZone('UTC')),
                    FALSE
                )
            );
        }
        finally {
            date_default_timezone_set($previousTimezone);
        }
    }

    public function testNeverExpiresCheckoutWithPayments(): void
    {
        $this->assertFalse(
            CRM_HelloassoPaymentProcessor_CheckoutAbandonment::isExpired(
                '2026-06-08 12:00:00',
                new DateTimeImmutable('2026-06-09 12:00:00'),
                TRUE
            )
        );
    }

    public function testRejectsMissingOrInvalidOrigin(): void
    {
        $now = new DateTimeImmutable('2026-06-08 13:00:00');
        $this->assertFalse(
            CRM_HelloassoPaymentProcessor_CheckoutAbandonment::isExpired(NULL, $now, FALSE)
        );
        $this->assertFalse(
            CRM_HelloassoPaymentProcessor_CheckoutAbandonment::isExpired('invalid', $now, FALSE)
        );
    }

    public function testExpirationRuleAlsoAppliesToClassicCheckout(): void
    {
        $this->assertTrue(
            CRM_HelloassoPaymentProcessor_CheckoutAbandonment::isExpired(
                '2026-06-08 12:00:00',
                new DateTimeImmutable('2026-06-08 12:46:00'),
                FALSE
            )
        );
    }
}
