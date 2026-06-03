<?php

use Civi\Payment\Exception\PaymentProcessorException;
use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

/**
 * Optional HelloAsso partner authorization-code flow.
 *
 * This is intentionally separate from the payment processor credentials. The
 * existing client_credentials flow remains the default payment path.
 */
class CRM_HelloassoPaymentProcessor_PartnerAuth {

  private const STATE_TTL = '10 minutes';
  private const REFRESH_TOKEN_TTL_SECONDS = 2592000;
  private const ACCESS_TOKEN_MARGIN = 30;
  private const PROD_AUTHORIZE_URL = 'https://auth.helloasso.com/authorize';
  private const PROD_TOKEN_URL = 'https://api.helloasso.com/oauth2/token';
  private const SANDBOX_AUTHORIZE_URL = 'https://auth.helloasso-sandbox.com/authorize';
  private const SANDBOX_TOKEN_URL = 'https://api.helloasso-sandbox.com/oauth2/token';
  private ?int $paymentProcessorId = NULL;
  private ?array $paymentProcessor = NULL;

  public function __construct(?int $paymentProcessorId = NULL) {
    $this->paymentProcessorId = $paymentProcessorId ? (int) $paymentProcessorId : NULL;
  }

  public function isEnabled(): bool {
    return (bool) Civi::settings()->get('helloasso_partner_auth_enabled');
  }

  public function buildAuthorizationUrl(): string {
    $this->assertConfigured();

    $state = bin2hex(random_bytes(24));
    $codeVerifier = $this->base64UrlEncode(random_bytes(64));
    $codeChallenge = $this->base64UrlEncode(hash('sha256', $codeVerifier, TRUE));
    $redirectUri = $this->getRedirectUri();

    Civi::cache('long')->set($this->getStateCacheKey($state), [
      'code_verifier' => $codeVerifier,
      'redirect_uri' => $redirectUri,
      'created_at' => time(),
      'payment_processor_id' => $this->paymentProcessorId,
    ], DateInterval::createFromDateString(self::STATE_TTL));

    return rtrim($this->getAuthorizeUrl(), '?') . '?' . http_build_query([
      'client_id' => $this->getClientId(),
      'redirect_uri' => $redirectUri,
      'code_challenge' => $codeChallenge,
      'code_challenge_method' => 'S256',
      'state' => $state,
    ]);
  }

  public function completeCallback(string $code, string $state): array {
    $this->assertConfigured();

    $stateData = Civi::cache('long')->get($this->getStateCacheKey($state));
    Civi::cache('long')->delete($this->getStateCacheKey($state));

    if (empty($stateData['code_verifier']) || empty($stateData['redirect_uri'])) {
      throw new PaymentProcessorException(E::ts('HelloAsso partner authorization state is invalid or expired.'));
    }

    if (!empty($stateData['payment_processor_id'])) {
      $this->paymentProcessorId = (int) $stateData['payment_processor_id'];
      $this->paymentProcessor = NULL;
    }

    $token = $this->requestToken([
      'grant_type' => 'authorization_code',
      'client_id' => $this->getClientId(),
      'client_secret' => $this->getClientSecret(),
      'code' => $code,
      'code_verifier' => $stateData['code_verifier'],
      'redirect_uri' => $stateData['redirect_uri'],
    ]);

    $link = $this->normalizeToken($token) + [
      'organization_slug' => $token['organization_slug'] ?? NULL,
      'linked_at' => date('Y-m-d H:i:s'),
      'redirect_uri' => $stateData['redirect_uri'],
      'payment_processor_id' => !empty($stateData['payment_processor_id']) ? (int) $stateData['payment_processor_id'] : NULL,
    ];

    if (empty($link['organization_slug'])) {
      throw new PaymentProcessorException(E::ts('HelloAsso partner authorization did not return an organization slug.'));
    }

    if (!empty($stateData['payment_processor_id'])) {
      $paymentProcessorId = (int) $stateData['payment_processor_id'];
      $this->getProcessorAuthConfig()->storeLink($paymentProcessorId, $link);
      $this->paymentProcessorId = $paymentProcessorId;
      $this->paymentProcessor = NULL;
      if ($this->isTestProcessor()) {
        $this->getProcessorAuthConfig()->clearClassicCredentials($paymentProcessorId);
      }
      if ($this->getProcessorAuthConfig()->isWebhookAutoRegistrationEnabled($paymentProcessorId)) {
        $webhookUrl = CRM_HelloassoPaymentProcessor_Webhook::getWebhookPath($paymentProcessorId);
        $webhookRegistration = $this->configureOrganizationWebhook($webhookUrl);
        $this->getProcessorAuthConfig()->storeWebhookRegistration($paymentProcessorId, [
          'url' => $webhookRegistration['url'] ?? $webhookUrl,
          'signatureKey' => $webhookRegistration['signatureKey'] ?? NULL,
        ]);
      }
    }
    else {
      throw new PaymentProcessorException(E::ts('HelloAsso partner authorization requires a payment processor.'));
    }

    return $link;
  }

