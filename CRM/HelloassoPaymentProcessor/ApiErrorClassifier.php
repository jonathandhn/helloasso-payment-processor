<?php

/**
 * Classify HelloAsso API responses which need a dedicated user-facing message.
 */
class CRM_HelloassoPaymentProcessor_ApiErrorClassifier
{
    public static function isOrganizationPaymentBlocked(
        string $method,
        string $path,
        int $statusCode
    ): bool {
        return $statusCode === 409
            && self::isCheckoutInitializationRequest($method, $path);
    }

    public static function isCheckoutInitializationRequest(string $method, string $path): bool
    {
        return strtoupper($method) === 'POST'
            && (bool) preg_match('#^/v5/organizations/[^/]+/checkout-intents$#', $path);
    }
}
