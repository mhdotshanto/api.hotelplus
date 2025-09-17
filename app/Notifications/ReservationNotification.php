<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationNotification extends Notification
{
    use Queueable;
    public $receiverName;
    public $message;
    private $mailDetails;
    /**
     * Create a new notification instance.
     */
    public function __construct($mailDetails)
    {
        $this->mailDetails = $mailDetails;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mailDetails = $this->mailDetails;
        $subject = $mailDetails['subject'];
        return (new MailMessage)->subject($subject)->view('email.reservation_notification', compact('mailDetails'))->attachData(
            $mailDetails['pdfContent'],
            $mailDetails['pdfFileName'],
            ['mime' => 'application/pdf']
        );
        // return (new MailMessage)->subject($subject)->view('email.reservation_notification', compact('mailDetails'))->attach($mailDetails['targetFile']);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
