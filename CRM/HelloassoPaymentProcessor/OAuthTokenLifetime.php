<?php

/**
 * Pure OAuth lifetime calculations shared by both HelloAsso auth flows.
 */
class CRM_HelloassoPaymentProcessor_OAuthTokenLifetime
{
    public const DEFAULT_REFRESH_TTL_SECONDS = 2592000;
    public const ACCESS_MARGIN_SECONDS = 30;

    public static function isAccessTokenUsable(
        ?string $accessToken,
        ?int $expiresAt,
        int $now,
        int $margin = self::ACCESS_MARGIN_SECONDS
    ): bool {
        return $accessToken !== NULL
            && $accessToken !== ''
            && $expiresAt !== NULL
            && ($now + $margin) <= $expiresAt;
    }

    public static function isRefreshTokenPastHalfLife(
        ?int $issuedAt,
        ?int $expiresAt,
        int $now,
        int $defaultTtl = self::DEFAULT_REFRESH_TTL_SECONDS
    ): bool {
        if (!$expiresAt) {
            return TRUE;
        }

        if (!$issuedAt) {
            $issuedAt = max(0, $expiresAt - $defaultTtl);
        }

        $midpoint = $issuedAt + (int) floor(($expiresAt - $issuedAt) / 2);
        return $now >= $midpoint;
    }

    /**
     * @return array{0:int,1:int}
     */
    public static function refreshWindow(array $token, int $fallbackIssuedAt): array
    {
        $claims = self::decodeJwtClaims((string) ($token['refresh_token'] ?? ''));
        $issuedAt = !empty($claims['iat']) ? (int) $claims['iat'] : $fallbackIssuedAt;
        if (!empty($claims['exp']) && (int) $claims['exp'] > $issuedAt) {
            return [$issuedAt, (int) $claims['exp']];
        }

        foreach (['refresh_token_expires_in', 'refresh_expires_in'] as $field) {
            if (!empty($token[$field]) && (int) $token[$field] > 0) {
                return [$issuedAt, $issuedAt + (int) $token[$field]];
            }
        }

        return [$issuedAt, $issuedAt + self::DEFAULT_REFRESH_TTL_SECONDS];
    }

    private static function decodeJwtClaims(string $token): array
    {
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
}
