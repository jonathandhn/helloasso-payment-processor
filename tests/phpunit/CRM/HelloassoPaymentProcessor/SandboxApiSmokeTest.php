<?php

/**
 * Read-only smoke tests against the HelloAsso sandbox.
 *
 * @group headless
 * @group sandbox
 */
class CRM_HelloassoPaymentProcessor_SandboxApiSmokeTest
    extends CRM_HelloassoPaymentProcessor_Base_CiviHeadlessTestCase
{
    public function testSandboxCredentialsAuthenticateAndReadPayments(): void
    {
        $clientId = trim((string) getenv('HELLOASSO_TEST_API_CLIENT_ID'));
        $clientSecret = trim((string) getenv('HELLOASSO_TEST_API_CLIENT_SECRET'));
        $organizationSlug = trim((string) getenv('HELLOASSO_TEST_ORGANIZATION_SLUG'));
        if ($clientId === '' || $clientSecret === '' || $organizationSlug === '') {
            $this->markTestSkipped(
                'HelloAsso sandbox credentials and organization slug are not configured.'
            );
        }

        $processor = [
            'id' => 900001,
            'url_site' => 'https://api.helloasso-sandbox.com',
            'user_name' => $clientId,
            'password' => $clientSecret,
            'subject' => $organizationSlug,
        ];
        $client = CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance();
        $client->invalidateToken(TRUE, $processor);

        $token = $client->getToken(
            TRUE,
            $processor,
            'https://api.helloasso-sandbox.com/oauth2/token',
            $clientId,
            $clientSecret
        );
        $this->assertNotEmpty($token->access_token ?? NULL);

        $payments = $client->listOrganizationPayments($processor, TRUE, [
            'pageIndex' => 1,
            'pageSize' => 1,
        ]);
        $this->assertIsArray($payments);
        $this->assertArrayHasKey('data', $payments);
    }
}
