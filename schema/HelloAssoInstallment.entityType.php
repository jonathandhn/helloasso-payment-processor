<?php
use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

return [
  'name' => 'HelloAssoInstallment',
  'table' => 'civicrm_hello_asso_installment',
  'class' => 'CRM_HelloassoPaymentProcessor_DAO_HelloAssoInstallment',
  'getInfo' => fn() => [
    'title' => E::ts('Hello Asso Installment'),
    'title_plural' => E::ts('Hello Asso Installments'),
    'description' => E::ts('Idempotent mapping of HelloAsso installments to CiviCRM contributions'),
    'log' => TRUE,
  ],
  'getIndices' => fn() => [
    'uniq_processor_payment' => [
      'fields' => [
        'payment_processor_id' => TRUE,
        'helloasso_payment_id' => TRUE,
      ],
      'unique' => TRUE,
    ],
    'uniq_processor_order_installment' => [
      'fields' => [
        'payment_processor_id' => TRUE,
        'order_id' => TRUE,
        'installment_number' => TRUE,
      ],
      'unique' => TRUE,
    ],
    'index_contribution_recur_id' => [
      'fields' => [
        'contribution_recur_id' => TRUE,
      ],
    ],
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
    'contribution_recur_id' => [
      'title' => E::ts('Contribution Recur ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'required' => TRUE,
      'entity_reference' => [
        'entity' => 'ContributionRecur',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'contribution_id' => [
      'title' => E::ts('Contribution ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'default' => NULL,
      'entity_reference' => [
        'entity' => 'Contribution',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
    'checkout_intent_id' => [
      'title' => E::ts('Checkout Intent ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'default' => NULL,
    ],
    'order_id' => [
      'title' => E::ts('Order ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
    ],
    'installment_number' => [
      'title' => E::ts('Installment Number'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
    ],
    'helloasso_payment_id' => [
      'title' => E::ts('Helloasso Payment ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
    ],
    'amount' => [
      'title' => E::ts('Amount'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'default' => NULL,
    ],
    'payment_date' => [
      'title' => E::ts('Payment Date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'default' => NULL,
    ],
    'state' => [
      'title' => E::ts('State'),
      'sql_type' => 'varchar(64)',
      'input_type' => 'Text',
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
