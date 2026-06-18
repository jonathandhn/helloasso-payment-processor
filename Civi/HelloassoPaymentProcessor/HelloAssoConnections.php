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
      // Run after civi_contribute registers CheckoutBlock so our admin wrapper sticks.
      'civi.afform.input_types' => ['alterAfformInputTypes', -100],
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

  public function alterAfformInputTypes(GenericHookEvent $e): void {
    if (!$this->isEnabled() || empty($e->inputTypes['CheckoutBlock'])) {
      return;
    }

    $e->inputTypes['CheckoutBlock']['admin_template'] = '~/crmHelloassoPaymentProcessor/checkoutBlockAdmin.html';
    $e->inputTypes['CheckoutBlock']['admin_module'] = 'crmHelloassoPaymentProcessor';
  }

  protected function isEnabled(): bool {
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
