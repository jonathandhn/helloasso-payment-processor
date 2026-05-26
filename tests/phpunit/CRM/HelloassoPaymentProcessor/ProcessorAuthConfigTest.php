<?php

class CRM_HelloassoPaymentProcessor_ProcessorAuthConfigTest extends \PHPUnit\Framework\TestCase
{
    private $originalServerHost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServerHost = $_SERVER['HTTP_HOST'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->originalServerHost !== null) {
            $_SERVER['HTTP_HOST'] = $this->originalServerHost;
        } else {
            unset($_SERVER['HTTP_HOST']);
        }
        parent::tearDown();
    }

    public function testShouldUsePluginPublicBlocksOnDomainMismatchForLiveProcessors()
    {
        $config = $this->getMockBuilder(CRM_HelloassoPaymentProcessor_ProcessorAuthConfig::class)
            ->onlyMethods(['getStoredLink', 'getConnectionMode', 'hasClassicCredentials', 'getLinkedOrganization'])
            ->getMock();

        // 1. Setup mock returns for a live processor using plugin_public
        $config->method('hasClassicCredentials')->willReturn(false);
        $config->method('getConnectionMode')->willReturn('plugin_public');
        $config->method('getLinkedOrganization')->willReturn([
            'organization_slug' => 'test-org',
        ]);

        // Link authorized on domain: crm.association.org
        $config->method('getStoredLink')->willReturn([
            'redirect_uri' => 'https://crm.association.org/civicrm/payment/ipn/14',
        ]);

        // 2. Scenario A: Domain matches -> should return TRUE
        $_SERVER['HTTP_HOST'] = 'crm.association.org';
        $paymentProcessorMatched = [
            'id' => 14,
            'is_test' => 0, // Live processor
        ];
        $this->assertTrue($config->shouldUsePluginPublic(14, $paymentProcessorMatched));

        // 3. Scenario B: Domain mismatches (staging/local) -> should return FALSE (safety mute!)
        $_SERVER['HTTP_HOST'] = 'staging.association.org';
        $paymentProcessorMismatched = [
            'id' => 14,
            'is_test' => 0, // Live processor
        ];
        $this->assertFalse($config->shouldUsePluginPublic(14, $paymentProcessorMismatched));

        // 4. Scenario C: Sandbox test processor with domain mismatch -> should still return TRUE
        // (we allow developers to test sandbox on different hosts/local)
        $_SERVER['HTTP_HOST'] = 'localhost';
        $paymentProcessorSandbox = [
            'id' => 13,
            'is_test' => 1, // Sandbox processor
        ];
        $this->assertTrue($config->shouldUsePluginPublic(13, $paymentProcessorSandbox));
    }
}
