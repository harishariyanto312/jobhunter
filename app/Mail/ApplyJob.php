<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ApplyJob extends Mailable
{
    use Queueable, SerializesModels;

    public $job_details;
    public $user;
    public $current_date;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($job_details, $user, $current_date)
    {
        $this->job_details = $job_details;
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $attachment_file_name = 'Lamaran ' . $this->job_details['position'] . ' - ' . $this->user['Nama'];
        return $this->subject($this->job_details['subject'])
                    ->text('apply_job')
                    ->attachFromStorage('lamaran.pdf', $attachment_file_name);
    }
}
