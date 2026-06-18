<?php

use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

/**
 * Test class for private data normalizer methods in the Upgrader.
 *
 * @group headless
 */
class CRM_HelloassoPaymentProcessor_UpgraderSqlFixtureTest extends \PHPUnit\Framework\TestCase
{
    private CRM_HelloassoPaymentProcessor_Upgrader $upgrader;

    public function setUp(): void
    {
        parent::setUp();
        $mapper = \CRM_Extension_System::singleton()->getMapper();
        $this->upgrader = new CRM_HelloassoPaymentProcessor_Upgrader(
            'helloasso-payment-processor',
            $mapper->keyToBasePath('helloasso-payment-processor')
        );
    }

    private function invokePrivateMethod(string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(CRM_HelloassoPaymentProcessor_Upgrader::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($this->upgrader, $parameters);
    }

    public function testNormalizeNullableDateTime(): void
    {
        // Null or empty strings
        $this->assertNull($this->invokePrivateMethod('normalizeNullableDateTime', [null]));
        $this->assertNull($this->invokePrivateMethod('normalizeNullableDateTime', ['']));

        // YmdHis integers and strings
        $this->assertEquals('20251231235959', $this->invokePrivateMethod('normalizeNullableDateTime', [20251231235959]));
        $this->assertEquals('20251231235959', $this->invokePrivateMethod('normalizeNullableDateTime', ['20251231235959']));

        // Ymd format
        $this->assertEquals('20251231', $this->invokePrivateMethod('normalizeNullableDateTime', [20251231]));
        $this->assertEquals('20251231', $this->invokePrivateMethod('normalizeNullableDateTime', ['20251231']));

        // UNIX Timestamps
        $this->assertEquals('20250101000000', $this->invokePrivateMethod('normalizeNullableDateTime', [1735689600]));
        $this->assertEquals('20250101000000', $this->invokePrivateMethod('normalizeNullableDateTime', ['1735689600']));

        // ISO 8601 Strings
        $this->assertEquals('20231015123000', $this->invokePrivateMethod('normalizeNullableDateTime', ['2023-10-15 12:30:00']));
        $this->assertEquals('20231015123000', $this->invokePrivateMethod('normalizeNullableDateTime', ['2023-10-15T12:30:00Z']));

        // Invalid string falls back to the original string
        $this->assertEquals('invalid', $this->invokePrivateMethod('normalizeNullableDateTime', ['invalid']));
    }

    public function testBuildNullableTimestampParam(): void
    {
        // Valid date
        $this->assertEquals(
            ['20251231235959', 'Timestamp'],
            $this->invokePrivateMethod('buildNullableTimestampParam', ['2025-12-31 23:59:59'])
        );

        // Null value returns null string for DB with NO_QUOTES
        $this->assertEquals(
            ['null', 'String', \CRM_Core_DAO::QUERY_FORMAT_NO_QUOTES],
            $this->invokePrivateMethod('buildNullableTimestampParam', [null])
        );

        // Empty string acts as null
        $this->assertEquals(
            ['null', 'String', \CRM_Core_DAO::QUERY_FORMAT_NO_QUOTES],
            $this->invokePrivateMethod('buildNullableTimestampParam', [''])
        );
    }
}
