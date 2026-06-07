<?php

/**
 * Inject and validate the community credentials embedded in release archives.
 */
class CRM_HelloassoPaymentProcessor_ReleaseCredentialInjector
{
    private const CREDENTIALS = [
        'HELLOASSO_LIVE_CLIENT_ID' => '%%HELLOASSO_LIVE_CLIENT_ID%%',
        'HELLOASSO_LIVE_CLIENT_SECRET' => '%%HELLOASSO_LIVE_CLIENT_SECRET%%',
        'HELLOASSO_SANDBOX_CLIENT_ID' => '%%HELLOASSO_SANDBOX_CLIENT_ID%%',
        'HELLOASSO_SANDBOX_CLIENT_SECRET' => '%%HELLOASSO_SANDBOX_CLIENT_SECRET%%',
    ];

    public static function inject(string $file, array $environment): void
    {
        $content = file_get_contents($file);
        if (!is_string($content)) {
            throw new RuntimeException('Unable to read PartnerCredentials.php.');
        }

        $values = [];
        foreach (self::CREDENTIALS as $name => $placeholder) {
            $value = trim((string) ($environment[$name] ?? ''));
            if ($value === '') {
                throw new RuntimeException("Missing required release secret: {$name}.");
            }
            if (substr_count($content, $placeholder) !== 1) {
                throw new RuntimeException("Expected exactly one {$placeholder} placeholder.");
            }
            $values[$name] = $value;
            $content = str_replace($placeholder, self::escapePhpSingleQuotedString($value), $content);
        }

        if (strpos($content, '%%HELLOASSO_') !== FALSE) {
            throw new RuntimeException('Unresolved HelloAsso credential placeholder remains.');
        }

        self::assertKnownFingerprint(
            $content,
            'live',
            $values['HELLOASSO_LIVE_CLIENT_ID'],
            $values['HELLOASSO_LIVE_CLIENT_SECRET']
        );
        self::assertKnownFingerprint(
            $content,
            'sandbox',
            $values['HELLOASSO_SANDBOX_CLIENT_ID'],
            $values['HELLOASSO_SANDBOX_CLIENT_SECRET']
        );

        if (file_put_contents($file, $content) === FALSE) {
            throw new RuntimeException('Unable to write injected PartnerCredentials.php.');
        }
    }

    private static function assertKnownFingerprint(
        string $content,
        string $mode,
        string $clientId,
        string $clientSecret
    ): void {
        $fingerprint = hash('sha256', $clientId . "\n" . $clientSecret);
        if (strpos($content, "'{$fingerprint}'") === FALSE) {
            throw new RuntimeException(
                "The injected {$mode} credential pair does not match a recognized release fingerprint."
            );
        }
    }

    private static function escapePhpSingleQuotedString(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }
}
