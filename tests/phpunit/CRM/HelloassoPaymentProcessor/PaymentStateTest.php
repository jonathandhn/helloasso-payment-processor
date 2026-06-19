<?php


use PHPUnit\Framework\Attributes\DataProvider;
class CRM_HelloassoPaymentProcessor_PaymentStateTest extends \PHPUnit\Framework\TestCase
{
    /**
     */
    #[DataProvider("outcomeProvider")]
    public function testMapsPublishedHelloAssoStates(string $state, string $expected): void
    {
        $this->assertSame(
            $expected,
            CRM_HelloassoPaymentProcessor_PaymentState::outcome($state)
        );
    }

    public static function outcomeProvider(): array
    {
        return [
            'Pending' => ['Pending', 'pending'],
            'Authorized' => ['Authorized', 'success'],
            'Refused' => ['Refused', 'failed'],
            'Unknown' => ['Unknown', 'pending'],
            'Registered' => ['Registered', 'success'],
            'Error' => ['Error', 'failed'],
            'Refunded' => ['Refunded', 'refunded'],
            'Refunding' => ['Refunding', 'refunding'],
            'Waiting' => ['Waiting', 'pending'],
            'Canceled' => ['Canceled', 'failed'],
            'Contested' => ['Contested', 'contested'],
            'WaitingBankValidation' => ['WaitingBankValidation', 'pending'],
            'WaitingBankWithdraw' => ['WaitingBankWithdraw', 'pending'],
            'Abandoned' => ['Abandoned', 'failed'],
            'WaitingAuthentication' => ['WaitingAuthentication', 'pending'],
            'AuthorizedPreprod' => ['AuthorizedPreprod', 'success'],
            'Corrected' => ['Corrected', 'success'],
            'Deleted' => ['Deleted', 'failed'],
            'Inconsistent' => ['Inconsistent', 'failed'],
            'NoDonation' => ['NoDonation', 'failed'],
            'Init' => ['Init', 'pending'],
            'RecoveryExpired' => ['RecoveryExpired', 'failed'],
        ];
    }

    public function testUnknownFutureStateRemainsPending(): void
    {
        $this->assertSame(
            CRM_HelloassoPaymentProcessor_PaymentState::PENDING,
            CRM_HelloassoPaymentProcessor_PaymentState::outcome('FutureApiState')
        );
    }

    public function testSepaWaitingStatesRemainNonTerminal(): void
    {
        foreach (['WaitingBankValidation', 'WaitingBankWithdraw'] as $state) {
            $this->assertFalse(CRM_HelloassoPaymentProcessor_PaymentState::isShortFollowUpTerminal($state));
            $this->assertFalse(CRM_HelloassoPaymentProcessor_PaymentState::isLongFollowUpTerminal($state));
        }
    }

    public function testContestedIsTerminalForBothFollowUpRails(): void
    {
        $this->assertTrue(CRM_HelloassoPaymentProcessor_PaymentState::isShortFollowUpTerminal('Contested'));
        $this->assertTrue(CRM_HelloassoPaymentProcessor_PaymentState::isLongFollowUpTerminal('Contested'));
    }
}
