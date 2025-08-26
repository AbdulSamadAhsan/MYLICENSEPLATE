<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendMultiplePlateData extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $platesData;
    public $dueDate;
    public $user;
    public  $files;
    public function __construct($platesData, $dueDate, $user, $attachments)
    {
        //
        $this->platesData = $platesData;
        $this->dueDate = $dueDate;
        $this->user = $user;
        $this->files = $attachments;
    }
  
    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Send Multiple Plate Data',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.plate_challan_multiple',
            with: [
                "platesData" => $this->platesData,
                'dueDate'    => $this->dueDate,
                'user'       => $this->user
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->files as $filePath) {
            $attachments[] = Attachment::fromPath($filePath);
        }

        return $attachments;
    }
}
