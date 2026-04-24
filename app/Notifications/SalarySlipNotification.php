<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\SalarySlip;

class SalarySlipNotification extends Notification implements ShouldQueue
{
    use Queueable;
    protected $salary_slip;

    /**
     * Create a new notification instance.
     */
    public function __construct(SalarySlip $salary_slip)
    {
        $this->salary_slip = $salary_slip;
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
        $pdfPath = public_path('Salary Slip/salary-slip-' . $this->salary_slip->user_id . '-' . $this->salary_slip->month . '.pdf');
        return (new MailMessage)
                ->subject("📄 Salary Slip for {$this->salary_slip->user->name}")
                ->greeting("Dear {$notifiable->name},")
                ->line("Please find the attached salary slip for your review.")
                ->attach($pdfPath, [
                    'as' => 'SalarySlip.pdf',
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
