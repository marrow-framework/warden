<?php

declare(strict_types=1);

namespace Marrow\Warden;

/**
 * Stateless, signed email-verification links (HMAC-SHA256, keyed by APP_KEY)
 * — no database table needed, unlike {@see PasswordResetBroker}: a
 * verification link only has to prove "whoever clicked this controls this
 * user's inbox", and reusing it (a user re-clicking after already
 * confirming) is harmless, so there's no need to make it revocable.
 *
 * Token shape: "{userId}.{expiresAt}.{signature}".
 */
class EmailVerifier
{
    private const DEFAULT_TTL_SECONDS = 86400; // 24h

    public function __construct(private readonly int $ttlSeconds = self::DEFAULT_TTL_SECONDS)
    {
    }

    /** Build the token to embed in the verification URL sent by email. */
    public function makeToken(int|string $userId, string $email): string
    {
        $expires = time() + $this->ttlSeconds;
        $payload = $userId . '.' . $expires;

        return $payload . '.' . $this->sign($payload, $email);
    }

    /**
     * Verify a token issued by makeToken() for this user id and email.
     *
     * $email must be the user's *current* address, looked up by $userId —
     * binding the signature to it means a token issued for an old address
     * stops verifying the moment the user changes their email, rather than
     * silently confirming whatever address happens to be on the row now.
     */
    public function verify(string $token, int|string $userId, string $email): bool
    {
        $parts = explode('.', $token, 3);
        if (count($parts) !== 3) {
            return false;
        }
        [$id, $expires, $signature] = $parts;

        if (!hash_equals((string) $userId, $id)) {
            return false;
        }
        if (!ctype_digit($expires) || (int) $expires < time()) {
            return false;
        }

        return hash_equals($this->sign($id . '.' . $expires, $email), $signature);
    }

    /**
     * @throws \RuntimeException if APP_KEY is not configured.
     */
    private function sign(string $payload, string $email): string
    {
        $key = $_ENV['APP_KEY'] ?? '';
        if ($key === '') {
            throw new \RuntimeException(
                'APP_KEY is not set — required to sign verification links. Generate one with: php forge key:generate'
            );
        }

        // $email is folded into the signed material, not just carried
        // alongside it, so the link stays single-purpose: it can't be
        // replayed against a different address by swapping the lookup, only
        // reused for the exact address it was issued for.
        return hash_hmac('sha256', $payload . '.' . mb_strtolower(trim($email)), $key);
    }
}
