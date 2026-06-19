<?php

class CRM_HelloassoPaymentProcessor_SettingsDefinitionTest extends \PHPUnit\Framework\TestCase
{
    public function testInstallmentAndSepaFlagsAreExposedOnHelloAssoSettingsPage(): void
    {
        require_once __DIR__ . '/../../../../helloasso_payment_processor.civix.php';
        $settings = require __DIR__ . '/../../../../settings/helloasso_payment_processor.setting.php';

        $this->assertSame(
            0,
            $settings['helloasso_enable_installments']['default']
        );
        $this->assertArrayHasKey(
            'helloasso',
            $settings['helloasso_enable_installments']['settings_pages']
        );

        $this->assertArrayHasKey(
            'default',
            $settings['helloasso_quickform_redirect_message']
        );
        $this->assertArrayHasKey(
            'helloasso',
            $settings['helloasso_quickform_redirect_message']['settings_pages']
        );

        $this->assertSame(
            1,
            $settings['helloasso_enable_sepa']['default']
        );
        $this->assertArrayHasKey(
            'helloasso',
            $settings['helloasso_enable_sepa']['settings_pages']
        );
    }
}
