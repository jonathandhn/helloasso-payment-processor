<?php

class CRM_HelloassoPaymentProcessor_InstallmentLifecycleTest extends \PHPUnit\Framework\TestCase
{
    public function testAdvancesToNextPendingInstallment(): void
    {
        $result = CRM_HelloassoPaymentProcessor_InstallmentLifecycle::derive([
            $this->term(1, 'Authorized', '2026-06-08 10:00:00'),
            $this->term(2, 'Authorized', '2026-07-08 10:00:00'),
            $this->term(3, 'Pending', '2026-08-08 10:00:00'),
        ], 3);

        $this->assertSame('In Progress', $result['status']);
        $this->assertSame('20260808100000', $result['next_sched_contribution_date']);
        $this->assertNull($result['end_date']);
        $this->assertSame(0, $result['failure_count']);
    }

    public function testCompletesAfterLastInstallment(): void
    {
        $result = CRM_HelloassoPaymentProcessor_InstallmentLifecycle::derive([
            $this->term(1, 'Authorized', '2026-06-08 10:00:00'),
            $this->term(2, 'Registered', '2026-07-08 10:00:00'),
        ], 2);

        $this->assertSame('Completed', $result['status']);
        $this->assertNull($result['next_sched_contribution_date']);
        $this->assertSame('20260708100000', $result['end_date']);
        $this->assertSame(0, $result['failure_count']);
    }

    public function testMarksRefusedInstallmentOverdueAndKeepsRecoveryDate(): void
    {
        $result = CRM_HelloassoPaymentProcessor_InstallmentLifecycle::derive([
            $this->term(1, 'Authorized', '2026-06-08 10:00:00'),
            $this->term(2, 'Refused', '2026-07-08 10:00:00'),
            $this->term(3, 'Pending', '2026-08-08 10:00:00'),
        ], 3);

        $this->assertSame('Overdue', $result['status']);
        $this->assertSame('20260708100000', $result['next_sched_contribution_date']);
        $this->assertNull($result['end_date']);
        $this->assertSame(1, $result['failure_count']);
    }

    public function testRecoveredInstallmentRestoresPlanProgress(): void
    {
        $result = CRM_HelloassoPaymentProcessor_InstallmentLifecycle::derive([
            $this->term(1, 'Authorized', '2026-06-08 10:00:00'),
            $this->term(2, 'Authorized', '2026-07-10 10:00:00'),
            $this->term(3, 'Pending', '2026-08-08 10:00:00'),
        ], 3);

        $this->assertSame('In Progress', $result['status']);
        $this->assertSame('20260808100000', $result['next_sched_contribution_date']);
        $this->assertSame(0, $result['failure_count']);
    }

    public function testContestedInstallmentMarksPlanAsChargeback(): void
    {
        $result = CRM_HelloassoPaymentProcessor_InstallmentLifecycle::derive([
            $this->term(1, 'Authorized', '2026-06-08 10:00:00'),
            $this->term(2, 'Contested', '2026-07-08 10:00:00'),
        ], 2);

        $this->assertSame('Chargeback', $result['status']);
        $this->assertNull($result['next_sched_contribution_date']);
        $this->assertSame('20260608100000', $result['end_date']);
        $this->assertSame(1, $result['failure_count']);
    }

    public function testExpiredRecoveryMarksPlanFailed(): void
    {
        $result = CRM_HelloassoPaymentProcessor_InstallmentLifecycle::derive([
            $this->term(1, 'Authorized', '2026-06-08 10:00:00'),
            $this->term(2, 'RecoveryExpired', '2026-07-08 10:00:00'),
            $this->term(3, 'Pending', '2026-08-08 10:00:00'),
        ], 3);

        $this->assertSame('Failed', $result['status']);
        $this->assertNull($result['next_sched_contribution_date']);
        $this->assertSame('20260608100000', $result['end_date']);
        $this->assertSame(1, $result['failure_count']);
    }

    public function testPreservesExistingNextDateUntilFutureTermsAreMapped(): void
    {
        $result = CRM_HelloassoPaymentProcessor_InstallmentLifecycle::derive([
            $this->term(1, 'Authorized', '2026-06-08 10:00:00'),
        ], 2, '2026-07-08 10:00:00');

        $this->assertSame('In Progress', $result['status']);
        $this->assertSame('20260708100000', $result['next_sched_contribution_date']);
        $this->assertNull($result['end_date']);
    }

    private function term(int $number, string $state, string $date): array
    {
        return [
            'installment_number' => $number,
            'state' => $state,
            'payment_date' => $date,
        ];
    }
}
