<?php

declare(strict_types=1);

namespace Marrow\Warden\Commands;

use Marrow\Console\Command;

/**
 * Scaffolds a full, editable login/registration/password-reset/
 * email-verification/2FA-challenge flow into modules/Auth/ — Breeze-style:
 * every published file is plain, ordinary Marrow source the app owns from
 * the moment this command finishes. Re-running it is safe (nothing is
 * overwritten) unless --force is passed, same convention as
 * `anvil:install`.
 *
 * What stays in the package (never copied, referenced by the published
 * code as an ordinary composer dependency): PasswordResetBroker,
 * EmailVerifier, RememberMeBroker, and the two Mail\* classes — the parts
 * that are awkward to hand-roll safely (token hashing/signing) and benefit
 * from staying upgradable.
 *
 *   php forge warden:install
 *   php forge warden:install --force
 */
class WardenInstallCommand extends Command
{
    protected string $signature = 'warden:install {--force : Overwrite existing files}';
    protected string $description = 'Scaffold login, registration, password reset, email verification, and a 2FA challenge into modules/Auth/';

    private const CONTROLLERS = [
        'LoginController',
        'RegisterController',
        'ForgotPasswordController',
        'ResetPasswordController',
        'VerifyEmailController',
        'TwoFactorChallengeController',
    ];

    private const MIDDLEWARE = [
        'EnsureEmailIsVerified',
        'AttemptRememberLogin',
    ];

    /** marrow/form-builder Form classes — one per published controller/view pair that has fields. */
    private const FORMS = [
        'LoginForm',
        'RegisterForm',
        'ForgotPasswordForm',
        'ResetPasswordForm',
        'TwoFactorChallengeForm',
    ];

    private const VIEWS = [
        'login',
        'register',
        'forgot-password',
        'reset-password',
        'verify-email',
        'two-factor-challenge',
    ];

    protected function handle(): int
    {
        $force = (bool) $this->option('force');
        $stubsPath = dirname(__DIR__) . '/Stubs';
        $authPath = base_path('modules/Auth');

        foreach (self::CONTROLLERS as $name) {
            $this->publishStub("{$stubsPath}/Controllers/{$name}.php.stub", "{$authPath}/Controllers/{$name}.php", $force);
        }

        foreach (self::MIDDLEWARE as $name) {
            $this->publishStub("{$stubsPath}/Middleware/{$name}.php.stub", "{$authPath}/Middleware/{$name}.php", $force);
        }

        foreach (self::FORMS as $name) {
            $this->publishStub("{$stubsPath}/Forms/{$name}.php.stub", "{$authPath}/Forms/{$name}.php", $force);
        }

        foreach (self::VIEWS as $name) {
            $this->publishStub("{$stubsPath}/Views/{$name}.html.twig.stub", "{$authPath}/Views/{$name}.html.twig", $force);
        }

        $this->publishStub("{$stubsPath}/AuthModule.php.stub", "{$authPath}/AuthModule.php", $force);
        $this->publishStub("{$stubsPath}/routes.php.stub", "{$authPath}/routes.php", $force);

        // Timestamped (not a fixed date like the Account module's own
        // migrations) so these always sort after whatever schema already
        // exists in the app at install time, including a `users` table the
        // app may have customized beyond the skeleton's default.
        $this->publishMigration(
            "{$stubsPath}/Migrations/create_password_reset_tokens_table.php.stub",
            'create_password_reset_tokens_table',
            $authPath,
            $force,
            secondsOffset: 0,
        );
        $this->publishMigration(
            "{$stubsPath}/Migrations/add_remember_token_to_users_table.php.stub",
            'add_remember_token_to_users_table',
            $authPath,
            $force,
            secondsOffset: 1,
        );

        $this->registerModule();

        $this->newLine();
        $this->success('Warden scaffolding installed into modules/Auth/.');
        $this->newLine();
        $this->printNextSteps();

        return self::SUCCESS;
    }

    // ── Publishing ────────────────────────────────────────────────────

    private function publishStub(string $stubPath, string $targetPath, bool $force): void
    {
        $this->writeFile($targetPath, (string) file_get_contents($stubPath), $force);
    }

