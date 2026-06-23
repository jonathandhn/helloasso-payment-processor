<?php

/**
 * Compatibility DAO for installment line-item allocations.
 *
 * @property string $id
 * @property string $contribution_recur_id
 * @property string $installment_number
 * @property string $line_item_ordinal
 * @property string $source_line_item_id
 * @property string $entity_table
 * @property string $entity_id
 * @property string $price_field_value_id
 * @property string $financial_type_id
 * @property string $label
 * @property string $original_amount
 * @property string $allocated_amount
 * @property string $created_at
 */
class CRM_HelloassoPaymentProcessor_DAO_HelloAssoInstallmentLineItem extends CRM_HelloassoPaymentProcessor_DAO_Base {

  public static $_tableName = 'civicrm_hello_asso_installment_line_item';

}
