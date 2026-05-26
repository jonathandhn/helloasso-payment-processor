<?php

use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

/**
 * Process long-window HelloAsso consistency follow-up.
 *
 * @param array $params
 *
 * @return array
 */
function civicrm_api3_job_process_helloasso_long_followup($params) {
  $processorParams = [
    'class_name' => 'Payment_HelloAsso',
    'options' => ['limit' => 0],
  ];

  if (!empty($params['payment_processor_id'])) {
    $processorParams['id'] = (int) $params['payment_processor_id'];
  }
  else {
    $processorParams['is_active'] = 1;
  }

  $processors = civicrm_api3('PaymentProcessor', 'get', $processorParams);
  $statusNames = [];
  if (!empty($params['status_names'])) {
    $statusNames = array_values(array_filter(array_map('trim', explode(',', (string) $params['status_names']))));
  }

  $options = [
    'contribution_id' => !empty($params['contribution_id']) ? (int) $params['contribution_id'] : NULL,
    'status_names' => $statusNames,
    'receive_date_from' => $params['receive_date_from'] ?? NULL,
    'receive_date_to' => $params['receive_date_to'] ?? NULL,
    'due_before' => array_key_exists('due_before', $params) ? $params['due_before'] : NULL,
    'limit' => !empty($params['limit']) ? (int) $params['limit'] : 30,
  ];

  $results = [
    'processors' => 0,
    'checked' => 0,
    'updated' => 0,
    'errors' => [],
  ];

  foreach ($processors['values'] ?? [] as $processor) {
    $mode = !empty($processor['is_test']) ? 'test' : 'live';
    $paymentProcessor = new CRM_Core_Payment_HelloAsso($mode, $processor);
    $processorResult = $paymentProcessor->processLongScheduledSynchronization($options);

    $results['processors']++;
    $results['checked'] += $processorResult['checked'];
    $results['updated'] += $processorResult['updated'];
    foreach ($processorResult['errors'] as $error) {
      $results['errors'][] = sprintf('[processor:%s] %s', $processor['id'], $error);
    }
  }

  return civicrm_api3_create_success($results, $params, 'Job', 'process_helloasso_long_followup');
}

/**
 * Specification for Job.process_helloasso_long_followup.
 *
 * @param array $params
 */
function _civicrm_api3_job_process_helloasso_long_followup_spec(&$params) {
  $params['payment_processor_id'] = [
    'type' => CRM_Utils_Type::T_INT,
    'title' => E::ts('Payment processor ID'),
    'description' => E::ts('Restrict processing to one HelloAsso payment processor.'),
  ];
  $params['contribution_id'] = [
    'type' => CRM_Utils_Type::T_INT,
    'title' => E::ts('Contribution ID'),
    'description' => E::ts('Force processing for a specific contribution.'),
  ];
  $params['status_names'] = [
    'type' => CRM_Utils_Type::T_STRING,
    'title' => E::ts('Contribution statuses'),
    'description' => E::ts('Comma-separated contribution status names to include, for example "Completed,Pending".'),
  ];
  $params['receive_date_from'] = [
    'type' => CRM_Utils_Type::T_STRING,
    'title' => E::ts('Receive date from'),
    'description' => E::ts('Only process contributions received on or after this date/time.'),
  ];
  $params['receive_date_to'] = [
    'type' => CRM_Utils_Type::T_STRING,
    'title' => E::ts('Receive date to'),
    'description' => E::ts('Only process contributions received on or before this date/time.'),
  ];
  $params['due_before'] = [
    'type' => CRM_Utils_Type::T_STRING,
    'title' => E::ts('Due before'),
    'description' => E::ts('Only process long follow-ups due on or before this date/time. Leave empty to use now.'),
  ];
  $params['limit'] = [
    'type' => CRM_Utils_Type::T_INT,
    'title' => E::ts('Limit'),
    'description' => E::ts('Maximum number of contributions to process per payment processor.'),
    'api.default' => 30,
  ];
}
