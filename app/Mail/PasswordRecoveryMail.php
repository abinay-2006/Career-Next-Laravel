<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordRecoveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $recoveryUrl) {}

    public function build()
    {
        return $this->subject('Career Next password recovery')->view('emails.password-recovery');
    }
}
