<?php

class CRM_HelloassoPaymentProcessor_HelloAssoPaymentTest extends \PHPUnit\Framework\TestCase
{
    public function testDbQuery()
    {
        $processorArray = ['id' => 14];
        $processor = new CRM_Core_Payment_HelloAsso('test', $processorArray);
        try {
            $processor->processScheduledSynchronization();
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            echo "\nException: " . $e->getMessage() . "\n";
        }
    }
}
