<?php

namespace Civi\HelloassoPaymentProcessor\CheckoutOption;

use Civi\Afform\Event\AfformValidateEvent;
use Civi\Checkout\AfformCheckoutOptionInterface;
use Civi\Checkout\CheckoutOptionInterface;
use Civi\Checkout\CheckoutSession;
use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

class HelloAssoHostedCheckout implements CheckoutOptionInterface, AfformCheckoutOptionInterface {

  protected ?array $liveConnection;

  protected ?array $testConnection;

  public function __construct(?array $liveConnection, ?array $testConnection) {
    $this->liveConnection = $liveConnection;
    $this->testConnection = $testConnection;
  }

  public function getLabel(): string {
    return (string) ($this->getConnectionDetails()['title'] ?? E::ts('HelloAsso'));
  }

  public function getFrontendLabel(): string {
    return (string) ($this->getConnectionDetails()['frontend_title'] ?? $this->getConnectionDetails()['title'] ?? E::ts('HelloAsso'));
  }

  public function getPaymentMethod(): ?string {
    $connection = $this->getConnectionDetails();
    if (empty($connection['payment_instrument_id'])) {
      return NULL;
    }

    $instrument = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('name')
      ->addWhere('option_group_id:name', '=', 'payment_instrument')
      ->addWhere('value', '=', $connection['payment_instrument_id'])
      ->execute()
      ->first();

    return !empty($instrument['name']) ? (string) $instrument['name'] : NULL;
  }

  public function getPaymentProcessorId(): ?int {
    $connection = $this->liveConnection ?: $this->testConnection;
    return $connection['id'] ?? NULL;
  }

  public function validate(AfformValidateEvent $event): void {
    // No additional frontend validation required for offsite redirect checkout.
  }

  public function getAfformSettings(bool $testMode): array {
    return [
      'description' => E::ts('You will be redirected to HelloAsso to complete your payment.'),
      'template' => '~/crmHelloassoPaymentProcessor/installments.html',
    ];
  }

  public function getAfformModule(): ?string {
    return 'crmHelloassoPaymentProcessor';
  }

  public function startCheckout(CheckoutSession $session): void {
    $prepared = $this->prepareContributionRecur($session);

    try {
      /** @var \CRM_Core_Payment_HelloAsso $processor */
      $processor = $this->getQuickformProcessor($session->isTestMode());
      $landingUrl = $session->getLandingUrl();
      $redirectUrl = $processor->startHostedCheckoutForContribution($session->getContributionId(), [
        'landing_url' => $landingUrl,
        'return_url' => $landingUrl,
        'cancel_url' => $this->addResultMarker($landingUrl, 'cancel'),
        'error_url' => $landingUrl,
        'schedule_total_amount' => $prepared['schedule_total_amount'],
      ]);
    }
    catch (\Throwable $e) {
      if ($prepared['created_recur_id']) {
        $this->rollbackPreparedContributionRecur(
          $session->getContributionId(),
          $prepared['created_recur_id']
        );
      }
      throw $e;
    }

    $session->setResponseItem('redirect', $redirectUrl);
  }

