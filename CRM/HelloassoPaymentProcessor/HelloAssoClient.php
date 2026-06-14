<?php

use Civi\Payment\Exception\PaymentProcessorException;
use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

class CRM_HelloassoPaymentProcessor_HelloAssoClient
{

    // Refresh token will be valid only for 30 days.
    // https://dev.helloasso.com/docs/getting-started
    private const REFRESH_TOKEN_EXP = '30 days';
    private static ?self $instance = NULL;

    /**
     * @var GuzzleHttp\Client
     */
    protected $guzzleClient;

    private function __construct()
    {
    }


    /**
     * @return \GuzzleHttp\Client
     */
    public function getGuzzleClient(): \GuzzleHttp\Client
    {
        return $this->guzzleClient ?? new \GuzzleHttp\Client();
    }

    /**
     * @param \GuzzleHttp\Client $guzzleClient
     */
    public function setGuzzleClient(\GuzzleHttp\Client $guzzleClient): void
    {
        $this->guzzleClient = $guzzleClient;
    }

    public static function getInstance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getToken(
        bool $is_test,
        array $paymentProcessor,
        string $oauthUrl,
        string $clientId,
        string $clientSecret,
        string $requestProfile = CRM_HelloassoPaymentProcessor_RequestOptions::PROFILE_DEFAULT
    ): object
    {
        if (Civi::cache('long')->has($this->getCacheKey($is_test, $paymentProcessor))
            && !$this->isAccessTokenExpired($is_test, $paymentProcessor)) {
            return Civi::cache('long')->get($this->getCacheKey($is_test, $paymentProcessor));
        }

        $lock = Civi::lockManager()->acquire($this->getTokenLockName($is_test, $paymentProcessor), 10);
        if (!$lock->isAcquired()) {
            if (Civi::cache('long')->has($this->getCacheKey($is_test, $paymentProcessor))
                && !$this->isAccessTokenExpired($is_test, $paymentProcessor)) {
                return Civi::cache('long')->get($this->getCacheKey($is_test, $paymentProcessor));
            }
            throw new PaymentProcessorException(E::ts('HelloAsso authentication is currently being refreshed. Please retry the payment.'));
        }

        try {
            if (!Civi::cache('long')->has($this->getCacheKey($is_test, $paymentProcessor))) {
                $this->accessToken($is_test, $paymentProcessor, $oauthUrl, $clientId, $clientSecret, $requestProfile);
            }
            elseif ($this->isAccessTokenExpired($is_test, $paymentProcessor)) {
                $cachedToken = Civi::cache('long')->get($this->getCacheKey($is_test, $paymentProcessor));
                if (!empty($cachedToken->refresh_token)) {
                    $this->refreshToken($is_test, $paymentProcessor, $oauthUrl, $requestProfile);
                }
                else {
                    $this->accessToken($is_test, $paymentProcessor, $oauthUrl, $clientId, $clientSecret, $requestProfile);
                }
            }
        }
        finally {
            $lock->release();
        }

        return Civi::cache('long')->get($this->getCacheKey($is_test, $paymentProcessor));
    }

    public function invalidateToken(bool $is_test, array $paymentProcessor): void
    {
        Civi::cache('long')->delete($this->getCacheKey($is_test, $paymentProcessor));
    }

    private function isAccessTokenExpired(bool $is_test, array $paymentProcessor): bool
    {
        $token = Civi::cache('long')->get($this->getCacheKey($is_test, $paymentProcessor));
        if (empty($token) || empty($token->not_after)) {
            return TRUE;
        }

        return !CRM_HelloassoPaymentProcessor_OAuthTokenLifetime::isAccessTokenUsable(
            (string) ($token->access_token ?? ''),
            isset($token->not_after) ? (int) $token->not_after : NULL,
            time()
        );
    }

    private function accessToken(
        bool $is_test,
        array $paymentProcessor,
        string $oauthUrl,
        string $clientId,
        string $clientSecret,
        string $requestProfile = CRM_HelloassoPaymentProcessor_RequestOptions::PROFILE_DEFAULT
    ): void
    {
        $this->assertSslVerificationEnabled($is_test);

        $oauth_response = $this->getGuzzleClient()->request('POST', $oauthUrl, CRM_HelloassoPaymentProcessor_RequestOptions::defaults([
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret
            ],
        ], $requestProfile));

