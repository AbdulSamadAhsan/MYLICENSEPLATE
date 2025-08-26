<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class SinglePlateMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $plate;
    public $dueDate;
    public $user;
    public $provinceLogo;
   public $file;
    public function __construct($plate, $dueDate, $user, $provinceLogo,$file)
    {
        $this->plate = $plate;
        $this->dueDate = $dueDate;
        $this->user = $user;
        $this->provinceLogo = $provinceLogo;
       $this->file=$file;
    }
    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Single Plate Mail',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.plate_challan',
            with: [
                'plate'        => $this->plate,
                'dueDate'      => $this->dueDate,
                'user'         => $this->user,
                'provinceLogo' => $this->provinceLogo,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    
   public function attachments(): array
    {
        return [

            Attachment::fromPath($this->file)
        ];
    }
    // $this->file 
    //     ? Attachment::fromPath($this->file) 
    //     : null;

 
}
