<?php
use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

return [
  'name' => 'HelloAssoInstallmentLineItem',
  'table' => 'civicrm_hello_asso_installment_line_item',
  'class' => 'CRM_HelloassoPaymentProcessor_DAO_HelloAssoInstallmentLineItem',
  'getInfo' => fn() => [
    'title' => E::ts('HelloAsso Installment Line Item'),
    'title_plural' => E::ts('HelloAsso Installment Line Items'),
    'description' => E::ts('Original and per-installment line-item allocation for HelloAsso plans'),
    'log' => TRUE,
  ],
  'getIndices' => fn() => [
    'uniq_recur_installment_ordinal' => [
      'fields' => [
        'contribution_recur_id' => TRUE,
        'installment_number' => TRUE,
        'line_item_ordinal' => TRUE,
      ],
      'unique' => TRUE,
    ],
    'index_source_line_item_id' => [
      'fields' => [
        'source_line_item_id' => TRUE,
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
    'installment_number' => [
      'title' => E::ts('Installment Number'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
    ],
    'line_item_ordinal' => [
      'title' => E::ts('Line Item Ordinal'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
    ],
    'source_line_item_id' => [
      'title' => E::ts('Source Line Item ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'default' => NULL,
      'entity_reference' => [
        'entity' => 'LineItem',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
    'entity_table' => [
      'title' => E::ts('Entity Table'),
      'sql_type' => 'varchar(64)',
      'input_type' => 'Text',
      'required' => TRUE,
    ],
    'entity_id' => [
      'title' => E::ts('Entity ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
    ],
    'price_field_value_id' => [
      'title' => E::ts('Price Field Value ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'default' => NULL,
      'entity_reference' => [
        'entity' => 'PriceFieldValue',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
    'financial_type_id' => [
      'title' => E::ts('Financial Type ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'required' => TRUE,
      'entity_reference' => [
        'entity' => 'FinancialType',
        'key' => 'id',
        'on_delete' => 'RESTRICT',
      ],
    ],
    'label' => [
      'title' => E::ts('Label'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'required' => TRUE,
    ],
    'original_amount' => [
      'title' => E::ts('Original Amount'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
    ],
    'allocated_amount' => [
      'title' => E::ts('Allocated Amount'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
    ],
    'created_at' => [
      'title' => E::ts('Created At'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'required' => TRUE,
    ],
  ],
];
