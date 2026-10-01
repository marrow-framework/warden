<?php

declare(strict_types=1);

namespace Marrow\Warden;

use Marrow\Database\Connection;
use Marrow\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * "Remember me" persistent login — a long-lived cookie that lets a user
 * stay logged in past the PHP session itself expiring (browser close, idle
 * timeout), without keeping a long-lived server-side session.
 *
 * Requires a `remember_token` column on the users table (see this package's
 * migration). The cookie carries "{userId}|{rawToken}"; only
 * `hash('sha256', rawToken)` is ever persisted on the user row, so a leaked
 * `users` table (backup, replica, SQL injection) can't be replayed into a
 * live session — same reasoning as {@see PasswordResetBroker}.
 *
 * This only issues/reads/clears the cookie. Call site responsibility (see
 * the published LoginController/StartSession-equivalent stub): on a
 * fruitful resolve(), call `$auth->login($user)` yourself — this class
 * never touches the session.
 */
class RememberMeBroker
{
    public const COOKIE_NAME = 'warden_remember';
    private const TTL_DAYS = 30;

    public function __construct(
        private readonly Connection $db,
        private readonly string $table = 'users',
    ) {
    }

    /** Issue a new remember-me cookie for $user and persist its hash. */
    public function makeCookie(object $user): Cookie
    {
        $token = bin2hex(random_bytes(32));

        $this->db->update($this->table, ['remember_token' => hash('sha256', $token)], ['id' => $user->id]);

        return Cookie::create(self::COOKIE_NAME)
            ->withValue($user->id . '|' . $token)
            ->withExpires(time() + self::TTL_DAYS * 86400)
            ->withHttpOnly(true)
            ->withSecure(!$this->isLocal())
            ->withSameSite(Cookie::SAMESITE_LAX);
    }

    /** Resolve the user row carried by a remember-me cookie, if still valid. */
    public function resolve(Request $request): ?array
    {
        $raw = $request->cookies->get(self::COOKIE_NAME);
        if (!is_string($raw) || !str_contains($raw, '|')) {
            return null;
        }

        [$id, $token] = explode('|', $raw, 2);
        if ($id === '' || $token === '') {
            return null;
        }

        $row = $this->db->selectOne("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
        if ($row === null || empty($row['remember_token'])) {
            return null;
        }

        return hash_equals((string) $row['remember_token'], hash('sha256', $token)) ? $row : null;
    }

    /**
     * Invalidate the stored token (call on logout) and return an expired
     * cookie the caller must attach to the response to clear it client-side.
     */
    public function forget(object $user): Cookie
    {
        $this->db->update($this->table, ['remember_token' => null], ['id' => $user->id]);

        return Cookie::create(self::COOKIE_NAME)->withValue(null)->withExpires(1);
    }

    private function isLocal(): bool
    {
        $env = strtolower((string) ($_ENV['APP_ENV'] ?? 'production'));
        $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
        return $env === 'local' || $debug;
    }
}
