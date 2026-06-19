<?php

class CRM_HelloassoPaymentProcessor_RequestOptionsTest extends \PHPUnit\Framework\TestCase
{
    public function testDefaultProfileKeepsStandardTimeouts(): void
    {
        $options = CRM_HelloassoPaymentProcessor_RequestOptions::defaults();

        $this->assertSame(5.0, $options['connect_timeout']);
        $this->assertSame(20.0, $options['timeout']);
        $this->assertFalse($options['http_errors']);
    }

    public function testBrowserReturnProfileUsesShortTimeouts(): void
    {
        $options = CRM_HelloassoPaymentProcessor_RequestOptions::browserReturn();

        $this->assertSame(2.0, $options['connect_timeout']);
        $this->assertSame(5.0, $options['timeout']);
        $this->assertFalse($options['http_errors']);
    }
}
