<?php

/**
 * Determines when a future HelloAsso installment should enter reconciliation.
 */
class CRM_HelloassoPaymentProcessor_InstallmentFollowUp
{
    public static function isFuturePending(array $paymentData, DateTimeImmutable $now): bool
    {
        if (($paymentData['state'] ?? NULL) !== 'Pending') {
            return FALSE;
        }

        if ((int) ($paymentData['installmentNumber'] ?? 0) < 2) {
            return FALSE;
        }

        $paymentDate = self::paymentDate($paymentData);
        return $paymentDate !== NULL && $paymentDate > $now;
    }

    public static function originDate(array $paymentData, DateTimeImmutable $fallback): DateTimeImmutable
    {
        $candidates = [
            $paymentData['date'] ?? NULL,
            $paymentData['meta']['createdAt'] ?? NULL,
        ];

        foreach ($candidates as $candidate) {
            if (empty($candidate)) {
                continue;
            }

            try {
                return new DateTimeImmutable((string) $candidate);
            }
            catch (Exception $e) {
            }
        }

        return $fallback;
    }

    private static function paymentDate(array $paymentData): ?DateTimeImmutable
    {
        if (empty($paymentData['date'])) {
            return NULL;
        }

        try {
            return new DateTimeImmutable((string) $paymentData['date']);
        }
        catch (Exception $e) {
            return NULL;
        }
    }
}
