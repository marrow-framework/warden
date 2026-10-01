# Marrow Warden

Account security scaffolding for [Marrow](https://github.com/marrow-framework/core) — login, registration,
password reset, email verification, "remember me", and a 2FA login challenge.

Breeze-style, not Fortify-style: `php forge warden:install` publishes real, plain, fully-editable
controllers/views/routes/migrations into `modules/Auth/` — nothing is hidden behind the package at runtime
for the web-facing flow. Only the parts that are awkward (and risky) to hand-roll stay in the package as an
ordinary versioned dependency:

- `PasswordResetBroker` — hashed, single-use, expiring password-reset tokens.
- `EmailVerifier` — stateless, HMAC-signed email-verification links (no extra table).
- `RememberMeBroker` — persistent "remember me" login cookie.
- `Mail\ResetPasswordMail` / `Mail\VerifyEmailMail` — the two transactional emails.

## Why this exists

`marrow/framework`'s `Marrow\Auth\*` is a solid, low-level authentication/authorization toolkit (Argon2id
hashing, session + JWT guards, Gate/Policy, RBAC, TOTP 2FA) — but it deliberately ships no actual
login/registration *flow*. The skeleton's own `Modules\Account\AccountModule` says so explicitly: it owns the
`User` model and RBAC/2FA/audit schema, and nothing else, on purpose.

That leaves real gaps every app ends up solving itself, inconsistently:

- `users.email_verified_at` exists in the base migration, but nothing ever sets it.
- There is no password-reset flow at all.
- There is no `remember_token` column or persistent-login cookie.
- `HasTwoFactor` provides the TOTP primitives, but **nothing calls `verifyTwoFactor()` at login** —
  a user who enables 2FA is not actually protected by it unless something bridges the gap. Warden's
  `TwoFactorChallengeController` is exactly that bridge.

## Install

```bash
composer require marrow/warden
php forge warden:install
php forge migrate
```

Re-running `warden:install` is safe — existing files are skipped unless you pass `--force`.

The command prints the manual follow-ups it can't safely automate: adding the `remember`/`verified`
middleware aliases to `config/middleware.php`, and making sure `MAIL_DSN`/`APP_KEY` are configured (the
latter signs verification links and is required anyway for `HasTwoFactor`'s secret encryption).

## What gets published

```
modules/Auth/
├── AuthModule.php
├── routes.php
├── Controllers/
│   ├── LoginController.php
│   ├── RegisterController.php
│   ├── ForgotPasswordController.php
│   ├── ResetPasswordController.php
│   ├── VerifyEmailController.php
│   └── TwoFactorChallengeController.php
├── Middleware/
│   ├── EnsureEmailIsVerified.php
│   └── AttemptRememberLogin.php
├── Views/
│   └── *.html.twig
└── Database/Migrations/
    ├── ..._create_password_reset_tokens_table.php
    └── ..._add_remember_token_to_users_table.php
```

All of it is yours from that point on — rename routes, restyle views, add fields to registration, whatever
the app needs. The package itself never reaches into `modules/Auth/` again.

## Requirements

- `marrow/framework` ^2.2
- A mailer configured (`config/mail.php`) for password reset / verification emails to actually send.
- `APP_KEY` set (`php forge key:generate`) — used to sign email-verification links.
