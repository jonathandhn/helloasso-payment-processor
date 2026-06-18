<?php

/**
 * Builds the optional HelloAsso Checkout SEPA payload.
 */
class CRM_HelloassoPaymentProcessor_SepaOptions
{
    public static function build(bool $enabled): array
    {
        return $enabled
            ? ['paymentOptions' => ['enableSepa' => TRUE]]
            : [];
    }
}
