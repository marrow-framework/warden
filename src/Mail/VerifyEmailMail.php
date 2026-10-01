<?php

declare(strict_types=1);

namespace Marrow\Warden\Mail;

use Marrow\Mail\Mailable;
use Symfony\Component\Mime\Email;

class VerifyEmailMail extends Mailable
{
    public function __construct(
        private readonly string $toEmail,
        private readonly string $verifyUrl,
    ) {
    }

    public function build(array $config = []): Email
    {
        return $this->makeEmail()
            ->to($this->toEmail)
            ->subject('Confirmez votre adresse email')
            ->html($this->renderView('@warden/emails/verify-email', [
                'verifyUrl' => $this->verifyUrl,
            ]));
    }
}
