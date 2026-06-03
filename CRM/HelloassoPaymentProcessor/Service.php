<?php

use Civi\Payment\Exception\PaymentProcessorException;
use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

/**
 * Read-only integration facade for other extensions.
 *
 * This class exposes a narrow import-oriented API so other extensions can read
 * HelloAsso data without receiving payment-processor secrets or write methods.
 */
class CRM_HelloassoPaymentProcessor_Service {

  /**
   * Return HelloAsso payment processors.
   *
   * @param bool|null $isTest
   *   TRUE for sandbox processors, FALSE for live processors, NULL for both.
   * @param bool $onlyActive
   *   Restrict to active processors.
   *
   * @return array
   */
  public function getProcessors(?bool $isTest = NULL, bool $onlyActive = TRUE): array {
    return array_map([$this, 'sanitizeProcessor'], $this->getProcessorConfigs($isTest, $onlyActive));
  }

  /**
   * Return a single HelloAsso processor by id.
   *
   * @param int $paymentProcessorId
   *
   * @return array
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function getProcessorById(int $paymentProcessorId): array {
    return $this->sanitizeProcessor($this->getProcessorConfigById($paymentProcessorId));
  }

  /**
   * Return the preferred HelloAsso processor for a mode.
   *
   * Selection order:
   * 1. active + default processor for the requested mode
   * 2. first active processor for the requested mode
   *
   * @param bool $isTest
   *
   * @return array
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function getPreferredProcessor(bool $isTest): array {
    $processors = $this->getProcessorConfigs($isTest, TRUE);
    if (!$processors) {
      throw new PaymentProcessorException(E::ts('No active HelloAsso payment processor found for mode %1.', [
        1 => $isTest ? 'test' : 'live',
      ]));
    }

    foreach ($processors as $processor) {
      if (!empty($processor['is_default'])) {
        return $this->sanitizeProcessor($processor);
      }
    }

    return $this->sanitizeProcessor(reset($processors));
  }

  /**
   * List organization payments for a mode using the preferred processor.
   *
   * @param bool $isTest
   * @param array $query
   *
   * @return array
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function listOrganizationPayments(bool $isTest, array $query = []): array {
    $processor = $this->getPreferredProcessorConfig($isTest);
    return CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance()
      ->listOrganizationPayments($processor, $isTest ? 1 : 0, $query);
  }

  /**
   * Retrieve a payment by HelloAsso payment id.
   *
   * @param bool $isTest
   * @param int $paymentId
   * @param array $query
   *
   * @return array
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function getPayment(bool $isTest, int $paymentId, array $query = []): array {
    $processor = $this->getPreferredProcessorConfig($isTest);
    return CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance()
      ->getPayment($processor, $isTest ? 1 : 0, $paymentId, $query);
  }

  /**
   * Retrieve a checkout intent by HelloAsso checkout intent id.
   *
   * @param bool $isTest
   * @param int $checkoutIntentId
   * @param array $query
   *
   * @return array
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function getCheckoutIntent(bool $isTest, int $checkoutIntentId, array $query = []): array {
    $processor = $this->getPreferredProcessorConfig($isTest);
    return CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance()
      ->getCheckoutIntent($processor, $isTest ? 1 : 0, $checkoutIntentId, $query);
  }

  /**
   * Return the organization linked through the authorization screen.
   *
   * @param bool $isTest
   *   TRUE for sandbox, FALSE for production.
   *
   * @return array|null
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function getPartnerLinkedOrganization(bool $isTest = FALSE): ?array {
    return $this->getPreferredPartnerAuth($isTest)->getLinkedOrganization();
  }

  /**
   * List payments using the authorization-screen token.
   *
   * @param bool $isTest
   *   TRUE for sandbox, FALSE for production.
   * @param array $query
   *
   * @return array
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function listPartnerOrganizationPayments($isTest = FALSE, array $query = []): array {
    if (is_array($isTest)) {
      $query = $isTest;
      $isTest = FALSE;
    }
    $isTest = (bool) $isTest;
    return $this->getPreferredPartnerAuth($isTest)->listOrganizationPayments($query);
  }

  /**
   * Retrieve a payment using the authorization-screen token.
   *
   * @param int $paymentId
   * @param array $query
   * @param bool $isTest
   *   TRUE for sandbox, FALSE for production.
   *
   * @return array
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function getPartnerPayment(int $paymentId, array $query = [], bool $isTest = FALSE): array {
    return $this->getPreferredPartnerAuth($isTest)->getPayment($paymentId, $query);
  }

  /**
   * Retrieve a checkout intent using the authorization-screen token.
   *
   * @param int $checkoutIntentId
   * @param array $query
   * @param bool $isTest
   *   TRUE for sandbox, FALSE for production.
   *
   * @return array
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function getPartnerCheckoutIntent(int $checkoutIntentId, array $query = [], bool $isTest = FALSE): array {
    return $this->getPreferredPartnerAuth($isTest)->getCheckoutIntent($checkoutIntentId, $query);
  }

  private function getPreferredPartnerAuth(bool $isTest): CRM_HelloassoPaymentProcessor_PartnerAuth {
    $processor = $this->getPreferredPartnerProcessorConfig($isTest);
    return new CRM_HelloassoPaymentProcessor_PartnerAuth((int) $processor['id']);
  }

  private function getPreferredPartnerProcessorConfig(bool $isTest): array {
    $processorAuthConfig = new CRM_HelloassoPaymentProcessor_ProcessorAuthConfig();
    $processors = $this->getProcessorConfigs($isTest, TRUE);

    foreach ($processors as $processor) {
      $processorId = (int) ($processor['id'] ?? 0);
      if ($processorId && !empty($processor['is_default']) && $processorAuthConfig->shouldUsePluginPublic($processorId, $processor)) {
        return $processor;
      }
    }

    foreach ($processors as $processor) {
      $processorId = (int) ($processor['id'] ?? 0);
      if ($processorId && $processorAuthConfig->shouldUsePluginPublic($processorId, $processor)) {
        return $processor;
      }
    }

    throw new PaymentProcessorException(E::ts('No active HelloAsso authorization-screen processor is linked for mode %1.', [
      1 => $isTest ? 'test' : 'live',
    ]));
  }

  private function getPreferredProcessorConfig(bool $isTest): array {
    $processors = $this->getProcessorConfigs($isTest, TRUE);
    if (!$processors) {
      throw new PaymentProcessorException(E::ts('No active HelloAsso payment processor found for mode %1.', [
        1 => $isTest ? 'test' : 'live',
      ]));
    }

    foreach ($processors as $processor) {
      if (!empty($processor['is_default'])) {
        return $processor;
      }
    }

    return reset($processors);
  }

  private function getProcessorConfigById(int $paymentProcessorId): array {
    $processor = civicrm_api3('PaymentProcessor', 'getsingle', [
      'id' => $paymentProcessorId,
      'class_name' => 'Payment_HelloAsso',
    ]);

    if (empty($processor)) {
      throw new PaymentProcessorException(E::ts('HelloAsso payment processor not found: %1', [1 => $paymentProcessorId]));
    }

    return $processor;
  }

  private function getProcessorConfigs(?bool $isTest = NULL, bool $onlyActive = TRUE): array {
    $params = [
      'class_name' => 'Payment_HelloAsso',
      'options' => [
        'limit' => 0,
        'sort' => 'is_default DESC, id ASC',
      ],
    ];

    if ($onlyActive) {
      $params['is_active'] = 1;
    }
    if ($isTest !== NULL) {
      $params['is_test'] = $isTest ? 1 : 0;
    }

    $result = civicrm_api3('PaymentProcessor', 'get', $params);

    return array_values($result['values'] ?? []);
  }

  private function sanitizeProcessor(array $processor): array {
    return array_intersect_key($processor, array_flip([
      'id',
      'name',
      'title',
      'is_active',
      'is_default',
      'is_test',
      'class_name',
      'url_site',
      'subject',
    ]));
  }

}
