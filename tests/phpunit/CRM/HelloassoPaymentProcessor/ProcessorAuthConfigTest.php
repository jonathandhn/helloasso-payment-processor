<?php

class CRM_HelloassoPaymentProcessor_ProcessorAuthConfigTest extends \PHPUnit\Framework\TestCase
{
    public function testAllowsLiveProcessorOnAuthorizedDomain(): void
    {
        $config = $this->createPluginPublicConfig('crm.association.org');

        $this->assertTrue($config->shouldUsePluginPublic(14, [
            'id' => 14,
            'is_test' => 0,
        ]));
    }

    public function testBlocksLiveProcessorOnAnotherDomain(): void
    {
        $config = $this->createPluginPublicConfig('staging.association.org');

        $this->assertFalse($config->shouldUsePluginPublic(14, [
            'id' => 14,
            'is_test' => 0,
        ]));
    }

    public function testAllowsSandboxProcessorOnAnotherDomain(): void
    {
        $config = $this->createPluginPublicConfig('localhost');

        $this->assertTrue($config->shouldUsePluginPublic(13, [
            'id' => 13,
            'is_test' => 1,
        ]));
    }

    private function createPluginPublicConfig(string $currentHost): CRM_HelloassoPaymentProcessor_ProcessorAuthConfig
    {
        $config = $this->getMockBuilder(CRM_HelloassoPaymentProcessor_ProcessorAuthConfig::class)
            ->onlyMethods([
                'getStoredLink',
                'getConnectionMode',
                'hasClassicCredentials',
                'getLinkedOrganization',
                'getCurrentHost',
            ])
            ->getMock();

        $config->method('hasClassicCredentials')->willReturn(false);
        $config->method('getConnectionMode')->willReturn('plugin_public');
        $config->method('getLinkedOrganization')->willReturn([
            'organization_slug' => 'test-org',
        ]);
        $config->method('getStoredLink')->willReturn([
            'redirect_uri' => 'https://crm.association.org/civicrm/payment/ipn/14',
        ]);
        $config->method('getCurrentHost')->willReturn($currentHost);

        return $config;
    }
}
