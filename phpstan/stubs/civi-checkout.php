<?php

namespace Civi\Checkout;

/**
 * PHPStan stub for the Checkout API CheckoutSession.
 * This class is part of an optional CiviCRM extension (e.g. civi_checkout).
 */
interface CheckoutOptionInterface {
}

interface AfformCheckoutOptionInterface extends CheckoutOptionInterface {
}

class CheckoutSession {
  public const STATUS_CANCEL = 'cancel';
  public const STATUS_SUCCESS = 'success';
  public const STATUS_FAIL = 'fail';
  public const STATUS_PENDING = 'pending';

  public function getContributionId(): int {
  }

  public function isTestMode(): bool {
  }

  /** @param string $key */
  public function getCheckoutParam(string $key): mixed {
  }

  public function success(): void {
  }

  public function cancel(): void {
  }

  public function fail(): void {
  }

  public function pending(): void {
  }
}
