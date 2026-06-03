<?php

use Civi\Payment\Exception\PaymentProcessorException;
use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_HelloassoPaymentProcessor_Upgrader extends CRM_Extension_Upgrader_Base {
  private const LEGACY_TRXN_REPAIR_LOCK_DATE = '2028-01-01 00:00:00';
  private const LEGACY_TRXN_GUI_LIMIT = 3000;
  private const LEGACY_TRXN_BATCH_SIZE = 25;

  public function upgrade_4200(): bool {
    $this->ctx->log->info('Applying update 4200');

    $this->addColumnIfMissing('checkout_intent_id', "ALTER TABLE civicrm_hello_asso_metadata ADD COLUMN checkout_intent_id int NULL DEFAULT NULL");
    $this->addColumnIfMissing('helloasso_payment_id', "ALTER TABLE civicrm_hello_asso_metadata ADD COLUMN helloasso_payment_id int NULL DEFAULT NULL");
    $this->addColumnIfMissing('event_type', "ALTER TABLE civicrm_hello_asso_metadata ADD COLUMN event_type varchar(64) NULL DEFAULT NULL");
    $this->addColumnIfMissing('state', "ALTER TABLE civicrm_hello_asso_metadata ADD COLUMN state varchar(64) NULL DEFAULT NULL");

    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_hello_asso_metadata m
      INNER JOIN civicrm_contribution c ON c.id = m.contribution_id
      SET m.checkout_intent_id = CAST(c.trxn_id AS UNSIGNED)
      WHERE m.checkout_intent_id IS NULL
        AND c.trxn_id REGEXP '^[0-9]+$'
    ");

    return TRUE;
  }

  public function upgrade_4201(): bool {
    $this->ctx->log->info('Applying update 4201');

    $columns = [
      'payment_processor_id' => "ALTER TABLE civicrm_hello_asso_metadata ADD COLUMN payment_processor_id int unsigned NULL DEFAULT NULL",
      'sync_origin_date' => "ALTER TABLE civicrm_hello_asso_metadata ADD COLUMN sync_origin_date datetime NULL DEFAULT NULL",
      'sync_next_date' => "ALTER TABLE civicrm_hello_asso_metadata ADD COLUMN sync_next_date datetime NULL DEFAULT NULL",
      'sync_last_date' => "ALTER TABLE civicrm_hello_asso_metadata ADD COLUMN sync_last_date datetime NULL DEFAULT NULL",
      'sync_attempt_count' => "ALTER TABLE civicrm_hello_asso_metadata ADD COLUMN sync_attempt_count int unsigned NULL DEFAULT 0",
    ];

    foreach ($columns as $columnName => $sql) {
      $this->addColumnIfMissing($columnName, $sql);
    }

    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_hello_asso_metadata m
      INNER JOIN civicrm_entity_financial_trxn eft ON eft.entity_table = 'civicrm_contribution' AND eft.entity_id = m.contribution_id
      INNER JOIN civicrm_financial_trxn ft ON ft.id = eft.financial_trxn_id
      SET m.payment_processor_id = ft.payment_processor_id
      WHERE m.payment_processor_id IS NULL
        AND ft.payment_processor_id IS NOT NULL
    ");

    return TRUE;
  }

  public function upgrade_4202(): bool {
    $this->ctx->log->info('Applying update 4202');

    $indexes = [
      'index_contribution_id' => [
        'columns' => ['contribution_id'],
        'sql' => "ALTER TABLE civicrm_hello_asso_metadata ADD INDEX index_contribution_id (contribution_id)",
      ],
      'index_checkout_intent_id' => [
        'columns' => ['checkout_intent_id'],
        'sql' => "ALTER TABLE civicrm_hello_asso_metadata ADD INDEX index_checkout_intent_id (checkout_intent_id)",
      ],
      'index_helloasso_payment_id' => [
        'columns' => ['helloasso_payment_id'],
        'sql' => "ALTER TABLE civicrm_hello_asso_metadata ADD INDEX index_helloasso_payment_id (helloasso_payment_id)",
      ],
      'index_payment_processor_id_sync_next_date' => [
        'columns' => ['payment_processor_id', 'sync_next_date'],
        'sql' => "ALTER TABLE civicrm_hello_asso_metadata ADD INDEX index_payment_processor_id_sync_next_date (payment_processor_id, sync_next_date)",
      ],
      'index_sync_next_date' => [
        'columns' => ['sync_next_date'],
        'sql' => "ALTER TABLE civicrm_hello_asso_metadata ADD INDEX index_sync_next_date (sync_next_date)",
      ],
    ];

    foreach ($indexes as $indexName => $indexSpec) {
      if ($this->hasColumns($indexSpec['columns'])) {
        $this->addIndexIfMissing($indexName, $indexSpec['sql']);
      }
    }

    return TRUE;
  }

  public function upgrade_4203(): bool {
    $this->ctx->log->info('Applying update 4203');
    return $this->runLegacyRepairUpgrade();
  }

  public function upgrade_4204(): bool {
    $this->ctx->log->info('Applying update 4204');
    return $this->runLegacyRepairUpgrade();
  }

  public function upgrade_4205(): bool {
    $this->ctx->log->info('Applying update 4205');
    return $this->runLegacyRepairUpgrade();
  }

  public function upgrade_4206(): bool {
    $this->ctx->log->info('Applying update 4206');
    return $this->runLegacyRepairUpgrade();
  }

  public function upgrade_4207(): bool {
    $this->ctx->log->info('Applying update 4207');

    $this->addColumnIfMissing('payment_processor_id', "ALTER TABLE civicrm_hello_asso_metadata ADD COLUMN payment_processor_id int unsigned NULL DEFAULT NULL");

    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_hello_asso_metadata m
      INNER JOIN civicrm_entity_financial_trxn eft
        ON eft.entity_table = 'civicrm_contribution' AND eft.entity_id = m.contribution_id
      INNER JOIN civicrm_financial_trxn ft
        ON ft.id = eft.financial_trxn_id
      SET m.payment_processor_id = ft.payment_processor_id
      WHERE m.payment_processor_id IS NULL
        AND ft.payment_processor_id IS NOT NULL
    ");

    if ($this->hasColumns(['payment_processor_id', 'sync_next_date'])) {
      $this->addIndexIfMissing(
        'index_payment_processor_id_sync_next_date',
        "ALTER TABLE civicrm_hello_asso_metadata ADD INDEX index_payment_processor_id_sync_next_date (payment_processor_id, sync_next_date)"
      );
    }

    return TRUE;
  }

  public function upgrade_4208(): bool {
    $this->ctx->log->info('Applying update 4208');

    $columns = [
      'long_sync_scheme' => "ALTER TABLE civicrm_hello_asso_metadata ADD COLUMN long_sync_scheme varchar(16) NULL DEFAULT NULL",
      'long_sync_origin_date' => "ALTER TABLE civicrm_hello_asso_metadata ADD COLUMN long_sync_origin_date datetime NULL DEFAULT NULL",
      'long_sync_next_date' => "ALTER TABLE civicrm_hello_asso_metadata ADD COLUMN long_sync_next_date datetime NULL DEFAULT NULL",
      'long_sync_last_date' => "ALTER TABLE civicrm_hello_asso_metadata ADD COLUMN long_sync_last_date datetime NULL DEFAULT NULL",
      'long_sync_attempt_count' => "ALTER TABLE civicrm_hello_asso_metadata ADD COLUMN long_sync_attempt_count int unsigned NULL DEFAULT 0",
    ];

    foreach ($columns as $columnName => $sql) {
      $this->addColumnIfMissing($columnName, $sql);
    }

    if ($this->hasColumns(['payment_processor_id', 'long_sync_next_date'])) {
      $this->addIndexIfMissing(
        'index_payment_processor_id_long_sync_next_date',
        "ALTER TABLE civicrm_hello_asso_metadata ADD INDEX index_payment_processor_id_long_sync_next_date (payment_processor_id, long_sync_next_date)"
      );
    }

    return TRUE;
  }

  public function upgrade_4209(): bool {
    $this->ctx->log->info('Applying update 4209');

    CRM_Core_DAO::executeQuery("
      CREATE TABLE IF NOT EXISTS civicrm_hello_asso_processor_auth (
        id int unsigned NOT NULL AUTO_INCREMENT,
        payment_processor_id int unsigned NOT NULL,
        connection_mode varchar(32) NULL DEFAULT NULL,
        organization_slug varchar(255) NULL DEFAULT NULL,
        access_token text NULL,
        refresh_token text NULL,
        expires_at int unsigned NULL DEFAULT NULL,
        refresh_issued_at int unsigned NULL DEFAULT NULL,
        refresh_expires_at int unsigned NULL DEFAULT NULL,
        refresh_status varchar(32) NULL DEFAULT NULL,
        last_refresh_error text NULL,
        last_refresh_error_date datetime NULL DEFAULT NULL,
        last_refresh_http_status int unsigned NULL DEFAULT NULL,
        linked_at datetime NULL DEFAULT NULL,
        redirect_uri text NULL,
        webhook_ownership varchar(32) NULL DEFAULT NULL,
        webhook_url text NULL,
        webhook_signature_key text NULL,
        webhook_updated_at datetime NULL DEFAULT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_payment_processor_id (payment_processor_id),
        KEY index_connection_mode (connection_mode),
        KEY index_organization_slug (organization_slug)
      ) ENGINE=InnoDB
    ");

    return TRUE;
  }

  public function upgrade_4210(): bool {
    $this->ctx->log->info('Applying update 4210');
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_setting WHERE name = 'helloasso_v2_drupal_browser_workarounds'");
    return TRUE;
  }

  public function upgrade_4211(): bool {
    $this->ctx->log->info('Applying update 4211: protect HelloAsso follow-up rails from repeated calls.');

    $this->addColumnIfMissing('sync_error_count', "ALTER TABLE civicrm_hello_asso_metadata ADD COLUMN sync_error_count int unsigned NULL DEFAULT 0");
    $this->addColumnIfMissing('long_sync_error_count', "ALTER TABLE civicrm_hello_asso_metadata ADD COLUMN long_sync_error_count int unsigned NULL DEFAULT 0");

    $shortResolvedStatusIds = $this->getContributionStatusIds(['Completed', 'Failed', 'Refunded']);
    $shortResolvedStatusSql = $shortResolvedStatusIds ? ' OR c.contribution_status_id IN (' . implode(', ', $shortResolvedStatusIds) . ')' : '';
    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_hello_asso_metadata m
      INNER JOIN civicrm_contribution c ON c.id = m.contribution_id
      SET m.sync_next_date = NULL
      WHERE m.sync_next_date IS NOT NULL
        AND (
          m.state IN ('Authorized', 'Registered', 'Refused', 'Error', 'Canceled', 'Abandoned', 'Refunded')
          OR COALESCE(m.sync_attempt_count, 0) >= 2
          {$shortResolvedStatusSql}
        )
    ");

    $longResolvedStatusIds = $this->getContributionStatusIds(['Failed', 'Refunded']);
    $longResolvedStatusSql = $longResolvedStatusIds ? ' OR c.contribution_status_id IN (' . implode(', ', $longResolvedStatusIds) . ')' : '';
    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_hello_asso_metadata m
      INNER JOIN civicrm_contribution c ON c.id = m.contribution_id
      SET m.long_sync_next_date = NULL
      WHERE m.long_sync_next_date IS NOT NULL
        AND (
          m.state IN ('Refused', 'Error', 'Canceled', 'Abandoned', 'Refunded')
          OR COALESCE(m.long_sync_attempt_count, 0) >= 3
          {$longResolvedStatusSql}
        )
    ");

    return TRUE;
  }

  public function upgrade_4212(): bool {
    $this->ctx->log->info('Applying update 4212: track HelloAsso partner refresh token issue date.');

    $column = CRM_Core_DAO::executeQuery("SHOW COLUMNS FROM civicrm_hello_asso_processor_auth LIKE 'refresh_issued_at'");
    if (!$column->fetch()) {
      CRM_Core_DAO::executeQuery('ALTER TABLE civicrm_hello_asso_processor_auth ADD COLUMN refresh_issued_at int unsigned NULL DEFAULT NULL AFTER expires_at');
    }

    // Existing values were written from the documented 30-day refresh token lifetime.
    CRM_Core_DAO::executeQuery('
      UPDATE civicrm_hello_asso_processor_auth
      SET refresh_issued_at = GREATEST(0, refresh_expires_at - 2592000)
      WHERE refresh_issued_at IS NULL
        AND refresh_expires_at IS NOT NULL
    ');

    return TRUE;
  }

  public function upgrade_4213(): bool {
    $this->ctx->log->info('Applying update 4213: persist HelloAsso partner refresh failures for administrator alerts.');

    $columns = [
      'refresh_status' => "ALTER TABLE civicrm_hello_asso_processor_auth ADD COLUMN refresh_status varchar(32) NULL DEFAULT NULL AFTER refresh_expires_at",
      'last_refresh_error' => "ALTER TABLE civicrm_hello_asso_processor_auth ADD COLUMN last_refresh_error text NULL AFTER refresh_status",
      'last_refresh_error_date' => "ALTER TABLE civicrm_hello_asso_processor_auth ADD COLUMN last_refresh_error_date datetime NULL DEFAULT NULL AFTER last_refresh_error",
      'last_refresh_http_status' => "ALTER TABLE civicrm_hello_asso_processor_auth ADD COLUMN last_refresh_http_status int unsigned NULL DEFAULT NULL AFTER last_refresh_error_date",
    ];

    foreach ($columns as $columnName => $sql) {
      $column = CRM_Core_DAO::executeQuery("SHOW COLUMNS FROM civicrm_hello_asso_processor_auth LIKE %1", [
        1 => [$columnName, 'String'],
      ]);
      if (!$column->fetch()) {
        CRM_Core_DAO::executeQuery($sql);
      }
    }

    return TRUE;
  }

  public function upgrade_4214(): bool {
    $this->ctx->log->info('Applying update 4214: configure a safe default payment method for HelloAsso processors.');

    $creditCardInstrument = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('value')
      ->addWhere('option_group_id:name', '=', 'payment_instrument')
      ->addWhere('name', '=', 'Credit Card')
      ->execute()
      ->first();
    $paymentInstrumentId = (int) ($creditCardInstrument['value'] ?? 0);
    if (!$paymentInstrumentId) {
      $this->ctx->log->warning('HelloAsso payment method default was not configured because CiviCRM Credit Card was not found.');
      return TRUE;
    }

    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_payment_processor_type
      SET payment_instrument_id = %1
      WHERE class_name = 'Payment_HelloAsso'
        AND (payment_instrument_id IS NULL OR payment_instrument_id = 0)
    ", [
      1 => [$paymentInstrumentId, 'Integer'],
    ]);

    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_payment_processor
      SET payment_instrument_id = %1
      WHERE class_name = 'Payment_HelloAsso'
        AND (payment_instrument_id IS NULL OR payment_instrument_id = 0)
    ", [
      1 => [$paymentInstrumentId, 'Integer'],
    ]);

    return TRUE;
  }

  private function runLegacyRepairUpgrade(): bool {
    if (!$this->isLegacyRepairWindowOpen()) {
      $this->ctx->log->warning('HelloAsso legacy trxn_id repair is locked after ' . self::LEGACY_TRXN_REPAIR_LOCK_DATE . '.');
      return TRUE;
    }

    $candidateIds = $this->getLegacyRepairCandidateIds();
    $candidateCount = count($candidateIds);

    if (!$candidateCount) {
      $this->ctx->log->info('HelloAsso legacy trxn_id repair: no candidates found.');
      return TRUE;
    }

    $this->addTask(
      E::ts('HelloAsso: analyze concatenated legacy trxn_id values (%1 cases)', [1 => $candidateCount]),
      'taskLegacyRepairScan',
      $candidateCount
    );

    if ($candidateCount > self::LEGACY_TRXN_GUI_LIMIT) {
      $this->addTask(
        E::ts('HelloAsso: too many cases (%1) for GUI repair. Use terminal mode.', [1 => $candidateCount]),
        'taskLegacyRepairTooManyCandidates',
        $candidateCount,
        self::LEGACY_TRXN_GUI_LIMIT
      );
      return TRUE;
    }

    foreach (array_chunk($candidateIds, self::LEGACY_TRXN_BATCH_SIZE) as $batchIndex => $batchIds) {
      $firstId = reset($batchIds);
      $lastId = end($batchIds);
      $this->addTask(
        E::ts('HelloAsso: repair legacy trxn_id values (batch %1, contributions %2 to %3)', [
          1 => $batchIndex + 1,
          2 => $firstId,
          3 => $lastId,
        ]),
        'taskLegacyRepairBatch',
        $batchIds
      );
    }

    $this->addTask(
      E::ts('HelloAsso: final report of remaining legacy trxn_id values'),
      'taskLegacyRepairReport'
    );

    return TRUE;
  }

  private function addColumnIfMissing(string $columnName, string $sql): void {
    $column = CRM_Core_DAO::executeQuery("SHOW COLUMNS FROM civicrm_hello_asso_metadata LIKE %1", [
      1 => [$columnName, 'String'],
    ]);
    if (!$column->fetch()) {
      CRM_Core_DAO::executeQuery($sql);
    }
  }

  private function addIndexIfMissing(string $indexName, string $sql): void {
    $index = CRM_Core_DAO::executeQuery("SHOW INDEX FROM civicrm_hello_asso_metadata WHERE Key_name = %1", [
      1 => [$indexName, 'String'],
    ]);
    if (!$index->fetch()) {
      CRM_Core_DAO::executeQuery($sql);
    }
  }

  private function hasColumns(array $columnNames): bool {
    foreach ($columnNames as $columnName) {
      $column = CRM_Core_DAO::executeQuery("SHOW COLUMNS FROM civicrm_hello_asso_metadata LIKE %1", [
        1 => [$columnName, 'String'],
      ]);
      if (!$column->fetch()) {
        return FALSE;
      }
    }
    return TRUE;
  }

  private function getContributionStatusIds(array $statusNames): array {
    $statusIds = [];
    foreach ($statusNames as $statusName) {
      $statusId = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', $statusName);
      if ($statusId) {
        $statusIds[] = (int) $statusId;
      }
    }

    return $statusIds;
  }

  private function normalizeNullableInt($value): ?int {
    if ($value === NULL || $value === '') {
      return NULL;
    }

    return (int) $value;
  }

  private function normalizeNullableString($value): string {
    if ($value === NULL) {
      return '';
    }

    return (string) $value;
  }

  private function normalizeNullableDateTime($value): ?string {
    if ($value === NULL || $value === '') {
      return NULL;
    }

    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
      $digits = (string) $value;
      if (strlen($digits) === 14 || strlen($digits) === 8) {
        return $digits;
      }
      if (strlen($digits) === 10) {
        return gmdate('YmdHis', (int) $digits);
      }
    }

    try {
      return (new DateTimeImmutable((string) $value))->format('YmdHis');
    }
    catch (Exception $e) {
      return (string) $value;
    }
  }

  private function buildNullableIntegerParam($value): array {
    $value = $this->normalizeNullableInt($value);
    if ($value === NULL) {
      return ['null', 'String', CRM_Core_DAO::QUERY_FORMAT_NO_QUOTES];
    }

    return [$value, 'Integer'];
  }

  private function buildNullableTimestampParam($value): array {
    $value = $this->normalizeNullableDateTime($value);
    if ($value === NULL || $value === '') {
      return ['null', 'String', CRM_Core_DAO::QUERY_FORMAT_NO_QUOTES];
    }

    return [$value, 'Timestamp'];
  }

  public function taskLegacyRepairScan(int $candidateCount): bool {
    $this->ctx->log->warning(sprintf(
      'HelloAsso legacy trxn_id repair: %d candidate(s) found. Auto-repair is limited to GUI runs up to %d candidates and remains locked after %s.',
      $candidateCount,
      self::LEGACY_TRXN_GUI_LIMIT,
      self::LEGACY_TRXN_REPAIR_LOCK_DATE
    ));
    return TRUE;
  }

  public function taskLegacyRepairTooManyCandidates(int $candidateCount, int $guiLimit): bool {
    $this->ctx->log->warning(sprintf(
      'HelloAsso legacy trxn_id repair skipped in GUI mode: %d candidate(s) exceed the GUI limit of %d. Please use the terminal workflow for this migration.',
      $candidateCount,
      $guiLimit
    ));
    return TRUE;
  }

  public function taskLegacyRepairBatch(array $contributionIds): bool {
    $client = CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance();

    foreach ($contributionIds as $contributionId) {
      $candidate = $this->loadLegacyRepairCandidate((int) $contributionId);
      if (!$candidate) {
        continue;
      }

      try {
        $resolved = $this->resolveLegacyRepairContext($candidate, $client);
      }
      catch (PaymentProcessorException $e) {
        if (strpos($e->getMessage(), '(429)') !== FALSE || strpos($e->getMessage(), '429') !== FALSE) {
          throw new CRM_Core_Exception(sprintf(
            'HelloAsso legacy trxn_id repair paused because the API returned 429 Too Many Requests while processing contribution %d. Retry later or skip this task.',
            $contributionId
          ));
        }

        $this->ctx->log->warning(sprintf(
          'HelloAsso legacy trxn_id repair: contribution %d skipped after API error: %s',
          $contributionId,
          $e->getMessage()
        ));
        continue;
      }

      if (!$resolved) {
        $this->ctx->log->warning(sprintf('HelloAsso legacy trxn_id repair: contribution %d skipped because no HelloAsso processor could be resolved.', $contributionId));
        continue;
      }

      if (!$this->isLegacyRepairMatchValid($candidate, $resolved['checkoutIntent'], $resolved['payment'])) {
        $this->ctx->log->warning(sprintf(
          'HelloAsso legacy trxn_id repair: contribution %d left unresolved because the checkout/payment pair could not be proven.',
          $contributionId
        ));
        continue;
      }

      $this->applyLegacyRepair($candidate, $resolved['processor'], $resolved['checkoutIntent'], $resolved['payment']);
    }

    return TRUE;
  }

  public function taskLegacyRepairReport(): bool {
    $remaining = $this->countLegacyRepairCandidates();
    if ($remaining) {
      $this->ctx->log->warning(sprintf(
        'HelloAsso legacy trxn_id repair completed with %d unresolved candidate(s). Review them manually or use the terminal workflow.',
        $remaining
      ));
    }
    else {
      $this->ctx->log->info('HelloAsso legacy trxn_id repair completed with no remaining candidates.');
    }
    return TRUE;
  }

  private function isLegacyRepairWindowOpen(): bool {
    return strtotime('now') < strtotime(self::LEGACY_TRXN_REPAIR_LOCK_DATE);
  }

  private function countLegacyRepairCandidates(): int {
    return (int) CRM_Core_DAO::singleValueQuery("
      SELECT COUNT(*)
      FROM civicrm_contribution c
      INNER JOIN civicrm_hello_asso_metadata m ON m.contribution_id = c.id
      WHERE c.trxn_id REGEXP '^[0-9]+,[0-9]+$'
    ");
  }

  private function getLegacyRepairCandidateIds(): array {
    $dao = CRM_Core_DAO::executeQuery("
      SELECT c.id
      FROM civicrm_contribution c
      INNER JOIN civicrm_hello_asso_metadata m ON m.contribution_id = c.id
      WHERE c.trxn_id REGEXP '^[0-9]+,[0-9]+$'
      ORDER BY c.id ASC
    ");

    $ids = [];
    while ($dao->fetch()) {
      $ids[] = (int) $dao->id;
    }

    return $ids;
  }

  private function loadLegacyRepairCandidate(int $contributionId): ?array {
    $dao = CRM_Core_DAO::executeQuery("
      SELECT c.id AS contribution_id,
             c.invoice_id,
             c.trxn_id,
             SUBSTRING_INDEX(c.trxn_id, ',', 1) AS left_part,
             SUBSTRING_INDEX(c.trxn_id, ',', -1) AS right_part,
             m.id AS metadata_id,
             m.checkout_intent_id,
             m.helloasso_payment_id,
             m.event_type,
             m.state
      FROM civicrm_contribution c
      INNER JOIN civicrm_hello_asso_metadata m ON m.contribution_id = c.id
      WHERE c.id = %1
        AND c.trxn_id REGEXP '^[0-9]+,[0-9]+$'
      LIMIT 1
    ", [
      1 => [$contributionId, 'Integer'],
    ]);

    if (!$dao->fetch()) {
      return NULL;
    }

    return [
      'contribution_id' => (int) $dao->contribution_id,
      'invoice_id' => (string) $dao->invoice_id,
      'trxn_id' => (string) $dao->trxn_id,
      'left_part' => (int) $dao->left_part,
      'right_part' => (int) $dao->right_part,
      'metadata_id' => (int) $dao->metadata_id,
      'checkout_intent_id' => $dao->checkout_intent_id === NULL ? NULL : (int) $dao->checkout_intent_id,
      'helloasso_payment_id' => $dao->helloasso_payment_id === NULL ? NULL : (int) $dao->helloasso_payment_id,
      'event_type' => $dao->event_type,
      'state' => $dao->state,
    ];
  }

  private function getHelloAssoProcessorConfigForContribution(int $contributionId): ?array {
    $hasMetadataProcessorId = $this->hasColumns(['payment_processor_id']);
    $processorMatch = $hasMetadataProcessorId
      ? "(
          (m.payment_processor_id IS NOT NULL AND pp.id = m.payment_processor_id)
          OR (m.payment_processor_id IS NULL AND pp.id = ft.payment_processor_id)
        )"
      : "pp.id = ft.payment_processor_id";

    $sql = "
      SELECT pp.id,
             pp.is_test,
             pp.user_name,
             pp.password,
             pp.signature,
             pp.subject,
             pp.url_site,
             pp.is_active
      FROM civicrm_payment_processor pp
      INNER JOIN civicrm_payment_processor_type ppt ON ppt.id = pp.payment_processor_type_id
      LEFT JOIN civicrm_hello_asso_metadata m ON m.contribution_id = %1
      LEFT JOIN civicrm_entity_financial_trxn eft
        ON eft.entity_table = 'civicrm_contribution' AND eft.entity_id = %1
      LEFT JOIN civicrm_financial_trxn ft ON ft.id = eft.financial_trxn_id
      WHERE ppt.class_name = 'Payment_HelloAsso'
        AND pp.is_active = 1
        AND {$processorMatch}
      ORDER BY pp.is_test ASC, pp.id DESC
      LIMIT 1
    ";

    $dao = CRM_Core_DAO::executeQuery($sql, [
      1 => [$contributionId, 'Integer'],
    ]);

    if (!$dao->fetch()) {
      return NULL;
    }

    return [
      'id' => (int) $dao->id,
      'is_test' => (bool) $dao->is_test,
      'user_name' => (string) $dao->user_name,
      'password' => (string) $dao->password,
      'signature' => (string) $dao->signature,
      'subject' => (string) $dao->subject,
      'url_site' => (string) $dao->url_site,
      'is_active' => (bool) $dao->is_active,
    ];
  }

  private function getActiveHelloAssoProcessors(): array {
    $dao = CRM_Core_DAO::executeQuery("
      SELECT pp.id,
             pp.is_test,
             pp.user_name,
             pp.password,
             pp.signature,
             pp.subject,
             pp.url_site,
             pp.is_active
      FROM civicrm_payment_processor pp
      INNER JOIN civicrm_payment_processor_type ppt ON ppt.id = pp.payment_processor_type_id
      WHERE ppt.class_name = 'Payment_HelloAsso'
        AND pp.is_active = 1
      ORDER BY pp.is_test ASC, pp.id DESC
    ");

    $processors = [];
    while ($dao->fetch()) {
      $processors[] = [
        'id' => (int) $dao->id,
        'is_test' => (bool) $dao->is_test,
        'user_name' => (string) $dao->user_name,
        'password' => (string) $dao->password,
        'signature' => (string) $dao->signature,
        'subject' => (string) $dao->subject,
        'url_site' => (string) $dao->url_site,
        'is_active' => (bool) $dao->is_active,
      ];
    }

    return $processors;
  }

  private function resolveLegacyRepairContext(array $candidate, CRM_HelloassoPaymentProcessor_HelloAssoClient $client): ?array {
    $processor = $this->getHelloAssoProcessorConfigForContribution((int) $candidate['contribution_id']);
    if ($processor) {
      $checkoutIntent = $this->normalizeCheckoutIntentPayload($client->getCheckoutIntent(
        $processor,
        (bool) $processor['is_test'],
        (int) $candidate['left_part'],
        ['withFailedRefundOperation' => 'true']
      ));

      return [
        'processor' => $processor,
        'checkoutIntent' => $checkoutIntent,
        'payment' => [
          'id' => (int) $candidate['right_part'],
          'state' => $this->inferPaymentStateFromCheckoutIntent($checkoutIntent, (int) $candidate['right_part']),
        ],
      ];
    }

    foreach ($this->getActiveHelloAssoProcessors() as $activeProcessor) {
      try {
        $checkoutIntent = $this->normalizeCheckoutIntentPayload($client->getCheckoutIntent(
          $activeProcessor,
          (bool) $activeProcessor['is_test'],
          (int) $candidate['left_part'],
          ['withFailedRefundOperation' => 'true']
        ));
      }
      catch (PaymentProcessorException $e) {
        if (strpos($e->getMessage(), '(429)') !== FALSE || strpos($e->getMessage(), '429') !== FALSE) {
          throw $e;
        }
        continue;
      }

      $checkoutInvoiceId = (string) ($checkoutIntent['metadata']['invoiceID'] ?? '');
      $checkoutId = (int) ($checkoutIntent['id'] ?? 0);
      if ($checkoutId !== (int) $candidate['left_part'] || $checkoutInvoiceId !== (string) $candidate['invoice_id']) {
        continue;
      }

      return [
        'processor' => $activeProcessor,
        'checkoutIntent' => $checkoutIntent,
        'payment' => [
          'id' => (int) $candidate['right_part'],
          'state' => $this->inferPaymentStateFromCheckoutIntent($checkoutIntent, (int) $candidate['right_part']),
        ],
      ];
    }

    return NULL;
  }

  private function isLegacyRepairMatchValid(array $candidate, array $checkoutIntent, array $payment): bool {
    $checkoutId = (int) ($checkoutIntent['id'] ?? 0);
    $checkoutInvoiceId = (string) ($checkoutIntent['metadata']['invoiceID'] ?? '');
    $paymentIds = $this->extractPaymentIdsFromCheckoutIntent($checkoutIntent);

    if ($checkoutId !== (int) $candidate['left_part']) {
      return FALSE;
    }

    if (!in_array((int) $candidate['right_part'], $paymentIds, TRUE)) {
      return FALSE;
    }

    if ($checkoutInvoiceId === '' || $checkoutInvoiceId !== (string) $candidate['invoice_id']) {
      return FALSE;
    }

    return TRUE;
  }

  private function normalizeCheckoutIntentPayload(array $checkoutIntent): array {
    if (isset($checkoutIntent[0]) && is_array($checkoutIntent[0])) {
      return $checkoutIntent[0];
    }

    return $checkoutIntent;
  }

  private function normalizePaymentPayload(array $payment): array {
    if (isset($payment[0]) && is_array($payment[0])) {
      return $payment[0];
    }

    return $payment;
  }

  private function extractPaymentIdsFromCheckoutIntent(array $checkoutIntent): array {
    $paymentIds = [];

    foreach (($checkoutIntent['order']['payments'] ?? []) as $payment) {
      if (!empty($payment['id'])) {
        $paymentIds[] = (int) $payment['id'];
      }
    }

    foreach (($checkoutIntent['order']['items'] ?? []) as $item) {
      foreach (($item['payments'] ?? []) as $payment) {
        if (!empty($payment['id'])) {
          $paymentIds[] = (int) $payment['id'];
        }
      }
    }

    return array_values(array_unique($paymentIds));
  }

  private function inferPaymentStateFromCheckoutIntent(array $checkoutIntent, int $paymentId): string {
    foreach (($checkoutIntent['order']['payments'] ?? []) as $payment) {
      if (!empty($payment['id']) && (int) $payment['id'] === $paymentId) {
        return (string) ($payment['state'] ?? '');
      }
    }

    return '';
  }

  private function applyLegacyRepair(array $candidate, array $processor, array $checkoutIntent, array $payment): void {
    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_contribution
      SET trxn_id = %1
      WHERE id = %2
    ", [
      1 => [(string) $candidate['right_part'], 'String'],
      2 => [$candidate['contribution_id'], 'Integer'],
    ]);

    $updates = [
      "checkout_intent_id = %1",
      "helloasso_payment_id = %2",
      "event_type = %3",
      "state = %4",
    ];
    $params = [
      1 => [(int) $candidate['left_part'], 'Integer'],
      2 => [(int) $candidate['right_part'], 'Integer'],
      3 => ['LegacyRepair', 'String'],
      4 => [(string) ($payment['state'] ?? ''), 'String'],
      5 => [$candidate['metadata_id'], 'Integer'],
    ];

    if ($this->hasColumns(['payment_processor_id'])) {
      $updates[] = "payment_processor_id = %6";
      $params[6] = [$processor['id'], 'Integer'];
    }
    if ($this->hasColumns(['sync_last_date'])) {
      $updates[] = "sync_last_date = NOW()";
    }

    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_hello_asso_metadata
      SET " . implode(",\n          ", $updates) . "
      WHERE id = %5
    ", $params);
  }

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Example: Run an external SQL script when the module is installed.
   *
   * Note that if a file is present sql\auto_install that will run regardless of this hook.
   */
  // public function install(): void {
  //   $this->executeSqlFile('sql/my_install.sql');
  // }

  /**
   * Example: Work with entities usually not available during the install step.
   *
   * This method can be used for any post-install tasks. For example, if a step
   * of your installation depends on accessing an entity that is itself
   * created during the installation (e.g., a setting or a managed entity), do
   * so here to avoid order of operation problems.
   */
  // public function postInstall(): void {
  //  $customFieldId = civicrm_api3('CustomField', 'getvalue', array(
  //    'return' => array("id"),
  //    'name' => "customFieldCreatedViaManagedHook",
  //  ));
  //  civicrm_api3('Setting', 'create', array(
  //    'myWeirdFieldSetting' => array('id' => $customFieldId, 'weirdness' => 1),
  //  ));
  // }

  /**
   * Example: Run an external SQL script when the module is uninstalled.
   *
   * Note that if a file is present sql\auto_uninstall that will run regardless of this hook.
   */
  // public function uninstall(): void {
  //   $this->executeSqlFile('sql/my_uninstall.sql');
  // }

  /**
   * Example: Run a simple query when a module is enabled.
   */
  // public function enable(): void {
  //  CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 1 WHERE bar = "whiz"');
  // }

  /**
   * Example: Run a simple query when a module is disabled.
   */
  // public function disable(): void {
  //   CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 0 WHERE bar = "whiz"');
  // }

  /**
   * Example: Run a couple simple queries.
   *
   * @return TRUE on success
   * @throws CRM_Core_Exception
   */
  // public function upgrade_4200(): bool {
  //   $this->ctx->log->info('Applying update 4200');
  //   CRM_Core_DAO::executeQuery('UPDATE foo SET bar = "whiz"');
  //   CRM_Core_DAO::executeQuery('DELETE FROM bang WHERE willy = wonka(2)');
  //   return TRUE;
  // }

  /**
   * Example: Run an external SQL script.
   *
   * @return TRUE on success
   * @throws CRM_Core_Exception
   */
  // public function upgrade_4201(): bool {
  //   $this->ctx->log->info('Applying update 4201');
  //   // this path is relative to the extension base dir
  //   $this->executeSqlFile('sql/upgrade_4201.sql');
  //   return TRUE;
  // }

  /**
   * Example: Run a slow upgrade process by breaking it up into smaller chunk.
   *
   * @return TRUE on success
   * @throws CRM_Core_Exception
   */
  // public function upgrade_4202(): bool {
  //   $this->ctx->log->info('Planning update 4202'); // PEAR Log interface

  //   $this->addTask(E::ts('Process first step'), 'processPart1', $arg1, $arg2);
  //   $this->addTask(E::ts('Process second step'), 'processPart2', $arg3, $arg4);
  //   $this->addTask(E::ts('Process second step'), 'processPart3', $arg5);
  //   return TRUE;
  // }
  // public function processPart1($arg1, $arg2) { sleep(10); return TRUE; }
  // public function processPart2($arg3, $arg4) { sleep(10); return TRUE; }
  // public function processPart3($arg5) { sleep(10); return TRUE; }

  /**
   * Example: Run an upgrade with a query that touches many (potentially
   * millions) of records by breaking it up into smaller chunks.
   *
   * @return TRUE on success
   * @throws CRM_Core_Exception
   */
  // public function upgrade_4203(): bool {
  //   $this->ctx->log->info('Planning update 4203'); // PEAR Log interface

  //   $minId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(min(id),0) FROM civicrm_contribution');
  //   $maxId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(max(id),0) FROM civicrm_contribution');
  //   for ($startId = $minId; $startId <= $maxId; $startId += self::BATCH_SIZE) {
  //     $endId = $startId + self::BATCH_SIZE - 1;
  //     $title = E::ts('Upgrade Batch (%1 => %2)', array(
  //       1 => $startId,
  //       2 => $endId,
  //     ));
  //     $sql = '
  //       UPDATE civicrm_contribution SET foobar = apple(banana()+durian)
  //       WHERE id BETWEEN %1 and %2
  //     ';
  //     $params = array(
  //       1 => array($startId, 'Integer'),
  //       2 => array($endId, 'Integer'),
  //     );
  //     $this->addTask($title, 'executeSql', $sql, $params);
  //   }
  //   return TRUE;
  // }

}
