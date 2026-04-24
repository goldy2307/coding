<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EODReport extends Notification implements ShouldQueue
{
    use Queueable;
    protected $date;
    protected $user;

    /**
     * Create a new notification instance.
     */
    public function __construct($date, $user)
    {
        $this->date = $date;
        $this->user = $user;
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
        $pdfPath = public_path('app/temp/eod-report-' . $this->date . '.pdf');

        return (new MailMessage)
            ->subject("EOD Delivery Report Submitted - {$this->date}")
            ->greeting("Hello Accounts Team,")
            ->line("The logistics team has submitted the End of Day (EOD) Delivery Report for {$this->date}.")
            ->line("Please review and verify the listed deliveries. Once confirmed, kindly proceed with the approval process.")
            ->attach($pdfPath, [
                    'as' => 'EOD-Report.pdf',
                    'mime' => 'application/pdf',
                ])
            ->line("Thank you for your diligence and continued support.");

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
