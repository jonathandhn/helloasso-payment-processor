<?php

use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

/**
 * Store HelloAsso per-processor authorization state in a dedicated table.
 *
 */
class CRM_HelloassoPaymentProcessor_ProcessorAuthConfig {

  private const TABLE_NAME = 'civicrm_hello_asso_processor_auth';

  public function getAll(): array {
    if ($this->hasDedicatedTable()) {
      return $this->getAllFromTable();
    }

    return [];
  }

  public function get(int $paymentProcessorId): array {
    $all = $this->getAll();
    $config = $all[(string) $paymentProcessorId] ?? [];
    return is_array($config) ? $config : [];
  }

  public function set(int $paymentProcessorId, array $config): void {
    if ($this->hasDedicatedTable()) {
      $this->setInTable($paymentProcessorId, $config);
      return;
    }

    throw new RuntimeException(E::ts('HelloAsso processor authorization table is missing.'));
  }

  public function remove(int $paymentProcessorId): void {
    if ($this->hasDedicatedTable()) {
      CRM_Core_DAO::executeQuery(
        'DELETE FROM ' . self::TABLE_NAME . ' WHERE payment_processor_id = %1',
        [1 => [$paymentProcessorId, 'Integer']]
      );
      return;
    }

    throw new RuntimeException(E::ts('HelloAsso processor authorization table is missing.'));
  }

  public function getConnectionMode(int $paymentProcessorId, array $paymentProcessor = []): string {
    $config = $this->get($paymentProcessorId);
    $mode = (string) ($config['connection_mode'] ?? '');
    if (in_array($mode, ['community', 'plugin_public'], TRUE)) {
      return $mode;
    }

    return $this->getDefaultConnectionMode($paymentProcessor);
  }

  public function getDefaultConnectionMode(array $paymentProcessor = []): string {
    return !$this->hasClassicCredentials($paymentProcessor) ? 'plugin_public' : 'community';
  }

  public function setConnectionMode(int $paymentProcessorId, string $mode): void {
    if (!in_array($mode, ['community', 'plugin_public'], TRUE)) {
      throw new InvalidArgumentException(E::ts('Unsupported HelloAsso connection mode.'));
    }

    $config = $this->get($paymentProcessorId);
    $config['connection_mode'] = $mode;
    $config['updated_at'] = date('Y-m-d H:i:s');
    $this->set($paymentProcessorId, $config);
  }

  public function getLinkedOrganization(int $paymentProcessorId): ?array {
    $config = $this->get($paymentProcessorId);
    $link = $config['oauth_link'] ?? NULL;
    if (!is_array($link) || empty($link['organization_slug'])) {
      return NULL;
    }

    return [
      'organization_slug' => $link['organization_slug'] ?? NULL,
      'linked_at' => $link['linked_at'] ?? NULL,
      'expires_at' => $link['expires_at'] ?? NULL,
      'refresh_issued_at' => $link['refresh_issued_at'] ?? NULL,
      'refresh_expires_at' => $link['refresh_expires_at'] ?? NULL,
      'refresh_status' => $link['refresh_status'] ?? NULL,
      'last_refresh_error' => $link['last_refresh_error'] ?? NULL,
      'last_refresh_error_date' => $link['last_refresh_error_date'] ?? NULL,
      'last_refresh_http_status' => $link['last_refresh_http_status'] ?? NULL,
    ];
  }

  public function getStoredLink(int $paymentProcessorId): ?array {
    $config = $this->get($paymentProcessorId);
    $link = $config['oauth_link'] ?? NULL;
    return is_array($link) ? $link : NULL;
  }

  public function storeLink(int $paymentProcessorId, array $link): void {
    $config = $this->get($paymentProcessorId);
    $config['oauth_link'] = $link;
    $config['updated_at'] = date('Y-m-d H:i:s');
    $this->set($paymentProcessorId, $config);
  }

  public function unlink(int $paymentProcessorId): void {
    $config = $this->get($paymentProcessorId);
    unset($config['oauth_link'], $config['webhook_registration']);
    $config['updated_at'] = date('Y-m-d H:i:s');
    $this->set($paymentProcessorId, $config);
  }

  public function clearClassicCredentials(int $paymentProcessorId): void {
    civicrm_api4('PaymentProcessor', 'save', [
      'records' => [[
        'id' => $paymentProcessorId,
        'user_name' => '',
        'password' => '',
        'signature' => '',
      ]],
    ]);
  }

