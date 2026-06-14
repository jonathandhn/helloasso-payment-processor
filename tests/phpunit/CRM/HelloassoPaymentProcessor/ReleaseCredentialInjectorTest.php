<?php

require_once dirname(__DIR__, 4) . '/scripts/ReleaseCredentialInjector.php';

class CRM_HelloassoPaymentProcessor_ReleaseCredentialInjectorTest extends \PHPUnit\Framework\TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'helloasso-credentials-');
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testInjectsCompleteRecognizedCredentialPairs(): void
    {
        $environment = $this->environment();
        file_put_contents($this->file, $this->fixture($environment));

        CRM_HelloassoPaymentProcessor_ReleaseCredentialInjector::inject($this->file, $environment);

        $content = file_get_contents($this->file);
        $this->assertStringNotContainsString('%%HELLOASSO_', $content);
        $this->assertStringContainsString("live-id", $content);
        $this->assertStringContainsString("sandbox-secret", $content);
    }

    public function testRejectsMissingSecret(): void
    {
        $environment = $this->environment();
        $environment['HELLOASSO_SANDBOX_CLIENT_SECRET'] = '';
        file_put_contents($this->file, $this->fixture($this->environment()));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HELLOASSO_SANDBOX_CLIENT_SECRET');

        CRM_HelloassoPaymentProcessor_ReleaseCredentialInjector::inject($this->file, $environment);
    }

    public function testRejectsUnknownCredentialFingerprint(): void
    {
        $environment = $this->environment();
        file_put_contents($this->file, $this->fixture($environment, FALSE));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('recognized release fingerprint');

        CRM_HelloassoPaymentProcessor_ReleaseCredentialInjector::inject($this->file, $environment);
    }

    public function testEscapesSecretsForPhpSingleQuotedStrings(): void
    {
        $environment = $this->environment();
        $environment['HELLOASSO_LIVE_CLIENT_SECRET'] = "secret'with\\characters";
        file_put_contents($this->file, $this->fixture($environment));

        CRM_HelloassoPaymentProcessor_ReleaseCredentialInjector::inject($this->file, $environment);

        $content = file_get_contents($this->file);
        $this->assertStringContainsString("secret\\'with\\\\characters", $content);
        exec('php -l ' . escapeshellarg($this->file) . ' >/dev/null 2>&1', $output, $exitCode);
        $this->assertSame(0, $exitCode);
    }

    private function environment(): array
    {
        return [
            'HELLOASSO_LIVE_CLIENT_ID' => 'live-id',
            'HELLOASSO_LIVE_CLIENT_SECRET' => 'live-secret',
            'HELLOASSO_SANDBOX_CLIENT_ID' => 'sandbox-id',
            'HELLOASSO_SANDBOX_CLIENT_SECRET' => 'sandbox-secret',
        ];
    }

    private function fixture(array $environment, bool $includeKnownFingerprints = TRUE): string
    {
        $fingerprints = '';
        if ($includeKnownFingerprints) {
            $fingerprints = sprintf(
                "\$fingerprints = ['%s', '%s'];\n",
                hash('sha256', $environment['HELLOASSO_LIVE_CLIENT_ID'] . "\n" . $environment['HELLOASSO_LIVE_CLIENT_SECRET']),
                hash('sha256', $environment['HELLOASSO_SANDBOX_CLIENT_ID'] . "\n" . $environment['HELLOASSO_SANDBOX_CLIENT_SECRET'])
            );
        }

        return "<?php\n"
            . "\$liveId = '%%HELLOASSO_LIVE_CLIENT_ID%%';\n"
            . "\$liveSecret = '%%HELLOASSO_LIVE_CLIENT_SECRET%%';\n"
            . "\$sandboxId = '%%HELLOASSO_SANDBOX_CLIENT_ID%%';\n"
            . "\$sandboxSecret = '%%HELLOASSO_SANDBOX_CLIENT_SECRET%%';\n"
            . $fingerprints;
    }
}