  /**
   * @return array{schedule_total_amount:?float, created_recur_id:?int}
   */
  private function prepareContributionRecur(CheckoutSession $session): array {
    if ($session->getCheckoutParam('helloasso_installments_enabled') !== TRUE) {
      return $this->preparedRecurResult();
    }

    $installments = $session->getCheckoutParam('helloasso_installments');
    if ($installments === NULL || $installments === '') {
      return $this->preparedRecurResult();
    }

    $minimum = $this->normalizeInstallmentBound(
      $session->getCheckoutParam('helloasso_installments_min'),
      2
    );
    $maximum = $this->normalizeInstallmentBound(
      $session->getCheckoutParam('helloasso_installments_max'),
      12
    );
    $maximum = max($minimum, $maximum);

    $installments = filter_var($installments, FILTER_VALIDATE_INT);
    if ($installments === FALSE || $installments < $minimum || $installments > $maximum) {
      throw new \CRM_Core_Exception(E::ts('HelloAsso requires between %1 and %2 installments for this form.', [
        1 => $minimum,
        2 => $maximum,
      ]));
    }
    if (!(bool) \Civi::settings()->get('helloasso_enable_installments')) {
      throw new \CRM_Core_Exception(E::ts('HelloAsso installment payments are disabled.'));
    }

    $contribution = \Civi\Api4\Contribution::get(FALSE)
      ->addSelect(
        'contact_id',
        'total_amount',
        'currency',
        'is_test',
        'contribution_recur_id'
      )
      ->addWhere('id', '=', $session->getContributionId())
      ->execute()
      ->single();

    if (!empty($contribution['contribution_recur_id'])) {
      return $this->preparedRecurResult((float) $contribution['total_amount']);
    }

    $collectionDay = min((int) date('j'), 27);
    $schedule = \CRM_HelloassoPaymentProcessor_InstallmentSchedule::buildMonthly(
      (int) round(((float) $contribution['total_amount']) * 100),
      $installments,
      new \DateTimeImmutable(),
      $collectionDay
    );

    $recurId = (int) \Civi\Api4\ContributionRecur::create(FALSE)
      ->setValues([
        'contact_id' => (int) $contribution['contact_id'],
        'amount' => $schedule['initialAmount'] / 100,
        'currency' => (string) $contribution['currency'],
        'is_test' => (bool) $contribution['is_test'],
        'frequency_unit' => 'month',
        'frequency_interval' => 1,
        'installments' => $installments,
        'cycle_day' => $collectionDay,
        'payment_processor_id' => (int) $this->getConnectionDetails(
          $session->isTestMode(),
          TRUE
        )['id'],
        'next_sched_contribution_date' => $schedule['terms'][0]['date'],
      ])
      ->execute()
      ->single()['id'];

    \Civi\Api4\Contribution::update(FALSE)
      ->addWhere('id', '=', $session->getContributionId())
      ->addValue('contribution_recur_id', $recurId)
      ->execute();

    return $this->preparedRecurResult((float) $contribution['total_amount'], $recurId);
  }

  /**
   * @return array{schedule_total_amount:?float, created_recur_id:?int}
   */
  private function preparedRecurResult(
    ?float $scheduleTotalAmount = NULL,
    ?int $createdRecurId = NULL
  ): array {
    return [
      'schedule_total_amount' => $scheduleTotalAmount,
      'created_recur_id' => $createdRecurId,
    ];
  }

  private function rollbackPreparedContributionRecur(int $contributionId, int $recurId): void {
    $transaction = new \CRM_Core_Transaction();
    try {
      $checkoutIntentId = \CRM_Core_DAO::singleValueQuery(
        'SELECT COALESCE(m.checkout_intent_id, NULLIF(c.trxn_id, %3))
         FROM civicrm_contribution c
         LEFT JOIN civicrm_hello_asso_metadata m ON m.contribution_id = c.id
         WHERE c.id = %1
           AND c.contribution_recur_id = %2
         LIMIT 1',
        [
          1 => [$contributionId, 'Integer'],
          2 => [$recurId, 'Integer'],
          3 => ['', 'String'],
        ]
      );
      if ($checkoutIntentId) {
        $transaction->commit();
        return;
      }

      \CRM_Core_DAO::executeQuery(
        'UPDATE civicrm_contribution
         SET contribution_recur_id = NULL
         WHERE id = %1
           AND contribution_recur_id = %2',
        [
          1 => [$contributionId, 'Integer'],
          2 => [$recurId, 'Integer'],
        ]
      );
      $references = (int) \CRM_Core_DAO::singleValueQuery(
        'SELECT COUNT(*)
         FROM civicrm_contribution
         WHERE contribution_recur_id = %1',
        [1 => [$recurId, 'Integer']]
      );
      if ($references === 0) {
        \CRM_Core_DAO::executeQuery(
          'DELETE FROM civicrm_contribution_recur
           WHERE id = %1
             AND processor_id IS NULL
             AND trxn_id IS NULL',
          [1 => [$recurId, 'Integer']]
        );
      }
      $transaction->commit();
    }
    catch (\Throwable $e) {
      $transaction->rollback();
      \Civi::log()->error(sprintf(
        'Unable to roll back HelloAsso recurring contribution %d after checkout initialization failed: %s',
        $recurId,
        $e->getMessage()
      ));
    }
  }