  public function hasClassicCredentials(array $paymentProcessor): bool {
    foreach (['user_name', 'password'] as $field) {
      if (trim((string) ($paymentProcessor[$field] ?? '')) !== '') {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * @return array<int, array>
   */
  public function getLinkedProcessors(): array {
    $linked = [];
    foreach ($this->getAll() as $paymentProcessorId => $config) {
      if (!empty($config['oauth_link']) && is_array($config['oauth_link']) && !empty($config['oauth_link']['organization_slug'])) {
        $linked[(int) $paymentProcessorId] = $config['oauth_link'];
      }
    }

    return $linked;
  }

  public function isWebhookAutoRegistrationEnabled(int $paymentProcessorId): bool {
    return $this->getWebhookOwnership($paymentProcessorId) === 'managed_by_civicrm';
  }

  public function setWebhookAutoRegistrationEnabled(int $paymentProcessorId, bool $enabled): void {
    $this->setWebhookOwnership($paymentProcessorId, $enabled ? 'managed_by_civicrm' : 'manual');
  }

  public function getWebhookRegistration(int $paymentProcessorId): ?array {
    $config = $this->get($paymentProcessorId);
    $registration = $config['webhook_registration'] ?? NULL;
    return is_array($registration) ? $registration : NULL;
  }

  public function storeWebhookRegistration(int $paymentProcessorId, array $registration): void {
    $config = $this->get($paymentProcessorId);
    $config['webhook_registration'] = $registration + [
      'updated_at' => date('Y-m-d H:i:s'),
    ];
    $config['updated_at'] = date('Y-m-d H:i:s');
    $this->set($paymentProcessorId, $config);
  }

  public function getWebhookOwnership(int $paymentProcessorId): string {
    $config = $this->get($paymentProcessorId);
    $ownership = (string) ($config['webhook_ownership'] ?? '');
    if (in_array($ownership, ['manual', 'managed_by_civicrm', 'managed_by_other_tool'], TRUE)) {
      return $ownership;
    }

    if (!empty($config['webhook_auto_register'])) {
      return 'managed_by_civicrm';
    }

    return 'managed_by_civicrm';
  }

  public function setWebhookOwnership(int $paymentProcessorId, string $ownership): void {
    if (!in_array($ownership, ['manual', 'managed_by_civicrm', 'managed_by_other_tool'], TRUE)) {
      throw new InvalidArgumentException(E::ts('Unsupported HelloAsso webhook ownership mode.'));
    }

    $config = $this->get($paymentProcessorId);
    $config['webhook_ownership'] = $ownership;
    unset($config['webhook_auto_register']);
    $config['updated_at'] = date('Y-m-d H:i:s');
    $this->set($paymentProcessorId, $config);
  }

  public function shouldUsePluginPublic(int $paymentProcessorId, array $paymentProcessor = []): bool {
    if ($this->hasClassicCredentials($paymentProcessor)) {
      return FALSE;
    }

    if ($this->getConnectionMode($paymentProcessorId, $paymentProcessor) !== 'plugin_public') {
      return FALSE;
    }

    // Safety check: block live public connections if CiviCRM domain mismatches the OAuth callback domain
    $link = $this->getStoredLink($paymentProcessorId);
    if (!empty($link['redirect_uri']) && empty($paymentProcessor['is_test'])) {
      $authorizedHost = parse_url($link['redirect_uri'], PHP_URL_HOST);
      $currentHost = $this->getCurrentHost();
      if ($authorizedHost && $currentHost && strcasecmp($authorizedHost, $currentHost) !== 0) {
        return FALSE;
      }
    }

    return (bool) $this->getLinkedOrganization($paymentProcessorId);
  }

  protected function getCurrentHost(): ?string {
    $host = parse_url(CRM_Utils_System::url(), PHP_URL_HOST);
    return is_string($host) && $host !== '' ? $host : NULL;
  }

  /**
   * @return array<string, array>
   */
  private function getAllFromTable(): array {
    $rows = CRM_Core_DAO::executeQuery('
      SELECT *
      FROM ' . self::TABLE_NAME . '
      ORDER BY payment_processor_id ASC
    ');

    $all = [];
    while ($rows->fetch()) {
      $all[(string) $rows->payment_processor_id] = $this->mapRowToConfig($rows);
    }

    return $all;
  }

  private function setInTable(int $paymentProcessorId, array $config): void {
    $existing = CRM_Core_DAO::singleValueQuery(
      'SELECT id FROM ' . self::TABLE_NAME . ' WHERE payment_processor_id = %1',
      [1 => [$paymentProcessorId, 'Integer']]
    );

    $link = is_array($config['oauth_link'] ?? NULL) ? $config['oauth_link'] : [];
    $registration = is_array($config['webhook_registration'] ?? NULL) ? $config['webhook_registration'] : [];
    $now = date('Y-m-d H:i:s');
    $updatedAt = (string) ($config['updated_at'] ?? $now);

    $params = [
      1 => [$paymentProcessorId, 'Integer'],
      2 => [$this->normalizeNullableString($config['connection_mode'] ?? NULL), 'String'],
      3 => [$this->normalizeNullableString($link['organization_slug'] ?? NULL), 'String'],
      4 => [$this->normalizeNullableString($link['access_token'] ?? NULL), 'String'],
      5 => [$this->normalizeNullableString($link['refresh_token'] ?? NULL), 'String'],
      6 => $this->buildNullableIntegerParam($link['expires_at'] ?? NULL),
      7 => $this->buildNullableIntegerParam($link['refresh_expires_at'] ?? NULL),
      8 => $this->buildNullableTimestampParam($link['linked_at'] ?? NULL),
      9 => [$this->normalizeNullableString($link['redirect_uri'] ?? NULL), 'String'],
      10 => [$this->normalizeNullableString($config['webhook_ownership'] ?? NULL), 'String'],
      11 => [$this->normalizeNullableString($registration['url'] ?? NULL), 'String'],
      12 => [$this->normalizeNullableString($registration['signatureKey'] ?? NULL), 'String'],
      13 => $this->buildNullableTimestampParam($registration['updated_at'] ?? NULL),
      14 => $this->buildNullableTimestampParam($updatedAt),
      15 => $this->buildNullableIntegerParam($link['refresh_issued_at'] ?? NULL),
      16 => [$this->normalizeNullableString($link['refresh_status'] ?? NULL), 'String'],
      17 => [$this->normalizeNullableString($link['last_refresh_error'] ?? NULL), 'String'],
      18 => $this->buildNullableTimestampParam($link['last_refresh_error_date'] ?? NULL),
      19 => $this->buildNullableIntegerParam($link['last_refresh_http_status'] ?? NULL),
    ];

    if ($existing) {
      $params[20] = [$paymentProcessorId, 'Integer'];
      CRM_Core_DAO::executeQuery(
        'UPDATE ' . self::TABLE_NAME . '
         SET connection_mode = %2,
             organization_slug = %3,
             access_token = %4,
             refresh_token = %5,
             expires_at = %6,
             refresh_issued_at = %15,
             refresh_expires_at = %7,
             refresh_status = %16,
             last_refresh_error = %17,
             last_refresh_error_date = %18,
             last_refresh_http_status = %19,
             linked_at = %8,
             redirect_uri = %9,
             webhook_ownership = %10,
             webhook_url = %11,
             webhook_signature_key = %12,
             webhook_updated_at = %13,
             updated_at = %14
         WHERE payment_processor_id = %20',
        $params
      );
      return;
    }

    CRM_Core_DAO::executeQuery(
      'INSERT INTO ' . self::TABLE_NAME . ' (
         payment_processor_id,
         connection_mode,
         organization_slug,
         access_token,
         refresh_token,
         expires_at,
         refresh_issued_at,
         refresh_expires_at,
         refresh_status,
         last_refresh_error,
         last_refresh_error_date,
         last_refresh_http_status,
         linked_at,
         redirect_uri,
         webhook_ownership,
         webhook_url,
         webhook_signature_key,
         webhook_updated_at,
         created_at,
         updated_at
       ) VALUES (
         %1, %2, %3, %4, %5, %6, %15, %7, %16, %17, %18, %19, %8, %9, %10, %11, %12, %13, %14, %14
       )',
      $params
    );
  }

  private function mapRowToConfig(CRM_Core_DAO $row): array {
    $config = [
      'connection_mode' => $row->connection_mode ?? NULL,
      'updated_at' => $row->updated_at ?? NULL,
      'webhook_ownership' => $row->webhook_ownership ?? NULL,
    ];

    if (!empty($row->organization_slug)) {
      $config['oauth_link'] = [
        'organization_slug' => $row->organization_slug,
        'access_token' => $row->access_token ?? NULL,
        'refresh_token' => $row->refresh_token ?? NULL,
        'expires_at' => $this->normalizeNullableInt($row->expires_at ?? NULL),
        'refresh_issued_at' => $this->normalizeNullableInt($row->refresh_issued_at ?? NULL),
        'refresh_expires_at' => $this->normalizeNullableInt($row->refresh_expires_at ?? NULL),
        'refresh_status' => $row->refresh_status ?? NULL,
        'last_refresh_error' => $row->last_refresh_error ?? NULL,
        'last_refresh_error_date' => $row->last_refresh_error_date ?? NULL,
        'last_refresh_http_status' => $this->normalizeNullableInt($row->last_refresh_http_status ?? NULL),
        'linked_at' => $row->linked_at ?? NULL,
        'redirect_uri' => $row->redirect_uri ?? NULL,
      ];
    }

    if (!empty($row->webhook_url) || !empty($row->webhook_signature_key)) {
      $config['webhook_registration'] = [
        'url' => $row->webhook_url ?? NULL,
        'signatureKey' => $row->webhook_signature_key ?? NULL,
        'updated_at' => $row->webhook_updated_at ?? NULL,
      ];
    }

    return $config;
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

  private function hasDedicatedTable(): bool {
    static $result;

    if ($result !== NULL) {
      return $result;
    }

    $dao = CRM_Core_DAO::executeQuery(
      "SHOW TABLES LIKE %1",
      [1 => [self::TABLE_NAME, 'String']]
    );
    $result = (bool) $dao->fetch();
    return $result;
  }

}
