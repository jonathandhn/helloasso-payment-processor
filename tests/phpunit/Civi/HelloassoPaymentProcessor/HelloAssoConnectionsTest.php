<?php

use Civi\Core\Event\GenericHookEvent;
use Civi\HelloassoPaymentProcessor\HelloAssoConnections;

class Civi_HelloassoPaymentProcessor_HelloAssoConnectionsTest extends \PHPUnit\Framework\TestCase
{
    public function testSubscribedEventsApplyAfformInputTypeAfterCore(): void
    {
        $events = HelloAssoConnections::getSubscribedEvents();

        $this->assertArrayHasKey('civi.afform.input_types', $events);
        $this->assertSame(
            ['alterAfformInputTypes', -100],
            $events['civi.afform.input_types']
        );
    }

    public function testAlterAfformInputTypesWrapsCheckoutBlockAdmin(): void
    {
        $service = $this->getMockBuilder(HelloAssoConnections::class)
            ->onlyMethods(['isEnabled'])
            ->disableOriginalConstructor()
            ->getMock();

        $service->method('isEnabled')->willReturn(TRUE);

        $inputTypes = [
            'CheckoutBlock' => [
                'admin_template' => '~/afCheckout/inputType/CheckoutBlockAdmin.html',
                'admin_module' => 'afCheckout',
            ],
        ];
        $event = GenericHookEvent::create([
            'inputTypes' => &$inputTypes,
        ]);

        $service->alterAfformInputTypes($event);

        $this->assertSame(
            '~/crmHelloassoPaymentProcessor/checkoutBlockAdmin.html',
            $event->inputTypes['CheckoutBlock']['admin_template']
        );
        $this->assertSame(
            'crmHelloassoPaymentProcessor',
            $event->inputTypes['CheckoutBlock']['admin_module']
        );
    }
}
