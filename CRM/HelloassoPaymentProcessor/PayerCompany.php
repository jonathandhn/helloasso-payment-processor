<?php

/**
 * Resolves the HelloAsso company name from the contribution contact.
 */
class CRM_HelloassoPaymentProcessor_PayerCompany
{
    public static function resolveForContribution(int $contributionId): string
    {
        if ($contributionId <= 0) {
            return '';
        }

        try {
            $contribution = \Civi\Api4\Contribution::get(FALSE)
                ->addSelect('contact_id')
                ->addWhere('id', '=', $contributionId)
                ->execute()
                ->first();
            if (empty($contribution['contact_id'])) {
                return '';
            }

            $organization = \Civi\Api4\Contact::get(FALSE)
                ->addSelect('contact_type', 'organization_name')
                ->addWhere('id', '=', (int) $contribution['contact_id'])
                ->execute()
                ->first();

            return self::organizationName($contribution, $organization ?: []);
        }
        catch (\Throwable $e) {
            return '';
        }
    }

    public static function organizationName(array $contribution, array $contact): string
    {
        if (($contact['contact_type'] ?? '') !== 'Organization') {
            return '';
        }

        return trim((string) ($contact['organization_name'] ?? ''));
    }
}
