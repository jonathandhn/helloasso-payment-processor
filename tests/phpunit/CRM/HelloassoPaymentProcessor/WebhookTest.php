<?php

class CRM_HelloassoPaymentProcessor_WebhookTest extends \PHPUnit\Framework\TestCase
{
    public function testOnlyPostCanCarryJsonWebhookPayload(): void
    {
        $this->assertTrue(CRM_HelloassoPaymentProcessor_Webhook::acceptsJsonPayload('POST'));
        $this->assertTrue(CRM_HelloassoPaymentProcessor_Webhook::acceptsJsonPayload(' post '));
        $this->assertFalse(CRM_HelloassoPaymentProcessor_Webhook::acceptsJsonPayload('GET'));
        $this->assertFalse(CRM_HelloassoPaymentProcessor_Webhook::acceptsJsonPayload(NULL));
    }
}
