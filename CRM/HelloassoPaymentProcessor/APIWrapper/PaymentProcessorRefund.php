<?php

/**
 * CiviCRM 6.14 still exposes PaymentProcessor.Refund through API3, but the
 * action spec uses legacy numeric type constants which the modern validator can
 * reject before the processor's doRefund() method is reached.
 */
class CRM_HelloassoPaymentProcessor_APIWrapper_PaymentProcessorRefund implements API_Wrapper {

  /**
   * Fix the legacy API3 refund schema before APIv3SchemaAdapter validates it.
   *
   * @param \Civi\API\Event\PrepareEvent $event
   */
  public static function onApiPrepare(\Civi\API\Event\PrepareEvent $event): void {
    $apiRequest = $event->getApiRequest();
    if (!is_array($apiRequest)) {
      return;
    }
    if (!self::isPaymentProcessorRefundRequest($apiRequest)) {
      return;
    }

    if (isset($apiRequest['fields']['payment_processor_id']['type'])) {
      $apiRequest['fields']['payment_processor_id']['type'] = 'Integer';
    }
    if (isset($apiRequest['fields']['amount']['type'])) {
      $apiRequest['fields']['amount']['type'] = 'Money';
    }

    $event->setApiRequest($apiRequest);
  }

  /**
   * {@inheritdoc}
   */
  public function fromApiInput($apiRequest) {
    if (!self::isHelloAssoRefundRequest($apiRequest)) {
      return $apiRequest;
    }

    return $apiRequest;
  }

  /**
   * {@inheritdoc}
   */
  public function toApiOutput($apiRequest, $result) {
    return $result;
  }

  /**
   * @param array $apiRequest
   *
   * @return bool
   */
  private static function isHelloAssoRefundRequest(array $apiRequest): bool {
    if (!self::isPaymentProcessorRefundRequest($apiRequest)) {
      return FALSE;
    }

    $processorId = (int) ($apiRequest['params']['payment_processor_id'] ?? 0);
    if (!$processorId) {
      return FALSE;
    }

    return (bool) CRM_Core_DAO::singleValueQuery(
      'SELECT COUNT(*)
         FROM civicrm_payment_processor pp
   INNER JOIN civicrm_payment_processor_type ppt
           ON ppt.id = pp.payment_processor_type_id
        WHERE pp.id = %1
          AND ppt.class_name = %2',
      [
        1 => [$processorId, 'Integer'],
        2 => ['Payment_HelloAsso', 'String'],
      ]
    );
  }

  /**
   * @param array $apiRequest
   *
   * @return bool
   */
  private static function isPaymentProcessorRefundRequest(array $apiRequest): bool {
    if ((int) ($apiRequest['version'] ?? 0) !== 3) {
      return FALSE;
    }
    if (!in_array(strtolower($apiRequest['entity'] ?? ''), ['paymentprocessor', 'payment_processor'], TRUE) || strtolower($apiRequest['action'] ?? '') !== 'refund') {
      return FALSE;
    }

    return TRUE;
  }

}
