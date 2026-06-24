<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Civi\Payment\Exception\PaymentProcessorException;

/**
 * Tests d'intégration pour le client HelloAsso (GuzzleHttp + cache CiviCRM).
 *
 */
#[\PHPUnit\Framework\Attributes\Group('headless')]
class CRM_HelloassoPaymentProcessor_HelloAssoClientContractTest extends CRM_HelloassoPaymentProcessor_Base_CiviHeadlessTestCase
{
    private CRM_HelloassoPaymentProcessor_HelloAssoClient $client;
    private MockHandler $mockHandler;
    private array $history = [];

    public function setUp(): void
    {
        parent::setUp();
        
        \Civi::cache('long')->clear();

        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStack->push(Middleware::history($this->history));
        $guzzleClient = new Client(['handler' => $handlerStack]);

        $this->client = CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance();
        $this->client->setGuzzleClient($guzzleClient);
    }

    public function tearDown(): void
    {
        // Remettre le client par défaut pour ne pas affecter les autres tests
        $this->client->setGuzzleClient(new Client());
        parent::tearDown();
    }

    private function getDummyProcessor(): array
    {
        return [
            'id' => 999,
            'url_site' => 'https://api.helloasso.com',
            'user_name' => 'client_id',
            'password' => 'client_secret',
            'subject' => 'org-slug',
        ];
    }

    public function testGetCheckoutIntentThrowsExceptionOn429TooManyRequests(): void
    {
        // Mock token
        $this->mockHandler->append(
            new Response(200, [], json_encode(['access_token' => 'token123', 'expires_in' => 3600]))
        );
        // Mock 429 response
        $this->mockHandler->append(
            new Response(429, [], json_encode(['message' => 'Rate limit exceeded']))
        );

        $this->expectException(PaymentProcessorException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $this->client->getCheckoutIntent($this->getDummyProcessor(), true, 12345);
    }

    public function testHandlesMalformedJsonGracefullyOn200(): void
    {
        // Mock token
        $this->mockHandler->append(
            new Response(200, [], json_encode(['access_token' => 'token123', 'expires_in' => 3600]))
        );
        // Mock 200 with malformed JSON
        $this->mockHandler->append(
            new Response(200, [], '{"malformed": "json" // oops')
        );

        $result = $this->client->getCheckoutIntent($this->getDummyProcessor(), true, 12345);
        
        // requestHelloAsso returns empty array if json_decode returns null
        $this->assertSame([], $result);
    }

    public function testHandlesMalformedJsonGracefullyOnError(): void
    {
        // Mock token
        $this->mockHandler->append(
            new Response(200, [], json_encode(['access_token' => 'token123', 'expires_in' => 3600]))
        );
        // Mock 500 with malformed JSON
        $this->mockHandler->append(
            new Response(500, [], '{"error": "fatal" } }}')
        );

        $this->expectException(PaymentProcessorException::class);
        // buildApiErrorMessage should fallback to generic message if decoded is null
        $this->expectExceptionMessage(CRM_HelloassoPaymentProcessor_ExtensionUtil::ts('HelloAsso API error (%1)', [1 => 500]));

        $this->client->getCheckoutIntent($this->getDummyProcessor(), true, 12345);
    }

    public function testPaginationParametersAreSentInQueryString(): void
    {
        // Mock token
        $this->mockHandler->append(
            new Response(200, [], json_encode(['access_token' => 'token123', 'expires_in' => 3600]))
        );
        // Mock 200 empty response
        $this->mockHandler->append(
            new Response(200, [], json_encode(['data' => []]))
        );

        $this->client->listOrganizationPayments($this->getDummyProcessor(), true, [
            'pageIndex' => 2,
            'pageSize' => 50,
        ]);

        $request = $this->mockHandler->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('pageIndex=2&pageSize=50', $request->getUri()->getQuery());
    }

    public function testBrowserReturnProfileUsesShortTimeoutBudget(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode(['access_token' => 'token123', 'expires_in' => 3600]))
        );
        $this->mockHandler->append(
            new Response(200, [], json_encode(['id' => 12345]))
        );

        $this->client->getCheckoutIntent(
            $this->getDummyProcessor(),
            true,
            12345,
            [],
            CRM_HelloassoPaymentProcessor_RequestOptions::PROFILE_BROWSER_RETURN
        );

        $this->assertCount(2, $this->history);
        $apiRequestOptions = $this->history[1]['options'];
        $this->assertSame(2.0, $apiRequestOptions['connect_timeout']);
        $this->assertSame(5.0, $apiRequestOptions['timeout']);
    }

    public function testDefaultProfileKeepsStandardTimeoutBudget(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode(['access_token' => 'token123', 'expires_in' => 3600]))
        );
        $this->mockHandler->append(
            new Response(200, [], json_encode(['id' => 12345]))
        );

        $this->client->getCheckoutIntent($this->getDummyProcessor(), true, 12345);

        $this->assertCount(2, $this->history);
        $apiRequestOptions = $this->history[1]['options'];
        $this->assertSame(5.0, $apiRequestOptions['connect_timeout']);
        $this->assertSame(20.0, $apiRequestOptions['timeout']);
    }

    public function testGetOrganizationSlugThrowsClearExceptionWhenSubjectIsMissing(): void
    {
        $method = new ReflectionMethod($this->client, 'getOrganizationSlug');
        $method->setAccessible(TRUE);

        $this->expectException(PaymentProcessorException::class);
        $this->expectExceptionMessage('organization slug is missing');

        $method->invoke($this->client, [
            'id' => 999,
            'url_site' => 'https://api.helloasso.com',
            'user_name' => 'client_id',
            'password' => 'client_secret',
        ], TRUE);
    }
}
