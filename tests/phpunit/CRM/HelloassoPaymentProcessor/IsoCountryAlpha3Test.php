<?php

class CRM_HelloassoPaymentProcessor_IsoCountryAlpha3Test extends \PHPUnit\Framework\TestCase
{
    public function testGetCountryAlpha3MatchesIso3166()
    {
        $converter = CRM_HelloassoPaymentProcessor_IsoCountryAlpha3::getInstance();

        // Standard countries
        $this->assertEquals('FRA', $converter->get('FR'));
        $this->assertEquals('ESP', $converter->get('ES'));
        $this->assertEquals('CAN', $converter->get('CA'));
        $this->assertEquals('USA', $converter->get('US'));
        $this->assertEquals('DEU', $converter->get('DE'));
        $this->assertEquals('CHE', $converter->get('CH'));

        // Island and territory updates
        $this->assertEquals('REU', $converter->get('RE'));
        $this->assertEquals('MTQ', $converter->get('MQ'));
        $this->assertEquals('GLP', $converter->get('GP'));

        // Non-existent or invalid country code should return NULL
        $this->assertNull($converter->get('XX'));
        $this->assertNull($converter->get(''));
    }

    public function testSupportReturnsBooleanCorrectly()
    {
        $converter = CRM_HelloassoPaymentProcessor_IsoCountryAlpha3::getInstance();

        $this->assertTrue($converter->support('FR'));
        $this->assertTrue($converter->support('ES'));
        $this->assertFalse($converter->support('XX'));
        $this->assertFalse($converter->support(''));
    }
}
