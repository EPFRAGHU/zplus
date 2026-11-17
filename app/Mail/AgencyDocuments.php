<?php

namespace App\Mail;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The e-mail to the client: the operator's message plus the bill PDFs (signed copies where uploaded). */
class AgencyDocuments extends Mailable
{
    /** @param list<array{name: string, data: string, signed: bool}> $documents */
    public function __construct(
        public readonly string $fromAddress,
        public readonly string $fromName,
        public readonly string $subjectLine,
        public readonly string $body,
        public readonly array $documents,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(from: new Address($this->fromAddress, $this->fromName), replyTo: [new Address($this->fromAddress, $this->fromName)],
            subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.agency-documents', with: ['body' => $this->body]);
    }

    public function attachments(): array
    {
        return array_map(fn ($d) => Attachment::fromData(fn () => $d['data'], $d['name'])->withMime('application/pdf'), $this->documents);
    }
}
