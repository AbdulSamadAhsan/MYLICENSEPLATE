<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use App\Mail\SinglePlateMail;

class MailJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public $plate;
    public $dueDate;
    public $user;
    public $provinceLogo;
 
    public $file;

  

    public function __construct($plate, $dueDate, $user, $provinceLogo ,$file)
    {
        $this->plate = $plate;
        $this->dueDate = $dueDate;
        $this->user = $user;
        $this->provinceLogo = $provinceLogo;
        $this->file=$file;

        
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $email=$this->user->email;
        Mail::to($email)
            ->send(new SinglePlateMail($this->plate,$this->dueDate,$this->user, $this->provinceLogo,$this->file));
    }
}
