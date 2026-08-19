<?php

class CRM_HelloassoPaymentProcessor_Webhook {

  /**
   * Build the standard CiviCRM webhook path for a payment processor.
   *
   * mjwshared is a hard dependency of this extension, so delegate to its
   * canonical helper rather than keeping a second copy in sync.
   */
  public static function getWebhookPath(int $paymentProcessorID): string {
    return CRM_Mjwshared_Webhook::getWebhookPath($paymentProcessorID);
  }

  /**
   * Whether the HTTP method can carry a HelloAsso JSON webhook.
   */
  public static function acceptsJsonPayload(?string $requestMethod): bool {
    return strtoupper(trim((string) $requestMethod)) === 'POST';
  }

}
