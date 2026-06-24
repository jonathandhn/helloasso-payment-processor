<?php

/**
 * @group headless
 */
class CRM_HelloassoPaymentProcessor_HostedCheckoutBrowserReturnIntegrationTest
    extends CRM_HelloassoPaymentProcessor_Base_CiviHeadlessTestCase
{
    public function testBrowserReturnPendingFallbackQueuesImmediateSynchronization(): void
    {
        $processorId = $this->createTestProcessor();
        $contactId = $this->createTestContact();
        $contributionId = $this->createTestContribution($contactId, $processorId, [
            'contribution_status_id' => 'Pending',
            'total_amount' => 42.50,
        ]);

        $metadata = new CRM_HelloassoPaymentProcessor_BAO_HelloAssoMetadata();
        $metadata->contribution_id = $contributionId;
        $metadata->signing_key = 'test-signing-key';
        $metadata->payment_processor_id = $processorId;
        $metadata->save();

        $processorConfig = ['id' => $processorId];
        $processor = new class('test', $processorConfig) extends CRM_Core_Payment_HelloAsso {
            public array $capturedOptions = [];

            public function processScheduledSynchronization(array $options = []): array
            {
                $this->capturedOptions = $options;

                return [
                    'checked' => 1,
                    'updated' => 0,
                    'errors' => ['browser return timeout budget reached'],
                ];
            }
        };

        $result = $processor->synchronizeContributionForHostedCheckout($contributionId);

        $this->assertSame('pending', $result['checkout_status']);
        $this->assertTrue($result['browser_return_fallback']);
        $this->assertSame(
            ['browser return timeout budget reached'],
            $result['synchronization_errors']
        );
        $this->assertSame($contributionId, $processor->capturedOptions['contribution_id']);
        $this->assertSame(1, $processor->capturedOptions['limit']);
        $this->assertSame(
            CRM_HelloassoPaymentProcessor_RequestOptions::PROFILE_BROWSER_RETURN,
            $processor->capturedOptions['request_profile']
        );

        $reloaded = new CRM_HelloassoPaymentProcessor_BAO_HelloAssoMetadata();
        $reloaded->contribution_id = $contributionId;
        $this->assertSame(1, (int) $reloaded->find(TRUE));
        $this->assertNotEmpty($reloaded->sync_origin_date);
        $this->assertNotEmpty($reloaded->sync_next_date);
        $this->assertSame('0', (string) $reloaded->sync_attempt_count);
    }
}
