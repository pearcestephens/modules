<?php
/**
 * Filename: XeroTokenStore.php
 * Purpose : Persist and retrieve Xero OAuth tokens with graceful refresh support.
 * Author  : GitHub Copilot
 * Last Modified: 2025-10-31
 * Dependencies: PDO connection supplied by caller
 */
declare(strict_types=1);

use PDO;
use PDOException;

final class XeroTokenStore
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Fetch the current Xero access token.
     */
    public function getAccessToken(): ?string
    {
        $statement = $this->db->query("SELECT access_token FROM oauth_tokens WHERE provider = 'xero' LIMIT 1");
        $record = $statement ? $statement->fetch(PDO::FETCH_ASSOC) : null;

        return $record['access_token'] ?? getenv('XERO_ACCESS_TOKEN') ?: null;
    }

    /**
     * Fetch the current Xero refresh token.
     */
    public function getRefreshToken(): ?string
    {
        $statement = $this->db->query("SELECT refresh_token FROM oauth_tokens WHERE provider = 'xero' LIMIT 1");
        $record = $statement ? $statement->fetch(PDO::FETCH_ASSOC) : null;

        return $record['refresh_token'] ?? getenv('XERO_REFRESH_TOKEN') ?: null;
    }

    /**
     * Persist token values.
     */
    public function saveTokens(string $accessToken, string $refreshToken, int $expiresAt): void
    {
        $query = $this->db->prepare(
            "INSERT INTO oauth_tokens (provider, access_token, refresh_token, expires_at)
             VALUES ('xero', ?, ?, FROM_UNIXTIME(?))
             ON DUPLICATE KEY UPDATE access_token = VALUES(access_token), refresh_token = VALUES(refresh_token), expires_at = VALUES(expires_at)"
        );
        $query->execute([$accessToken, $refreshToken, $expiresAt]);
    }

    /**
     * Obtain a valid access token, refreshing as needed.
     *
     * @param callable $refreshCallback Invoked when a refresh is required. Should return [access, refresh, expiryTs].
     */
    public function refreshIfNeeded(callable $refreshCallback): string
    {
        $accessToken = $this->getAccessToken();
        if ($accessToken && !$this->isExpiringSoon()) {
            return $accessToken;
        }

        [$newAccess, $newRefresh, $expiryTs] = $refreshCallback($this->getRefreshToken());
        $this->saveTokens($newAccess, $newRefresh, (int)$expiryTs);

        return $newAccess;
    }

    private function isExpiringSoon(): bool
    {
        try {
            $statement = $this->db->query("SELECT expires_at FROM oauth_tokens WHERE provider = 'xero' LIMIT 1");
        } catch (PDOException $exception) {
            error_log('XeroTokenStore: failed to query token expiry - ' . $exception->getMessage());
            return true;
        }

        $record = $statement ? $statement->fetch(PDO::FETCH_ASSOC) : null;
        if (!$record || empty($record['expires_at'])) {
            return true;
        }

        $expiry = strtotime((string)$record['expires_at']);
        if ($expiry === false) {
            return true;
        }

        return $expiry <= (time() + 300); // refresh within five minutes of expiry
    }
}