  public function getLinkedOrganization(): ?array {
    $link = $this->getStoredLink();
    if (!$link) {
      return NULL;
    }

    return [
      'organization_slug' => $link['organization_slug'] ?? NULL,
      'linked_at' => $link['linked_at'] ?? NULL,
      'expires_at' => $link['expires_at'] ?? NULL,
      'refresh_expires_at' => $link['refresh_expires_at'] ?? NULL,
      'refresh_status' => $link['refresh_status'] ?? NULL,
      'last_refresh_error' => $link['last_refresh_error'] ?? NULL,
      'last_refresh_error_date' => $link['last_refresh_error_date'] ?? NULL,
      'last_refresh_http_status' => $link['last_refresh_http_status'] ?? NULL,
    ];
  }

  public function unlink(): void {
    if ($this->paymentProcessorId) {
      $this->getProcessorAuthConfig()->unlink($this->paymentProcessorId);
      return;
    }

    throw new PaymentProcessorException(E::ts('HelloAsso partner authorization requires a payment processor.'));
  }

  public function listOrganizationPayments(array $query = []): array {
    $link = $this->getUsableLink();
    return $this->request('GET', '/v5/organizations/' . rawurlencode($link['organization_slug']) . '/payments', [
      'query' => $query,
    ]);
  }

  public function getPayment(int $paymentId, array $query = []): array {
    return $this->request('GET', '/v5/payments/' . $paymentId, [
      'query' => $query,
    ]);
  }

  public function getCheckoutIntent(int $checkoutIntentId, array $query = []): array {
    $link = $this->getUsableLink();
    return $this->request('GET', '/v5/organizations/' . rawurlencode($link['organization_slug']) . '/checkout-intents/' . $checkoutIntentId, [
      'query' => $query,
    ]);
  }

  public function configureOrganizationWebhook(string $url): array {
    $link = $this->getUsableLink();
    return $this->request('PUT', '/v5/partners/me/api-notifications/organizations/' . rawurlencode($link['organization_slug']), [
      'json' => [
        'url' => $url,
      ],
    ]);
  }

  public function getPartnerInformation(): array {
    return $this->request('GET', '/v5/partners/me');
  }

  public function requestApi(string $method, string $path, array $options = []): array {
    return $this->request($method, $path, $options);
  }

  /**
   * Renew a linked authorization once its current refresh token reaches mid-life.
   *
   * @return bool
   *   TRUE when a token rotation was performed.
   */
  public function refreshLinkIfPastHalfLife(): bool {
    $link = $this->getStoredLink();
    if (!$link || empty($link['refresh_token'])) {
      return FALSE;
    }

    if (($link['refresh_status'] ?? '') === 'reconnect_required') {
      return FALSE;
    }

    if (!$this->isRefreshTokenPastHalfLife($link)) {
      return FALSE;
    }

    $this->refreshStoredLink(FALSE, TRUE);
    return TRUE;
  }

  public function getRedirectUri(): string {
    $query = 'reset=1';
    if ($this->paymentProcessorId) {
      $query .= '&processor_id=' . $this->paymentProcessorId;
    }

    return CRM_Utils_System::url('civicrm/helloasso/partner/callback', $query, TRUE, NULL, FALSE, TRUE);
  }

  public function isTestProcessor(): bool {
    $processor = $this->getPaymentProcessor();
    return !empty($processor['is_test']);
  }

  public function getEffectiveAuthorizeUrl(): string {
    return $this->getAuthorizeUrl();
  }

