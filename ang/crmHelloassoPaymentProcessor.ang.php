<?php

return [
  'js' => [
    'ang/crmHelloassoPaymentProcessor.js',
    'ang/crmHelloassoPaymentProcessor/*.js',
  ],
  'partials' => [
    'ang/crmHelloassoPaymentProcessor',
  ],
  'requires' => [
    'afCheckout',
  ],
  // Load through the checkout option, editor or exported elements only.
  'basePages' => [],
  'exports' => [
    'helloasso-installments' => 'E',
    'helloasso-checkout-admin' => 'E',
  ],
];
