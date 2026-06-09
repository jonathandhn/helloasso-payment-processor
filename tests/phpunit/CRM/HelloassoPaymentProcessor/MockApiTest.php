<?php
require_once __DIR__ . '/../../../../api/v3/Job/ProcessHelloasso.php';

function civicrm_api3($entity, $action, $params = []) {
    echo "MOCK API CALLED: $entity $action\n";
    return ['values' => [['id' => 14, 'is_test' => 0]]];
}

class CRM_HelloassoPaymentProcessor_MockApiTest extends \PHPUnit\Framework\TestCase {
    public function testCronJobWrapper() {
        try {
            civicrm_api3_job_process_helloasso(['limit' => 5]);
        } catch (\Throwable $e) {
            echo "Exception: " . $e->getMessage() . "\n";
        }
        $this->assertTrue(true);
    }
}
