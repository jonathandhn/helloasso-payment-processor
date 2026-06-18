<?php

/**
 * Centralizes the default Guzzle options used for HelloAsso HTTP requests.
 */
class CRM_HelloassoPaymentProcessor_RequestOptions
{
    private const CONNECT_TIMEOUT_SECONDS = 5.0;
    private const REQUEST_TIMEOUT_SECONDS = 20.0;

    public static function defaults(array $options = []): array
    {
        $defaults = [
            'http_errors' => FALSE,
            'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
            'timeout' => self::REQUEST_TIMEOUT_SECONDS,
            'curl' => [
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_SSL_VERIFYPEER => Civi::settings()->get('verifySSL'),
            ],
        ];

        if (!isset($options['curl']) || !is_array($options['curl'])) {
            return $options + $defaults;
        }

        $options['curl'] += $defaults['curl'];
        unset($defaults['curl']);

        return $options + $defaults;
    }
}
