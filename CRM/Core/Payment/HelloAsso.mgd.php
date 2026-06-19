<?php

use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

return [
  [
    'name' => 'HelloAsso Payments Processor',
    'entity' => 'PaymentProcessorType',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'HelloAsso',
        'title' => E::ts('HelloAsso'),
        'description' => E::ts('HelloAsso Payments Processor'),
        'class_name' => 'Payment_HelloAsso',
        'is_active' => TRUE,
        'is_default' => FALSE,
        'user_name_label' => E::ts('Client Id'),
        'password_label' => E::ts('Client Secret'),
        'subject_label' => E::ts('Organization Name'),
        'url_site_default' => 'https://api.helloasso.com',
        'url_site_test_default' => 'https://api.helloasso-sandbox.com',
        'billing_mode' => 4,
        'payment_type' => 1,
        'is_recur' => TRUE,
      ],
      'match' => ['name'],
    ],
  ],
];
