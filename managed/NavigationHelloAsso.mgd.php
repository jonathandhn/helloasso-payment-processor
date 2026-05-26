<?php

use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

return [
  [
    'name' => 'helloasso_payment_processor_settings',
    'entity' => 'Navigation',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'label' => E::ts('Parametres HelloAsso'),
        'name' => 'helloasso_payment_processor_settings',
        'url' => 'civicrm/admin/setting/helloasso?reset=1',
        'permission' => 'administer CiviCRM',
        'permission_operator' => 'OR',
        'parent_id.name' => 'CiviContribute',
        'is_active' => TRUE,
        'has_separator' => 0,
        'weight' => 95,
      ],
      'match' => ['name'],
    ],
  ],
];
