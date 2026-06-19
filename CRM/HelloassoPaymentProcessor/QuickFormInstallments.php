<?php

/**
 * Maps the HelloAsso QuickForm control to CiviCRM recurring fields.
 */
class CRM_HelloassoPaymentProcessor_QuickFormInstallments
{
    public static function apply(array $fields): array
    {
        $value = trim((string) ($fields['helloasso_installments'] ?? ''));
        if ($value === '') {
            return $fields;
        }

        $installments = filter_var($value, FILTER_VALIDATE_INT);
        if ($installments === FALSE || $installments < 2 || $installments > 12) {
            throw new InvalidArgumentException('HelloAsso requires between 2 and 12 installments.');
        }

        $fields['helloasso_installments'] = $installments;
        $fields['is_recur'] = 1;
        $fields['installments'] = $installments;
        $fields['frequency_unit'] = 'month';
        $fields['frequency_interval'] = 1;

        return $fields;
    }
}
