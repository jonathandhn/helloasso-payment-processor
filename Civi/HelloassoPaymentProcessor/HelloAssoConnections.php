<?php

namespace Civi\HelloassoPaymentProcessor;

use Civi\Core\Event\GenericHookEvent;
use Civi\Core\Service\AutoService;
use Civi\HelloassoPaymentProcessor\CheckoutOption\HelloAssoHostedCheckout;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Publish HelloAsso CheckoutOptions for Afform/Form Builder.
 *
 * @service helloasso.connections
 */
class HelloAssoConnections extends AutoService implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return [
      'civi.checkout.options' => 'getCheckoutOptions',
    ];
  }

  public function getCheckoutOptions(GenericHookEvent $e): void {
    if (!$this->isEnabled()) {
      return;
    }

    foreach ($this->getPaymentProcessorPairs() as $name => $pair) {
      $e->options['helloasso_hosted_checkout_' . $name] = new HelloAssoHostedCheckout($pair['live'] ?? NULL, $pair['test'] ?? NULL);
    }
  }

  private function isEnabled(): bool {
    return (bool) \Civi::settings()->get('helloasso_v2_afform_checkout');
  }

  private function getPaymentProcessorPairs(): array {
    $all = \Civi\Api4\PaymentProcessor::get(FALSE)
      ->addWhere('class_name', '=', 'Payment_HelloAsso')
      ->addWhere('is_active', '=', TRUE)
      ->addWhere('is_test', 'IN', [TRUE, FALSE])
      ->execute();

    $pairs = [];
    foreach ($all as $processor) {
      $pairs[$processor['name']][$processor['is_test'] ? 'test' : 'live'] = $processor;
    }

    return $pairs;
  }

}
