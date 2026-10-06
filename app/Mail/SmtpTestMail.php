<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SmtpTestMail extends Mailable
{
    public function __construct(
        public readonly string $fromAddress,
        public readonly string $fromName,
        public readonly string $host,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            subject: config('mailbatch.name').' SMTP test',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.smtp-test', text: 'emails.smtp-test-text');
    }
}