        if ($oauth_response->getStatusCode() != 200) {
            Civi::cache('long')->delete($this->getCacheKey($is_test, $paymentProcessor));
            throw new PaymentProcessorException(E::ts('HelloAsso: unable to authenticate with the payment processor API keys.'));
        }
        $token = json_decode($oauth_response->getBody());
        $token->not_after = time() + ($token->expires_in ?? 0);
        Civi::cache('long')->set($this->getCacheKey($is_test, $paymentProcessor), $token, DateInterval::createFromDateString(self::REFRESH_TOKEN_EXP));
    }

    private function refreshToken(
        bool $is_test,
        array $paymentProcessor,
        string $oauthUrl,
        string $requestProfile = CRM_HelloassoPaymentProcessor_RequestOptions::PROFILE_DEFAULT
    ): void
    {
        $this->assertSslVerificationEnabled($is_test);

        $oauth_response = $this->getGuzzleClient()->request('POST', $oauthUrl, CRM_HelloassoPaymentProcessor_RequestOptions::defaults([
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => Civi::cache('long')->get($this->getCacheKey($is_test, $paymentProcessor))->refresh_token
            ],
        ], $requestProfile));

        if ($oauth_response->getStatusCode() != 200) {
            Civi::cache('long')->delete($this->getCacheKey($is_test, $paymentProcessor));
            throw new PaymentProcessorException(E::ts('HelloAsso: unable to authenticate with the payment processor API keys.'));
        }
        $token = json_decode($oauth_response->getBody());
        $token->not_after = time() + ($token->expires_in ?? 0);
        Civi::cache('long')->set($this->getCacheKey($is_test, $paymentProcessor), $token, DateInterval::createFromDateString(self::REFRESH_TOKEN_EXP));
    }

    public function createCheckoutIntent(
        array $paymentProcessor,
        bool $isTest,
        array $request,
        string $requestProfile = CRM_HelloassoPaymentProcessor_RequestOptions::PROFILE_DEFAULT
    ): array
    {
        return $this->requestHelloAsso(
            $paymentProcessor,
            $isTest,
            'POST',
            '/v5/organizations/' . $this->getOrganizationSlug($paymentProcessor, $isTest) . '/checkout-intents',
            ['json' => $request],
            TRUE,
            $requestProfile
        );
    }

    public function getCheckoutIntent(
        array $paymentProcessor,
        bool $isTest,
        int $checkoutIntentId,
        array $query = [],
        string $requestProfile = CRM_HelloassoPaymentProcessor_RequestOptions::PROFILE_DEFAULT
    ): array
    {
        return $this->requestHelloAsso(
            $paymentProcessor,
            $isTest,
            'GET',
            '/v5/organizations/' . $this->getOrganizationSlug($paymentProcessor, $isTest) . '/checkout-intents/' . $checkoutIntentId,
            ['query' => $query],
            TRUE,
            $requestProfile
        );
    }

    public function getPayment(
        array $paymentProcessor,
        bool $isTest,
        int $paymentId,
        array $query = [],
        string $requestProfile = CRM_HelloassoPaymentProcessor_RequestOptions::PROFILE_DEFAULT
    ): array
    {
        return $this->requestHelloAsso(
            $paymentProcessor,
            $isTest,
            'GET',
            '/v5/payments/' . $paymentId,
            ['query' => $query],
            TRUE,
            $requestProfile
        );
    }

    public function listOrganizationPayments(
        array $paymentProcessor,
        bool $isTest,
        array $query = [],
        string $requestProfile = CRM_HelloassoPaymentProcessor_RequestOptions::PROFILE_DEFAULT
    ): array
    {
        return $this->requestHelloAsso(
            $paymentProcessor,
            $isTest,
            'GET',
            '/v5/organizations/' . $this->getOrganizationSlug($paymentProcessor, $isTest) . '/payments',
            ['query' => $query],
            TRUE,
            $requestProfile
        );
    }

    public function refundPayment(
        array $paymentProcessor,
        bool $isTest,
        int $paymentId,
        string $requestProfile = CRM_HelloassoPaymentProcessor_RequestOptions::PROFILE_DEFAULT
    ): array
    {
        if (!(bool) Civi::settings()->get('helloasso_enable_refunds')) {
            throw new PaymentProcessorException(E::ts('HelloAsso refunds are disabled by this extension.'));
        }

        return $this->requestHelloAsso(
            $paymentProcessor,
            $isTest,
            'POST',
            '/v5/payments/' . $paymentId . '/refund',
            [],
            TRUE,
            $requestProfile
        );
    }

    public function cancelOrder(
        array $paymentProcessor,
        bool $isTest,
        int $orderId,
        string $requestProfile = CRM_HelloassoPaymentProcessor_RequestOptions::PROFILE_DEFAULT
    ): array
    {
        $paymentProcessorId = (int) ($paymentProcessor['id'] ?? 0);
        $authConfig = new CRM_HelloassoPaymentProcessor_ProcessorAuthConfig();
        if (
            !$paymentProcessorId
            || !$authConfig->shouldUsePluginPublic($paymentProcessorId, $paymentProcessor)
        ) {
            throw new PaymentProcessorException(E::ts('HelloAsso installment cancellation requires an authorization-screen connection.'));
        }

        $partnerInformation = (new CRM_HelloassoPaymentProcessor_PartnerAuth($paymentProcessorId))
            ->getPartnerInformation();
        $apiClient = is_array($partnerInformation['apiClient'] ?? NULL)
            ? $partnerInformation['apiClient']
            : [];
        $privileges = is_array($apiClient['privileges'] ?? NULL)
            ? $apiClient['privileges']
            : [];
        if (!in_array('RefundManagement', $privileges, TRUE)) {
            throw new PaymentProcessorException(E::ts('The HelloAsso authorization does not include the RefundManagement privilege required to cancel future installments.'));
        }

        return $this->requestHelloAsso(
            $paymentProcessor,
            $isTest,
            'POST',
            '/v5/orders/' . $orderId . '/cancel',
            [],
            TRUE,
            $requestProfile
        );
    }

    private function requestHelloAsso(
        array $paymentProcessor,
        bool $is_test,
        string $method,
        string $path,
        array $options = [],
        bool $retryOnUnauthorized = TRUE,
        string $requestProfile = CRM_HelloassoPaymentProcessor_RequestOptions::PROFILE_DEFAULT
    ): array
    {
        $this->assertSslVerificationEnabled($is_test);

        if ($this->shouldUsePluginPublic($paymentProcessor, $is_test)) {
            return $this->requestHelloAssoViaPluginPublic($paymentProcessor, $method, $path, $options, $requestProfile);
        }

        $baseUrl = rtrim($paymentProcessor['url_site'], '/');
        $oauthUrl = $baseUrl . '/oauth2/token';
        $token = $this->getToken(
            $is_test,
            $paymentProcessor,
            $oauthUrl,
            (string) ($paymentProcessor['user_name'] ?? ''),
            (string) ($paymentProcessor['password'] ?? ''),
            $requestProfile
        );

        $requestOptions = CRM_HelloassoPaymentProcessor_RequestOptions::defaults($options + [
            'headers' => [],
        ], $requestProfile);
        $requestOptions['headers']['Authorization'] = 'Bearer ' . $token->access_token;

        $response = $this->getGuzzleClient()->request($method, $baseUrl . $path, $requestOptions);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        $decoded = $body === '' ? [] : json_decode($body, TRUE);

        if ($statusCode === 401 && $retryOnUnauthorized) {
            $this->invalidateToken($is_test, $paymentProcessor);
            return $this->requestHelloAsso($paymentProcessor, $is_test, $method, $path, $options, FALSE, $requestProfile);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            if (
                $statusCode === 403
                && $method === 'POST'
                && preg_match('#^/v5/orders/\d+/cancel$#', $path)
            ) {
                throw new PaymentProcessorException(E::ts(
                    'HelloAsso refused the cancellation. Reconnect the organization through the authorization screen and grant the OrganizationAdmin or FormAdmin role; the client must also include the RefundManagement privilege.'
                ));
            }
            $this->throwApiError($paymentProcessor, $method, $path, $statusCode, $decoded);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function throwApiError(
        array $paymentProcessor,
        string $method,
        string $path,
        int $statusCode,
        mixed $decoded
    ): void {
        $errorMessage = $this->buildApiErrorMessage($decoded, $statusCode);
        if (CRM_HelloassoPaymentProcessor_ApiErrorClassifier::isOrganizationPaymentBlocked(
            $method,
            $path,
            $statusCode
        )) {
            Civi::log()->error(sprintf(
                'HelloAsso organization cannot receive payments for payment processor %s (HTTP %d): %s',
                $paymentProcessor['id'] ?? 'unknown',
                $statusCode,
                $errorMessage
            ));
            throw new PaymentProcessorException($this->getOrganizationPaymentBlockedMessage());
        }

        throw new PaymentProcessorException($errorMessage);
    }

    private function getOrganizationPaymentBlockedMessage(): string
    {
        return E::ts('HelloAsso payment is temporarily unavailable: the linked organization is not currently allowed by HelloAsso to receive online payments.');
    }

    private function requestHelloAssoViaPluginPublic(
        array $paymentProcessor,
        string $method,
        string $path,
        array $options = [],
        string $requestProfile = CRM_HelloassoPaymentProcessor_RequestOptions::PROFILE_DEFAULT
    ): array
    {
        $paymentProcessorId = (int) ($paymentProcessor['id'] ?? 0);
        if (!$paymentProcessorId) {
            throw new PaymentProcessorException(E::ts('HelloAsso plugin-public mode requires a saved payment processor ID.'));
        }

        return (new CRM_HelloassoPaymentProcessor_PartnerAuth($paymentProcessorId))
            ->requestApi($method, $path, $options, $requestProfile);
    }

    private function buildApiErrorMessage(mixed $decoded, int $statusCode): string
    {
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

    private function assertSslVerificationEnabled(bool $is_test): void
    {
        if (!$is_test && !Civi::settings()->get('verifySSL')) {
            throw new PaymentProcessorException(E::ts('HelloAsso live API calls require SSL verification to be enabled.'));
        }
    }

    private function getCacheKey(bool $is_test, array $paymentProcessor): string
    {
        $processorIdentifier = $paymentProcessor['id']
            ?? sha1(implode('|', [
                (string) ($paymentProcessor['url_site'] ?? ''),
                (string) ($paymentProcessor['user_name'] ?? ''),
                (string) ($paymentProcessor['subject'] ?? ''),
            ]));

        return 'helloasso-token-' . $processorIdentifier . ($is_test ? '-test' : '-live');
    }

    private function getTokenLockName(bool $is_test, array $paymentProcessor): string
    {
        return 'data.helloasso.api.refresh.' . sha1($this->getCacheKey($is_test, $paymentProcessor));
    }

    private function shouldUsePluginPublic(array $paymentProcessor, bool $is_test): bool
    {
        $paymentProcessorId = (int) ($paymentProcessor['id'] ?? 0);
        if (!$paymentProcessorId) {
            return FALSE;
        }

        return (new CRM_HelloassoPaymentProcessor_ProcessorAuthConfig())->shouldUsePluginPublic($paymentProcessorId, $paymentProcessor);
    }

    private function getOrganizationSlug(array $paymentProcessor, bool $is_test): string
    {
        if ($this->shouldUsePluginPublic($paymentProcessor, $is_test)) {
            $paymentProcessorId = (int) ($paymentProcessor['id'] ?? 0);
            $linked = (new CRM_HelloassoPaymentProcessor_ProcessorAuthConfig())->getLinkedOrganization($paymentProcessorId);
            if (!empty($linked['organization_slug'])) {
                return (string) $linked['organization_slug'];
            }
        }

        return (string) $paymentProcessor['subject'];
    }
}
