<?php

class CRM_HelloassoPaymentProcessor_QuickFormInstallmentsTest extends \PHPUnit\Framework\TestCase
{
    public function testBlankValueKeepsOneTimePaymentFieldsUntouched(): void
    {
        $fields = ['helloasso_installments' => ''];

        $this->assertSame(
            $fields,
            CRM_HelloassoPaymentProcessor_QuickFormInstallments::apply($fields)
        );
    }

    public function testMapsFiniteMonthlyInstallmentsToNativeFields(): void
    {
        $result = CRM_HelloassoPaymentProcessor_QuickFormInstallments::apply([
            'helloasso_installments' => '12',
        ]);

        $this->assertSame(12, $result['helloasso_installments']);
        $this->assertSame(1, $result['is_recur']);
        $this->assertSame(12, $result['installments']);
        $this->assertSame('month', $result['frequency_unit']);
        $this->assertSame(1, $result['frequency_interval']);
    }

    /**
     * @dataProvider invalidInstallmentsProvider
     */
    public function testRejectsValuesOutsideSupportedRange($value): void
    {
        $this->expectException(InvalidArgumentException::class);
        CRM_HelloassoPaymentProcessor_QuickFormInstallments::apply([
            'helloasso_installments' => $value,
        ]);
    }

    public static function invalidInstallmentsProvider(): array
    {
        return [
            'one' => [1],
            'thirteen' => [13],
            'decimal' => ['2.5'],
            'text' => ['monthly'],
        ];
    }
}
