<?php

/**
 * Tests d'intégration pour InstallmentStore.
 *
 * Vérifie le comportement réel de la couche de persistance :
 * UPSERT, contraintes d'unicité MySQL, cohérence des lectures.
 *
 * Ces tests nécessitent un contexte CiviCRM complet avec la vraie base de
 * données. TransactionalInterface assure le rollback automatique après chaque
 * test — les tables civicrm_hello_asso_installment et civicrm_contribution_recur
 * sont restaurées sans intervention manuelle.
 *
 */
#[\PHPUnit\Framework\Attributes\Group('headless')]
class CRM_HelloassoPaymentProcessor_InstallmentStoreIntegrationTest
    extends CRM_HelloassoPaymentProcessor_Base_CiviHeadlessTestCase
{
    private CRM_HelloassoPaymentProcessor_InstallmentStore $store;
    private int $processorId;
    private int $recurId;
    private int $contactId;

    public function setUp(): void
    {
        parent::setUp();
        $this->store = new CRM_HelloassoPaymentProcessor_InstallmentStore();
        $this->processorId = $this->createTestProcessor();
        $this->contactId = $this->createTestContact();
        $this->recurId = $this->createTestRecur($this->processorId, ['contact_id' => $this->contactId]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // tableExists()
    // ──────────────────────────────────────────────────────────────────────────

    public function testTableExistsReturnsTrueWhenTableIsPresent(): void
    {
        $this->assertTrue($this->store->tableExists());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // claim() — insertion
    // ──────────────────────────────────────────────────────────────────────────

    public function testClaimInsertsRowAndReturnsNullContributionId(): void
    {
        $identity = $this->makeInstallmentIdentity();

        $result = $this->store->claim($this->processorId, $this->recurId, $identity);

        $this->assertIsInt($result['id']);
        $this->assertGreaterThan(0, $result['id']);
        $this->assertNull($result['contribution_id']);
    }

    public function testClaimStoresCorrectPaymentId(): void
    {
        $identity = $this->makeInstallmentIdentity(['payment_id' => 99999]);

        $result = $this->store->claim($this->processorId, $this->recurId, $identity);

        // findContributionId devrait retrouver la ligne par payment_id
        $found = $this->store->findContributionId(
            $this->processorId,
            99999,
            $identity['order_id'],
            $identity['installment_number']
        );
        $this->assertNull($found); // contribution pas encore attachée
    }

    public function testClaimIsIdempotentOnDuplicatePaymentId(): void
    {
        $identity = $this->makeInstallmentIdentity();

        $first  = $this->store->claim($this->processorId, $this->recurId, $identity);
        $second = $this->store->claim($this->processorId, $this->recurId, $identity);

        // La clé UNIQUE KEY uniq_processor_payment garantit le même id
        $this->assertSame($first['id'], $second['id']);
    }

    public function testClaimWithNullOptionalFields(): void
    {
        $identity = $this->makeInstallmentIdentity([
            'checkout_intent_id' => null,
            'amount'             => null,
            'payment_date'       => null,
            'state'              => null,
        ]);

        $result = $this->store->claim($this->processorId, $this->recurId, $identity);

        $this->assertIsInt($result['id']);
        $this->assertNull($result['contribution_id']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // attachContribution()
    // ──────────────────────────────────────────────────────────────────────────

    public function testAttachContributionLinksContributionId(): void
    {
        $identity       = $this->makeInstallmentIdentity();
        $claimed        = $this->store->claim($this->processorId, $this->recurId, $identity);
        $contactId      = (int) civicrm_api3('ContributionRecur', 'getvalue', [
            'id'     => $this->recurId,
            'return' => 'contact_id',
        ]);
        $contributionId = $this->createTestContribution($contactId, $this->processorId);

        $this->store->attachContribution($claimed['id'], $contributionId);

        // La ligne doit maintenant renvoyer le contribution_id
        $found = $this->store->findContributionId(
            $this->processorId,
            $identity['payment_id'],
            $identity['order_id'],
            $identity['installment_number']
        );
        $this->assertSame($contributionId, $found);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // findContributionId()
    // ──────────────────────────────────────────────────────────────────────────

    public function testFindContributionIdByPaymentId(): void
    {
        $identity       = $this->makeInstallmentIdentity();
        $claimed        = $this->store->claim($this->processorId, $this->recurId, $identity);
        $contactId      = (int) civicrm_api3('ContributionRecur', 'getvalue', [
            'id'     => $this->recurId,
            'return' => 'contact_id',
        ]);
        $contributionId = $this->createTestContribution($contactId, $this->processorId);
        $this->store->attachContribution($claimed['id'], $contributionId);

        $found = $this->store->findContributionId(
            $this->processorId,
            $identity['payment_id'],
            0,  // order_id différent — doit quand même trouver par payment_id
            99
        );

        $this->assertSame($contributionId, $found);
    }

    public function testFindContributionIdByOrderAndInstallmentNumber(): void
    {
        $identity = $this->makeInstallmentIdentity([
            'payment_id'         => 55555,
            'order_id'           => 77777,
            'installment_number' => 2,
        ]);
        $claimed        = $this->store->claim($this->processorId, $this->recurId, $identity);
        $contactId      = (int) civicrm_api3('ContributionRecur', 'getvalue', [
            'id'     => $this->recurId,
            'return' => 'contact_id',
        ]);
        $contributionId = $this->createTestContribution($contactId, $this->processorId);
        $this->store->attachContribution($claimed['id'], $contributionId);

        // Chercher avec un payment_id invalide mais order_id + installment_number corrects
        $found = $this->store->findContributionId(
            $this->processorId,
            0,      // payment_id inconnu
            77777,
            2
        );

        $this->assertSame($contributionId, $found);
    }

    public function testFindContributionIdReturnsNullWhenNotFound(): void
    {
        $found = $this->store->findContributionId($this->processorId, 999888777, 111222, 1);
        $this->assertNull($found);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // updateState()
    // ──────────────────────────────────────────────────────────────────────────

    public function testUpdateStateChangesStateInDb(): void
    {
        $identity = $this->makeInstallmentIdentity(['state' => 'Pending']);
        $this->store->claim($this->processorId, $this->recurId, $identity);

        $this->store->updateState($this->processorId, array_merge($identity, ['state' => 'Authorized']));

        // Vérifie directement en base
        $row = CRM_Core_DAO::executeQuery(
            'SELECT state FROM civicrm_hello_asso_installment
             WHERE payment_processor_id = %1 AND helloasso_payment_id = %2',
            [
                1 => [$this->processorId, 'Integer'],
                2 => [$identity['payment_id'], 'Integer'],
            ]
        );
        $this->assertTrue((bool) $row->fetch());
        $this->assertSame('Authorized', $row->state);
    }

    public function testUpdateStatePreservesExistingAmountWhenNull(): void
    {
        $identity = $this->makeInstallmentIdentity(['amount' => 5000, 'state' => 'Pending']);
        $this->store->claim($this->processorId, $this->recurId, $identity);

        // Mettre à jour uniquement l'état, laisser amount à null → COALESCE doit conserver 5000
        $this->store->updateState($this->processorId, array_merge($identity, [
            'amount' => null,
            'state'  => 'Authorized',
        ]));

        $row = CRM_Core_DAO::executeQuery(
            'SELECT amount, state FROM civicrm_hello_asso_installment
             WHERE payment_processor_id = %1 AND helloasso_payment_id = %2',
            [
                1 => [$this->processorId, 'Integer'],
                2 => [$identity['payment_id'], 'Integer'],
            ]
        );
        $this->assertTrue((bool) $row->fetch());
        $this->assertSame('5000', (string) $row->amount, 'COALESCE doit conserver le montant existant');
        $this->assertSame('Authorized', $row->state);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Scénario complet : claim → attach → lifecycle
    // ──────────────────────────────────────────────────────────────────────────

    public function testFullInstallmentWorkflow(): void
    {
        // 1. Claim
        $identity1 = $this->makeInstallmentIdentity(['installment_number' => 1, 'state' => 'Authorized']);
        $identity2 = $this->makeInstallmentIdentity(['installment_number' => 2, 'state' => 'Pending']);

        $row1 = $this->store->claim($this->processorId, $this->recurId, $identity1);
        $row2 = $this->store->claim($this->processorId, $this->recurId, $identity2);

        $this->assertNotSame($row1['id'], $row2['id']);
        $this->assertNull($row1['contribution_id']);
        $this->assertNull($row2['contribution_id']);

        // 2. Attach une contribution au premier installment
        $contactId = (int) civicrm_api3('ContributionRecur', 'getvalue', [
            'id' => $this->recurId, 'return' => 'contact_id',
        ]);
        $contrib1 = $this->createTestContribution($contactId, $this->processorId);
        $this->store->attachContribution($row1['id'], $contrib1);

        // 3. Vérification : le claim suivant doit voir le contribution_id attaché
        $reClaimed = $this->store->claim($this->processorId, $this->recurId, $identity1);
        $this->assertSame($row1['id'], $reClaimed['id']);
        $this->assertSame($contrib1, $reClaimed['contribution_id']);
    }
}
