<?php

class CRM_HelloassoPaymentProcessor_CronDataIntegrityTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Teste la conversion des montants (Centimes HelloAsso vers format décimal CiviCRM)
     * et l'extraction des données clés.
     */
    public function testExtractsCorrectAmountsAndDatesFromHelloAssoPayload(): void
    {
        // Données réalistes inspirées de la contribution ID 79340 (10.00 EUR en prod)
        $helloassoApiPaymentResponse = [
            'id' => 1234567,
            'installmentNumber' => 1,
            'amount' => 1000, // 10 Euros = 1000 centimes
            'date' => '2026-05-07T00:17:07+02:00',
            'state' => 'Authorized',
            'order' => [
                'id' => 98765,
                'checkoutIntentId' => 456
            ]
        ];

        $identity = CRM_HelloassoPaymentProcessor_InstallmentIdentity::fromPayment($helloassoApiPaymentResponse);

        // 1. Vérifie que le parser récupère bien l'entier exact sans perte
        $this->assertSame(1000, $identity['amount'], 'Le montant brut en centimes doit être extrait intact');
        
        // 2. Vérifie la logique de conversion utilisée par le Cron CiviCRM (centimes -> decimal string)
        $convertedAmount = number_format(((int) $identity['amount']) / 100, 2, '.', '');
        $this->assertSame('10.00', $convertedAmount, 'Le montant doit être converti en string décimal pour CiviCRM');
        
        // 3. Vérifie les dates
        $this->assertSame('2026-05-07T00:17:07+02:00', $identity['payment_date'], 'La date ISO 8601 est préservée');
    }

    /**
     * Teste l'erreur d'une association non validée par HelloAsso
     * (Le KYC n'est pas fait, l'association n'a pas le droit d'encaisser)
     */
    public function testDetectsUnvalidatedAssociationError(): void
    {
        // L'API HelloAsso renvoie un HTTP 409 Conflict si l'association
        // n'a pas validé ses documents d'identité pour créer un paiement.
        $isBlocked = CRM_HelloassoPaymentProcessor_ApiErrorClassifier::isOrganizationPaymentBlocked(
            'POST',
            '/v5/organizations/mon-asso/checkout-intents',
            409
        );

        $this->assertTrue($isBlocked, 'Une erreur 409 sur la création de paiement signifie KYC manquant');
    }
}