  public function getEffectiveTokenUrl(): string {
    return $this->getTokenUrl();
  }

  private function request(string $method, string $path, array $options = [], bool $retryOnUnauthorized = TRUE): array {
    $link = $this->getUsableLink();

    $requestOptions = $options + [
      'headers' => [],
      'http_errors' => FALSE,
      'curl' => [
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_SSL_VERIFYPEER => Civi::settings()->get('verifySSL'),
      ],
    ];
    $requestOptions['headers']['Authorization'] = 'Bearer ' . $link['access_token'];

    $response = $this->getGuzzleClient()->request($method, $this->getApiBaseUrl() . $path, $requestOptions);
    $statusCode = $response->getStatusCode();
    $body = (string) $response->getBody();
    $decoded = $body === '' ? [] : json_decode($body, TRUE);

    if ($statusCode === 401 && $retryOnUnauthorized) {
      $this->refreshStoredLink(TRUE);
      return $this->request($method, $path, $options, FALSE);
    }

    if ($statusCode < 200 || $statusCode >= 300) {
      $errorMessage = $this->buildApiErrorMessage($decoded, $statusCode);
      if ($this->isCheckoutInitializationRequest($method, $path) && $statusCode === 409) {
        $this->recordOrganizationPaymentBlock($errorMessage, $statusCode);
        throw new PaymentProcessorException($this->getOrganizationPaymentBlockedMessage());
      }

      throw new PaymentProcessorException($errorMessage);
    }

    if ($this->isCheckoutInitializationRequest($method, $path)) {
      $this->clearOrganizationPaymentBlock();
    }

    return is_array($decoded) ? $decoded : [];
  }

  private function getUsableLink(): array {
    $link = $this->getStoredLink();
    if (!$link) {
      throw new PaymentProcessorException(E::ts('HelloAsso partner authorization is not linked to an organization.'));
    }

    if (($link['refresh_status'] ?? '') === 'reconnect_required') {
      throw new PaymentProcessorException($this->getReconnectRequiredPaymentMessage());
    }

    if ((time() + self::ACCESS_TOKEN_MARGIN) > (int) ($link['expires_at'] ?? 0)) {
      $link = $this->refreshStoredLink();
    }

    return $link;
  }

  private function refreshStoredLink(bool $force = FALSE, bool $refreshAtHalfLife = FALSE): array {
    $initialLink = $this->getStoredLink();
    $initialRefreshToken = (string) ($initialLink['refresh_token'] ?? '');
    $lock = Civi::lockManager()->acquire($this->getRefreshLockName(), 10);
    if (!$lock->isAcquired()) {
      $link = $this->getStoredLink();
      if (!$refreshAtHalfLife && $this->isAccessTokenUsable($link)) {
        return $link;
      }
      throw new PaymentProcessorException(E::ts('HelloAsso partner authorization is currently being refreshed. Please retry the payment.'));
    }

    try {
      $link = $this->getStoredLink();
      if (!$link || empty($link['refresh_token'])) {
        throw new PaymentProcessorException(E::ts('HelloAsso partner authorization cannot be refreshed.'));
      }

      if ($initialRefreshToken !== '' && $initialRefreshToken !== (string) $link['refresh_token']) {
        return $link;
      }

      if ($refreshAtHalfLife) {
        if (!$this->isRefreshTokenPastHalfLife($link)) {
          return $link;
        }
      }
      elseif (!$force && $this->isAccessTokenUsable($link)) {
        return $link;
      }

      if (!$force && !empty($link['refresh_expires_at']) && time() > (int) $link['refresh_expires_at']) {
        $this->recordRefreshFailure(E::ts('HelloAsso partner authorization refresh token has expired.'), 0, TRUE);
        throw new PaymentProcessorException($this->getReconnectRequiredPaymentMessage());
      }

      $token = $this->requestToken([
        'grant_type' => 'refresh_token',
        'refresh_token' => $link['refresh_token'],
      ], TRUE);

      $refreshed = $this->normalizeToken($token) + [
        'organization_slug' => $token['organization_slug'] ?? $link['organization_slug'],
        'linked_at' => $link['linked_at'] ?? date('Y-m-d H:i:s'),
        'redirect_uri' => $link['redirect_uri'] ?? $this->getRedirectUri(),
      ];

      if ($this->paymentProcessorId) {
        $this->getProcessorAuthConfig()->storeLink($this->paymentProcessorId, $refreshed);
      }
      else {
        throw new PaymentProcessorException(E::ts('HelloAsso partner authorization requires a payment processor.'));
      }

      return $refreshed;
    }
    finally {
      $lock->release();
    }
  }

