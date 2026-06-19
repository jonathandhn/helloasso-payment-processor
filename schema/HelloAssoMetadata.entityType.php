<?php
use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

return [
  'name' => 'HelloAssoMetadata',
  'table' => 'civicrm_hello_asso_metadata',
  'class' => 'CRM_HelloassoPaymentProcessor_DAO_HelloAssoMetadata',
  'getInfo' => fn() => [
    'title' => E::ts('Hello Asso Metadata'),
    'title_plural' => E::ts('Hello Asso Metadatas'),
    'description' => E::ts('HelloAsso payment reconciliation metadata'),
    'log' => TRUE,
  ],
  'getIndices' => fn() => [
    'index_contribution_id' => [
      'fields' => [
        'contribution_id' => TRUE,
      ],
    ],
    'index_checkout_intent_id' => [
      'fields' => [
        'checkout_intent_id' => TRUE,
      ],
    ],
    'index_helloasso_payment_id' => [
      'fields' => [
        'helloasso_payment_id' => TRUE,
      ],
    ],
    'index_payment_processor_id_sync_next_date' => [
      'fields' => [
        'payment_processor_id' => TRUE,
        'sync_next_date' => TRUE,
      ],
    ],
    'index_sync_next_date' => [
      'fields' => [
        'sync_next_date' => TRUE,
      ],
    ],
    'index_payment_processor_id_long_sync_next_date' => [
      'fields' => [
        'payment_processor_id' => TRUE,
        'long_sync_next_date' => TRUE,
      ],
    ],
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique HelloassoMetaData ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'contribution_id' => [
      'title' => E::ts('Contribution ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to Contribution'),
      'input_attrs' => [
        'label' => E::ts('Contribution'),
      ],
      'entity_reference' => [
        'entity' => 'Contribution',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'signing_key' => [
      'title' => E::ts('Signing Key'),
      'sql_type' => 'text',
      'input_type' => 'TextArea',
      'required' => TRUE,
      'description' => E::ts('Key used to sign contribution'),
    ],
    'helloasso_ref_cmd_id' => [
      'title' => E::ts('HelloAsso Reference command ID'),
      'sql_type' => 'int',
      'input_type' => 'Number',
      'default' => NULL,
    ],
    'checkout_intent_id' => [
      'title' => E::ts('Checkout intent ID'),
      'sql_type' => 'int',
      'input_type' => 'Number',
      'default' => NULL,
    ],
    'helloasso_payment_id' => [
      'title' => E::ts('HelloAsso Payment ID'),
      'sql_type' => 'int',
      'input_type' => 'Number',
      'default' => NULL,
    ],
    'event_type' => [
      'title' => E::ts('Last event type'),
      'sql_type' => 'varchar(64)',
      'input_type' => 'Text',
      'default' => NULL,
    ],
    'state' => [
      'title' => E::ts('Last known state'),
      'sql_type' => 'varchar(64)',
      'input_type' => 'Text',
      'default' => NULL,
    ],
    'payment_processor_id' => [
      'title' => E::ts('Payment processor ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'default' => NULL,
    ],
    'sync_origin_date' => [
      'title' => E::ts('Sync origin date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'default' => NULL,
    ],
    'sync_next_date' => [
      'title' => E::ts('Next scheduled sync date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'default' => NULL,
    ],
    'sync_last_date' => [
      'title' => E::ts('Last sync date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'default' => NULL,
    ],
    'sync_attempt_count' => [
      'title' => E::ts('Sync attempt count'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'default' => 0,
    ],
    'sync_error_count' => [
      'title' => E::ts('Sync technical error count'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'default' => 0,
    ],
    'long_sync_scheme' => [
      'title' => E::ts('Long follow-up scheme'),
      'sql_type' => 'varchar(16)',
      'input_type' => 'Text',
      'default' => NULL,
    ],
    'long_sync_origin_date' => [
      'title' => E::ts('Long sync origin date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'default' => NULL,
    ],
    'long_sync_next_date' => [
      'title' => E::ts('Next scheduled long sync date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'default' => NULL,
    ],
    'long_sync_last_date' => [
      'title' => E::ts('Last long sync date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'default' => NULL,
    ],
    'long_sync_attempt_count' => [
      'title' => E::ts('Long sync attempt count'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'default' => 0,
    ],
    'long_sync_error_count' => [
      'title' => E::ts('Long sync technical error count'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'default' => 0,
    ],
  ],
];
