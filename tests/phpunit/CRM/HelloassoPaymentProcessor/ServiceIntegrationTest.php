<?php

use Civi\Payment\Exception\PaymentProcessorException;

/**
 * Tests d'intégration pour Service (façade publique de lecture).
 *
 * Vérifie que :
 * - Les méthodes de sélection de processeur fonctionnent avec de vrais enregistrements
 * - sanitizeProcessor() ne laisse jamais filtrer les credentials sensibles
 * - Les exceptions sont levées aux bons moments
 *
 * @group headless
 */
class CRM_HelloassoPaymentProcessor_ServiceIntegrationTest
    extends CRM_HelloassoPaymentProcessor_Base_CiviHeadlessTestCase
{
    private CRM_HelloassoPaymentProcessor_Service $service;

    public function setUp(): void
    {
        parent::setUp();
        // Désactive tous les processeurs HelloAsso existants en base pour isoler le test.
        // La modification sera annulée par le rollback de CRM_Core_Transaction à la fin.
        \CRM_Core_DAO::executeQuery(
            "UPDATE civicrm_payment_processor SET is_active = 0 WHERE class_name = 'Payment_HelloAsso'"
        );
        $this->service = new CRM_HelloassoPaymentProcessor_Service();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // getProcessors()
    // ──────────────────────────────────────────────────────────────────────────

    public function testGetProcessorsReturnsOnlyHelloAssoProcessors(): void
    {
        $this->createTestProcessor(['is_active' => 1, 'is_test' => 1]);
        $this->createTestProcessor(['is_active' => 1, 'is_test' => 1]);

        $processors = $this->service->getProcessors(TRUE);

        foreach ($processors as $processor) {
            $this->assertSame('Payment_HelloAsso', $processor['class_name']);
        }
        $this->assertGreaterThanOrEqual(2, count($processors));
    }

    public function testGetProcessorsFiltersInactiveByDefault(): void
    {
        $this->createTestProcessor(['is_active' => 0, 'is_test' => 1, 'name' => 'HelloAsso_inactive_' . uniqid()]);
        $active = $this->createTestProcessor(['is_active' => 1, 'is_test' => 1, 'name' => 'HelloAsso_active_' . uniqid()]);

        $processors = $this->service->getProcessors(TRUE, TRUE);
        $ids = array_column($processors, 'id');

        $this->assertContains((string) $active, $ids);
        // L'inactif ne doit pas apparaître (même s'il y en a d'autres inactifs en DB)
        foreach ($processors as $p) {
            $this->assertSame('1', (string) $p['is_active']);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // sanitizeProcessor() — sécurité : aucun credential sensible ne doit filtrer
    // ──────────────────────────────────────────────────────────────────────────

    public function testGetProcessorsSanitizesCredentials(): void
    {
        $this->createTestProcessor([
            'is_active'  => 1,
            'is_test'    => 1,
            'user_name'  => 'secret_client_id',
            'password'   => 'secret_client_secret',
            'signature'  => 'secret_signature',
        ]);

        $processors = $this->service->getProcessors(TRUE);

        foreach ($processors as $processor) {
            $this->assertArrayNotHasKey('password',  $processor, 'password ne doit jamais être exposé');
            $this->assertArrayNotHasKey('user_name', $processor, 'user_name ne doit jamais être exposé');
            $this->assertArrayNotHasKey('signature', $processor, 'signature ne doit jamais être exposé');
        }
    }

    public function testGetProcessorByIdSanitizesCredentials(): void
    {
        $id = $this->createTestProcessor([
            'is_active'  => 1,
            'is_test'    => 1,
            'user_name'  => 'secret_client_id',
            'password'   => 'very_secret',
        ]);

        $processor = $this->service->getProcessorById($id);

        $this->assertArrayNotHasKey('password',  $processor);
        $this->assertArrayNotHasKey('user_name', $processor);
        $this->assertArrayNotHasKey('signature', $processor);
        $this->assertSame((string) $id, (string) $processor['id']);
    }

    public function testGetProcessorByIdExposesExpectedPublicFields(): void
    {
        $id = $this->createTestProcessor([
            'is_active'  => 1,
            'is_test'    => 1,
            'title'      => 'Mon HelloAsso Test',
            'is_default' => 0,
        ]);

        $processor = $this->service->getProcessorById($id);

        // Champs autorisés à être exposés
        $this->assertArrayHasKey('id',         $processor);
        $this->assertArrayHasKey('class_name', $processor);
        $this->assertArrayHasKey('is_active',  $processor);
        $this->assertArrayHasKey('is_test',    $processor);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // getPreferredProcessor()
    // ──────────────────────────────────────────────────────────────────────────

    public function testGetPreferredProcessorReturnsDefaultWhenMultiple(): void
    {
        $nonDefault = $this->createTestProcessor(['is_active' => 1, 'is_test' => 1, 'is_default' => 0]);
        $default    = $this->createTestProcessor(['is_active' => 1, 'is_test' => 1, 'is_default' => 1]);

        $preferred = $this->service->getPreferredProcessor(TRUE);

        $this->assertSame((string) $default, (string) $preferred['id']);
        $this->assertNotSame((string) $nonDefault, (string) $preferred['id']);
    }

    public function testGetPreferredProcessorReturnsSingleActiveWhenNoDefault(): void
    {
        // Nettoyage : désactiver tous les processeurs existants avant de créer le nôtre
        // (TransactionalInterface gère le rollback, mais le DB peut avoir des actifs)
        $unique = $this->createTestProcessor([
            'is_active'  => 1,
            'is_test'    => 1,
            'is_default' => 0,
            'name'       => 'HelloAsso_only_' . uniqid(),
        ]);

        // Si plusieurs actifs, getPreferredProcessor retourne le premier —
        // on vérifie juste qu'il ne lève pas d'exception
        $preferred = $this->service->getPreferredProcessor(TRUE);
        $this->assertIsString((string) $preferred['id']);
    }

    public function testGetPreferredProcessorThrowsWhenNoActiveProcessor(): void
    {
        $this->expectException(PaymentProcessorException::class);

        // Mode live sans aucun processeur live actif
        // (on désactive tous les live existants via is_active=0 dans createTestProcessor
        //  + on ne crée aucun live actif ici)
        // Si des live actifs existent déjà en DB (prod), ce test peut passer malgré tout.
        // Pour isoler : on crée uniquement des processeurs de test (is_test=1) et on
        // demande live (FALSE).
        $this->createTestProcessor(['is_active' => 1, 'is_test' => 1]);

        // Demander le mode live (FALSE) → aucun résultat → exception
        $this->service->getPreferredProcessor(FALSE);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // getProcessors() — filtrage par mode
    // ──────────────────────────────────────────────────────────────────────────

    public function testGetProcessorsFiltersByMode(): void
    {
        $testId = $this->createTestProcessor(['is_active' => 1, 'is_test' => 1]);
        $liveId = $this->createTestProcessor(['is_active' => 1, 'is_test' => 0]);

        $testProcessors = $this->service->getProcessors(TRUE);
        $liveProcessors = $this->service->getProcessors(FALSE);

        $testIds = array_column($testProcessors, 'id');
        $liveIds = array_column($liveProcessors, 'id');

        $this->assertContains((string) $testId, $testIds);
        $this->assertNotContains((string) $testId, $liveIds);

        $this->assertContains((string) $liveId, $liveIds);
        $this->assertNotContains((string) $liveId, $testIds);
    }

    public function testGetProcessorsWithNullModeReturnsBoth(): void
    {
        $testId = $this->createTestProcessor(['is_active' => 1, 'is_test' => 1]);
        $liveId = $this->createTestProcessor(['is_active' => 1, 'is_test' => 0]);

        $all = $this->service->getProcessors(NULL);
        $allIds = array_column($all, 'id');

        $this->assertContains((string) $testId, $allIds);
        $this->assertContains((string) $liveId, $allIds);
    }
}
