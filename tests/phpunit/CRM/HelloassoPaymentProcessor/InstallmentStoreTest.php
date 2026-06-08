<?php

class CRM_HelloassoPaymentProcessor_InstallmentStoreTest extends \PHPUnit\Framework\TestCase
{
    public function testNullValueUsesSqlNullWithoutTypedParameter(): void
    {
        $store = new CRM_HelloassoPaymentProcessor_TestableInstallmentStore();
        $params = [];

        $sql = $store->exposeNullableParameter($params, 3, NULL, 'Integer');

        $this->assertSame('NULL', $sql);
        $this->assertSame([], $params);
    }

    public function testPresentValueUsesTypedParameter(): void
    {
        $store = new CRM_HelloassoPaymentProcessor_TestableInstallmentStore();
        $params = [];

        $sql = $store->exposeNullableParameter($params, 3, 224536, 'Integer');

        $this->assertSame('%3', $sql);
        $this->assertSame([3 => [224536, 'Integer']], $params);
    }
}

class CRM_HelloassoPaymentProcessor_TestableInstallmentStore extends CRM_HelloassoPaymentProcessor_InstallmentStore
{
    public function exposeNullableParameter(
        array &$params,
        int $index,
        $value,
        string $type
    ): string {
        return $this->nullableParameter($params, $index, $value, $type);
    }
}
