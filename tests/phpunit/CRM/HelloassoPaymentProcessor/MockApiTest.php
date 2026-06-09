<?php
require_once __DIR__ . '/../../../../api/v3/Job/ProcessHelloasso.php';

class CRM_HelloassoPaymentProcessor_MockApiTest extends \PHPUnit\Framework\TestCase
{
    public function testCronJobWrapper(): void
    {
        $apiCalls = [];
        $processorCallRecorder = new class {
            public array $calls = [];
        };

        $result = _civicrm_api3_job_process_helloasso_run(
            ['limit' => 5, 'status_names' => 'Pending, Failed'],
            static function (string $entity, string $action, array $params) use (&$apiCalls): array {
                $apiCalls[] = [$entity, $action, $params];

                return ['values' => [['id' => 14, 'is_test' => 0]]];
            },
            static function (string $mode, array $processor) use ($processorCallRecorder): object {
                return new class($mode, $processor, $processorCallRecorder) {
                    public function __construct(
                        private string $mode,
                        private array $processor,
                        private object $callRecorder
                    ) {
                    }

                    public function processScheduledSynchronization(array $options): array
                    {
                        $this->callRecorder->calls[] = [$this->mode, $this->processor, $options];

                        return ['checked' => 3, 'updated' => 2, 'errors' => ['example error']];
                    }
                };
            },
            static function (array $values, array $params, string $entity, string $action): array {
                return compact('values', 'params', 'entity', 'action');
            }
        );

        $this->assertSame('PaymentProcessor', $apiCalls[0][0]);
        $this->assertSame('get', $apiCalls[0][1]);
        $this->assertSame(1, $apiCalls[0][2]['is_active']);
        $this->assertSame('live', $processorCallRecorder->calls[0][0]);
        $this->assertSame(['Pending', 'Failed'], $processorCallRecorder->calls[0][2]['status_names']);
        $this->assertSame(5, $processorCallRecorder->calls[0][2]['limit']);
        $this->assertSame(
            ['processors' => 1, 'checked' => 3, 'updated' => 2, 'errors' => ['[processor:14] example error']],
            $result['values']
        );
        $this->assertSame('Job', $result['entity']);
        $this->assertSame('process_helloasso', $result['action']);
    }
}
