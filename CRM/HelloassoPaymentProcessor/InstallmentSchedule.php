<?php

/**
 * Builds finite monthly installment schedules accepted by HelloAsso Checkout.
 *
 * This class is deliberately independent from CiviCRM recurring contributions.
 * It only models the Checkout request amounts and future term dates.
 */
class CRM_HelloassoPaymentProcessor_InstallmentSchedule
{
    private const MAX_COLLECTION_DAY = 27;
    private const MAX_FUTURE_TERMS = 11;

    /**
     * Build a monthly schedule with one immediate payment and future terms.
     *
     * @return array{
     *   totalAmount: int,
     *   initialAmount: int,
     *   terms: array<int, array{amount: int, date: string}>
     * }
     */
    public static function buildMonthly(
        int $totalAmount,
        int $installmentCount,
        DateTimeImmutable $checkoutDate,
        int $collectionDay
    ): array {
        self::assertValidInput($totalAmount, $installmentCount, $collectionDay);

        $amounts = self::splitAmount($totalAmount, $installmentCount);
        $terms = [];

        for ($index = 1; $index < $installmentCount; $index++) {
            $termMonth = $checkoutDate->modify('first day of +' . $index . ' month');
            $termDate = $termMonth
                ->setDate(
                    (int) $termMonth->format('Y'),
                    (int) $termMonth->format('m'),
                    $collectionDay
                );

            $terms[] = [
                'amount' => $amounts[$index],
                'date' => $termDate->format('Y-m-d'),
            ];
        }

        return [
            'totalAmount' => $totalAmount,
            'initialAmount' => $amounts[0],
            'terms' => $terms,
        ];
    }

    private static function assertValidInput(int $totalAmount, int $installmentCount, int $collectionDay): void
    {
        if ($installmentCount < 2) {
            throw new InvalidArgumentException('An installment schedule requires at least two payments.');
        }

        if (($installmentCount - 1) > self::MAX_FUTURE_TERMS) {
            throw new InvalidArgumentException('HelloAsso installments are limited to twelve payments by this integration.');
        }

        if ($totalAmount < $installmentCount) {
            throw new InvalidArgumentException('Each installment must contain at least one cent.');
        }

        if ($collectionDay < 1 || $collectionDay > self::MAX_COLLECTION_DAY) {
            throw new InvalidArgumentException('HelloAsso installment dates must fall between day 1 and day 27.');
        }
    }

    /**
     * Split an integer amount without losing or creating a cent.
     *
     * Any remainder is allocated to the earliest payments.
     *
     * @return int[]
     */
    private static function splitAmount(int $totalAmount, int $installmentCount): array
    {
        $baseAmount = intdiv($totalAmount, $installmentCount);
        $remainder = $totalAmount % $installmentCount;
        $amounts = [];

        for ($index = 0; $index < $installmentCount; $index++) {
            $amounts[] = $baseAmount + ($index < $remainder ? 1 : 0);
        }

        return $amounts;
    }
}
