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
    ];
  }

  public function getAfformModule(): ?string {
    return NULL;
  }

  public function startCheckout(CheckoutSession $session): void {
    /** @var \CRM_Core_Payment_HelloAsso $processor */
    $processor = $this->getQuickformProcessor($session->isTestMode());
    $landingUrl = $session->getLandingUrl();
    $redirectUrl = $processor->startHostedCheckoutForContribution($session->getContributionId(), [
      'landing_url' => $landingUrl,
      'return_url' => $landingUrl,
      'cancel_url' => $this->addResultMarker($landingUrl, 'cancel'),
      'error_url' => $landingUrl,
    ]);

    $session->setResponseItem('redirect', $redirectUrl);
  }

  public function continueCheckout(CheckoutSession $session): void {
    if (($_GET['helloasso_result'] ?? '') === 'cancel') {
      $session->cancel();
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
