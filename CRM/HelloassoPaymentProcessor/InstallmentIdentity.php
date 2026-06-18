<?php

/**
 * Extracts stable installment identifiers from a HelloAsso payment payload.
 */
class CRM_HelloassoPaymentProcessor_InstallmentIdentity
{
    /**
     * @return array{
     *   payment_id: int,
     *   order_id: int,
     *   checkout_intent_id: int|null,
     *   installment_number: int,
     *   amount: int|null,
     *   payment_date: string|null,
     *   state: string|null
     * }|null
     */
    public static function fromPayment(array $paymentData, array $orderData = []): ?array
    {
        $paymentId = self::positiveInt($paymentData['id'] ?? NULL);
        $orderId = self::positiveInt($orderData['id'] ?? ($paymentData['order']['id'] ?? NULL));
        $installmentNumber = self::positiveInt($paymentData['installmentNumber'] ?? NULL);

        if (!$paymentId || !$orderId || !$installmentNumber) {
            return NULL;
        }

        return [
            'payment_id' => $paymentId,
            'order_id' => $orderId,
            'checkout_intent_id' => self::positiveInt(
                $orderData['checkoutIntentId'] ?? ($paymentData['order']['checkoutIntentId'] ?? NULL)
            ),
            'installment_number' => $installmentNumber,
            'amount' => self::nonNegativeInt($paymentData['amount'] ?? NULL),
            'payment_date' => self::nullableString($paymentData['date'] ?? NULL),
            'state' => self::nullableString($paymentData['state'] ?? NULL),
        ];
    }

    private static function positiveInt($value): ?int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return NULL;
        }

        $value = (int) $value;
        return $value > 0 ? $value : NULL;
    }

    private static function nonNegativeInt($value): ?int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return NULL;
        }

        $value = (int) $value;
        return $value >= 0 ? $value : NULL;
    }

    private static function nullableString($value): ?string
    {
        if (!is_scalar($value)) {
            return NULL;
        }

        $value = trim((string) $value);
        return $value !== '' ? $value : NULL;
    }
}
