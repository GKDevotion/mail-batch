<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Symfony\Component\Mime\Email;

/** A fully rendered email (HTML + plain text). Also used by the sending engine in Phase 6. */
class CampaignMail extends Mailable
{
    public function __construct(
        public readonly string $mailSubject,
        public readonly string $htmlBody,
        public readonly string $textBody,
        public readonly string $fromAddress,
        public readonly string $fromName,
        public readonly ?string $unsubscribeUrl = null,
    ) {
        $this->withSymfonyMessage(function (Email $message) {
            $message->text($this->textBody);

            if ($this->unsubscribeUrl && preg_match('#^https?://#i', $this->unsubscribeUrl)) {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$this->unsubscribeUrl.'>');
            }
        });
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->htmlBody);
    }
}
