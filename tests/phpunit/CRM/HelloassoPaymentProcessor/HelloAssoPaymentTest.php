<?php

class CRM_HelloassoPaymentProcessor_HelloAssoPaymentTest extends \PHPUnit\Framework\TestCase
{
    public function testBuildCheckoutAmountFieldsUsesQuickFormInstallmentSelection(): void
    {
        $processor = (new ReflectionClass(CRM_Core_Payment_HelloAsso::class))
            ->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($processor, 'resolveCheckoutInstallmentCount');
        $method->setAccessible(TRUE);

        $propertyBag = \Civi\Payment\PropertyBag::cast([
            'amount' => 50.00,
            'is_recur' => TRUE,
            'frequency_unit' => 'month',
            'frequency_interval' => 1,
        ]);

        $count = $method->invoke($processor, $propertyBag, [
            'helloasso_installments' => 2,
        ]);

        $this->assertSame(2, $count);
    }

    public function testScheduledSynchronizationForwardsNormalizedFilters(): void
    {
        $processorArray = ['id' => 14];
        $processor = new class('test', $processorArray) extends CRM_Core_Payment_HelloAsso {
            public int $capturedLimit = 0;

            public array $capturedFilters = [];

            public function synchronizePendingContributions(
                int $limit = 30,
                array $filters = [],
                string $requestProfile = CRM_HelloassoPaymentProcessor_RequestOptions::PROFILE_DEFAULT
            ): array
            {
                $this->capturedLimit = $limit;
                $this->capturedFilters = $filters;

                return ['checked' => 1, 'updated' => 0, 'errors' => []];
            }
        };

        $result = $processor->processScheduledSynchronization([
            'limit' => 7,
            'contribution_id' => '42',
            'status_names' => 'Pending',
            'only_scheduled' => FALSE,
            'allow_recent_scan' => TRUE,
        ]);

        $this->assertSame(7, $processor->capturedLimit);
        $this->assertSame(42, $processor->capturedFilters['contribution_id']);
        $this->assertSame(['Pending'], $processor->capturedFilters['status_names']);
        $this->assertFalse($processor->capturedFilters['only_scheduled']);
        $this->assertTrue($processor->capturedFilters['allow_recent_scan']);
        $this->assertSame(['checked' => 1, 'updated' => 0, 'errors' => []], $result);
    }

    public function testAllocateAmountCentsByWeightPreservesTargetAmount(): void
    {
        $processor = (new ReflectionClass(CRM_Core_Payment_HelloAsso::class))
            ->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($processor, 'allocateAmountCentsByWeight');
        $method->setAccessible(TRUE);

        $allocated = $method->invoke($processor, 500, [700, 300]);

        $this->assertSame([350, 150], $allocated);
        $this->assertSame(500, array_sum($allocated));
    }

    public function testAllocateAmountCentsByWeightFallsBackToFirstSlotWhenWeightsAreZero(): void
    {
        $processor = (new ReflectionClass(CRM_Core_Payment_HelloAsso::class))
            ->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($processor, 'allocateAmountCentsByWeight');
        $method->setAccessible(TRUE);

        $allocated = $method->invoke($processor, 500, [0, 0, 0]);

        $this->assertSame([500, 0, 0], $allocated);
    }
}
