<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PlatesExportNotification extends Notification
{
    use Queueable;

    public string $zipPath;

    public function __construct(string $zipPath)
    {
        $this->zipPath = $zipPath;
    }

    // Define notification channels
    public function via($notifiable)
    {
        return ['mail', 'database']; // email + database
    }

    // Email content
    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->subject('Latest Plates Export')
                    ->greeting("Hello {$notifiable->name},")
                    ->line('Please find attached the latest license plates export.')
                    ->attach($this->zipPath, [
                        'as' => 'plates_export.zip',
                        'mime' => 'application/zip',
                    ])
                    ->line('Thank you for using our service.');
    }

    // Store notification in database
    public function toDatabase($notifiable)
    {
        return [
            'message' => 'Latest license plates export is ready.',
            'file' => basename($this->zipPath),
        ];
    }
}
