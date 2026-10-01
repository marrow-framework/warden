<?php

declare(strict_types=1);

namespace Marrow\Warden\Mail;

use Marrow\Mail\Mailable;
use Symfony\Component\Mime\Email;

class ResetPasswordMail extends Mailable
{
    public function __construct(
        private readonly string $toEmail,
        private readonly string $resetUrl,
        private readonly int $expiresInMinutes,
    ) {
    }

    public function build(array $config = []): Email
    {
        return $this->makeEmail()
            ->to($this->toEmail)
            ->subject('Réinitialisation de votre mot de passe')
            ->html($this->renderView('@warden/emails/reset-password', [
                'resetUrl' => $this->resetUrl,
                'expiresInMinutes' => $this->expiresInMinutes,
            ]));
    }
}
