<?php

use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

return [
  [
    'name' => 'ProcessHelloAsso',
    'entity' => 'Job',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Process HelloAsso',
        'description' => E::ts('Process scheduled HelloAsso payment follow-ups and targeted synchronisations.'),
        'run_frequency' => 'Always',
        'api_entity' => 'Job',
        'api_action' => 'process_helloasso',
        'parameters' => 'only_scheduled=1 due_before=now limit=15',
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'ProcessHelloAssoLongFollowUp',
    'entity' => 'Job',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Process HelloAsso Long Follow-Up',
        'description' => E::ts('Process scheduled long-window HelloAsso consistency checks.'),
        'run_frequency' => 'Hourly',
        'api_entity' => 'Job',
        'api_action' => 'process_helloasso_long_followup',
        'parameters' => 'due_before=now limit=15',
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'RefreshHelloAssoPartnerLinks',
    'entity' => 'Job',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Refresh HelloAsso Partner Links',
        'description' => E::ts('Refresh linked HelloAsso authorizations once their current refresh tokens reach mid-life.'),
        'run_frequency' => 'Daily',
        'api_entity' => 'Job',
        'api_action' => 'refresh_helloasso_partner_links',
        'parameters' => '',
      ],
      'match' => [
        'name',
      ],
    ],
  ],
];
