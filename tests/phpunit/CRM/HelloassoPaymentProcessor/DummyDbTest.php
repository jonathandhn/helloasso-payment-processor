<?php

class CRM_HelloassoPaymentProcessor_DummyDbTest extends \PHPUnit\Framework\TestCase {

  public function testExtensionClassCanBeAutoloaded(): void {
    $this->assertTrue(class_exists(CRM_Core_Payment_HelloAsso::class));
  }

}
