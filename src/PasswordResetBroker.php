<?php

declare(strict_types=1);

namespace Marrow\Warden;

use Marrow\Database\Connection;

/**
 * Issues and consumes password-reset tokens.
 *
 * Table: `password_reset_tokens` (email, token_hash, created_at) — see this
 * package's migration. Only `hash('sha256', $token)` is ever persisted, the
 * same reasoning as `HasTwoFactor`'s recovery codes: a leaked table (backup,
 * replica, SQL injection) must never hand out a usable reset link by itself.
 * The raw token exists only in memory and in the email sent to the user —
 * a plain fast hash is fine here (unlike a user password) because the token
 * itself already carries 256 bits of random entropy; there's nothing for a
 * KDF to slow down an attacker guessing.
 *
 * One email has at most one live token: issuing a new one invalidates any
 * previous request for that address.
 */
class PasswordResetBroker
{
    private const TABLE = 'password_reset_tokens';
    private const DEFAULT_TTL_SECONDS = 3600;

    public function __construct(
        private readonly Connection $db,
        private readonly int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ) {
    }

    /**
     * Create a new token for $email, invalidating any previous one.
     * Returns the raw token — put it in the reset link, never persist it.
     */
    public function createToken(string $email): string
    {
        $email = $this->normalize($email);
        $token = bin2hex(random_bytes(32));

        $this->db->delete(self::TABLE, ['email' => $email]);
        $this->db->insert(self::TABLE, [
            'email' => $email,
            'token_hash' => $this->hash($token),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    /**
     * Validate a (email, token) pair without consuming it — use this to
     * decide whether to show the "set a new password" form at all, before
     * the user has typed anything.
     */
    public function tokenIsValid(string $email, string $token): bool
    {
        return $this->findValidRow($this->normalize($email), $token) !== null;
    }

    /**
     * Validate and consume (delete) the token in one call.
     *
     * Call this only once the new password has actually been persisted —
     * if saving the new password fails, the token must still be usable on
     * retry, so consuming it earlier (e.g. right after tokenIsValid()) would
     * strand the user with a broken, already-burned link.
     */
    public function consume(string $email, string $token): bool
    {
        $email = $this->normalize($email);

        if ($this->findValidRow($email, $token) === null) {
            return false;
        }

        $this->db->delete(self::TABLE, ['email' => $email]);
        return true;
    }

    private function findValidRow(string $email, string $token): ?array
    {
        $row = $this->db->selectOne(
            'SELECT * FROM ' . self::TABLE . ' WHERE email = ?',
            [$email]
        );

        if ($row === null || !hash_equals((string) $row['token_hash'], $this->hash($token))) {
            return null;
        }

        $createdAt = strtotime((string) $row['created_at']);
        if ($createdAt === false || (time() - $createdAt) > $this->ttlSeconds) {
            return null;
        }

        return $row;
    }

    private function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
