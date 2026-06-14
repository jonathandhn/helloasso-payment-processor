<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

/**
 * Tests end-to-end de synchronisation (Cron et webhooks).
 *
 * @group headless
 */
class CRM_HelloassoPaymentProcessor_SynchronizationE2ETest extends CRM_HelloassoPaymentProcessor_Base_CiviHeadlessTestCase
{
    private MockHandler $mockHandler;

    public function setUp(): void
    {
        parent::setUp();
        
        \Civi::cache('long')->clear();

        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $guzzleClient = new Client(['handler' => $handlerStack]);

        $client = CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance();
        $client->setGuzzleClient($guzzleClient);
        
        // Mock token request which is always the first call when starting fresh
        $this->mockHandler->append(
            new Response(200, [], json_encode(['access_token' => 'token123', 'expires_in' => 3600]))
        );
    }

    public function tearDown(): void
    {
        CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance()->setGuzzleClient(new Client());
        parent::tearDown();
    }

    public function testFollowupCronDetectsAuthorizedPaymentAndCompletesContribution(): void
    {
        $processorId = $this->createTestProcessor();
        $contactId = $this->createTestContact();

        // Create a Pending contribution with HelloAsso metadata
        $contribution = civicrm_api3('Contribution', 'create', [
            'contact_id' => $contactId,
            'total_amount' => 50.00,
            'financial_type_id' => 1,
            'contribution_status_id' => 'Pending',
            'payment_processor_id' => $processorId,
            'trxn_id' => 'checkout-1234',
            'invoice_id' => 'inv-' . uniqid(),
            'currency' => 'EUR',
            'source' => 'E2E Test One-off',
        ]);
        $contributionId = $contribution['id'];

        // Add metadata so that followup script processes it
        $metadata = new CRM_HelloassoPaymentProcessor_DAO_HelloAssoMetadata();
        $metadata->contribution_id = $contributionId;
        $metadata->payment_processor_id = $processorId;
        $metadata->checkout_intent_id = '1234';
        $metadata->sync_attempt_count = 0;
        $metadata->sync_next_date = date('YmdHis', strtotime('-1 hour'));
        $metadata->signing_key = 'fakekey';
        $metadata->save();

        // Mock the HelloAsso API response for getCheckoutIntent
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'id' => 1234,
                'order' => [
                    'id' => 9999,
                    'payments' => [
                        [
                            'id' => 8888,
                            'state' => 'Authorized',
                            'amount' => 5000,
                        ],
                    ],
                ],
            ]))
        );

        // Run the short followup cron
        civicrm_api3('Job', 'process_helloasso', [
            'only_scheduled' => 1,
            'due_before' => 'now',
            'limit' => 10,
        ]);

        // Verify contribution is now Completed
        $updatedContribution = civicrm_api3('Contribution', 'getsingle', [
            'id' => $contributionId,
        ]);

        $this->assertEquals(
            CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed'),
            $updatedContribution['contribution_status_id']
        );
    }
    
    public function testLongFollowupCronDetectsRefundAndUpdatesContribution(): void
    {
        $processorId = $this->createTestProcessor();
        $contactId = $this->createTestContact();

        // Create a Completed contribution that will be refunded later
        $contribution = civicrm_api3('Contribution', 'create', [
            'contact_id' => $contactId,
            'total_amount' => 30.00,
            'financial_type_id' => 1,
            'contribution_status_id' => 'Completed',
            'payment_processor_id' => $processorId,
            'trxn_id' => 'checkout-refunded',
            'invoice_id' => 'inv-' . uniqid(),
            'currency' => 'EUR',
            'source' => 'E2E Test Refund',
        ]);
        $contributionId = $contribution['id'];

        // Add metadata indicating a successful payment
        $metadata = new CRM_HelloassoPaymentProcessor_DAO_HelloAssoMetadata();
        $metadata->contribution_id = $contributionId;
        $metadata->payment_processor_id = $processorId;
        $metadata->checkout_intent_id = '5678';
        $metadata->state = 'Authorized';
        $metadata->helloasso_payment_id = 8888;
        $metadata->long_sync_attempt_count = 0;
        $metadata->long_sync_next_date = date('YmdHis', strtotime('-1 day'));
        $metadata->signing_key = 'fakekey';
        $metadata->save();

        // Mock the HelloAsso API response for getPayment returning Refunded status
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'id' => 8888,
                'state' => 'Refunded',
                'amount' => 3000,
            ]))
        );

        // Run the long followup cron
        civicrm_api3('Job', 'process_helloasso_long_followup', [
            'due_before' => 'now',
            'limit' => 10,
        ]);

        // Verify contribution is now Refunded
        $updatedContribution = civicrm_api3('Contribution', 'getsingle', [
            'id' => $contributionId,
        ]);

        $this->assertEquals(
            CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Refunded'),
            $updatedContribution['contribution_status_id']
        );
    }
    
    public function testWebhookProcessesAuthorizedPayment(): void
    {
        $processorId = $this->createTestProcessor();
        $contactId = $this->createTestContact();

        $invoiceId = 'inv-' . uniqid();

        $contribution = civicrm_api3('Contribution', 'create', [
            'contact_id' => $contactId,
            'total_amount' => 40.00,
            'financial_type_id' => 1,
            'contribution_status_id' => 'Pending',
            'payment_processor_id' => $processorId,
            'trxn_id' => 'checkout-webhook',
            'invoice_id' => $invoiceId,
            'currency' => 'EUR',
            'source' => 'E2E Test Webhook',
        ]);
        $contributionId = $contribution['id'];

        $metadata = new CRM_HelloassoPaymentProcessor_DAO_HelloAssoMetadata();
        $metadata->contribution_id = $contributionId;
        $metadata->payment_processor_id = $processorId;
        $metadata->checkout_intent_id = 'checkout-webhook';
        $metadata->signing_key = 'fakekey';
        $metadata->save();

        $processor = \Civi\Payment\System::singleton()->getById($processorId);
        
        // Build the payload
        $payloadArray = [
            'eventType' => 'Payment',
            'data' => [
                'id' => 7777,
                'state' => 'Authorized',
                'amount' => 4000,
                'order' => [
                    'id' => 5555,
                ],
            ],
            'metadata' => [
                'invoiceID' => $invoiceId,
                'sig' => hash_hmac('sha256', $invoiceId, 'fakekey'),
            ],
        ];

        $webhookRecord = [
            'id' => 999,
            'payment_processor_id' => $processorId,
            'event_id' => 'evt-1234',
            'trigger' => 'webhook',
            'data' => json_encode($payloadArray),
        ];

        // Call the processor's processWebhookEvent method with simulated queue row
        $success = $processor->processWebhookEvent($webhookRecord);

        $this->assertTrue($success);

        $updatedContribution = civicrm_api3('Contribution', 'getsingle', [
            'id' => $contributionId,
        ]);

        if ($updatedContribution['contribution_status_id'] != 1) {
            $payments = civicrm_api3('Payment', 'get', ['contribution_id' => $contributionId]);
            print_r($payments);
            print_r($updatedContribution);
        }

        $this->assertEquals(
            CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed'),
            $updatedContribution['contribution_status_id']
        );
    }
}
