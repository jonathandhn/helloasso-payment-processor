<?php

class CRM_HelloassoPaymentProcessor_ContributionStatusTest extends \PHPUnit\Framework\TestCase
{
    public function testUsesInProgressWhenCoreSupportsTheTransition(): void
    {
        $resolver = $this->createResolver(['Pending' => 2, 'In Progress' => 5], TRUE);

        $this->assertSame('In Progress', $resolver->getProcessingStatusName());
    }

    public function testFallsBackWhenInProgressIsUnavailable(): void
    {
        $resolver = $this->createResolver(['Pending' => 2], TRUE);

        $this->assertSame('Pending', $resolver->getProcessingStatusName());
    }

    public function testFallsBackWhenCoreRejectsTheTransition(): void
    {
        $resolver = $this->createResolver(['Pending' => 2, 'In Progress' => 5], FALSE);

        $this->assertSame('Pending', $resolver->getProcessingStatusName());
    }

    private function createResolver(array $statusIds, bool $transitionIsValid): CRM_HelloassoPaymentProcessor_ContributionStatus
    {
        return new class($statusIds, $transitionIsValid) extends CRM_HelloassoPaymentProcessor_ContributionStatus {
            private array $statusIds;
            private bool $transitionIsValid;

            public function __construct(array $statusIds, bool $transitionIsValid)
            {
                $this->statusIds = $statusIds;
                $this->transitionIsValid = $transitionIsValid;
            }

            protected function getStatusId(string $statusName): ?int
            {
                return $this->statusIds[$statusName] ?? NULL;
            }

            protected function isValidStatusTransition(int $oldStatusId, int $newStatusId): bool
            {
                return $this->transitionIsValid;
            }
        };
    }
}
