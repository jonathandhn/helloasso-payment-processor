<?php

/**
 * Maps every HelloAsso payment state to a CiviCRM-facing outcome.
 */
class CRM_HelloassoPaymentProcessor_PaymentState
{
    public const SUCCESS = 'success';
    public const PENDING = 'pending';
    public const FAILED = 'failed';
    public const REFUNDING = 'refunding';
    public const REFUNDED = 'refunded';
    public const CONTESTED = 'contested';

    public static function outcome(string $state): string
    {
        if (in_array($state, ['Authorized', 'Registered', 'AuthorizedPreprod', 'Corrected'], TRUE)) {
            return self::SUCCESS;
        }

        if (in_array($state, [
            'Pending',
            'Unknown',
            'Waiting',
            'WaitingBankValidation',
            'WaitingBankWithdraw',
            'WaitingAuthentication',
            'Init',
        ], TRUE)) {
            return self::PENDING;
        }

        if (in_array($state, [
            'Refused',
            'Error',
            'Canceled',
            'Abandoned',
            'Deleted',
            'Inconsistent',
            'NoDonation',
            'RecoveryExpired',
        ], TRUE)) {
            return self::FAILED;
        }

        if ($state === 'Refunding') {
            return self::REFUNDING;
        }

        if ($state === 'Refunded') {
            return self::REFUNDED;
        }

        if ($state === 'Contested') {
            return self::CONTESTED;
        }

        // New HelloAsso states must not create a false failure.
        return self::PENDING;
    }

    public static function isShortFollowUpTerminal(string $state): bool
    {
        return in_array(self::outcome($state), [
            self::SUCCESS,
            self::FAILED,
            self::REFUNDED,
            self::CONTESTED,
        ], TRUE);
    }

    public static function isLongFollowUpTerminal(string $state): bool
    {
        return in_array(self::outcome($state), [
            self::FAILED,
            self::REFUNDED,
            self::CONTESTED,
        ], TRUE);
    }
}
