<?php

class CRM_HelloassoPaymentProcessor_PayerCompanyTest extends \PHPUnit\Framework\TestCase
{
    public function testReturnsOrganizationNameForOrganizationContribution(): void
    {
        $this->assertSame(
            'Club Sportif Exemple',
            CRM_HelloassoPaymentProcessor_PayerCompany::organizationName(
                [],
                [
                    'contact_type' => 'Organization',
                    'organization_name' => ' Club Sportif Exemple ',
                ]
            )
        );
    }

    public function testIgnoresNonOrganizationContact(): void
    {
        $this->assertSame(
            '',
            CRM_HelloassoPaymentProcessor_PayerCompany::organizationName(
                [],
                [
                    'contact_type' => 'Individual',
                    'organization_name' => '',
                ]
            )
        );
    }
}
