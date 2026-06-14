<?php

class CRM_HelloassoPaymentProcessor_OAuthTokenLifetimeTest extends \PHPUnit\Framework\TestCase
{
    private const NOW = 1780912800;

    public function testAccessTokenIsUsableOutsideSafetyMargin(): void
    {
        $this->assertTrue(
            CRM_HelloassoPaymentProcessor_OAuthTokenLifetime::isAccessTokenUsable(
                'access-token',
                self::NOW + 31,
                self::NOW
            )
        );
    }

    public function testAccessTokenRemainsUsableAtExactMargin(): void
    {
        $this->assertTrue(
            CRM_HelloassoPaymentProcessor_OAuthTokenLifetime::isAccessTokenUsable(
                'access-token',
                self::NOW + 30,
                self::NOW
            )
        );
    }

    public function testAccessTokenExpiresInsideSafetyMargin(): void
    {
        $this->assertFalse(
            CRM_HelloassoPaymentProcessor_OAuthTokenLifetime::isAccessTokenUsable(
                'access-token',
                self::NOW + 29,
                self::NOW
            )
        );
        $this->assertFalse(
            CRM_HelloassoPaymentProcessor_OAuthTokenLifetime::isAccessTokenUsable(
                '',
                self::NOW + 3600,
                self::NOW
            )
        );
    }

    public function testRefreshTokenRotatesAtDayFifteen(): void
    {
        $issuedAt = self::NOW;
        $expiresAt = $issuedAt + 30 * 86400;

        $this->assertFalse(
            CRM_HelloassoPaymentProcessor_OAuthTokenLifetime::isRefreshTokenPastHalfLife(
                $issuedAt,
                $expiresAt,
                $issuedAt + 15 * 86400 - 1
            )
        );
        $this->assertTrue(
            CRM_HelloassoPaymentProcessor_OAuthTokenLifetime::isRefreshTokenPastHalfLife(
                $issuedAt,
                $expiresAt,
                $issuedAt + 15 * 86400
            )
        );
    }

    public function testLegacyRefreshTokenInfersThirtyDayWindow(): void
    {
        $expiresAt = self::NOW + 15 * 86400;

        $this->assertTrue(
            CRM_HelloassoPaymentProcessor_OAuthTokenLifetime::isRefreshTokenPastHalfLife(
                NULL,
                $expiresAt,
                self::NOW
            )
        );
    }

    public function testMissingRefreshExpiryForcesRotation(): void
    {
        $this->assertTrue(
            CRM_HelloassoPaymentProcessor_OAuthTokenLifetime::isRefreshTokenPastHalfLife(
                self::NOW,
                NULL,
                self::NOW
            )
        );
    }

    public function testRefreshWindowUsesJwtClaims(): void
    {
        $issuedAt = self::NOW - 100;
        $expiresAt = self::NOW + 500;
        $refreshToken = $this->jwt(['iat' => $issuedAt, 'exp' => $expiresAt]);

        $this->assertSame(
            [$issuedAt, $expiresAt],
            CRM_HelloassoPaymentProcessor_OAuthTokenLifetime::refreshWindow(
                ['refresh_token' => $refreshToken],
                self::NOW
            )
        );
    }

    public function testRefreshWindowUsesExplicitLifetime(): void
    {
        $this->assertSame(
            [self::NOW, self::NOW + 7200],
            CRM_HelloassoPaymentProcessor_OAuthTokenLifetime::refreshWindow([
                'refresh_token' => 'opaque-token',
                'refresh_token_expires_in' => 7200,
            ], self::NOW)
        );
    }

    public function testRefreshWindowFallsBackToThirtyDays(): void
    {
        $this->assertSame(
            [
                self::NOW,
                self::NOW + CRM_HelloassoPaymentProcessor_OAuthTokenLifetime::DEFAULT_REFRESH_TTL_SECONDS,
            ],
            CRM_HelloassoPaymentProcessor_OAuthTokenLifetime::refreshWindow([
                'refresh_token' => 'opaque-token',
            ], self::NOW)
        );
    }

    private function jwt(array $claims): string
    {
        return 'header.' . rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=') . '.signature';
    }
}
