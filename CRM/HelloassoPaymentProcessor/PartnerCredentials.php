<?php

/**
 * Resolve HelloAsso authorization-screen client credentials.
 *
 * Local CiviCRM settings always win when they look like administrator-owned
 * credentials. Community credentials supplied by release/env are used only when
 * local settings are empty or when the local pair is recognized as one of the
 * community-owned pairs listed for rotation.
 */
class CRM_HelloassoPaymentProcessor_PartnerCredentials {

  private const ENV_JSON = 'HELLOASSO_PARTNER_COMMUNITY_CREDENTIALS_JSON';
  private const ENV_FILE = 'HELLOASSO_PARTNER_COMMUNITY_CREDENTIALS_FILE';
  private const ENV_FINGERPRINTS_JSON = 'HELLOASSO_PARTNER_COMMUNITY_CREDENTIAL_FINGERPRINTS_JSON';
  private const ENV_FINGERPRINTS = 'HELLOASSO_PARTNER_COMMUNITY_CREDENTIAL_FINGERPRINTS';

  /**
   * Fingerprints (SHA-256) of known community credentials (current and historical).
   * This allows CiviCRM to recognize when community-owned credentials are used
   * in order to avoid overwriting or misidentifying partner-owned local overrides,
   * without having to expose the secret keys in plain text in the public Git history.
   */
  private const COMMUNITY_FINGERPRINTS = [
    'live' => [
      'e546a1343456a691e010a7bc1f7b2b996252a639cd5c717f495fcb58ea91fe20', // v2.0.13 community live key
    ],
    'sandbox' => [
      '538fa2d226ee68bf0c48275e2cfd988a10fc529dc8efc3079b2ecdd29130ff20', // v2.0.21 community sandbox key (correct)
      '14f495022fd510aa124be68fde882de477189c4fd1c85cba8a47fc7aa434ec97', // v2.0.13 community sandbox key (legacy typo)
    ],
  ];

  public function resolve(bool $isTest): array {
    $mode = $isTest ? 'sandbox' : 'live';
    $local = $this->getLocalCredentials($isTest);
    $community = $this->getCommunityCredentials($mode);

    if ($this->hasCompletePair($local)) {
      if (!$this->isCommunityOwnedPair($mode, $local)) {
        return $local + [
          'source' => 'local',
          'is_community' => FALSE,
        ];
      }

      if (!$this->hasCompletePair($community)) {
        return $local + [
          'source' => 'community_retained',
          'is_community' => TRUE,
        ];
      }
    }

    if ($this->hasCompletePair($community)) {
      return $community + [
        'source' => $this->hasAnyValue($local) ? 'community_repair' : 'community',
        'is_community' => TRUE,
      ];
    }

    return $local + [
      'source' => $this->hasAnyValue($local) ? 'local_incomplete' : 'empty',
      'is_community' => FALSE,
    ];
  }

  public function hasCredentials(bool $isTest): bool {
    return $this->hasCompletePair($this->resolve($isTest));
  }

  public function getLocalCredentials(bool $isTest): array {
    if ($isTest) {
      return [
        'clientId' => trim((string) Civi::settings()->get('helloasso_partner_client_id_test')),
        'clientSecret' => trim((string) Civi::settings()->get('helloasso_partner_client_secret_test')),
      ];
    }

    return [
      'clientId' => trim((string) Civi::settings()->get('helloasso_partner_client_id_live')),
      'clientSecret' => trim((string) Civi::settings()->get('helloasso_partner_client_secret_live')),
    ];
  }

  public function getCommunityCredentials(string $mode): array {
    $all = $this->getCommunityCredentialsConfig();
    $credentials = is_array($all[$mode] ?? NULL) ? $all[$mode] : [];
    return [
      'clientId' => trim((string) ($credentials['clientId'] ?? $credentials['client_id'] ?? '')),
      'clientSecret' => trim((string) ($credentials['clientSecret'] ?? $credentials['client_secret'] ?? '')),
    ];
  }

  public function isCommunityOwnedPair(string $mode, array $credentials): bool {
    if (!$this->hasCompletePair($credentials)) {
      return FALSE;
    }

    return in_array($this->fingerprint($credentials), $this->getCommunityFingerprints($mode), TRUE);
  }

  public function fingerprint(array $credentials): string {
    return hash('sha256', trim((string) ($credentials['clientId'] ?? '')) . "\n" . trim((string) ($credentials['clientSecret'] ?? '')));
  }

  private function getCommunityFingerprints(string $mode): array {
    $fingerprints = [];
    $community = $this->getCommunityCredentials($mode);

    // Only compute fingerprint of community credentials if placeholders have been replaced
    if ($this->hasCompletePair($community) && strpos($community['clientId'], '%%') === FALSE) {
      $fingerprints[] = $this->fingerprint($community);
    }

    // Add statically defined community fingerprints (current + historical)
    if (isset(self::COMMUNITY_FINGERPRINTS[$mode])) {
      foreach (self::COMMUNITY_FINGERPRINTS[$mode] as $fp) {
        $fingerprints[] = $fp;
      }
    }

    $configured = $this->getConfiguredFingerprints($mode);
    foreach ($configured as $fingerprint) {
      $fingerprint = strtolower(trim($fingerprint));
      if (preg_match('/^[a-f0-9]{64}$/', $fingerprint)) {
        $fingerprints[] = $fingerprint;
      }
    }

    return array_values(array_unique($fingerprints));
  }

  private function getConfiguredFingerprints(string $mode): array {
    $json = trim((string) getenv(self::ENV_FINGERPRINTS_JSON));
    if ($json !== '') {
      $decoded = json_decode($json, TRUE);
      if (is_array($decoded)) {
        if (isset($decoded[$mode]) && is_array($decoded[$mode])) {
          return $decoded[$mode];
        }
        if (isset($decoded['all']) && is_array($decoded['all'])) {
          return $decoded['all'];
        }
      }
    }

    $raw = trim((string) getenv(self::ENV_FINGERPRINTS));
    if ($raw === '') {
      return [];
    }

    return preg_split('/[\s,;]+/', $raw) ?: [];
  }

  private function getCommunityCredentialsConfig(): array {
    $json = trim((string) getenv(self::ENV_JSON));
    if ($json === '') {
      $file = trim((string) getenv(self::ENV_FILE));
      if ($file !== '' && is_readable($file)) {
        $json = (string) file_get_contents($file);
      }
    }

    if ($json === '') {
      return [
        'live' => [
          'clientId' => '%%HELLOASSO_LIVE_CLIENT_ID%%',
          'clientSecret' => '%%HELLOASSO_LIVE_CLIENT_SECRET%%',
        ],
        'sandbox' => [
          'clientId' => '%%HELLOASSO_SANDBOX_CLIENT_ID%%',
          'clientSecret' => '%%HELLOASSO_SANDBOX_CLIENT_SECRET%%',
        ],
      ];
    }

    $decoded = json_decode($json, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  private function hasCompletePair(array $credentials): bool {
    return trim((string) ($credentials['clientId'] ?? '')) !== ''
      && trim((string) ($credentials['clientSecret'] ?? '')) !== '';
  }

  private function hasAnyValue(array $credentials): bool {
    return trim((string) ($credentials['clientId'] ?? '')) !== ''
      || trim((string) ($credentials['clientSecret'] ?? '')) !== '';
  }

}
