<?php

/**
 * DAOs provide an OOP-style facade for reading and writing database records.
 *
 * DAOs are a primary source for metadata in older versions of CiviCRM (<5.74)
 * and are required for some subsystems (such as APIv3).
 *
 * This stub provides compatibility. It is not intended to be modified in a
 * substantive way. Property annotations may be added, but are not required.
 * @property string $id
 * @property string $contribution_id
 * @property string $signing_key
 * @property int|string $helloasso_ref_cmd_id
 * @property int|string $checkout_intent_id
 * @property int|string $helloasso_payment_id
 * @property string $event_type
 * @property string $state
 * @property string $payment_processor_id
 * @property string $sync_origin_date
 * @property string $sync_next_date
 * @property string $sync_last_date
 * @property string $sync_attempt_count
 * @property string $sync_error_count
 * @property string $long_sync_scheme
 * @property string $long_sync_origin_date
 * @property string $long_sync_next_date
 * @property string $long_sync_last_date
 * @property string $long_sync_attempt_count
 * @property string $long_sync_error_count
 */
class CRM_HelloassoPaymentProcessor_DAO_HelloAssoMetadata extends CRM_HelloassoPaymentProcessor_DAO_Base {

  /**
   * Required by older versions of CiviCRM (<5.74).
   * @var string
   */
  public static $_tableName = 'civicrm_hello_asso_metadata';

}
