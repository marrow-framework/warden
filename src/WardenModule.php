<?php

declare(strict_types=1);

namespace Marrow\Warden;

use Marrow\Module\Attributes\Module;
use Marrow\Module\BaseModule;
use Marrow\Warden\Commands\WardenInstallCommand;

/**
 * Registers `php forge warden:install` and the `@warden` Twig namespace
 * (this package's own `Views/emails/*` templates, used directly by
 * ResetPasswordMail/VerifyEmailMail — the only views this package ever
 * renders itself; everything web-facing is published, not rendered from
 * here, see WardenInstallCommand).
 *
 * No routes, no controllers of its own on purpose — same reasoning as
 * `Modules\Account\AccountModule`: those are meant to be fully owned and
 * editable by the app once generated, not hidden behind package internals.
 */
#[Module(name: 'warden', commands: [WardenInstallCommand::class])]
class WardenModule extends BaseModule
{
    public function boot(): void
    {
        $this->registerViewNamespace('warden', $this->path('Views'));
    }
}
