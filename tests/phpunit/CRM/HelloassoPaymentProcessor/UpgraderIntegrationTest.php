<?php

use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

/**
 * Tests d'intégration pour l'Upgrader (idempotence DDL).
 *
 * Ce test ne dérive pas de CiviHeadlessTestCase pour éviter d'être englobé
 * dans une transaction MySQL (CRM_Core_Transaction). Les requêtes DDL
 * (CREATE TABLE, ALTER TABLE) forcent des commits implicites qui cassent
 * les transactions de tests.
 *
 * Comme le bootstrap a déjà booté CiviCRM, nous avons accès à la DB.
 *
 */
#[\PHPUnit\Framework\Attributes\Group('headless')]
class CRM_HelloassoPaymentProcessor_UpgraderIntegrationTest extends \PHPUnit\Framework\TestCase
{
    private CRM_HelloassoPaymentProcessor_Upgrader $upgrader;

    public function setUp(): void
    {
        parent::setUp();
        // L'upgrader prend généralement le nom de l'extension et le chemin.
        // On peut le récupérer proprement via le gestionnaire d'extensions :
        $mapper = \CRM_Extension_System::singleton()->getMapper();
        $this->upgrader = new CRM_HelloassoPaymentProcessor_Upgrader(
            'helloasso-payment-processor',
            $mapper->keyToBasePath('helloasso-payment-processor')
        );

        // L'upgrader a besoin d'un contexte avec un logger (propriété protected)
        $reflection = new \ReflectionProperty(CRM_HelloassoPaymentProcessor_Upgrader::class, 'ctx');
        $reflection->setAccessible(true);
        $reflection->setValue($this->upgrader, (object) [
            'log' => \Civi::log(),
        ]);

        // Injecter une file d'attente en mémoire pour ne pas crasher sur addTask()
        $queueReflection = new \ReflectionProperty(CRM_HelloassoPaymentProcessor_Upgrader::class, 'queue');
        $queueReflection->setAccessible(true);
        $queueReflection->setValue($this->upgrader, new \CRM_Queue_Queue_Memory(['name' => 'test-upgrade']));
    }

    public function testUpgradesAreIdempotentAndDoNotCrash(): void
    {
        // On récupère toutes les méthodes `upgrade_XXXX`
        $methods = get_class_methods($this->upgrader);
        $upgradeMethods = array_filter($methods, function ($method) {
            return strpos($method, 'upgrade_') === 0;
        });

        sort($upgradeMethods);

        foreach ($upgradeMethods as $method) {
            // L'exécution répétée ne doit pas crasher (idempotence)
            $result = $this->upgrader->$method();
            $this->assertTrue($result, "La méthode {$method} devrait retourner TRUE");
        }
    }

    public function testLegacyRepairTaskReturnsTrueWhenNoCandidates(): void
    {
        // Sur une DB de test propre, il n'y a pas de legacy candidates.
        // La méthode interne `runLegacyRepairUpgrade` (via upgrade_4203) le gère.
        // On va juste tester qu'un scan explicite sur 0 renvoie TRUE.
        $this->assertTrue($this->upgrader->taskLegacyRepairScan(0));
    }
}
