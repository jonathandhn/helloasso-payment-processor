<?php

/**
 * Converts CiviCRM finite recurring properties into HelloAsso Checkout terms.
 */
class CRM_HelloassoPaymentProcessor_InstallmentPlan
{
    private const MIN_INSTALLMENTS = 2;
    private const MAX_INSTALLMENTS = 12;

    /**
     * @return array{
     *   totalAmount: int,
     *   initialAmount: int,
     *   terms: array<int, array{amount: int, date: string}>
     * }
     */
    public static function buildMonthly(
        int $installmentAmount,
        int $installmentCount,
        int $frequencyInterval,
        string $frequencyUnit,
        DateTimeImmutable $checkoutDate
    ): array {
        if ($installmentAmount < 1) {
            throw new InvalidArgumentException('The installment amount must be positive.');
        }

        if (
            $installmentCount < self::MIN_INSTALLMENTS
            || $installmentCount > self::MAX_INSTALLMENTS
        ) {
            throw new InvalidArgumentException('HelloAsso requires between two and twelve finite installments.');
        }

        if ($frequencyUnit !== 'month' || $frequencyInterval !== 1) {
            throw new InvalidArgumentException('HelloAsso installments must use a monthly frequency.');
        }

        if ($installmentAmount > intdiv(PHP_INT_MAX, $installmentCount)) {
            throw new InvalidArgumentException('The installment schedule total is too large.');
        }

        $collectionDay = min((int) $checkoutDate->format('j'), 27);

        return CRM_HelloassoPaymentProcessor_InstallmentSchedule::buildMonthly(
            $installmentAmount * $installmentCount,
            $installmentCount,
            $checkoutDate,
            $collectionDay
        );
    }
}
