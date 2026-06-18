<?php

class CRM_HelloassoPaymentProcessor_ApiErrorClassifierTest extends \PHPUnit\Framework\TestCase
{
    public function testDetectsAssociationWithoutPaymentEligibility(): void
    {
        $this->assertTrue(
            CRM_HelloassoPaymentProcessor_ApiErrorClassifier::isOrganizationPaymentBlocked(
                'POST',
                '/v5/organizations/example-association/checkout-intents',
                409
            )
        );
    }

    /**
     * @dataProvider unrelatedApiResponses
     */
    public function testDoesNotMisclassifyOtherApiErrors(
        string $method,
        string $path,
        int $statusCode
    ): void {
        $this->assertFalse(
            CRM_HelloassoPaymentProcessor_ApiErrorClassifier::isOrganizationPaymentBlocked(
                $method,
                $path,
                $statusCode
            )
        );
    }

    public function unrelatedApiResponses(): array
    {
        return [
            'checkout unauthorized' => [
                'POST',
                '/v5/organizations/example-association/checkout-intents',
                401,
            ],
            'checkout lookup conflict' => [
                'GET',
                '/v5/organizations/example-association/checkout-intents/123',
                409,
            ],
            'refund conflict' => [
                'POST',
                '/v5/payments/123/refund',
                409,
            ],
        ];
    }
}
