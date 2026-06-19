<?php

use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

/**
 * Refresh linked HelloAsso partner authorizations before inactivity expires them.
 *
 * @param array $params
 *
 * @return array
 */
function civicrm_api3_job_refresh_helloasso_partner_links($params) {
  $config = new CRM_HelloassoPaymentProcessor_ProcessorAuthConfig();
  $linkedProcessors = $config->getLinkedProcessors();
  $paymentProcessorId = !empty($params['payment_processor_id']) ? (int) $params['payment_processor_id'] : NULL;

  $results = [
    'checked' => 0,
    'refreshed' => 0,
    'errors' => [],
  ];

  foreach ($linkedProcessors as $processorId => $link) {
    if ($paymentProcessorId && $paymentProcessorId !== $processorId) {
      continue;
    }

    $results['checked']++;
    try {
      $partnerAuth = new CRM_HelloassoPaymentProcessor_PartnerAuth($processorId);
      if ($partnerAuth->refreshLinkIfPastHalfLife()) {
        $results['refreshed']++;
      }
    }
    catch (Exception $e) {
      $message = sprintf('[processor:%d] %s', $processorId, $e->getMessage());
      $results['errors'][] = $message;
      Civi::log()->error('HelloAsso partner authorization maintenance failed: ' . $message);
    }
  }

  return civicrm_api3_create_success($results, $params, 'Job', 'refresh_helloasso_partner_links');
}

/**
 * Specification for Job.refresh_helloasso_partner_links.
 *
 * @param array $params
 */
function _civicrm_api3_job_refresh_helloasso_partner_links_spec(mixed &$params): void {
  $params['payment_processor_id'] = [
    'type' => CRM_Utils_Type::T_INT,
    'title' => E::ts('Payment processor ID'),
    'description' => E::ts('Restrict maintenance to one linked HelloAsso payment processor.'),
  ];
}
