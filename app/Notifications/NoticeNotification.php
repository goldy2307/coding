<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Notice;

class NoticeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $notice;

    /**
     * Create a new notification instance.
     */
    public function __construct(Notice $notice)
    {
        $this->notice = $notice;
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
        $pdfPath = public_path('Notice/notice-' . $this->notice->id . '.pdf');

        return (new MailMessage)
                ->subject("Warning Notice for Misconduct / Performance Issue")
                ->greeting("Dear {$notifiable->name},")
                ->line("Please find the attached warning notice.")
                ->attach($pdfPath, [
                    'as' => 'Notice.pdf',
                    'mime' => 'application/pdf',
                ])
                ->salutation("Warm regards, 
        The SwitchIt Team");
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
