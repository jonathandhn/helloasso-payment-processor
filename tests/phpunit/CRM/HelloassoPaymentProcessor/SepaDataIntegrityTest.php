<?php

class CRM_HelloassoPaymentProcessor_SepaDataIntegrityTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Teste qu'un payload webhook de type SEPA est correctement analysé 
     * par le code d'identité sans provoquer de plantage.
     */
    public function testParsesSepaPayloadWithoutError(): void
    {
        // Simulation d'un retour API HelloAsso pour un prélèvement SEPA théorique
        $sepaPayload = [
            'id' => 888999,
            'installmentNumber' => 1, // Payement unique (sepa classique)
            'amount' => 5000,
            'date' => '2026-06-15T12:00:00+02:00',
            'state' => 'Waiting', // État classique pour un SEPA en attente de traitement bancaire
            'paymentMeans' => 'Sepa', // La clé vitale envoyée par l'API
            'order' => [
                'id' => 777666,
                'checkoutIntentId' => 555
            ]
        ];

        // Vérification de l'extraction de base (Identity)
        $identity = CRM_HelloassoPaymentProcessor_InstallmentIdentity::fromPayment($sepaPayload);
        $this->assertSame(888999, $identity['payment_id']);
        $this->assertSame(5000, $identity['amount']);
        $this->assertSame('Waiting', $identity['state']);
        
        // Vérification du calendrier de suivi (FollowUp) via la méthode interne du Cron
        $processor = (new ReflectionClass(CRM_Core_Payment_HelloAsso::class))
            ->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($processor, 'detectLongFollowUpScheme');
        $method->setAccessible(TRUE);
        
        // Pour installmentNumber = 1, le type est 'sepa' normal (pas un échéancier)
        $this->assertSame('sepa', $method->invoke($processor, $sepaPayload));
    }

    /**
     * Teste que les états d'attente spécifiques au SEPA ne sont pas considérés
     * comme terminés (terminal) par notre système de transaction.
     */
    public function testSepaStatesAreHandledCorrectly(): void
    {
        $this->assertFalse(CRM_HelloassoPaymentProcessor_PaymentState::isLongFollowUpTerminal('Waiting'));
        $this->assertFalse(CRM_HelloassoPaymentProcessor_PaymentState::isLongFollowUpTerminal('WaitingBankValidation'));
        
        // Si la banque refuse le mandat SEPA plus tard, l'état devient Refused (terminal)
        $this->assertTrue(CRM_HelloassoPaymentProcessor_PaymentState::isLongFollowUpTerminal('Refused'));
    }
}
