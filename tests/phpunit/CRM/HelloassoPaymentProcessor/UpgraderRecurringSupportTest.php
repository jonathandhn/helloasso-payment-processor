<?php

class CRM_HelloassoPaymentProcessor_UpgraderRecurringSupportTest extends CRM_HelloassoPaymentProcessor_Base_CiviHeadlessTestCase
{
    public function testUpgrade4222ResyncsExistingHelloAssoProcessors(): void
    {
        $processorId = $this->createTestProcessor([
            'is_recur' => 0,
            'name' => 'HelloAsso_sync_' . uniqid(),
        ]);
        CRM_Core_DAO::executeQuery(
            'UPDATE civicrm_payment_processor SET is_recur = 0 WHERE id = %1',
            [1 => [$processorId, 'Integer']]
        );

        $mapper = CRM_Extension_System::singleton()->getMapper();
        $upgrader = new CRM_HelloassoPaymentProcessor_Upgrader(
            'helloasso-payment-processor',
            $mapper->keyToBasePath('helloasso-payment-processor')
        );

        $reflection = new ReflectionProperty(CRM_HelloassoPaymentProcessor_Upgrader::class, 'ctx');
        $reflection->setAccessible(true);
        $reflection->setValue($upgrader, (object) ['log' => Civi::log()]);

        $this->assertSame('0', civicrm_api3('PaymentProcessor', 'getvalue', [
            'id' => $processorId,
            'return' => 'is_recur',
        ]));

        $this->assertTrue($upgrader->upgrade_4222());

        $this->assertSame('1', civicrm_api3('PaymentProcessor', 'getvalue', [
            'id' => $processorId,
            'return' => 'is_recur',
        ]));
    }
}