    private function publishMigration(
        string $stubPath,
        string $snakeName,
        string $authPath,
        bool $force,
        int $secondsOffset,
    ): void {
        $timestamp = date('Y_m_d_His', time() + $secondsOffset);
        $filename = "{$timestamp}_{$snakeName}.php";
        $className = $this->classFromSnakeName($snakeName);

        $contents = str_replace('{{ class }}', $className, (string) file_get_contents($stubPath));

        $this->writeFile("{$authPath}/Database/Migrations/{$filename}", $contents, $force);
    }

    /** Mirrors Marrow\Database\Migrations\Migrator::classFromFile()'s naming convention. */
    private function classFromSnakeName(string $snakeName): string
    {
        return implode('', array_map('ucfirst', explode('_', $snakeName)));
    }

    private function writeFile(string $path, string $contents, bool $force): void
    {
        if (is_file($path) && !$force) {
            $this->warn("Skipped (already exists): {$path} — pass --force to overwrite.");
            return;
        }
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $contents);
        $this->success("Created: {$path}");
    }

    // ── config/modules.php registration ────────────────────────────────

    /**
     * Inserts \Modules\Auth\AuthModule::class into config/modules.php's
     * 'enabled' => [...] array. Mirrors Command::insertIntoProviders()'s
     * bracket-depth-matching approach (that one is private and scoped to a
     * #[Module(providers: [...])] attribute, not reusable here) — same
     * fallback philosophy: never risk corrupting the file, print a manual
     * instruction instead when the expected shape isn't found.
     */
    private function registerModule(): void
    {
        $entry = '\\Modules\\Auth\\AuthModule::class';
        $path = base_path('config/modules.php');

        if (!is_file($path)) {
            $this->warn("Could not find config/modules.php — add {$entry} to your enabled modules manually.");
            return;
        }

        $source = (string) file_get_contents($path);

        if (str_contains($source, $entry)) {
            return; // already registered — safe to re-run
        }

        if (!preg_match('/\'enabled\'\s*=>\s*\[/', $source, $match, PREG_OFFSET_CAPTURE)) {
            $this->warn("Could not find 'enabled' => [...] in config/modules.php — add {$entry} manually.");
            return;
        }

        $openBracket = $match[0][1] + strlen($match[0][0]) - 1;
        $depth = 0;
        $closeBracket = null;

        for ($i = $openBracket; $i < strlen($source); $i++) {
            if ($source[$i] === '[') {
                $depth++;
            } elseif ($source[$i] === ']') {
                $depth--;
                if ($depth === 0) {
                    $closeBracket = $i;
                    break;
                }
            }
        }

        if ($closeBracket === null) {
            $this->warn("Could not find the end of 'enabled' => [...] in config/modules.php — add {$entry} manually.");
            return;
        }

        $inner = substr($source, $openBracket + 1, $closeBracket - $openBracket - 1);
        $trimmedInner = trim($inner);
        $newInner = $trimmedInner === ''
            ? "\n        {$entry},\n    "
            : rtrim($inner) . (str_ends_with(rtrim($inner), ',') ? '' : ',') . "\n        {$entry},\n    ";

        $updated = substr($source, 0, $openBracket + 1) . $newInner . substr($source, $closeBracket);
        file_put_contents($path, $updated);
        $this->success('Registered Modules\\Auth\\AuthModule in config/modules.php.');
    }

    // ── Output ────────────────────────────────────────────────────────

    private function printNextSteps(): void
    {
        $this->line('   <fg=gray>Next:</>');
        $this->line('     php forge migrate');
        $this->newLine();
        $this->warn('"remember me" needs a middleware alias added to config/middleware.php:');
        $this->line("       'remember' => \\Modules\\Auth\\Middleware\\AttemptRememberLogin::class,");
        $this->line("     ...and added to the 'web' group, right after 'session'.");
        $this->newLine();
        $this->warn('Gating a route behind a verified email needs its own alias too:');
        $this->line("       'verified' => \\Modules\\Auth\\Middleware\\EnsureEmailIsVerified::class,");
        $this->line("     then: ->middleware(['auth', 'verified'])");
        $this->newLine();
        $this->warn('Password-reset and verification emails need a working mailer (config/mail.php → MAIL_DSN) and APP_KEY (php forge key:generate) to sign verification links.');
    }
}
