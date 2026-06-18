<?php

class CRM_HelloassoPaymentProcessor_PartnerCredentialsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider credentialPairProvider
     */
    public function testBuildPlaceholdersAreNotCompleteCredentials(array $credentials, bool $expected): void
    {
        $resolver = new CRM_HelloassoPaymentProcessor_PartnerCredentials();
        $method = new ReflectionMethod($resolver, 'hasCompletePair');

        $this->assertSame($expected, $method->invoke($resolver, $credentials));
    }

    public static function credentialPairProvider(): array
    {
        return [
            'real credentials' => [[
                'clientId' => 'client-id',
                'clientSecret' => 'client-secret',
            ], TRUE],
            'git placeholders' => [[
                'clientId' => '%%HELLOASSO_SANDBOX_CLIENT_ID%%',
                'clientSecret' => '%%HELLOASSO_SANDBOX_CLIENT_SECRET%%',
            ], FALSE],
            'placeholder client id' => [[
                'clientId' => '%%HELLOASSO_LIVE_CLIENT_ID%%',
                'clientSecret' => 'client-secret',
            ], FALSE],
            'empty secret' => [[
                'clientId' => 'client-id',
                'clientSecret' => '',
            ], FALSE],
        ];
    }
}