  private function requestToken(array $formParams, bool $isRefreshRequest = FALSE): array {
    $this->assertSslVerificationEnabled();

    $response = $this->getGuzzleClient()->request('POST', $this->getTokenUrl(), [
      'form_params' => $formParams,
      'headers' => [
        'content-type' => 'application/x-www-form-urlencoded',
      ],
      'curl' => [
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_SSL_VERIFYPEER => Civi::settings()->get('verifySSL'),
      ],
      'http_errors' => FALSE,
    ]);

    $statusCode = $response->getStatusCode();
    $body = (string) $response->getBody();
    $decoded = $body === '' ? [] : json_decode($body, TRUE);

    if ($statusCode < 200 || $statusCode >= 300 || !is_array($decoded)) {
      $errorMessage = $this->buildApiErrorMessage($decoded, $statusCode);
      if ($isRefreshRequest) {
        $this->recordRefreshFailure($errorMessage, $statusCode);
        if ($this->isReconnectRequiredHttpStatus($statusCode)) {
          throw new PaymentProcessorException($this->getReconnectRequiredPaymentMessage());
        }
      }

      throw new PaymentProcessorException($errorMessage);
    }

    return $decoded;
  }

  private function normalizeToken(array $token): array {
    if (empty($token['access_token']) || empty($token['refresh_token'])) {
      throw new PaymentProcessorException(E::ts('HelloAsso partner authorization response is missing OAuth tokens.'));
    }

    $now = time();
    [$refreshIssuedAt, $refreshExpiresAt] = $this->getRefreshTokenWindow($token, $now);
    return [
      'access_token' => $token['access_token'] ?? NULL,
      'refresh_token' => $token['refresh_token'] ?? NULL,
      'token_type' => $token['token_type'] ?? 'bearer',
      'expires_in' => (int) ($token['expires_in'] ?? 0),
      'expires_at' => $now + (int) ($token['expires_in'] ?? 0),
      'refresh_issued_at' => $refreshIssuedAt,
      'refresh_expires_at' => $refreshExpiresAt,
      'refresh_status' => 'active',
      'last_refresh_error' => NULL,
      'last_refresh_error_date' => NULL,
      'last_refresh_http_status' => NULL,
    ];
  }

  private function getStoredLink(): ?array {
    if ($this->paymentProcessorId) {
      return $this->getProcessorAuthConfig()->getStoredLink($this->paymentProcessorId);
    }

    return NULL;
  }

  private function recordRefreshFailure(string $message, int $statusCode, bool $reconnectRequired = FALSE): void {
    $link = $this->getStoredLink();
    if (!$link) {
      return;
    }

    $link['refresh_status'] = ($reconnectRequired || $this->isReconnectRequiredHttpStatus($statusCode)) ? 'reconnect_required' : 'refresh_failed';
    $link['last_refresh_error'] = $message;
    $link['last_refresh_error_date'] = date('Y-m-d H:i:s');
    $link['last_refresh_http_status'] = $statusCode;

    $this->storeLinkSafely($link, 'HelloAsso partner refresh failure could not be persisted');

    Civi::log()->error(sprintf(
      'HelloAsso partner token refresh failed for payment processor %s (HTTP %d): %s',
      $this->paymentProcessorId ?: 'legacy',
      $statusCode,
      $message
    ));
  }

  private function storeLinkSafely(array $link, string $failureMessage): void {
    try {
      if ($this->paymentProcessorId) {
        $this->getProcessorAuthConfig()->storeLink($this->paymentProcessorId, $link);
        return;
      }

    }
    catch (Exception $e) {
      Civi::log()->error($failureMessage . ': ' . $e->getMessage());
    }
  }

  private function isReconnectRequiredHttpStatus(int $statusCode): bool {
    return in_array($statusCode, [400, 401, 403, 404], TRUE);
  }

