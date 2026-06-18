<?php

/**
 * Centralizes the default Guzzle options used for HelloAsso HTTP requests.
 */
class CRM_HelloassoPaymentProcessor_RequestOptions
{
    public const PROFILE_DEFAULT = 'default';
    public const PROFILE_BROWSER_RETURN = 'browser_return';

    private const CONNECT_TIMEOUT_SECONDS = 5.0;
    private const REQUEST_TIMEOUT_SECONDS = 20.0;
    private const BROWSER_RETURN_CONNECT_TIMEOUT_SECONDS = 2.0;
    private const BROWSER_RETURN_TIMEOUT_SECONDS = 5.0;

    public static function defaults(
        array $options = [],
        string $profile = self::PROFILE_DEFAULT
    ): array
    {
        ['connect_timeout' => $connectTimeout, 'timeout' => $timeout] = self::resolveTimeouts($profile);

        $defaults = [
            'http_errors' => FALSE,
            'connect_timeout' => $connectTimeout,
            'timeout' => $timeout,
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

    public static function browserReturn(array $options = []): array
    {
        return self::defaults($options, self::PROFILE_BROWSER_RETURN);
    }

    private static function resolveTimeouts(string $profile): array
    {
        if ($profile === self::PROFILE_BROWSER_RETURN) {
            return [
                'connect_timeout' => self::BROWSER_RETURN_CONNECT_TIMEOUT_SECONDS,
                'timeout' => self::BROWSER_RETURN_TIMEOUT_SECONDS,
            ];
        }

        return [
            'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
            'timeout' => self::REQUEST_TIMEOUT_SECONDS,
        ];
    }
}
