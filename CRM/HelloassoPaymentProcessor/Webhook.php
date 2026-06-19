<?php

class CRM_HelloassoPaymentProcessor_Webhook {

  /**
   * Build the standard CiviCRM webhook path for a payment processor.
   *
   * This mirrors the shared mjwshared behavior so the processor does not
   * hardcode CMS-specific assumptions about the webhook entrypoint.
   */
  public static function getWebhookPath(int $paymentProcessorID): string {
    return CRM_Utils_System::url('civicrm/payment/ipn/' . $paymentProcessorID, NULL, TRUE, NULL, FALSE, TRUE);
  }

  /**
   * Whether the HTTP method can carry a HelloAsso JSON webhook.
   */
  public static function acceptsJsonPayload(?string $requestMethod): bool {
    return strtoupper(trim((string) $requestMethod)) === 'POST';
  }

}