  private function getReconnectRequiredPaymentMessage(): string {
    return E::ts('HelloAsso payment is temporarily unavailable: HelloAsso connection must be restored by an administrator.');
  }

  private function getOrganizationPaymentBlockedMessage(): string {
    return E::ts('HelloAsso payment is temporarily unavailable: the linked organization is not currently allowed by HelloAsso to receive online payments.');
  }

  private function isCheckoutInitializationRequest(string $method, string $path): bool {
    return strtoupper($method) === 'POST'
      && (bool) preg_match('#^/v5/organizations/[^/]+/checkout-intents$#', $path);
  }

  private function recordOrganizationPaymentBlock(string $message, int $statusCode): void {
    $link = $this->getStoredLink();
    if (!$link) {
      return;
    }

    $link['refresh_status'] = 'organization_blocked';
    $link['last_refresh_error'] = $message;
    $link['last_refresh_error_date'] = date('Y-m-d H:i:s');
    $link['last_refresh_http_status'] = $statusCode;
    $this->storeLinkSafely($link, 'HelloAsso organization payment block could not be persisted');

    Civi::log()->error(sprintf(
      'HelloAsso organization cannot receive payments for payment processor %s (HTTP %d): %s',
      $this->paymentProcessorId ?: 'legacy',
      $statusCode,
      $message
    ));
  }

  private function clearOrganizationPaymentBlock(): void {
    $link = $this->getStoredLink();
    if (!$link || ($link['refresh_status'] ?? '') !== 'organization_blocked') {
      return;
    }

    $link['refresh_status'] = 'active';
    $link['last_refresh_error'] = NULL;
    $link['last_refresh_error_date'] = NULL;
    $link['last_refresh_http_status'] = NULL;
    $this->storeLinkSafely($link, 'HelloAsso organization payment block could not be cleared');
  }

  private function assertConfigured(): void {
    if (!$this->isEnabled()) {
      throw new PaymentProcessorException(E::ts('HelloAsso partner authorization is disabled.'));
    }
    if (!$this->getClientId() || !$this->getClientSecret()) {
      throw new PaymentProcessorException(E::ts('HelloAsso partner client ID and secret must be configured before connecting an organization.'));
    }
    $this->assertSslVerificationEnabled();
  }

  private function assertSslVerificationEnabled(): void {
    if (!Civi::settings()->get('verifySSL')) {
      throw new PaymentProcessorException(E::ts('HelloAsso partner authorization requires SSL verification to be enabled.'));
    }
  }

  private function getClientId(): string {
    return (string) ($this->getCredentialResolver()->resolve($this->isTestProcessor())['clientId'] ?? '');
  }

  private function getClientSecret(): string {
    return (string) ($this->getCredentialResolver()->resolve($this->isTestProcessor())['clientSecret'] ?? '');
  }

  public function getResolvedCredentials(): array {
    return $this->getCredentialResolver()->resolve($this->isTestProcessor());
  }

  private function getCredentialResolver(): CRM_HelloassoPaymentProcessor_PartnerCredentials {
    return new CRM_HelloassoPaymentProcessor_PartnerCredentials();
  }

  private function getAuthorizeUrl(): string {
    $configured = trim((string) Civi::settings()->get('helloasso_partner_authorize_url'));
    if ($configured === '') {
      return $this->isTestProcessor() ? self::SANDBOX_AUTHORIZE_URL : self::PROD_AUTHORIZE_URL;
    }

    if ($this->isTestProcessor() && $configured === self::PROD_AUTHORIZE_URL) {
      return self::SANDBOX_AUTHORIZE_URL;
    }

    return $configured;
  }

  private function getTokenUrl(): string {
    $configured = trim((string) Civi::settings()->get('helloasso_partner_token_url'));
    if ($configured === '') {
      return $this->isTestProcessor() ? self::SANDBOX_TOKEN_URL : self::PROD_TOKEN_URL;
    }

    if ($this->isTestProcessor() && $configured === self::PROD_TOKEN_URL) {
      return self::SANDBOX_TOKEN_URL;
    }

    return $configured;
  }

  private function getApiBaseUrl(): string {
    $tokenUrl = $this->getTokenUrl();
    if (strpos($tokenUrl, 'helloasso-sandbox') !== FALSE) {
      return 'https://api.helloasso-sandbox.com';
    }
    return 'https://api.helloasso.com';
  }

