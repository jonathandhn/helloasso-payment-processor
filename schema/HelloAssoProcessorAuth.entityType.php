<?php
use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

return [
  'name' => 'HelloAssoProcessorAuth',
  'table' => 'civicrm_hello_asso_processor_auth',
  'class' => 'CRM_HelloassoPaymentProcessor_DAO_HelloAssoProcessorAuth',
  'getInfo' => fn() => [
    'title' => E::ts('Hello Asso Processor Auth'),
    'title_plural' => E::ts('Hello Asso Processor Auths'),
    'description' => E::ts('HelloAsso per-processor OAuth and webhook state'),
    'log' => TRUE,
  ],
  'getIndices' => fn() => [
    'uniq_payment_processor_id' => [
      'fields' => [
        'payment_processor_id' => TRUE,
      ],
      'unique' => TRUE,
    ],
    'index_connection_mode' => [
      'fields' => [
        'connection_mode' => TRUE,
      ],
    ],
    'index_organization_slug' => [
      'fields' => [
        'organization_slug' => TRUE,
      ],
    ],
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'payment_processor_id' => [
      'title' => E::ts('Payment Processor ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
    ],
    'connection_mode' => [
      'title' => E::ts('Connection Mode'),
      'sql_type' => 'varchar(32)',
      'input_type' => 'Text',
      'default' => NULL,
    ],
    'organization_slug' => [
      'title' => E::ts('Organization Slug'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'default' => NULL,
    ],
    'access_token' => [
      'title' => E::ts('Access Token'),
      'sql_type' => 'text',
      'input_type' => 'TextArea',
      'default' => NULL,
    ],
    'refresh_token' => [
      'title' => E::ts('Refresh Token'),
      'sql_type' => 'text',
      'input_type' => 'TextArea',
      'default' => NULL,
    ],
    'expires_at' => [
      'title' => E::ts('Expires At'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'default' => NULL,
    ],
    'refresh_issued_at' => [
      'title' => E::ts('Refresh Issued At'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'default' => NULL,
    ],
    'refresh_expires_at' => [
      'title' => E::ts('Refresh Expires At'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'default' => NULL,
    ],
    'refresh_status' => [
      'title' => E::ts('Refresh Status'),
      'sql_type' => 'varchar(32)',
      'input_type' => 'Text',
      'default' => NULL,
    ],
    'last_refresh_error' => [
      'title' => E::ts('Last Refresh Error'),
      'sql_type' => 'text',
      'input_type' => 'TextArea',
      'default' => NULL,
    ],
    'last_refresh_error_date' => [
      'title' => E::ts('Last Refresh Error Date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'default' => NULL,
    ],
    'last_refresh_http_status' => [
      'title' => E::ts('Last Refresh Http Status'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'default' => NULL,
    ],
    'linked_at' => [
      'title' => E::ts('Linked At'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'default' => NULL,
    ],
    'redirect_uri' => [
      'title' => E::ts('Redirect Uri'),
      'sql_type' => 'text',
      'input_type' => 'TextArea',
      'default' => NULL,
    ],
    'webhook_ownership' => [
      'title' => E::ts('Webhook Ownership'),
      'sql_type' => 'varchar(32)',
      'input_type' => 'Text',
      'default' => NULL,
    ],
    'webhook_url' => [
      'title' => E::ts('Webhook Url'),
      'sql_type' => 'text',
      'input_type' => 'TextArea',
      'default' => NULL,
    ],
    'webhook_signature_key' => [
      'title' => E::ts('Webhook Signature Key'),
      'sql_type' => 'text',
      'input_type' => 'TextArea',
      'default' => NULL,
    ],
    'webhook_updated_at' => [
      'title' => E::ts('Webhook Updated At'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'default' => NULL,
    ],
    'created_at' => [
      'title' => E::ts('Created At'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'required' => TRUE,
    ],
    'updated_at' => [
      'title' => E::ts('Updated At'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'required' => TRUE,
    ],
  ],
];
