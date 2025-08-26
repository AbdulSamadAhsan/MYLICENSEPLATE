<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendMultiplePlateData;

class SendMultiplePlates implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public $platesData;
    public $dueDate;
    public $user;
    public  $attachments;
    public function __construct($platesData, $dueDate, $user, $attachments)
    {
        $this->platesData=$platesData;
        $this->dueDate=$dueDate;
        $this->user=$user;
        $this->attachments=$attachments;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to($this->user->email)->send(new SendMultiplePlateData($this->platesData,$this->dueDate,$this->user,$this->attachments));
    }
}