  private function getStateCacheKey(string $state): string {
    return 'helloasso_partner_auth_state_' . $state;
  }

  private function getRefreshLockName(): string {
    return 'data.helloasso.partner.refresh.' . ($this->paymentProcessorId ?: 'legacy');
  }

  private function isAccessTokenUsable(?array $link): bool {
    return !empty($link['access_token'])
      && ((time() + self::ACCESS_TOKEN_MARGIN) <= (int) ($link['expires_at'] ?? 0));
  }

  private function isRefreshTokenPastHalfLife(array $link): bool {
    $expiresAt = (int) ($link['refresh_expires_at'] ?? 0);
    if (!$expiresAt) {
      return TRUE;
    }

    $issuedAt = (int) ($link['refresh_issued_at'] ?? 0);
    if (!$issuedAt) {
      // Existing stored links predate refresh_issued_at and used the documented TTL.
      $issuedAt = max(0, $expiresAt - self::REFRESH_TOKEN_TTL_SECONDS);
    }

    $midpoint = $issuedAt + (int) floor(($expiresAt - $issuedAt) / 2);
    return time() >= $midpoint;
  }

  private function getRefreshTokenWindow(array $token, int $fallbackIssuedAt): array {
    $claims = $this->decodeJwtClaims((string) ($token['refresh_token'] ?? ''));
    $issuedAt = !empty($claims['iat']) ? (int) $claims['iat'] : $fallbackIssuedAt;
    if (!empty($claims['exp']) && (int) $claims['exp'] > $issuedAt) {
      return [$issuedAt, (int) $claims['exp']];
    }

    foreach (['refresh_token_expires_in', 'refresh_expires_in'] as $field) {
      if (!empty($token[$field]) && (int) $token[$field] > 0) {
        return [$issuedAt, $issuedAt + (int) $token[$field]];
      }
    }

    // HelloAsso currently documents the refresh token lifetime but does not expose it in its published response schema.
    return [$issuedAt, $issuedAt + self::REFRESH_TOKEN_TTL_SECONDS];
  }

  private function decodeJwtClaims(string $token): array {
    $parts = explode('.', $token);
    if (count($parts) < 2) {
      return [];
    }

    $payload = strtr($parts[1], '-_', '+/');
    $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
    $decoded = base64_decode($payload, TRUE);
    if (!is_string($decoded)) {
      return [];
    }

    $claims = json_decode($decoded, TRUE);
    return is_array($claims) ? $claims : [];
  }

  private function getProcessorAuthConfig(): CRM_HelloassoPaymentProcessor_ProcessorAuthConfig {
    return new CRM_HelloassoPaymentProcessor_ProcessorAuthConfig();
  }

  private function getPaymentProcessor(): array {
    if ($this->paymentProcessor !== NULL) {
      return $this->paymentProcessor;
    }

    if (!$this->paymentProcessorId) {
      $this->paymentProcessor = [];
      return $this->paymentProcessor;
    }

    try {
      $this->paymentProcessor = civicrm_api3('PaymentProcessor', 'getsingle', ['id' => $this->paymentProcessorId]);
    }
    catch (Exception $e) {
      $this->paymentProcessor = [];
    }

    return $this->paymentProcessor;
  }

  private function getGuzzleClient(): \GuzzleHttp\Client {
    return new \GuzzleHttp\Client();
  }

  private function base64UrlEncode(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
  }

  private function buildApiErrorMessage($decoded, int $statusCode): string {
    if (is_array($decoded) && !empty($decoded['errors']) && is_array($decoded['errors'])) {
      $messages = [];
      foreach ($decoded['errors'] as $error) {
        if (is_array($error) && !empty($error['message'])) {
          $messages[] = $error['message'];
        }
        elseif (is_string($error)) {
          $messages[] = $error;
        }
        elseif (is_array($error)) {
          $messages[] = implode(', ', array_filter($error, 'is_scalar'));
        }
      }

      if ($messages) {
        return implode('; ', $messages);
      }
    }

    if (is_array($decoded) && !empty($decoded['message'])) {
      return (string) $decoded['message'];
    }

    return E::ts('HelloAsso API error (%1)', [1 => $statusCode]);
  }

}
