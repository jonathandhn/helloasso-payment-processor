<?php

class CRM_HelloassoPaymentProcessor_Logger
{
    public static function isDebugEnabled(): bool
    {
        try {
            return (bool) Civi::settings()->get('debug_enabled');
        }
        catch (Throwable $e) {
            return FALSE;
        }
    }

    public static function debug(string $message, array $context = []): void
    {
        if (!self::isDebugEnabled()) {
            return;
        }

        Civi::log()->debug($message, $context);
    }
}