  private function normalizeInstallmentBound($value, int $fallback): int {
    $value = filter_var($value, FILTER_VALIDATE_INT);
    if ($value === FALSE) {
      return $fallback;
    }
    return min(12, max(2, $value));
  }

  public function continueCheckout(CheckoutSession $session): void {
    $this->clearCheckoutValidationMessages();
    if (($_GET['helloasso_result'] ?? '') === 'cancel') {
      $this->cancelCheckoutSession($session);
      $this->cancelContributionRecur($session->getContributionId());
      try {
        $processor = $this->getQuickformProcessor($session->isTestMode());
        $processor->cancelHostedCheckoutFollowUps($session->getContributionId());
      }
      catch (\Throwable $e) {
        \Civi::log()->warning('Unable to stop HelloAsso follow-ups after checkout cancellation: ' . $e->getMessage());
      }
      return;
    }

    /** @var \CRM_Core_Payment_HelloAsso $processor */
    $processor = $this->getQuickformProcessor($session->isTestMode());
    $state = $processor->synchronizeContributionForHostedCheckout($session->getContributionId());

    switch ($state['checkout_status']) {
      case 'success':
        $session->success();
        return;

      case 'cancel':
        $session->cancel();
        return;

      case 'fail':
        $session->fail();
        return;
    }

    $session->pending();
  }

  private function cancelContributionRecur(int $contributionId): void {
    try {
      $contribution = \Civi\Api4\Contribution::get(FALSE)
        ->addSelect('contribution_recur_id')
        ->addWhere('id', '=', $contributionId)
        ->execute()
        ->single();
      $recurId = (int) ($contribution['contribution_recur_id'] ?? 0);
      if (!$recurId) {
        return;
      }

      $cancelledStatusId = \CRM_Core_PseudoConstant::getKey(
        'CRM_Contribute_BAO_ContributionRecur',
        'contribution_status_id',
        'Cancelled'
      );
      if ($cancelledStatusId) {
        \Civi\Api4\ContributionRecur::update(FALSE)
          ->addWhere('id', '=', $recurId)
          ->addValue('contribution_status_id', $cancelledStatusId)
          ->execute();
      }
    }
    catch (\Throwable $e) {
      \Civi::log()->warning('Unable to cancel HelloAsso recurring contribution after checkout cancellation: ' . $e->getMessage());
    }
  }

  private function cancelCheckoutSession(CheckoutSession $session): void {
    try {
      $session->cancel();
    }
    catch (\Throwable $e) {
      \Civi::log()->warning('Unable to mark HelloAsso checkout contribution as cancelled: ' . $e->getMessage());
      $status = new \ReflectionProperty($session, 'status');
      $status->setAccessible(TRUE);
      $status->setValue($session, CheckoutSession::STATUS_CANCEL);
    }
  }

  private function clearCheckoutValidationMessages(): void {
    $session = \CRM_Core_Session::singleton();
    $messages = $session->getStatus(TRUE);
    foreach ($messages as $message) {
      if (!empty($message['options']['helloasso_checkout_validation'])) {
        continue;
      }
      \CRM_Core_Session::setStatus(
        $message['text'] ?? '',
        $message['title'] ?? '',
        $message['type'] ?? 'alert',
        $message['options'] ?? []
      );
    }
  }

  private function addResultMarker(string $url, string $result): string {
    $separator = strpos($url, '?') === FALSE ? '?' : '&';
    return $url . $separator . 'helloasso_result=' . rawurlencode($result);
  }

  protected function getConnectionDetails(bool $testMode = FALSE, bool $strictMode = FALSE): array {
    $connection = $testMode ? $this->testConnection : $this->liveConnection;
    if (!$connection && !$strictMode) {
      $connection = $this->liveConnection ?: $this->testConnection;
    }
    if (!$connection) {
      throw new \CRM_Core_Exception(E::ts("No active HelloAsso connection is available for this Checkout Option."));
    }

    return $connection;
  }

  protected function getQuickformProcessor(bool $testMode = FALSE): \CRM_Core_Payment_HelloAsso {
    $connection = $this->getConnectionDetails($testMode, TRUE);
    /** @var \CRM_Core_Payment_HelloAsso $processor */
    $processor = \Civi\Payment\System::singleton()->getByName($connection['name'], $testMode);
    return $processor;
  }

}
