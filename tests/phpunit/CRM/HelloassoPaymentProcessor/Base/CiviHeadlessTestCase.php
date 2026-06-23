<?php

/**
 * Base class for HelloAsso CiviCRM integration tests (PHPUnit 10/11).
 *
 * CiviCRM est booté au niveau full par bootstrap-integration.php.
 * Chaque test s'exécute dans une transaction MySQL rollbackée automatiquement
 * dans tearDown() — équivalent manuel de TransactionalInterface.
 *
 * Lancement :
 *   php vendor/bin/phpunit -c phpunit-integration.xml --no-coverage
 *
 * @group headless
 */
abstract class CRM_HelloassoPaymentProcessor_Base_CiviHeadlessTestCase
    extends \PHPUnit\Framework\TestCase
{
    private ?\CRM_Core_Transaction $transaction = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transaction = new \CRM_Core_Transaction(TRUE);
        $this->transaction->rollback();
    }

    protected function tearDown(): void
    {
        if ($this->transaction !== null) {
            $this->transaction->rollback()->commit();
            $this->transaction = null;
        }
        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers de création de fixtures
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Crée un processeur HelloAsso minimal en base pour les tests.
     *
     * @param array<string, mixed> $overrides
     */
    protected function createTestProcessor(array $overrides = []): int
    {
        $result = civicrm_api3('PaymentProcessor', 'create', array_merge([
            'name'                      => 'HelloAsso_test_' . uniqid(),
            'title'                     => 'HelloAsso Test',
            'class_name'                => 'Payment_HelloAsso',
            'payment_processor_type_id' => 15, // HelloAsso
            'financial_account_id'      => 12, // Compte Passerelle
            'is_active'                 => 1,
            'is_default'                => 0,
            'is_test'                   => 1,
            'billing_mode'              => 4,
            'payment_type'              => 1,
            'url_site'                  => 'https://api.helloasso-sandbox.com',
            'user_name'                 => 'test_client_id',
            'password'                  => 'test_client_secret',
            'subject'                   => 'test-org',
        ], $overrides));
        return (int) $result['id'];
    }

    /**
     * Crée un contact Individual minimal.
     *
     * @param array<string, mixed> $overrides
     */
    protected function createTestContact(array $overrides = []): int
    {
        $result = civicrm_api3('Contact', 'create', array_merge([
            'contact_type' => 'Individual',
            'first_name'   => 'Test',
            'last_name'    => 'HelloAsso_' . uniqid(),
        ], $overrides));
        return (int) $result['id'];
    }

    /**
     * Crée une ContributionRecur de test.
     *
     * @param array<string, mixed> $overrides
     */
    protected function createTestRecur(int $processorId, array $overrides = []): int
    {
        $contactId = (int) ($overrides['contact_id'] ?? $this->createTestContact());
        $result = civicrm_api3('ContributionRecur', 'create', array_merge([
            'contact_id'             => $contactId,
            'payment_processor_id'   => $processorId,
            'frequency_unit'         => 'month',
            'frequency_interval'     => 1,
            'amount'                 => 100,
            'currency'               => 'EUR',
            'financial_type_id'      => 1,
            'installments'           => 3,
            'contribution_status_id' => 'In Progress',
        ], $overrides));
        return (int) $result['id'];
    }

    /**
     * Crée une Contribution de test.
     *
     * @param array<string, mixed> $overrides
     */
    protected function createTestContribution(int $contactId, int $processorId, array $overrides = []): int
    {
        $result = civicrm_api3('Contribution', 'create', array_merge([
            'contact_id'             => $contactId,
            'payment_processor_id'   => $processorId,
            'total_amount'           => 100,
            'currency'               => 'EUR',
            'financial_type_id'      => 1,
            'contribution_status_id' => 'Completed',
            'receive_date'           => date('Y-m-d'),
        ], $overrides));
        return (int) $result['id'];
    }

    /**
     * Construit une identité d'installment factice avec des IDs uniques par appel.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array{
     *   order_id: int,
     *   installment_number: int,
     *   payment_id: int,
     *   checkout_intent_id: int,
     *   amount: int,
     *   payment_date: string,
     *   state: string
     * }
     */
    protected function makeInstallmentIdentity(array $overrides = []): array
    {
        static $seq = 0;
        $seq++;
        return array_merge([
            'order_id'           => 100000 + $seq,
            'installment_number' => 1,
            'payment_id'         => 200000 + $seq,
            'checkout_intent_id' => 300000 + $seq,
            'amount'             => 9900,
            'payment_date'       => date('Y-m-d H:i:s'),
            'state'              => 'Authorized',
        ], $overrides);
    }
}
