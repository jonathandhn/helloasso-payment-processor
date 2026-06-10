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
 * @property string $payment_processor_id
 * @property string $connection_mode
 * @property string $organization_slug
 * @property string $access_token
 * @property string $refresh_token
 * @property string $expires_at
 * @property string $refresh_issued_at
 * @property string $refresh_expires_at
 * @property string $refresh_status
 * @property string $last_refresh_error
 * @property string $last_refresh_error_date
 * @property string $last_refresh_http_status
 * @property string $linked_at
 * @property string $redirect_uri
 * @property string $webhook_ownership
 * @property string $webhook_url
 * @property string $webhook_signature_key
 * @property string $webhook_updated_at
 * @property string $created_at
 * @property string $updated_at
 */
class CRM_HelloassoPaymentProcessor_DAO_HelloAssoProcessorAuth extends CRM_HelloassoPaymentProcessor_DAO_Base {

  /**
   * Required by older versions of CiviCRM (<5.74).
   * @var string
   */
  public static $_tableName = 'civicrm_hello_asso_processor_auth';

}
