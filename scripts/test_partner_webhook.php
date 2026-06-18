<?php

use Civi\Payment\Exception\PaymentProcessorException;

/**
 * Test the HelloAsso partner/plugin webhook configuration endpoint for one
 * linked payment processor.
 *
 * Example:
 * cv ev '$_SERVER["argv"] = ["script", "--processor-id=13", "--webhook-url=https://example.org/civicrm/helloasso/webhook"]; require "/abs/path/scripts/test_partner_webhook.php";' --cwd=/home/crm/public_html/
 */

$args = $_SERVER['argv'] ?? [];
$options = [];

foreach ($args as $arg) {
  if (strpos((string) $arg, '--') !== 0) {
    continue;
  }

  $parts = explode('=', substr((string) $arg, 2), 2);
  $options[$parts[0]] = $parts[1] ?? TRUE;
}

$processorId = isset($options['processor-id']) ? (int) $options['processor-id'] : 0;
$webhookUrl = isset($options['webhook-url']) ? trim((string) $options['webhook-url']) : '';

if ($processorId <= 0 || $webhookUrl === '') {
  fwrite(STDERR, "Usage: --processor-id=13 --webhook-url=https://example.org/civicrm/helloasso/webhook\n");
  return;
}

try {
  $partnerAuth = new CRM_HelloassoPaymentProcessor_PartnerAuth($processorId);
  $linked = $partnerAuth->getLinkedOrganization();
  if (!$linked || empty($linked['organization_slug'])) {
    throw new PaymentProcessorException("No linked HelloAsso organization found for payment processor #{$processorId}.");
  }

  $result = $partnerAuth->configureOrganizationWebhook($webhookUrl);

  echo json_encode([
    'ok' => TRUE,
    'processor_id' => $processorId,
    'organization_slug' => $linked['organization_slug'],
    'webhook_url' => $webhookUrl,
    'result' => $result,
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
catch (Exception $e) {
  echo json_encode([
    'ok' => FALSE,
    'processor_id' => $processorId,
    'webhook_url' => $webhookUrl,
    'error' => $e->getMessage(),
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
